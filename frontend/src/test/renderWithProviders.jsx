import { render } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';

// A fresh QueryClient per test with retries off, so error states surface
// immediately and no cache leaks between tests.
export function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0 },
      mutations: { retry: false },
    },
  });
}

/**
 * Render a component inside the providers most feature components need:
 * react-query + a router. Pass `route` to set the initial location.
 */
export function renderWithProviders(ui, { route = '/', queryClient = createTestQueryClient() } = {}) {
  return {
    queryClient,
    ...render(
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={[route]}>{ui}</MemoryRouter>
      </QueryClientProvider>,
    ),
  };
}
