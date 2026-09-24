import {
  Copy,
  KeyRound,
  Plus,
  MoreHorizontal,
} from 'lucide-react';

const demoCodes = [
  {
    id: 1,
    code: 'USR-7F4K-92QX',
    user: 'Development User',
    status: 'Active',
    usage: 142,
    expires: 'Never',
  },
  {
    id: 2,
    code: 'USR-3P8L-21MN',
    user: 'Marketing',
    status: 'Active',
    usage: 87,
    expires: 'Dec 31, 2026',
  },
];

export default function AccessCodesPage() {
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

        <button className="primary-button compact">
          <Plus size={17} />
          Generate code
        </button>
      </div>

      <div className="panel">
        <div className="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Access code</th>
                <th>User</th>
                <th>Usage</th>
                <th>Status</th>
                <th>Expires</th>
                <th />
              </tr>
            </thead>

            <tbody>
              {demoCodes.map((item) => (
                <tr key={item.id}>
                  <td>
                    <div className="code-cell">
                      <div className="code-icon">
                        <KeyRound size={15} />
                      </div>

                      <code>{item.code}</code>

                      <button className="icon-button">
                        <Copy size={14} />
                      </button>
                    </div>
                  </td>

                  <td>{item.user}</td>

                  <td>{item.usage}</td>

                  <td>
                    <span className="status-badge">
                      {item.status}
                    </span>
                  </td>

                  <td>{item.expires}</td>

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
      </div>
    </div>
  );
}