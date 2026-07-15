import { cn } from '@/fighter/lib/utils';

const ACCENTS = {
  brand: { ring: 'ring-orange-600/15', icon: 'bg-orange-50 text-orange-600', value: 'text-ink' },
  emerald: { ring: 'ring-emerald-600/15', icon: 'bg-emerald-50 text-emerald-600', value: 'text-ink' },
  sky: { ring: 'ring-sky-600/15', icon: 'bg-sky-50 text-sky-600', value: 'text-ink' },
  amber: { ring: 'ring-amber-600/15', icon: 'bg-amber-50 text-amber-600', value: 'text-ink' },
};

/** Compact KPI tile with an icon, label and value. */
export default function StatTile({ icon: Icon, label, value, sub, accent = 'brand' }) {
  const a = ACCENTS[accent] ?? ACCENTS.brand;
  return (
    <div className={cn('flex items-center gap-3.5 rounded-2xl bg-white p-4 ring-1', a.ring)}>
      <div className={cn('grid h-11 w-11 shrink-0 place-items-center rounded-xl', a.icon)}>
        {Icon && <Icon className="h-5 w-5" strokeWidth={2} />}
      </div>
      <div className="min-w-0">
        <div className="text-[11.5px] font-semibold uppercase tracking-[0.04em] text-muted-2">{label}</div>
        <div className={cn('mt-0.5 truncate text-[19px] font-bold tracking-[-0.02em]', a.value)}>{value}</div>
        {sub && <div className="text-[11.5px] text-muted">{sub}</div>}
      </div>
    </div>
  );
}
