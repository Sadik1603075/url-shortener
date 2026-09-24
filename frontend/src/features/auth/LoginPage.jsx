import { useState } from 'react';
import { ArrowRight, LockKeyhole, ShieldCheck } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { authStore } from './authStore';
import { login } from './api';

export default function LoginPage() {
  const navigate = useNavigate();
  const [error, setError] = useState('');

  const {
    register,
    handleSubmit,
    formState: { isSubmitting },
  } = useForm();

  async function onSubmit(data) {
    try {
      setError('');

      const response = await login(data);

      authStore.login(
        response.token,
        response.user,
      );

      navigate('/admin');
    } catch (err) {
      setError(
        err.response?.data?.message ||
        'Invalid credentials.',
      );
    }
  }

  return (
    <div className="login-page">
      <div className="login-decoration" />

      <div className="login-card">
        <div className="login-logo">
          <div className="logo-mark">
            <LockKeyhole size={20} />
          </div>
        </div>

        <div className="login-heading">
          <span className="eyebrow">Administration</span>

          <h1>Welcome back</h1>

          <p>
            Sign in to manage your private URL workspace.
          </p>
        </div>

        {error && (
          <div className="alert-error">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)}>
          <div className="form-field">
            <label>Email address</label>

            <input
              type="email"
              placeholder="admin@example.com"
              {...register('email', {
                required: true,
              })}
            />
          </div>

          <div className="form-field">
            <label>Password</label>

            <input
              type="password"
              placeholder="••••••••"
              {...register('password', {
                required: true,
              })}
            />
          </div>

          <button
            className="primary-button"
            disabled={isSubmitting}
          >
            {isSubmitting ? 'Signing in...' : 'Sign in'}
            <ArrowRight size={18} />
          </button>
        </form>

        <div className="login-security">
          <ShieldCheck size={16} />
          Protected administrative access
        </div>
      </div>
    </div>
  );
}