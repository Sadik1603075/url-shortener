import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';

import Logo from '../components/common/Logo';

// Harness smoke test (D1-T4): proves Vitest + jsdom + RTL + jest-dom are wired.
// Real feature tests live in the FE-TESTS ticket.
describe('test harness', () => {
  it('renders a trivial component into the jsdom document', () => {
    render(<Logo />);

    expect(screen.getByText('LinkForge')).toBeInTheDocument();
  });
});
