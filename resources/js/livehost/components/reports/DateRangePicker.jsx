import { useMemo } from 'react';
import { Button } from '@/livehost/components/ui/button';

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
    <div className="flex flex-wrap items-center gap-2">
      {PRESETS.map((p) => (
        <Button
          key={p.key}
          variant={presetMatches === p.key ? 'default' : 'outline'}
          size="sm"
          onClick={() => onChange(preset(p.key))}
        >
          {p.label}
        </Button>
      ))}
      <div className="ml-2 flex items-center gap-2">
        <input
          type="date"
          value={value.dateFrom}
          onChange={(e) => onChange({ ...value, dateFrom: e.target.value })}
          className="h-9 rounded-md border bg-background px-2 text-sm"
        />
        <span className="text-muted-foreground">→</span>
        <input
          type="date"
          value={value.dateTo}
          onChange={(e) => onChange({ ...value, dateTo: e.target.value })}
          className="h-9 rounded-md border bg-background px-2 text-sm"
        />
      </div>
    </div>
  );
}
