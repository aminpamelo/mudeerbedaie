import { ChevronRight } from 'lucide-react';
import { cn } from '../../lib/utils';
import { getAccent } from '../../lib/accents';
import { EmptyState } from './empty-state';

/**
 * RecordCard — the standard card row used in employee list pages.
 * Replaces table rows on mobile-first surfaces (MyApprovals, MyTasks, etc.)
 *
 * <RecordCard
 *   icon={Palmtree}
 *   accent="violet"
 *   title="Annual Leave"
 *   subtitle="20 Apr – 21 Apr · 2 days"
 *   meta="Submitted 3d ago"
 *   badge={<StatusBadge status="pending" />}
 *   onClick={() => navigate(`/my/leave/${id}`)}
 * />
 */
export function RecordCard({ icon: Icon, accent = 'indigo', title, subtitle, meta, badge, onClick, className, children }) {
    const a = getAccent(accent);
    const Component = onClick ? 'button' : 'div';

    return (
        <Component
            onClick={onClick}
            className={cn(
                'group flex w-full items-center gap-3 rounded-2xl border border-slate-200/80 bg-white p-3.5 text-left transition-all',
                onClick && 'hover:border-indigo-200 hover:shadow-md hover:shadow-slate-200/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2',
                className
            )}
        >
            {Icon && (
                <div className={cn('flex h-11 w-11 shrink-0 items-center justify-center rounded-xl shadow-sm', a.iconBgSolid)}>
                    <Icon className={cn('h-5 w-5', a.iconColorOnSolid)} strokeWidth={2.25} />
                </div>
            )}

            <div className="min-w-0 flex-1">
                {title && (
                    <div className="flex items-center gap-2">
                        <p className="truncate text-sm font-semibold text-slate-900">{title}</p>
                        {badge && <span className="shrink-0">{badge}</span>}
                    </div>
                )}
                {subtitle && (
                    <p className="mt-0.5 truncate text-xs text-slate-600">{subtitle}</p>
                )}
                {meta && (
                    <p className="mt-0.5 truncate text-[11px] text-slate-400">{meta}</p>
                )}
                {children}
            </div>

            {onClick && (
                <ChevronRight className="h-4 w-4 shrink-0 text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-indigo-500" />
            )}
        </Component>
    );
}

/**
 * RecordList — wraps RecordCards with empty state + skeleton support.
 *
 * <RecordList items={items} isLoading={isLoading} renderItem={(item) => <RecordCard ... />} />
 */
export function RecordList({
    items = [],
    isLoading = false,
    renderItem,
    emptyIcon,
    emptyTitle = 'Nothing here yet',
    emptyDescription,
    emptyAction,
    emptyAccent = 'slate',
    skeletonCount = 4,
    className,
}) {
    if (isLoading) {
        return (
            <div className={cn('space-y-2', className)}>
                {Array.from({ length: skeletonCount }).map((_, i) => (
                    <div key={i} className="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white p-3.5">
                        <div className="h-11 w-11 shrink-0 animate-pulse rounded-xl bg-slate-200" />
                        <div className="flex-1 space-y-2">
                            <div className="h-3 w-2/3 animate-pulse rounded bg-slate-200" />
                            <div className="h-2.5 w-1/2 animate-pulse rounded bg-slate-100" />
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    if (!items || items.length === 0) {
        return (
            <div className={cn('rounded-2xl border border-dashed border-slate-200 bg-white py-2', className)}>
                <EmptyState
                    icon={emptyIcon}
                    accent={emptyAccent}
                    title={emptyTitle}
                    description={emptyDescription}
                    action={emptyAction}
                />
            </div>
        );
    }

    return (
        <div className={cn('space-y-2', className)}>
            {items.map((item, i) => renderItem(item, i))}
        </div>
    );
}
