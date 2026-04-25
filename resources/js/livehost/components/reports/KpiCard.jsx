import { ArrowDown, ArrowUp, Minus } from 'lucide-react';

export default function KpiCard({ label, value, delta, format = (v) => v }) {
  const direction = delta == null ? 'flat' : delta > 0 ? 'up' : delta < 0 ? 'down' : 'flat';
  const Icon = direction === 'up' ? ArrowUp : direction === 'down' ? ArrowDown : Minus;
  const tone =
    direction === 'up'
      ? 'text-emerald-600'
      : direction === 'down'
        ? 'text-rose-600'
        : 'text-muted-foreground';

  return (
    <div className="rounded-xl border bg-card p-5">
      <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        {label}
      </div>
      <div className="mt-2 text-2xl font-semibold">{format(value)}</div>
      {delta != null && (
        <div className={`mt-1 flex items-center gap-1 text-xs ${tone}`}>
          <Icon className="size-3" />
          <span>{Math.abs(delta).toFixed(1)}% vs prior period</span>
        </div>
      )}
    </div>
  );
}
