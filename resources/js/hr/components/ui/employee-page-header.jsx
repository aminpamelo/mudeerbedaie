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
        indigo: { bg: 'bg-indigo-50', text: 'text-indigo-700', icon: 'text-indigo-500', ring: 'ring-indigo-100', darkText: 'dark:text-indigo-300', darkIcon: 'dark:text-indigo-400' },
        rose: { bg: 'bg-rose-50', text: 'text-rose-700', icon: 'text-rose-500', ring: 'ring-rose-100', darkText: 'dark:text-rose-300', darkIcon: 'dark:text-rose-400' },
        violet: { bg: 'bg-violet-50', text: 'text-violet-700', icon: 'text-violet-500', ring: 'ring-violet-100', darkText: 'dark:text-violet-300', darkIcon: 'dark:text-violet-400' },
        emerald: { bg: 'bg-emerald-50', text: 'text-emerald-700', icon: 'text-emerald-500', ring: 'ring-emerald-100', darkText: 'dark:text-emerald-300', darkIcon: 'dark:text-emerald-400' },
        amber: { bg: 'bg-amber-50', text: 'text-amber-700', icon: 'text-amber-500', ring: 'ring-amber-100', darkText: 'dark:text-amber-300', darkIcon: 'dark:text-amber-400' },
        sky: { bg: 'bg-sky-50', text: 'text-sky-700', icon: 'text-sky-500', ring: 'ring-sky-100', darkText: 'dark:text-sky-300', darkIcon: 'dark:text-sky-400' },
        pink: { bg: 'bg-pink-50', text: 'text-pink-700', icon: 'text-pink-500', ring: 'ring-pink-100', darkText: 'dark:text-pink-300', darkIcon: 'dark:text-pink-400' },
        slate: { bg: 'bg-slate-50', text: 'text-slate-700', icon: 'text-slate-500', ring: 'ring-slate-200', darkText: 'dark:text-slate-200', darkIcon: 'dark:text-slate-400' },
    };
    const c = colors[accent] || colors.indigo;
    return (
        <div className="flex items-center justify-between gap-3">
            <div className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold shadow-sm ring-1',
                c.bg, c.text, c.ring,
                'dark:bg-white/[0.06] dark:shadow-none dark:ring-white/[0.10] dark:backdrop-blur-md', c.darkText
            )}>
                {Icon && <Icon className={cn('h-3.5 w-3.5', c.icon, c.darkIcon)} strokeWidth={2.25} />}
                {title}
            </div>
            <div className="flex items-center gap-2">
                {context && (
                    <span className="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{context}</span>
                )}
                {action}
            </div>
        </div>
    );
}
