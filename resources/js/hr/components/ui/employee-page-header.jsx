import { cn } from '../../lib/utils';

/**
 * EmployeePageHeader — the friendly top row used across "My" pages.
 *
 * Layout: [greeting/title chip on left]            [context on right]
 *
 * <EmployeePageHeader
 *   icon={Palmtree}
 *   accent="violet"
 *   title="My Leave"
 *   context="14 days · annual"
 * />
 */
export function EmployeePageHeader({ icon: Icon, accent = 'indigo', title, context, action }) {
    const colors = {
        indigo: { bg: 'bg-indigo-50', text: 'text-indigo-700', icon: 'text-indigo-500', ring: 'ring-indigo-100' },
        rose: { bg: 'bg-rose-50', text: 'text-rose-700', icon: 'text-rose-500', ring: 'ring-rose-100' },
        violet: { bg: 'bg-violet-50', text: 'text-violet-700', icon: 'text-violet-500', ring: 'ring-violet-100' },
        emerald: { bg: 'bg-emerald-50', text: 'text-emerald-700', icon: 'text-emerald-500', ring: 'ring-emerald-100' },
        amber: { bg: 'bg-amber-50', text: 'text-amber-700', icon: 'text-amber-500', ring: 'ring-amber-100' },
        sky: { bg: 'bg-sky-50', text: 'text-sky-700', icon: 'text-sky-500', ring: 'ring-sky-100' },
        pink: { bg: 'bg-pink-50', text: 'text-pink-700', icon: 'text-pink-500', ring: 'ring-pink-100' },
        slate: { bg: 'bg-slate-50', text: 'text-slate-700', icon: 'text-slate-500', ring: 'ring-slate-200' },
    };
    const c = colors[accent] || colors.indigo;
    return (
        <div className="flex items-center justify-between gap-3">
            <div className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold shadow-sm ring-1',
                c.bg, c.text, c.ring
            )}>
                {Icon && <Icon className={cn('h-3.5 w-3.5', c.icon)} strokeWidth={2.25} />}
                {title}
            </div>
            <div className="flex items-center gap-2">
                {context && (
                    <span className="text-[11px] font-semibold text-slate-500">{context}</span>
                )}
                {action}
            </div>
        </div>
    );
}
