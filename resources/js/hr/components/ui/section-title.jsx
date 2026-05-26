import { cn } from '../../lib/utils';
import { getAccent } from '../../lib/accents';
import { CardTitle } from './card';

/**
 * Section title with icon chip — used inside card headers.
 *
 * <SectionTitle icon={CalendarCheck} accent="emerald">
 *   Today's Attendance
 * </SectionTitle>
 *
 * <SectionTitle icon={ClipboardList} accent="rose" badge={<Badge>8</Badge>}>
 *   Pending Approvals
 * </SectionTitle>
 */
export function SectionTitle({ icon: Icon, accent = 'indigo', children, badge, className }) {
    const a = getAccent(accent);
    return (
        <CardTitle className={cn('flex items-center gap-2.5 text-[15px] font-semibold text-slate-900', className)}>
            {Icon && (
                <div className={cn('flex h-7 w-7 items-center justify-center rounded-lg', a.iconBg)}>
                    <Icon className={cn('h-4 w-4', a.iconFlat)} strokeWidth={2.25} />
                </div>
            )}
            <span>{children}</span>
            {badge}
        </CardTitle>
    );
}
