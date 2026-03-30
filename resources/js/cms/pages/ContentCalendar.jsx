import { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { fetchCalendar } from '../lib/api';
import { cn } from '../lib/utils';
import { Button } from '../components/ui/button';

// ─── Constants ──────────────────────────────────────────────────────────────

const STAGE_PILL_COLORS = {
    idea: 'bg-blue-400',
    shooting: 'bg-purple-400',
    editing: 'bg-amber-400',
    posting: 'bg-emerald-400',
    posted: 'bg-green-400',
};

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

// ─── Helpers ────────────────────────────────────────────────────────────────

function buildCalendarDays(year, month) {
    const firstDay = new Date(year, month - 1, 1);
    const lastDay = new Date(year, month, 0);
    const startPadding = firstDay.getDay();
    const totalDays = lastDay.getDate();

    const days = [];

    // Previous month padding
    const prevMonthLastDay = new Date(year, month - 1, 0).getDate();
    for (let i = startPadding - 1; i >= 0; i--) {
        days.push({
            date: new Date(year, month - 2, prevMonthLastDay - i),
            isCurrentMonth: false,
        });
    }

    // Current month
    for (let d = 1; d <= totalDays; d++) {
        days.push({
            date: new Date(year, month - 1, d),
            isCurrentMonth: true,
        });
    }

    // Next month padding to fill 6 rows
    const remaining = 42 - days.length;
    for (let d = 1; d <= remaining; d++) {
        days.push({
            date: new Date(year, month, d),
            isCurrentMonth: false,
        });
    }

    return days;
}

function dateKey(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function isToday(date) {
    const now = new Date();
    return (
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate()
    );
}

// ─── Sub-components ─────────────────────────────────────────────────────────

function ContentPill({ content }) {
    const navigate = useNavigate();
    const pillColor = STAGE_PILL_COLORS[content.stage] || 'bg-zinc-400';

    return (
        <button
            onClick={(e) => {
                e.stopPropagation();
                navigate(`/contents/${content.id}`);
            }}
            title={content.title}
            className={cn(
                'w-full truncate rounded px-1.5 py-0.5 text-left text-[10px] font-medium text-white transition-opacity hover:opacity-80',
                pillColor
            )}
        >
            {content.title}
        </button>
    );
}

function SkeletonCalendar() {
    return (
        <div className="grid grid-cols-7 gap-px rounded-lg border border-zinc-200 bg-zinc-200">
            {Array.from({ length: 42 }).map((_, i) => (
                <div key={i} className="min-h-[100px] bg-white p-2">
                    <div className="h-4 w-6 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────

export default function ContentCalendar() {
    const [currentDate, setCurrentDate] = useState(new Date());
    const month = currentDate.getMonth() + 1;
    const year = currentDate.getFullYear();

    const { data, isLoading } = useQuery({
        queryKey: ['cms', 'calendar', { month, year }],
        queryFn: () => fetchCalendar({ month, year }),
    });

    // Build a map of date → contents
    const contentsByDate = useMemo(() => {
        const map = {};
        const items = data?.data || data || [];
        if (Array.isArray(items)) {
            items.forEach((item) => {
                if (item.due_date) {
                    const key = item.due_date.slice(0, 10);
                    if (!map[key]) {
                        map[key] = [];
                    }
                    map[key].push(item);
                }
            });
        }
        return map;
    }, [data]);

    const calendarDays = useMemo(() => buildCalendarDays(year, month), [year, month]);

    function goToPrevMonth() {
        setCurrentDate((prev) => new Date(prev.getFullYear(), prev.getMonth() - 1, 1));
    }

    function goToNextMonth() {
        setCurrentDate((prev) => new Date(prev.getFullYear(), prev.getMonth() + 1, 1));
    }

    function goToToday() {
        setCurrentDate(new Date());
    }

    return (
        <div>
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-zinc-900">Content Calendar</h1>
                    <p className="mt-1 text-sm text-zinc-500">
                        View upcoming content due dates at a glance.
                    </p>
                </div>
            </div>

            {/* Month Navigation */}
            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" onClick={goToPrevMonth}>
                        <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <h2 className="min-w-[180px] text-center text-lg font-semibold text-zinc-900">
                        {MONTH_NAMES[month - 1]} {year}
                    </h2>
                    <Button variant="outline" size="sm" onClick={goToNextMonth}>
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>
                <Button variant="outline" size="sm" onClick={goToToday}>
                    Today
                </Button>
            </div>

            {/* Weekday Headers */}
            <div className="grid grid-cols-7 gap-px rounded-t-lg border border-b-0 border-zinc-200 bg-zinc-200">
                {WEEKDAYS.map((day) => (
                    <div
                        key={day}
                        className="bg-zinc-50 px-2 py-2 text-center text-xs font-semibold text-zinc-500"
                    >
                        {day}
                    </div>
                ))}
            </div>

            {/* Calendar Grid */}
            {isLoading ? (
                <SkeletonCalendar />
            ) : (
                <div className="grid grid-cols-7 gap-px rounded-b-lg border border-t-0 border-zinc-200 bg-zinc-200">
                    {calendarDays.map((dayObj, index) => {
                        const key = dateKey(dayObj.date);
                        const dayContents = contentsByDate[key] || [];
                        const today = isToday(dayObj.date);

                        return (
                            <div
                                key={index}
                                className={cn(
                                    'min-h-[100px] bg-white p-2',
                                    !dayObj.isCurrentMonth && 'bg-zinc-50',
                                    today && 'ring-2 ring-inset ring-blue-500'
                                )}
                            >
                                <span
                                    className={cn(
                                        'inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium',
                                        !dayObj.isCurrentMonth && 'text-zinc-300',
                                        dayObj.isCurrentMonth && 'text-zinc-700',
                                        today && 'bg-blue-500 text-white'
                                    )}
                                >
                                    {dayObj.date.getDate()}
                                </span>
                                {dayContents.length > 0 && (
                                    <div className="mt-1 flex flex-col gap-0.5">
                                        {dayContents.slice(0, 3).map((content) => (
                                            <ContentPill key={content.id} content={content} />
                                        ))}
                                        {dayContents.length > 3 && (
                                            <span className="text-center text-[10px] text-zinc-400">
                                                +{dayContents.length - 3} more
                                            </span>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
