import { useMemo, useRef, useState } from 'react';

const W = 720;
const H = 240;
const PAD = { top: 16, right: 16, bottom: 28, left: 36 };

function formatDay(iso) {
  return new Date(iso + 'T00:00:00').toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
  });
}

export default function ActivityChart({ data = [], label = 'Activity' }) {
  const svgRef = useRef(null);
  const [hover, setHover] = useState(null);

  const geom = useMemo(() => {
    const n = data.length;
    const plotW = W - PAD.left - PAD.right;
    const plotH = H - PAD.top - PAD.bottom;
    const max = Math.max(1, ...data.map((d) => d.count));

    const xFor = (i) =>
      PAD.left + (n <= 1 ? plotW / 2 : (i / (n - 1)) * plotW);
    const yFor = (v) => PAD.top + plotH - (v / max) * plotH;

    const points = data.map((d, i) => ({
      ...d,
      x: xFor(i),
      y: yFor(d.count),
    }));

    const line = points.map((p) => `${p.x},${p.y}`).join(' ');
    const area =
      points.length > 0
        ? `M ${points[0].x},${PAD.top + plotH} ` +
          points.map((p) => `L ${p.x},${p.y}`).join(' ') +
          ` L ${points[points.length - 1].x},${PAD.top + plotH} Z`
        : '';

    return { n, plotW, plotH, max, points, line, area };
  }, [data]);

  const yTicks = useMemo(() => {
    const { max } = geom;
    return [0, Math.round(max / 2), max].filter(
      (v, i, a) => a.indexOf(v) === i,
    );
  }, [geom]);

  function handleMove(e) {
    if (!svgRef.current || geom.n === 0) return;

    const rect = svgRef.current.getBoundingClientRect();
    const ratio = (e.clientX - rect.left) / rect.width;
    const xInView = ratio * W;

    let nearest = 0;
    let best = Infinity;

    geom.points.forEach((p, i) => {
      const d = Math.abs(p.x - xInView);
      if (d < best) {
        best = d;
        nearest = i;
      }
    });

    setHover(nearest);
  }

  const hp = hover != null ? geom.points[hover] : null;

  return (
    <div className="chart">
      <svg
        ref={svgRef}
        viewBox={`0 0 ${W} ${H}`}
        className="chart-svg"
        onMouseMove={handleMove}
        onMouseLeave={() => setHover(null)}
        role="img"
        aria-label={`${label} per day`}
      >
        <defs>
          <linearGradient id="activityFill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--primary)" stopOpacity="0.28" />
            <stop offset="100%" stopColor="var(--primary)" stopOpacity="0.02" />
          </linearGradient>
        </defs>

        {yTicks.map((t) => {
          const y = PAD.top + geom.plotH - (t / geom.max) * geom.plotH;
          return (
            <g key={t}>
              <line
                x1={PAD.left}
                x2={W - PAD.right}
                y1={y}
                y2={y}
                className="chart-grid"
              />
              <text x={PAD.left - 8} y={y + 3} className="chart-axis-label" textAnchor="end">
                {t}
              </text>
            </g>
          );
        })}

        {geom.area && <path d={geom.area} fill="url(#activityFill)" />}

        {geom.points.length > 1 && (
          <polyline
            points={geom.line}
            fill="none"
            stroke="var(--primary)"
            strokeWidth="2"
            strokeLinejoin="round"
            strokeLinecap="round"
            vectorEffect="non-scaling-stroke"
          />
        )}

        {hp && (
          <g>
            <line
              x1={hp.x}
              x2={hp.x}
              y1={PAD.top}
              y2={PAD.top + geom.plotH}
              className="chart-crosshair"
              vectorEffect="non-scaling-stroke"
            />
            <circle cx={hp.x} cy={hp.y} r="4" className="chart-dot" />
          </g>
        )}

        {geom.points.map((p, i) => {
          const step = Math.max(1, Math.floor(geom.n / 6));
          if (i % step !== 0 && i !== geom.n - 1) return null;
          return (
            <text
              key={p.date}
              x={p.x}
              y={H - 8}
              className="chart-axis-label"
              textAnchor="middle"
            >
              {formatDay(p.date)}
            </text>
          );
        })}
      </svg>

      {hp && (
        <div
          className="chart-tooltip"
          style={{
            left: `${(hp.x / W) * 100}%`,
            top: `${(hp.y / H) * 100}%`,
          }}
        >
          <strong>{hp.count}</strong>
          <span>{formatDay(hp.date)}</span>
        </div>
      )}
    </div>
  );
}
