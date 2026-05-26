import { cn } from '../../lib/utils';
import { getAccent } from '../../lib/accents';
import { Card, CardHeader, CardContent, CardDescription } from './card';
import { SectionTitle } from './section-title';

/**
 * A card with a standardized section title header, optional tint, and slot for header action.
 * Replaces the recurring Card + CardHeader + SectionTitle + Description pattern.
 *
 * <SectionCard
 *   icon={CalendarCheck}
 *   accent="emerald"
 *   title="Today's Attendance"
 *   description="Real-time workforce presence"
 *   action={<Link to="/attendance">View all</Link>}
 * >
 *   {children}
 * </SectionCard>
 *
 * Tinted variant (rose subtle wash on a Pending Approvals card):
 * <SectionCard icon={ClipboardList} accent="rose" tint="rose" title="Pending Approvals">
 *   ...
 * </SectionCard>
 */
export function SectionCard({
    icon,
    accent = 'indigo',
    title,
    description,
    badge,
    action,
    tint,
    children,
    className,
    contentClassName,
}) {
    const tintAccent = tint ? getAccent(tint) : null;

    return (
        <Card
            className={cn(
                tintAccent && [tintAccent.cardTintSubtle, tintAccent.borderSubtle],
                className
            )}
        >
            {(title || action) && (
                <CardHeader className="pb-3">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0 flex-1">
                            <SectionTitle icon={icon} accent={accent} badge={badge}>
                                {title}
                            </SectionTitle>
                            {description && (
                                <CardDescription className="ml-[2.375rem] mt-1">
                                    {description}
                                </CardDescription>
                            )}
                        </div>
                        {action}
                    </div>
                </CardHeader>
            )}
            <CardContent className={contentClassName}>{children}</CardContent>
        </Card>
    );
}
