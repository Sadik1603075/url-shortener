import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it } from 'vitest';

import ProtectedRoute from './ProtectedRoute';
import { authStore } from './authStore';

function renderAt(route) {
  return render(
    <MemoryRouter initialEntries={[route]}>
      <Routes>
        <Route path="/login" element={<div>Login screen</div>} />
        <Route element={<ProtectedRoute />}>
          <Route path="/admin" element={<div>Secret admin area</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

describe('ProtectedRoute', () => {
  beforeEach(() => {
    authStore.logout();
  });

  it('redirects to /login when there is no token', () => {
    renderAt('/admin');

    expect(screen.getByText('Login screen')).toBeInTheDocument();
    expect(screen.queryByText('Secret admin area')).not.toBeInTheDocument();
  });

  it('renders the protected outlet when authenticated', () => {
    authStore.login('tok-123', { id: 1, email: 'admin@example.com' });

    renderAt('/admin');

    expect(screen.getByText('Secret admin area')).toBeInTheDocument();
    expect(screen.queryByText('Login screen')).not.toBeInTheDocument();
  });
});
