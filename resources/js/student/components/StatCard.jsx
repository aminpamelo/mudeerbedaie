import { cn } from '@/student/lib/utils';

export default function StatCard({ icon: Icon, label, value, className, iconClassName }) {
  return (
    <div className={cn('glass-card flex items-center gap-3.5 rounded-2xl p-4 shadow-sm', className)}>
      {Icon && (
        <div className={cn('grid h-10 w-10 shrink-0 place-items-center rounded-xl', iconClassName ?? 'bg-violet-100')}>
          <Icon className="h-5 w-5 text-violet-600" strokeWidth={2} />
        </div>
      )}
      <div className="min-w-0">
        <p className="text-[11px] font-semibold uppercase tracking-wider text-muted">{label}</p>
        <p className="text-[20px] font-extrabold leading-tight text-ink">{value}</p>
      </div>
    </div>
  );
}
