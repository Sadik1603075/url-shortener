import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import LoginPage from './LoginPage';
import { authStore } from './authStore';

// Mock the api module (the feature's boundary) and the router navigation.
vi.mock('./api', () => ({
  login: vi.fn(),
  logout: vi.fn(),
}));

const navigateMock = vi.fn();
vi.mock('react-router-dom', async (orig) => ({
  ...(await orig()),
  useNavigate: () => navigateMock,
}));

import { login } from './api';

describe('LoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    authStore.logout();
  });

  afterEach(() => {
    authStore.logout();
  });

  it('does not call the api when required fields are empty', async () => {
    const user = userEvent.setup();
    render(<LoginPage />);

    await user.click(screen.getByRole('button', { name: /sign in/i }));

    expect(login).not.toHaveBeenCalled();
    expect(navigateMock).not.toHaveBeenCalled();
  });

  it('logs in, stores the token, and navigates to /admin on success', async () => {
    login.mockResolvedValue({
      token: 'tok-123',
      user: { id: 1, name: 'Admin', email: 'admin@example.com', role: 'admin' },
    });

    const user = userEvent.setup();
    render(<LoginPage />);

    await user.type(screen.getByPlaceholderText('admin@example.com'), 'admin@example.com');
    await user.type(screen.getByPlaceholderText('••••••••'), 'secret-password');
    await user.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => expect(login).toHaveBeenCalledWith({
      email: 'admin@example.com',
      password: 'secret-password',
    }));

    expect(navigateMock).toHaveBeenCalledWith('/admin');
    expect(localStorage.getItem('auth_token')).toBe('tok-123');
  });

  it('surfaces the server error message on failure', async () => {
    login.mockRejectedValue({
      response: { data: { message: 'These credentials do not match.' } },
    });

    const user = userEvent.setup();
    render(<LoginPage />);

    await user.type(screen.getByPlaceholderText('admin@example.com'), 'admin@example.com');
    await user.type(screen.getByPlaceholderText('••••••••'), 'wrong');
    await user.click(screen.getByRole('button', { name: /sign in/i }));

    expect(await screen.findByText('These credentials do not match.')).toBeInTheDocument();
    expect(navigateMock).not.toHaveBeenCalled();
    expect(localStorage.getItem('auth_token')).toBeNull();
  });
});
