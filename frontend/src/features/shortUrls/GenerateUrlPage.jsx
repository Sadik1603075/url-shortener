import { useState } from 'react';
import { ArrowRight, Check, Copy, Link2, ShieldCheck } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';

const schema = z.object({
  access_code: z
    .string()
    .min(1, 'Access code is required'),

  long_url: z
    .string()
    .url('Enter a valid URL')
    .max(2048, 'URL is too long'),
});

export default function GenerateUrlPage() {
  const [result, setResult] = useState(null);
  const [copied, setCopied] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
  });

  async function onSubmit(data) {
    // Connected to the Laravel API in the next step.
    console.log(data);

    setResult({
      short_url: 'http://localhost:8000/zn9edcu',
    });
  }

  async function copyUrl() {
    if (!result?.short_url) return;

    await navigator.clipboard.writeText(result.short_url);

    setCopied(true);

    setTimeout(() => {
      setCopied(false);
    }, 2000);
  }

  return (
    <div className="generator-page">
      <div className="generator-background" />

      <section className="generator-container">
        <div className="hero-badge">
          <ShieldCheck size={15} />
          Private URL shortening
        </div>

        <h1>
          Turn long URLs into
          <span> simple links.</span>
        </h1>

        <p className="hero-description">
          Create secure, trackable short links using your
          authorized access code.
        </p>

        <div className="generator-card">
          <div className="generator-card-header">
            <div className="generator-icon">
              <Link2 size={22} />
            </div>

            <div>
              <h2>Create a short link</h2>
              <p>Your access code keeps this service private.</p>
            </div>
          </div>

          <form onSubmit={handleSubmit(onSubmit)}>
            <div className="form-field">
              <label>Access code</label>

              <input
                {...register('access_code')}
                placeholder="Enter your access code"
                className={errors.access_code ? 'input-error' : ''}
              />

              {errors.access_code && (
                <span className="field-error">
                  {errors.access_code.message}
                </span>
              )}
            </div>

            <div className="form-field">
              <label>Long URL</label>

              <input
                {...register('long_url')}
                placeholder="https://example.com/your-long-url"
                className={errors.long_url ? 'input-error' : ''}
              />

              {errors.long_url && (
                <span className="field-error">
                  {errors.long_url.message}
                </span>
              )}
            </div>

            <button
              className="primary-button"
              disabled={isSubmitting}
            >
              {isSubmitting ? 'Creating...' : 'Create short link'}
              <ArrowRight size={18} />
            </button>
          </form>

          {result && (
            <div className="generated-result">
              <div>
                <span>Your short URL</span>
                <strong>{result.short_url}</strong>
              </div>

              <button
                onClick={copyUrl}
                className="copy-button"
              >
                {copied ? <Check size={18} /> : <Copy size={18} />}
                {copied ? 'Copied' : 'Copy'}
              </button>
            </div>
          )}
        </div>

        <div className="generator-features">
          <div>
            <ShieldCheck size={18} />
            <span>Private access</span>
          </div>

          <div>
            <Link2 size={18} />
            <span>Fast redirects</span>
          </div>

          <div>
            <Check size={18} />
            <span>Simple & reliable</span>
          </div>
        </div>
      </section>
    </div>
  );
}