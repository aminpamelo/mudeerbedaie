import { cn } from '../../lib/utils';
import { getAccent } from '../../lib/accents';

/**
 * Empty state — colored disc + heading + optional description + optional action.
 *
 * <EmptyState
 *   icon={Coffee}
 *   accent="slate"
 *   title="Everyone's in today"
 *   description="No one is on leave"
 * />
 *
 * <EmptyState
 *   icon={UserCheck}
 *   accent="emerald"
 *   title="All caught up"
 *   description="No pending approvals"
 *   action={<Button>View all requests</Button>}
 * />
 */
export function EmptyState({ icon: Icon, accent = 'slate', title, description, action, className }) {
    const a = getAccent(accent);
    return (
        <div className={cn('flex flex-col items-center py-7 text-center', className)}>
            {Icon && (
                <div className={cn('mb-2 flex h-10 w-10 items-center justify-center rounded-full', a.iconBg)}>
                    <Icon className={cn('h-5 w-5', a.iconFlat)} strokeWidth={2.25} />
                </div>
            )}
            {title && <p className="text-xs font-medium text-slate-600">{title}</p>}
            {description && <p className="mt-0.5 text-[11px] text-slate-400">{description}</p>}
            {action && <div className="mt-3">{action}</div>}
        </div>
    );
}
