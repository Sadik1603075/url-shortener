import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import Topbar from './Topbar';

export default function AdminLayout() {
  return (
    <div className="admin-shell">
      <Sidebar />

      <div className="admin-content">
        <Topbar />

        <main className="admin-main">
          <Outlet />
        </main>
      </div>
    </div>
  );
}