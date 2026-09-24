import {
  BarChart3,
  KeyRound,
  Link2,
  LayoutDashboard,
  Settings,
  ShieldCheck,
} from 'lucide-react';
import { NavLink } from 'react-router-dom';
import Logo from '../common/Logo';

const navigation = [
  {
    label: 'Overview',
    path: '/admin',
    icon: LayoutDashboard,
    end: true,
  },
  {
    label: 'Short URLs',
    path: '/admin/urls',
    icon: Link2,
  },
  {
    label: 'Access Codes',
    path: '/admin/access-codes',
    icon: KeyRound,
  },
];

export default function Sidebar() {
  return (
    <aside className="sidebar">
      <div className="sidebar-brand">
        <Logo />
      </div>

      <div className="sidebar-section">
        <span className="sidebar-label">Workspace</span>

        <nav>
          {navigation.map((item) => {
            const Icon = item.icon;

            return (
              <NavLink
                key={item.path}
                to={item.path}
                end={item.end}
                className={({ isActive }) =>
                  `sidebar-link ${isActive ? 'active' : ''}`
                }
              >
                <Icon size={18} />
                <span>{item.label}</span>
              </NavLink>
            );
          })}
        </nav>
      </div>

      <div className="sidebar-bottom">
        <div className="security-card">
          <ShieldCheck size={20} />

          <div>
            <strong>Protected</strong>
            <span>Private workspace</span>
          </div>
        </div>

        <NavLink
          to="/admin/settings"
          className="sidebar-link"
        >
          <Settings size={18} />
          <span>Settings</span>
        </NavLink>
      </div>
    </aside>
  );
}