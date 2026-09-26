import { Link, useRouteError } from 'react-router-dom';
import { Home, Link2, SearchX } from 'lucide-react';
import Logo from './Logo';

export default function NotFound() {
  const error = useRouteError();

  const status = error?.status ?? 404;
  const isNotFound = status === 404 || !error?.status;

  const title = isNotFound ? 'Page not found' : 'Something went wrong';
  const message = isNotFound
    ? "The page you're looking for doesn't exist or may have been moved."
    : error?.statusText ||
      error?.message ||
      'An unexpected error occurred. Please try again.';

  return (
    <div className="error-page">
      <div className="error-background" />

      <div className="error-topbar">
        <Logo />
      </div>

      <section className="error-container">
        <div className="error-icon">
          <SearchX size={34} />
        </div>

        <div className="error-code">{isNotFound ? '404' : status}</div>

        <h1>{title}</h1>

        <p>{message}</p>

        <div className="error-actions">
          <Link to="/" className="primary-button">
            <Home size={18} />
            Back to home
          </Link>

          <Link to="/login" className="secondary-button">
            <Link2 size={18} />
            Admin portal
          </Link>
        </div>
      </section>

      <footer className="error-footer">
        <span>© {new Date().getFullYear()} LinkForge</span>
        <span>Secure URL Management</span>
      </footer>
    </div>
  );
}
