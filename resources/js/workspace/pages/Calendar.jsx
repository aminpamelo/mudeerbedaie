import { useState, useEffect, useCallback } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import { workspaceJson } from '@/workspace/lib/api';
import { cn, PRIORITY_COLORS } from '@/workspace/lib/utils';
import { router } from '@inertiajs/react';

export default function Calendar() {
    const [date, setDate] = useState(new Date());
    const [events, setEvents] = useState([]);

    const year = date.getFullYear();
    const month = date.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();

    const load = useCallback(async () => {
        const start = new Date(year, month, 1).toISOString().split('T')[0];
        const end = new Date(year, month + 1, 0).toISOString().split('T')[0];
        try {
            const res = await workspaceJson(
                `/workspace/calendar/events?start=${start}&end=${end}`
            );
            setEvents(res.data ?? []);
        } catch {
            setEvents([]);
        }
    }, [year, month]);

    useEffect(() => {
        load();
    }, [load]);

    const prev = () => setDate(new Date(year, month - 1, 1));
    const next = () => setDate(new Date(year, month + 1, 1));
    const goToday = () => setDate(new Date());

    const monthLabel = date.toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
    });
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    const cells = [];
    for (let i = 0; i < firstDay; i++) {
        cells.push(null);
    }
    for (let d = 1; d <= daysInMonth; d++) {
        cells.push(d);
    }

    const getEventsForDay = (day) => {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        return events.filter((e) => e.start === dateStr || e.end === dateStr);
    };

    const isToday = (day) =>
        day === today.getDate() &&
        month === today.getMonth() &&
        year === today.getFullYear();

    return (
        <WorkspaceLayout
            title="Calendar"
            subtitle="Task deadlines and schedules"
        >
            <div className="rounded-2xl border border-slate-200 bg-white">
                <div className="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <h2 className="text-[16px] font-bold text-slate-900">
                        {monthLabel}
                    </h2>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={goToday}
                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] font-semibold text-slate-600 hover:bg-slate-50"
                        >
                            Today
                        </button>
                        <button
                            onClick={prev}
                            className="grid h-8 w-8 place-items-center rounded-lg hover:bg-slate-100"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <button
                            onClick={next}
                            className="grid h-8 w-8 place-items-center rounded-lg hover:bg-slate-100"
                        >
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <div className="grid grid-cols-7">
                    {days.map((d) => (
                        <div
                            key={d}
                            className="border-b border-r border-slate-100 px-2 py-2 text-center text-[11px] font-semibold uppercase text-slate-400"
                        >
                            {d}
                        </div>
                    ))}
                    {cells.map((day, i) => {
                        const dayEvents = day ? getEventsForDay(day) : [];
                        return (
                            <div
                                key={i}
                                className={cn(
                                    'min-h-[100px] border-b border-r border-slate-100 p-1.5',
                                    !day && 'bg-slate-50/50'
                                )}
                            >
                                {day && (
                                    <>
                                        <span
                                            className={cn(
                                                'inline-grid h-6 w-6 place-items-center rounded-full text-[12px] font-semibold',
                                                isToday(day)
                                                    ? 'bg-indigo-500 text-white'
                                                    : 'text-slate-700'
                                            )}
                                        >
                                            {day}
                                        </span>
                                        <div className="mt-1 space-y-0.5">
                                            {dayEvents
                                                .slice(0, 3)
                                                .map((ev) => (
                                                    <button
                                                        key={ev.id}
                                                        onClick={() =>
                                                            router.visit(
                                                                `/workspace/tasks/${ev.id}`
                                                            )
                                                        }
                                                        className={cn(
                                                            'block w-full truncate rounded px-1.5 py-0.5 text-left text-[10px] font-medium',
                                                            PRIORITY_COLORS[
                                                                ev.priority
                                                            ] ??
                                                                'bg-slate-100 text-slate-600'
                                                        )}
                                                    >
                                                        {ev.title}
                                                    </button>
                                                ))}
                                            {dayEvents.length > 3 && (
                                                <span className="block px-1 text-[9px] text-slate-400">
                                                    +{dayEvents.length - 3}{' '}
                                                    more
                                                </span>
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </WorkspaceLayout>
    );
}
