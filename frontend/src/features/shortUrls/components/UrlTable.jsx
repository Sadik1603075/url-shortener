import { ExternalLink, MoreHorizontal } from 'lucide-react';
import { formatDate, formatNumber } from '../../../lib/utils';

export default function UrlTable({ urls = [] }) {
  if (!urls.length) {
    return (
      <div className="empty-state">
        <p>No short URLs have been created yet.</p>
      </div>
    );
  }

  return (
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
          {urls.map((url) => (
            <tr key={url.id}>
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
                <span className="destination">
                  {url.long_url}
                </span>
              </td>

              <td>
                {formatNumber(url.click_count)}
              </td>

              <td>
                <span className="status-badge">
                  Active
                </span>
              </td>

              <td>
                {formatDate(url.created_at)}
              </td>

              <td>
                <button className="icon-button">
                  <MoreHorizontal size={18} />
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}