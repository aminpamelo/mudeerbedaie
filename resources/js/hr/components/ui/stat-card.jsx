import { cn } from '../../lib/utils';
import { getAccent } from '../../lib/accents';

/**
 * Tinted KPI card with colored icon block.
 *
 * <StatCard
 *   label="Employees"
 *   value={39}
 *   sub="39 active"
 *   icon={Users}
 *   accent="indigo"
 *   onClick={() => navigate('/employees')}
 * />
 */
export function StatCard({ label, value, sub, icon: Icon, accent = 'indigo', onClick, className }) {
    const a = getAccent(accent);
    const Component = onClick ? 'button' : 'div';

    return (
        <Component
            onClick={onClick}
            className={cn(
                'group relative overflow-hidden rounded-2xl border p-5 text-left transition-all duration-200',
                onClick && 'hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-white',
                a.cardTint,
                a.border,
                onClick && a.shadow,
                onClick && a.ring,
                className
            )}
        >
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-2">
                    <div className={cn('h-1.5 w-1.5 rounded-full', a.dot)} />
                    <p className={cn('text-[11px] font-semibold uppercase tracking-wider', a.label)}>{label}</p>
                </div>
                {Icon && (
                    <div className={cn('flex h-9 w-9 items-center justify-center rounded-xl', a.iconBgSolid)}>
                        <Icon className={cn('h-[18px] w-[18px]', a.iconColorOnSolid)} strokeWidth={2.25} />
                    </div>
                )}
            </div>
            <p className="mt-4 text-[34px] font-bold leading-none tracking-tight text-slate-900 tabular-nums">
                {value}
            </p>
            {sub && <p className="mt-2 text-xs text-slate-600">{sub}</p>}
        </Component>
    );
}
