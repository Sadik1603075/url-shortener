export default function StatsCard({
  title,
  value,
  change,
  icon: Icon,
}) {
  return (
    <div className="stats-card">
      <div className="stats-card-top">
        <div className="stats-icon">
          <Icon size={19} />
        </div>

        <span className="stats-change">
          {change}
        </span>
      </div>

      <span className="stats-title">{title}</span>

      <strong className="stats-value">{value}</strong>
    </div>
  );
}