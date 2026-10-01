import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import ActivityChart from './ActivityChart';

describe('ActivityChart', () => {
  it('maps data points into a line and scales the y-axis to the max count', () => {
    const data = [
      { date: '2026-09-29', count: 3 },
      { date: '2026-09-30', count: 7 },
    ];

    const { container } = render(<ActivityChart data={data} label="Clicks" />);

    // Accessible name reflects the series label.
    expect(screen.getByRole('img', { name: 'Clicks per day' })).toBeInTheDocument();
    // A polyline is drawn once there is more than one point.
    expect(container.querySelector('polyline')).not.toBeNull();
    // Top y-tick equals the max count in the data.
    expect(screen.getByText('7')).toBeInTheDocument();
    // X-axis labels are rendered from the dates.
    expect(screen.getByText('Sep 30')).toBeInTheDocument();
  });

  it('renders without a line for empty data and does not crash', () => {
    const { container } = render(<ActivityChart data={[]} label="Clicks" />);

    expect(screen.getByRole('img', { name: 'Clicks per day' })).toBeInTheDocument();
    expect(container.querySelector('polyline')).toBeNull();
  });
});
