import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  ArrowUpRight,
  Copy,
  Check,
  ExternalLink,
  Pencil,
  Ban,
  RotateCcw,
  Trash2,
  Loader2,
  X,
  Link2,
} from 'lucide-react';

import { useShortUrls, useUpdateShortUrl, useDeleteShortUrl } from './hooks';
import { formatDate, formatNumber } from '../../lib/utils';

const editSchema = z.object({
  long_url: z.string().url('Enter a valid URL').max(2048),
  expires_at: z
    .string()
    .optional()
    .refine((v) => !v || new Date(v) > new Date(), {
      message: 'Expiry must be in the future.',
    }),
});

function statusOf(url) {
  if (!url.is_active) return { label: 'Inactive', cls: 'status-inactive' };

  if (url.expires_at && new Date(url.expires_at) < new Date()) {
    return { label: 'Expired', cls: 'status-inactive' };
  }

  return { label: 'Active', cls: 'status-active' };
}

function CopyButton({ text }) {
  const [copied, setCopied] = useState(false);

  async function handleCopy() {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      /* noop */
    }
  }

  return (
    <button
      type="button"
      className="row-action-button"
      onClick={handleCopy}
      title="Copy short URL"
      aria-label="Copy short URL"
    >
      {copied ? <Check size={15} /> : <Copy size={15} />}
    </button>
  );
}

function EditModal({ url, onClose }) {
  const updateMutation = useUpdateShortUrl();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(editSchema),
    defaultValues: {
      long_url: url.long_url,
      expires_at: url.expires_at ? url.expires_at.slice(0, 16) : '',
    },
  });

  async function onSubmit(data) {
    const payload = { id: url.id, long_url: data.long_url };

    if (data.expires_at) {
      payload.expires_at = new Date(data.expires_at).toISOString();
    }

    await updateMutation.mutateAsync(payload);
    onClose();
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h2>Edit short URL</h2>
          <button className="icon-button" onClick={onClose}>
            <X size={18} />
          </button>
        </div>

        <div className="edit-code-info">
          <div className="code-cell">
            <div className="code-icon">
              <Link2 size={15} />
            </div>
            <code>{url.short_code}</code>
          </div>
        </div>

        <form onSubmit={handleSubmit(onSubmit)}>
          <div className="form-field">
            <label htmlFor="edit-long-url">Destination URL</label>
            <input id="edit-long-url" type="text" {...register('long_url')} />
            {errors.long_url && (
              <span className="field-error">{errors.long_url.message}</span>
            )}
          </div>

          <div className="form-field">
            <label htmlFor="edit-url-expires">
              Expiry date <span className="optional">(optional)</span>
            </label>
            <input
              id="edit-url-expires"
              type="datetime-local"
              {...register('expires_at')}
            />
            {errors.expires_at && (
              <span className="field-error">{errors.expires_at.message}</span>
            )}
          </div>

          {updateMutation.isError && (
            <div className="alert-error">
              {updateMutation.error?.response?.data?.message ||
                'Failed to update.'}
            </div>
          )}

          <div className="modal-actions">
            <button type="button" className="secondary-button" onClick={onClose}>
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
                  Saving…
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

export default function ShortUrlsPage() {
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState(null);

  const { data, isLoading, isError } = useShortUrls({ page, per_page: 15 });
  const updateMutation = useUpdateShortUrl();
  const deleteMutation = useDeleteShortUrl();

  const urls = data?.data ?? [];
  const meta = data?.meta;

  function toggleActive(url) {
    updateMutation.mutate({ id: url.id, is_active: !url.is_active });
  }

  function remove(url) {
    if (window.confirm(`Delete short URL "${url.short_code}"?`)) {
      deleteMutation.mutate(url.id);
    }
  }

  return (
    <div>
      <div className="page-header">
        <div>
          <span className="eyebrow">Links</span>
          <h1>Short URLs</h1>
          <p>Manage every short link created with an access code.</p>
        </div>

        <Link to="/" className="primary-button compact">
          Create short URL
          <ArrowUpRight size={16} />
        </Link>
      </div>

      <div className="panel">
        {isLoading && (
          <div className="table-empty">
            <Loader2 size={24} className="spin" />
            <span>Loading short URLs…</span>
          </div>
        )}

        {isError && (
          <div className="table-empty">
            <span className="text-danger">Failed to load short URLs.</span>
          </div>
        )}

        {!isLoading && !isError && urls.length === 0 && (
          <div className="table-empty">
            <Link2 size={32} />
            <span>No short URLs have been created yet.</span>
          </div>
        )}

        {!isLoading && urls.length > 0 && (
          <div className="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Short URL</th>
                  <th>Destination</th>
                  <th>Clicks</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th />
                </tr>
              </thead>

              <tbody>
                {urls.map((url) => {
                  const status = statusOf(url);
                  return (
                    <tr
                      key={url.id}
                      className={!url.is_active ? 'row-inactive' : ''}
                    >
                      <td>
                        <a
                          href={url.short_url}
                          target="_blank"
                          rel="noreferrer"
                          className="short-link"
                        >
                          {url.short_code}
                          <ExternalLink size={13} />
                        </a>
                      </td>

                      <td>
                        <span className="destination" title={url.long_url}>
                          {url.long_url}
                        </span>
                      </td>

                      <td>{formatNumber(url.click_count)}</td>

                      <td>
                        <span className={`status-badge ${status.cls}`}>
                          {status.label}
                        </span>
                      </td>

                      <td>{formatDate(url.created_at)}</td>

                      <td>
                        <div className="row-actions">
                          <CopyButton text={url.short_url} />

                          <button
                            type="button"
                            className="row-action-button"
                            onClick={() => setEditing(url)}
                            title="Edit"
                            aria-label="Edit short URL"
                          >
                            <Pencil size={15} />
                          </button>

                          {url.is_active ? (
                            <button
                              type="button"
                              className="row-action-button row-action-warning"
                              onClick={() => toggleActive(url)}
                              title="Deactivate"
                              aria-label="Deactivate short URL"
                            >
                              <Ban size={15} />
                            </button>
                          ) : (
                            <button
                              type="button"
                              className="row-action-button row-action-success"
                              onClick={() => toggleActive(url)}
                              title="Reactivate"
                              aria-label="Reactivate short URL"
                            >
                              <RotateCcw size={15} />
                            </button>
                          )}

                          <button
                            type="button"
                            className="row-action-button row-action-danger"
                            onClick={() => remove(url)}
                            title="Delete"
                            aria-label="Delete short URL"
                          >
                            <Trash2 size={15} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
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

      {editing && (
        <EditModal url={editing} onClose={() => setEditing(null)} />
      )}
    </div>
  );
}
