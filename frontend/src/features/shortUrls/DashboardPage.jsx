import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  ArrowUpRight,
  Link2,
  MousePointerClick,
  Users,
  ShieldCheck,
  AlertCircle,
  Loader2,
} from 'lucide-react';

import StatsCard from './components/StatsCard';
import { useShortUrls } from './hooks';
import { useOverview } from '../analytics/hooks';
import ActivityChart from '../analytics/components/ActivityChart';
import { formatNumber } from '../../lib/utils';

const METRICS = {
  clicks: { key: 'clicks_series', label: 'Clicks' },
  created: { key: 'urls_created', label: 'Links created' },
};

export default function DashboardPage() {
  const [days, setDays] = useState(30);
  const [metric, setMetric] = useState('clicks');

  const { data: overview, isLoading, isError } = useOverview(days);
  const { data: recent } = useShortUrls({ per_page: 5 });

  const totals = overview?.totals;
  const recentLinks = recent?.data ?? [];
  const topUrls = overview?.top_urls ?? [];
  const maxTopClicks = Math.max(1, ...topUrls.map((u) => u.click_count));
  const series = overview?.[METRICS[metric].key] ?? [];

  return (
    <div>
      <div className="page-header">
        <div>
          <span className="eyebrow">Overview</span>

          <h1>Dashboard</h1>

          <p>Monitor your short links and access activity.</p>
        </div>

        <Link to="/" className="secondary-button">
          Create short URL
          <ArrowUpRight size={16} />
        </Link>
      </div>

      {isError && (
        <div className="alert-error">
          <AlertCircle size={15} />
          Failed to load analytics.
        </div>
      )}

      <div className="stats-grid">
        <StatsCard
          title="Total URLs"
          value={formatNumber(totals?.total_urls)}
          icon={Link2}
          loading={isLoading}
        />

        <StatsCard
          title="Total clicks"
          value={formatNumber(totals?.total_clicks)}
          icon={MousePointerClick}
          loading={isLoading}
        />

        <StatsCard
          title="Active codes"
          value={formatNumber(totals?.active_codes)}
          hint={
            totals ? `of ${formatNumber(totals.total_codes)} total` : undefined
          }
          icon={Users}
          loading={isLoading}
        />

        <StatsCard
          title="System status"
          value="Healthy"
          change="Live"
          icon={ShieldCheck}
        />
      </div>

      <section className="dashboard-grid">
        <div className="panel large-panel">
          <div className="panel-header">
            <div>
              <h2>Activity</h2>
              <p>{METRICS[metric].label} over the selected window.</p>
            </div>

            <div className="panel-controls">
              <div className="metric-toggle">
                {Object.entries(METRICS).map(([key, m]) => (
                  <button
                    key={key}
                    type="button"
                    className={metric === key ? 'active' : ''}
                    onClick={() => setMetric(key)}
                  >
                    {m.label}
                  </button>
                ))}
              </div>

              <select
                className="period-select"
                value={days}
                onChange={(e) => setDays(Number(e.target.value))}
              >
                <option value={30}>Last 30 days</option>
                <option value={7}>Last 7 days</option>
                <option value={90}>Last 90 days</option>
              </select>
            </div>
          </div>

          {isLoading ? (
            <div className="chart-placeholder">
              <Loader2 size={22} className="spin" />
              <span>Loading activity…</span>
            </div>
          ) : (
            <ActivityChart data={series} label={METRICS[metric].label} />
          )}
        </div>

        <div className="panel">
          <div className="panel-header">
            <div>
              <h2>Recent links</h2>
              <p>Latest generated URLs.</p>
            </div>
          </div>

          {recentLinks.length === 0 ? (
            <div className="recent-empty">No links yet.</div>
          ) : (
            <div className="recent-list">
              {recentLinks.map((link) => (
                <div className="recent-item" key={link.id}>
                  <div className="recent-icon">
                    <Link2 size={16} />
                  </div>

                  <div className="recent-body">
                    <strong>{link.short_code}</strong>
                    <span>{link.long_url}</span>
                  </div>

                  <span className="recent-clicks">
                    {formatNumber(link.click_count)}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>
      </section>

      <section className="panel">
        <div className="panel-header">
          <div>
            <h2>Top URLs by clicks</h2>
            <p>Your most-visited short links.</p>
          </div>
        </div>

        {topUrls.length === 0 ? (
          <div className="recent-empty">No click data yet.</div>
        ) : (
          <div className="bar-list">
            {topUrls.map((url) => (
              <div className="bar-row" key={url.short_code}>
                <div className="bar-label">
                  <strong>{url.short_code}</strong>
                  <span>{url.long_url}</span>
                </div>

                <div className="bar-track">
                  <div
                    className="bar-fill"
                    style={{
                      width: `${(url.click_count / maxTopClicks) * 100}%`,
                    }}
                  />
                </div>

                <span className="bar-value">
                  {formatNumber(url.click_count)}
                </span>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
