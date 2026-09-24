import { Outlet } from 'react-router-dom';
import Logo from '../common/Logo';

export default function PublicLayout() {
  return (
    <div className="public-shell">
      <header className="public-header">
        <Logo />

        <a
          href="/login"
          className="header-login-link"
        >
          Admin Portal
        </a>
      </header>

      <main>
        <Outlet />
      </main>

      <footer className="public-footer">
        <span>© {new Date().getFullYear()} LinkForge</span>
        <span>Secure URL Management</span>
      </footer>
    </div>
  );
}