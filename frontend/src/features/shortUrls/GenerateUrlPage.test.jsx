import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import GenerateUrlPage from './GenerateUrlPage';
import { renderWithProviders } from '../../test/renderWithProviders';

vi.mock('./api', () => ({
  createShortUrl: vi.fn(),
  getShortUrls: vi.fn(),
  updateShortUrl: vi.fn(),
  deleteShortUrl: vi.fn(),
}));

import { createShortUrl } from './api';

describe('GenerateUrlPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows validation errors and does not call the api when the form is empty', async () => {
    const user = userEvent.setup();
    renderWithProviders(<GenerateUrlPage />);

    await user.click(screen.getByRole('button', { name: /create short link/i }));

    expect(await screen.findByText('Access code is required')).toBeInTheDocument();
    expect(screen.getByText('Enter a valid URL')).toBeInTheDocument();
    expect(createShortUrl).not.toHaveBeenCalled();
  });

  it('rejects an invalid URL at the field level', async () => {
    const user = userEvent.setup();
    renderWithProviders(<GenerateUrlPage />);

    await user.type(screen.getByPlaceholderText(/enter your access code/i), 'CODE1');
    await user.type(screen.getByPlaceholderText(/example\.com/i), 'not-a-url');
    await user.click(screen.getByRole('button', { name: /create short link/i }));

    expect(await screen.findByText('Enter a valid URL')).toBeInTheDocument();
    expect(createShortUrl).not.toHaveBeenCalled();
  });

  it('creates a short URL and copies it to the clipboard', async () => {
    createShortUrl.mockResolvedValue({
      data: { short_code: 'abc1234', short_url: 'http://localhost:8000/abc1234' },
    });

    const user = userEvent.setup();
    renderWithProviders(<GenerateUrlPage />);

    await user.type(screen.getByPlaceholderText(/enter your access code/i), 'CODE1');
    await user.type(screen.getByPlaceholderText(/example\.com/i), 'https://example.com/a/long/path');
    await user.click(screen.getByRole('button', { name: /create short link/i }));

    expect(await screen.findByText('http://localhost:8000/abc1234')).toBeInTheDocument();
    // react-query v5 passes a context object as the 2nd arg to the mutationFn.
    expect(createShortUrl).toHaveBeenCalledWith(
      { access_code: 'CODE1', long_url: 'https://example.com/a/long/path' },
      expect.anything(),
    );

    // Install the clipboard stub AFTER userEvent.setup() (which installs its own),
    // so the component's navigator.clipboard.writeText is the one we assert on.
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });

    await user.click(screen.getByRole('button', { name: /copy/i }));

    expect(writeText).toHaveBeenCalledWith('http://localhost:8000/abc1234');
    expect(await screen.findByText('Copied')).toBeInTheDocument();
  });

  it('surfaces a 422 field error from the server', async () => {
    createShortUrl.mockRejectedValue({
      response: { status: 422, data: { errors: { access_code: ['The provided access code is invalid.'] } } },
    });

    const user = userEvent.setup();
    renderWithProviders(<GenerateUrlPage />);

    await user.type(screen.getByPlaceholderText(/enter your access code/i), 'BADCODE');
    await user.type(screen.getByPlaceholderText(/example\.com/i), 'https://example.com');
    await user.click(screen.getByRole('button', { name: /create short link/i }));

    expect(await screen.findByText('The provided access code is invalid.')).toBeInTheDocument();
  });
});
