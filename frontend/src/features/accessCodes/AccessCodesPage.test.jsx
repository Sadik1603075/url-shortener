import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import AccessCodesPage from './AccessCodesPage';
import { renderWithProviders } from '../../test/renderWithProviders';

vi.mock('./api', () => ({
  fetchAccessCodes: vi.fn(),
  createAccessCode: vi.fn(),
  updateAccessCode: vi.fn(),
  deleteAccessCode: vi.fn(),
  sendAccessCodeEmail: vi.fn(),
}));

import {
  fetchAccessCodes,
  createAccessCode,
  updateAccessCode,
  deleteAccessCode,
  sendAccessCodeEmail,
} from './api';

const row = {
  id: 7,
  code: 'USR-AAAA-BBBB',
  email: 'grantee@example.com',
  description: 'Marketing',
  is_active: true,
  expires_at: null,
  last_used_at: null,
};

const page = (codes, overrides = {}) => ({
  data: codes,
  meta: { current_page: 1, last_page: 1, ...overrides },
});

describe('AccessCodesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows the loading state', () => {
    fetchAccessCodes.mockReturnValue(new Promise(() => {}));

    renderWithProviders(<AccessCodesPage />);

    expect(screen.getByText(/loading access codes/i)).toBeInTheDocument();
  });

  it('shows the error state', async () => {
    fetchAccessCodes.mockRejectedValue(new Error('boom'));

    renderWithProviders(<AccessCodesPage />);

    expect(await screen.findByText('Failed to load access codes.')).toBeInTheDocument();
  });

  it('shows the empty state', async () => {
    fetchAccessCodes.mockResolvedValue(page([]));

    renderWithProviders(<AccessCodesPage />);

    expect(await screen.findByText('No access codes yet.')).toBeInTheDocument();
  });

  it('renders a row of code data', async () => {
    fetchAccessCodes.mockResolvedValue(page([row]));

    renderWithProviders(<AccessCodesPage />);

    expect(await screen.findByText('USR-AAAA-BBBB')).toBeInTheDocument();
    expect(screen.getByText('grantee@example.com')).toBeInTheDocument();
    expect(screen.getByText('Active')).toBeInTheDocument();
  });

  it('deactivates a code via the expire action', async () => {
    fetchAccessCodes.mockResolvedValue(page([row]));
    updateAccessCode.mockResolvedValue({ ...row, is_active: false });

    const user = userEvent.setup();
    renderWithProviders(<AccessCodesPage />);
    await screen.findByText('USR-AAAA-BBBB');

    await user.click(screen.getByLabelText('Expire access code'));

    await waitFor(() => expect(updateAccessCode).toHaveBeenCalledWith(7, { is_active: false }));
  });

  it('deletes a code after confirmation', async () => {
    fetchAccessCodes.mockResolvedValue(page([row]));
    deleteAccessCode.mockResolvedValue(undefined);
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    const user = userEvent.setup();
    renderWithProviders(<AccessCodesPage />);
    await screen.findByText('USR-AAAA-BBBB');

    await user.click(screen.getByLabelText('Delete access code'));

    await waitFor(() => expect(deleteAccessCode).toHaveBeenCalledWith(7, expect.anything()));
  });

  it('does not delete when confirmation is cancelled', async () => {
    fetchAccessCodes.mockResolvedValue(page([row]));
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    const user = userEvent.setup();
    renderWithProviders(<AccessCodesPage />);
    await screen.findByText('USR-AAAA-BBBB');

    await user.click(screen.getByLabelText('Delete access code'));

    expect(deleteAccessCode).not.toHaveBeenCalled();
  });

  it('sends the code by email and shows a success alert', async () => {
    fetchAccessCodes.mockResolvedValue(page([row]));
    sendAccessCodeEmail.mockResolvedValue({ message: 'ok' });
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    const user = userEvent.setup();
    renderWithProviders(<AccessCodesPage />);
    await screen.findByText('USR-AAAA-BBBB');

    await user.click(screen.getByLabelText('Send access code by email'));

    expect(await screen.findByText('Access code email sent successfully.')).toBeInTheDocument();
    expect(sendAccessCodeEmail).toHaveBeenCalledWith(7, expect.anything());
  });

  it('surfaces a send-email failure', async () => {
    fetchAccessCodes.mockResolvedValue(page([row]));
    sendAccessCodeEmail.mockRejectedValue({ response: { data: { message: 'Mailer down.' } } });
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    const user = userEvent.setup();
    renderWithProviders(<AccessCodesPage />);
    await screen.findByText('USR-AAAA-BBBB');

    await user.click(screen.getByLabelText('Send access code by email'));

    expect(await screen.findByText('Mailer down.')).toBeInTheDocument();
  });

  it('creates a code through the generate modal', async () => {
    fetchAccessCodes.mockResolvedValue(page([]));
    createAccessCode.mockResolvedValue({ ...row, email: 'new@example.com' });

    const user = userEvent.setup();
    renderWithProviders(<AccessCodesPage />);
    await screen.findByText('No access codes yet.');

    await user.click(screen.getByRole('button', { name: 'Generate code' }));

    const dialog = await screen.findByText('Generate access code');
    expect(dialog).toBeInTheDocument();

    await user.type(screen.getByLabelText('Email address'), 'new@example.com');
    await user.click(screen.getByRole('button', { name: 'Generate' }));

    await waitFor(() => expect(createAccessCode).toHaveBeenCalledWith({ email: 'new@example.com' }, expect.anything()));
    await waitFor(() => expect(screen.queryByText('Generate access code')).not.toBeInTheDocument());
  });
});
