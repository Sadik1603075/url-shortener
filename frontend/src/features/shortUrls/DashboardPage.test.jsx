import { screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import DashboardPage from './DashboardPage';
import { renderWithProviders } from '../../test/renderWithProviders';

// Mock both api boundaries the dashboard reads from.
vi.mock('./api', () => ({
  getShortUrls: vi.fn(),
  createShortUrl: vi.fn(),
  updateShortUrl: vi.fn(),
  deleteShortUrl: vi.fn(),
}));
vi.mock('../analytics/api', () => ({
  getOverview: vi.fn(),
}));

import { getShortUrls } from './api';
import { getOverview } from '../analytics/api';

const overview = {
  totals: { total_urls: 10, total_clicks: 42, active_codes: 4, total_codes: 6 },
  urls_created: [
    { date: '2026-09-29', count: 1 },
    { date: '2026-09-30', count: 2 },
  ],
  // Low counts so the chart's y-axis ticks (0..max) don't collide with the
  // totals we assert on (10/42/4).
  clicks_series: [
    { date: '2026-09-29', count: 1 },
    { date: '2026-09-30', count: 1 },
  ],
  top_urls: [
    { short_code: 'aaa', long_url: 'https://a.example.com', click_count: 9 },
  ],
};

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows the loading state while the overview is pending', () => {
    getOverview.mockReturnValue(new Promise(() => {})); // never resolves
    getShortUrls.mockResolvedValue({ data: [] });

    renderWithProviders(<DashboardPage />);

    expect(screen.getByText(/loading activity/i)).toBeInTheDocument();
  });

  it('shows an error banner when the overview request fails', async () => {
    getOverview.mockRejectedValue(new Error('boom'));
    getShortUrls.mockResolvedValue({ data: [] });

    renderWithProviders(<DashboardPage />);

    expect(await screen.findByText('Failed to load analytics.')).toBeInTheDocument();
  });

  it('maps overview data into totals, chart, recent links and top URLs', async () => {
    getOverview.mockResolvedValue(overview);
    // Distinct short_code from top_urls' 'aaa' so each section is unambiguous.
    getShortUrls.mockResolvedValue({
      data: [{ id: 1, short_code: 'recent1', long_url: 'https://recent.example.com', click_count: 2 }],
    });

    renderWithProviders(<DashboardPage />);

    // Totals (formatNumber): total_urls, total_clicks, active_codes.
    expect(await screen.findByText('10')).toBeInTheDocument();
    expect(screen.getByText('42')).toBeInTheDocument();
    expect(screen.getByText('4')).toBeInTheDocument();
    // Chart rendered from clicks_series.
    expect(screen.getByRole('img', { name: /clicks per day/i })).toBeInTheDocument();
    // Top URLs section shows the top_urls entry; recent links show the recent entry.
    expect(screen.getByText('Top URLs by clicks')).toBeInTheDocument();
    expect(screen.getByText('aaa')).toBeInTheDocument();
    expect(screen.getByText('recent1')).toBeInTheDocument();
  });

  it('shows empty states when there are no links or clicks', async () => {
    getOverview.mockResolvedValue({ ...overview, top_urls: [] });
    getShortUrls.mockResolvedValue({ data: [] });

    renderWithProviders(<DashboardPage />);

    expect(await screen.findByText('No links yet.')).toBeInTheDocument();
    expect(screen.getByText('No click data yet.')).toBeInTheDocument();
  });
});
