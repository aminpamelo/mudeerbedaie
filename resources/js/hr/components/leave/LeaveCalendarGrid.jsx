import { useMemo } from 'react';
import { cn } from '../../lib/utils';

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export default function LeaveCalendarGrid({
    year,
    month,
    leaves = [],
    onDayClick,
}) {
    const { days, startDay } = useMemo(() => {
        const daysInMonth = new Date(year, month, 0).getDate();
        const firstDay = new Date(year, month - 1, 1).getDay();
        const daysList = Array.from({ length: daysInMonth }, (_, i) => i + 1);
        return { days: daysList, startDay: firstDay };
    }, [year, month]);

    const leavesByDate = useMemo(() => {
        const map = {};
        leaves.forEach((leave) => {
            const day = new Date(leave.date).getDate();
            if (!map[day]) map[day] = [];
            map[day].push(leave);
        });
        return map;
    }, [leaves]);

    const today = new Date();
    const isCurrentMonth =
        today.getFullYear() === year && today.getMonth() + 1 === month;

    return (
        <div>
            <div className="grid grid-cols-7 gap-px">
                {WEEKDAY_LABELS.map((label) => (
                    <div
                        key={label}
                        className="py-2 text-center text-xs font-medium text-zinc-500"
                    >
                        {label}
                    </div>
                ))}

                {Array.from({ length: startDay }).map((_, i) => (
                    <div key={`empty-${i}`} />
                ))}

                {days.map((day) => {
                    const dayLeaves = leavesByDate[day] || [];
                    const isToday = isCurrentMonth && today.getDate() === day;

                    return (
                        <button
                            key={day}
                            type="button"
                            onClick={() => onDayClick?.(day)}
                            className={cn(
                                'flex min-h-[48px] flex-col items-center gap-0.5 rounded-lg p-1 text-sm transition-colors hover:bg-zinc-50',
                                isToday && 'bg-blue-50 font-bold text-blue-600'
                            )}
                        >
                            <span>{day}</span>
                            {dayLeaves.length > 0 && (
                                <div className="flex flex-wrap justify-center gap-0.5">
                                    {dayLeaves.slice(0, 3).map((leave, i) => (
                                        <div
                                            key={i}
                                            className="h-1.5 w-1.5 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    leave.color || '#3b82f6',
                                            }}
                                            title={`${leave.employee_name} - ${leave.leave_type}`}
                                        />
                                    ))}
                                    {dayLeaves.length > 3 && (
                                        <span className="text-[8px] text-zinc-400">
                                            +{dayLeaves.length - 3}
                                        </span>
                                    )}
                                </div>
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
