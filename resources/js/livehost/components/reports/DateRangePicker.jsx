import { useMemo } from 'react';

const PRESETS = [
  { key: 'today', label: 'Today' },
  { key: 'thisWeek', label: 'This week' },
  { key: 'thisMonth', label: 'This month' },
  { key: 'lastMonth', label: 'Last month' },
  { key: 'last30', label: 'Last 30 days' },
];

function preset(key) {
  const today = new Date();
  const iso = (d) => d.toISOString().slice(0, 10);
  const start = new Date(today);
  if (key === 'today') return { dateFrom: iso(today), dateTo: iso(today) };
  if (key === 'thisWeek') {
    const day = today.getDay() || 7;
    start.setDate(today.getDate() - day + 1);
    return { dateFrom: iso(start), dateTo: iso(today) };
  }
  if (key === 'thisMonth') {
    start.setDate(1);
    return { dateFrom: iso(start), dateTo: iso(today) };
  }
  if (key === 'lastMonth') {
    const first = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const last = new Date(today.getFullYear(), today.getMonth(), 0);
    return { dateFrom: iso(first), dateTo: iso(last) };
  }
  if (key === 'last30') {
    start.setDate(today.getDate() - 29);
    return { dateFrom: iso(start), dateTo: iso(today) };
  }
}

export default function DateRangePicker({ value, onChange }) {
  const presetMatches = useMemo(() => {
    for (const p of PRESETS) {
      const r = preset(p.key);
      if (r.dateFrom === value.dateFrom && r.dateTo === value.dateTo) return p.key;
    }
    return null;
  }, [value]);

  return (
    <div className="flex flex-wrap items-center gap-1.5">
      <div className="flex flex-wrap gap-1 rounded-lg bg-[var(--color-surface-2)] p-1">
        {PRESETS.map((p) => {
          const active = presetMatches === p.key;
          return (
            <button
              type="button"
              key={p.key}
              onClick={() => onChange(preset(p.key))}
              className={`rounded-md px-2.5 py-1.5 text-xs font-medium transition ${
                active
                  ? 'bg-[var(--color-surface)] text-[var(--color-ink)] shadow-sm'
                  : 'text-[var(--color-muted)] hover:text-[var(--color-ink)]'
              }`}
            >
              {p.label}
            </button>
          );
        })}
      </div>
      <div className="ml-1 flex items-center gap-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] px-2 py-1.5">
        <input
          type="date"
          value={value.dateFrom}
          onChange={(e) => onChange({ ...value, dateFrom: e.target.value })}
          className="bg-transparent text-xs text-[var(--color-ink)] focus:outline-none [color-scheme:light]"
        />
        <span className="text-[var(--color-muted)]">→</span>
        <input
          type="date"
          value={value.dateTo}
          onChange={(e) => onChange({ ...value, dateTo: e.target.value })}
          className="bg-transparent text-xs text-[var(--color-ink)] focus:outline-none [color-scheme:light]"
        />
      </div>
    </div>
  );
}
