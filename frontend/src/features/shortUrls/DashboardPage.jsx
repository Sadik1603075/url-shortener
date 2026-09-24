import {
  ArrowUpRight,
  Link2,
  MousePointerClick,
  Users,
  ShieldCheck,
  BarChart3 
} from 'lucide-react';
import StatsCard from './components/StatsCard';

export default function DashboardPage() {
  return (
    <div>
      <div className="page-header">
        <div>
          <span className="eyebrow">Overview</span>

          <h1>Dashboard</h1>

          <p>
            Monitor your short links and access activity.
          </p>
        </div>

        <a
          href="/"
          className="secondary-button"
        >
          Create short URL
          <ArrowUpRight size={16} />
        </a>
      </div>

      <div className="stats-grid">
        <StatsCard
          title="Total URLs"
          value="1,284"
          change="+12.8%"
          icon={Link2}
        />

        <StatsCard
          title="Total clicks"
          value="48,291"
          change="+18.4%"
          icon={MousePointerClick}
        />

        <StatsCard
          title="Active codes"
          value="37"
          change="+4.2%"
          icon={Users}
        />

        <StatsCard
          title="System status"
          value="Healthy"
          change="99.99%"
          icon={ShieldCheck}
        />
      </div>

      <section className="dashboard-grid">
        <div className="panel large-panel">
          <div className="panel-header">
            <div>
              <h2>URL activity</h2>
              <p>Short-link usage over the last 30 days.</p>
            </div>

            <select className="period-select">
              <option>Last 30 days</option>
              <option>Last 7 days</option>
              <option>Last 90 days</option>
            </select>
          </div>

          <div className="chart-placeholder">
            <BarChart3 size={32} />
            <span>Analytics visualization</span>
          </div>
        </div>

        <div className="panel">
          <div className="panel-header">
            <div>
              <h2>Recent links</h2>
              <p>Latest generated URLs.</p>
            </div>
          </div>

          <div className="recent-list">
            {[
              ['zn9edcu', 'example.com/product'],
              ['a82kLm9', 'company.com/report'],
              ['xK82mLp', 'docs.example.com/api'],
              ['Q7w8e9R', 'example.com/dashboard'],
            ].map(([code, url]) => (
              <div className="recent-item" key={code}>
                <div className="recent-icon">
                  <Link2 size={16} />
                </div>

                <div>
                  <strong>{code}</strong>
                  <span>{url}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}