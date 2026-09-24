import { Bell, LogOut } from 'lucide-react';
import { useAuth, authStore } from '../../features/auth/authStore';

export default function Topbar() {
  const { user } = useAuth();

  return (
    <header className="topbar">
      <div />

      <div className="topbar-actions">
        <button className="icon-button">
          <Bell size={19} />
        </button>

        <div className="user-menu">
          <div className="avatar">
            {user?.name?.charAt(0)?.toUpperCase() || 'A'}
          </div>

          <div className="user-info">
            <strong>{user?.name || 'Administrator'}</strong>
            <span>{user?.email || ''}</span>
          </div>

          <button
            className="logout-button"
            onClick={() => authStore.logout()}
          >
            <LogOut size={16} />
          </button>
        </div>
      </div>
    </header>
  );
}