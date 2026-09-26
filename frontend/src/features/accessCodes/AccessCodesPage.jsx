import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import {
  Copy,
  Check,
  KeyRound,
  Plus,
  Trash2,
  Ban,
  RotateCcw,
  Loader2,
  X,
  Mail,
  Pencil,
} from 'lucide-react';
import {
  useAccessCodes,
  useCreateAccessCode,
  useUpdateAccessCode,
  useDeleteAccessCode,
  useSendAccessCodeEmail,
} from './hooks';
import { createAccessCodeSchema, updateAccessCodeSchema } from './schema';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatDate(iso) {
  if (!iso) return 'Never';

  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

// ---------------------------------------------------------------------------
// Copy button (shows checkmark briefly)
// ---------------------------------------------------------------------------

function CopyButton({ text }) {
  const [copied, setCopied] = useState(false);

  async function handleCopy() {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // Fallback: noop in non-secure context
    }
  }

  return (
    <button
      className="icon-button"
      onClick={handleCopy}
      title="Copy code"
    >
      {copied ? <Check size={14} /> : <Copy size={14} />}
    </button>
  );
}

// ---------------------------------------------------------------------------
// Inline row actions (edit / expire / send / delete)
// ---------------------------------------------------------------------------

function RowActions({ accessCode, onToggle, onDelete, onEdit, onSendEmail }) {
  return (
    <div className="row-actions">
      <button
        type="button"
        className="row-action-button"
        onClick={onEdit}
        title="Edit"
        aria-label="Edit access code"
      >
        <Pencil size={15} />
      </button>

      <button
        type="button"
        className="row-action-button"
        onClick={onSendEmail}
        title="Send email"
        aria-label="Send access code by email"
      >
        <Mail size={15} />
      </button>

      {accessCode.is_active ? (
        <button
          type="button"
          className="row-action-button row-action-warning"
          onClick={onToggle}
          title="Expire"
          aria-label="Expire access code"
        >
          <Ban size={15} />
        </button>
      ) : (
        <button
          type="button"
          className="row-action-button row-action-success"
          onClick={onToggle}
          title="Reactivate"
          aria-label="Reactivate access code"
        >
          <RotateCcw size={15} />
        </button>
      )}

      <button
        type="button"
        className="row-action-button row-action-danger"
        onClick={onDelete}
        title="Delete"
        aria-label="Delete access code"
      >
        <Trash2 size={15} />
      </button>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Generate modal
// ---------------------------------------------------------------------------

function GenerateModal({ onClose }) {
  const createMutation = useCreateAccessCode();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(createAccessCodeSchema),
    defaultValues: {
      email: '',
      description: '',
      expires_at: '',
    },
  });

  async function onSubmit(data) {
    const payload = { email: data.email };

    if (data.description) {
      payload.description = data.description;
    }

    if (data.expires_at) {
      payload.expires_at = new Date(data.expires_at).toISOString();
    }

    await createMutation.mutateAsync(payload);
    onClose();
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal-content"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-header">
          <h2>Generate access code</h2>

          <button className="icon-button" onClick={onClose}>
            <X size={18} />
          </button>
        </div>

        <form onSubmit={handleSubmit(onSubmit)}>
          <div className="form-field">
            <label htmlFor="gen-email">Email address</label>

            <input
              id="gen-email"
              type="email"
              placeholder="user@example.com"
              {...register('email')}
            />

            {errors.email && (
              <span className="field-error">
                {errors.email.message}
              </span>
            )}
          </div>

          <div className="form-field">
            <label htmlFor="gen-description">
              Description{' '}
              <span className="optional">(optional)</span>
            </label>

            <input
              id="gen-description"
              type="text"
              placeholder="e.g. Development team"
              {...register('description')}
            />

            {errors.description && (
              <span className="field-error">
                {errors.description.message}
              </span>
            )}
          </div>

          <div className="form-field">
            <label htmlFor="gen-expires">
              Expiry date{' '}
              <span className="optional">(optional)</span>
            </label>

            <input
              id="gen-expires"
              type="datetime-local"
              {...register('expires_at')}
            />

            {errors.expires_at && (
              <span className="field-error">
                {errors.expires_at.message}
              </span>
            )}
          </div>

          {createMutation.isError && (
            <div className="alert-error">
              {createMutation.error?.response?.data?.message ||
                'Failed to generate code.'}
            </div>
          )}

          <div className="modal-actions">
            <button
              type="button"
              className="secondary-button"
              onClick={onClose}
            >
              Cancel
            </button>

            <button
              type="submit"
              className="primary-button compact"
              disabled={createMutation.isPending}
            >
              {createMutation.isPending ? (
                <>
                  <Loader2 size={16} className="spin" />
                  Generating...
                </>
              ) : (
                <>
                  <Plus size={16} />
                  Generate
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Edit modal
// ---------------------------------------------------------------------------

function EditModal({ accessCode, onClose }) {
  const updateMutation = useUpdateAccessCode();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(updateAccessCodeSchema),
    defaultValues: {
      description: accessCode.description || '',
      expires_at: accessCode.expires_at
        ? accessCode.expires_at.slice(0, 16)
        : '',
    },
  });

  async function onSubmit(data) {
    const payload = { id: accessCode.id };

    payload.description = data.description || null;

    if (data.expires_at) {
      payload.expires_at = new Date(data.expires_at).toISOString();
    } else {
      payload.expires_at = null;
    }

    await updateMutation.mutateAsync(payload);
    onClose();
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal-content"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-header">
          <h2>Edit access code</h2>

          <button className="icon-button" onClick={onClose}>
            <X size={18} />
          </button>
        </div>

        <div className="edit-code-info">
          <div className="code-cell">
            <div className="code-icon">
              <KeyRound size={15} />
            </div>
            <code>{accessCode.code}</code>
          </div>

          <span className="edit-email">{accessCode.email}</span>
        </div>

        <form onSubmit={handleSubmit(onSubmit)}>
          <div className="form-field">
            <label htmlFor="edit-description">
              Description{' '}
              <span className="optional">(optional)</span>
            </label>

            <input
              id="edit-description"
              type="text"
              placeholder="e.g. Development team"
              {...register('description')}
            />

            {errors.description && (
              <span className="field-error">
                {errors.description.message}
              </span>
            )}
          </div>

          <div className="form-field">
            <label htmlFor="edit-expires">
              Expiry date{' '}
              <span className="optional">(optional)</span>
            </label>

            <input
              id="edit-expires"
              type="datetime-local"
              {...register('expires_at')}
            />

            {errors.expires_at && (
              <span className="field-error">
                {errors.expires_at.message}
              </span>
            )}
          </div>

          {updateMutation.isError && (
            <div className="alert-error">
              {updateMutation.error?.response?.data?.message ||
                'Failed to update code.'}
            </div>
          )}

          <div className="modal-actions">
            <button
              type="button"
              className="secondary-button"
              onClick={onClose}
            >
              Cancel
            </button>

            <button
              type="submit"
              className="primary-button compact"
              disabled={updateMutation.isPending}
            >
              {updateMutation.isPending ? (
                <>
                  <Loader2 size={16} className="spin" />
                  Saving...
                </>
              ) : (
                'Save changes'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function AccessCodesPage() {
  const [showGenerateModal, setShowGenerateModal] = useState(false);
  const [editingCode, setEditingCode] = useState(null);
  const [page, setPage] = useState(1);
  const { data, isLoading, isError } = useAccessCodes(page);
  const updateMutation = useUpdateAccessCode();
  const deleteMutation = useDeleteAccessCode();
  const sendEmailMutation = useSendAccessCodeEmail();

  const codes = data?.data ?? [];
  const meta = data?.meta;

  function handleToggle(item) {
    updateMutation.mutate({
      id: item.id,
      is_active: !item.is_active,
    });
  }

  function handleDelete(item) {
    if (window.confirm(`Delete access code ${item.code}?`)) {
      deleteMutation.mutate(item.id);
    }
  }

  function handleSendEmail(item) {
    if (window.confirm(`Send access code to ${item.email}?`)) {
      sendEmailMutation.mutate(item.id);
    }
  }

  return (
    <div>
      <div className="page-header">
        <div>
          <span className="eyebrow">Security</span>

          <h1>Access codes</h1>

          <p>
            Manage the codes authorized to create short URLs.
          </p>
        </div>

        <button
          className="primary-button compact"
          onClick={() => setShowGenerateModal(true)}
        >
          <Plus size={17} />
          Generate code
        </button>
      </div>

      {sendEmailMutation.isSuccess && (
        <div className="alert-success">
          <Check size={14} />
          Access code email sent successfully.
        </div>
      )}

      {sendEmailMutation.isError && (
        <div className="alert-error">
          {sendEmailMutation.error?.response?.data?.message ||
            'Failed to send email.'}
        </div>
      )}

      <div className="panel">
        {isLoading && (
          <div className="table-empty">
            <Loader2 size={24} className="spin" />
            <span>Loading access codes...</span>
          </div>
        )}

        {isError && (
          <div className="table-empty">
            <span className="text-danger">
              Failed to load access codes.
            </span>
          </div>
        )}

        {!isLoading && !isError && codes.length === 0 && (
          <div className="table-empty">
            <KeyRound size={32} />
            <span>No access codes yet.</span>
            <button
              className="primary-button compact"
              onClick={() => setShowGenerateModal(true)}
            >
              <Plus size={16} />
              Generate your first code
            </button>
          </div>
        )}

        {!isLoading && codes.length > 0 && (
          <div className="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Access code</th>
                  <th>Email</th>
                  <th>Description</th>
                  <th>Status</th>
                  <th>Expires</th>
                  <th>Last used</th>
                  <th />
                </tr>
              </thead>

              <tbody>
                {codes.map((item) => (
                  <tr
                    key={item.id}
                    className={
                      !item.is_active ? 'row-inactive' : ''
                    }
                  >
                    <td>
                      <div className="code-cell">
                        <div className="code-icon">
                          <KeyRound size={15} />
                        </div>

                        <code>{item.code}</code>

                        <CopyButton text={item.code} />
                      </div>
                    </td>

                    <td>{item.email}</td>

                    <td>
                      <span className="description-cell">
                        {item.description || '---'}
                      </span>
                    </td>

                    <td>
                      <span
                        className={`status-badge ${
                          item.is_active
                            ? 'status-active'
                            : 'status-inactive'
                        }`}
                      >
                        {item.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>

                    <td>{formatDate(item.expires_at)}</td>

                    <td>{formatDate(item.last_used_at)}</td>

                    <td>
                      <RowActions
                        accessCode={item}
                        onToggle={() => handleToggle(item)}
                        onDelete={() => handleDelete(item)}
                        onEdit={() => setEditingCode(item)}
                        onSendEmail={() => handleSendEmail(item)}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta && meta.last_page > 1 && (
          <div className="table-pagination">
            <button
              className="secondary-button compact"
              disabled={page <= 1}
              onClick={() => setPage((p) => p - 1)}
            >
              Previous
            </button>

            <span className="pagination-info">
              Page {meta.current_page} of {meta.last_page}
            </span>

            <button
              className="secondary-button compact"
              disabled={page >= meta.last_page}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </button>
          </div>
        )}
      </div>

      {showGenerateModal && (
        <GenerateModal onClose={() => setShowGenerateModal(false)} />
      )}

      {editingCode && (
        <EditModal
          accessCode={editingCode}
          onClose={() => setEditingCode(null)}
        />
      )}
    </div>
  );
}
