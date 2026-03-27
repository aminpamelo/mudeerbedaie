import { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    ChevronLeft,
    ChevronRight,
    AlertTriangle,
    CalendarDays,
    X,
} from 'lucide-react';
import { fetchLeaveCalendar, fetchLeaveOverlaps, fetchDepartments } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';

const DAYS_OF_WEEK = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

function getMonthDays(year, month) {
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const days = [];

    for (let i = 0; i < firstDay; i++) {
        days.push(null);
    }
    for (let d = 1; d <= daysInMonth; d++) {
        days.push(d);
    }
    return days;
}

function formatDateKey(year, month, day) {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function SkeletonCalendar() {
    return (
        <div className="grid grid-cols-7 gap-1">
            {Array.from({ length: 35 }).map((_, i) => (
                <div key={i} className="h-24 animate-pulse rounded bg-zinc-100" />
            ))}
        </div>
    );
}

export default function LeaveCalendar() {
    const today = new Date();
    const [year, setYear] = useState(today.getFullYear());
    const [month, setMonth] = useState(today.getMonth());
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [selectedDay, setSelectedDay] = useState(null);

    const { data: calendarData, isLoading } = useQuery({
        queryKey: ['hr', 'leave', 'calendar', { year, month: month + 1, department: departmentFilter }],
        queryFn: () =>
            fetchLeaveCalendar({
                year,
                month: month + 1,
                department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
            }),
    });

    const { data: overlapsData } = useQuery({
        queryKey: ['hr', 'leave', 'calendar', 'overlaps', { year, month: month + 1, department: departmentFilter }],
        queryFn: () =>
            fetchLeaveOverlaps({
                year,
                month: month + 1,
                department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
            }),
    });

    const { data: departmentsData } = useQuery({
        queryKey: ['hr', 'departments', 'list'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const leaves = calendarData?.data || [];
    const overlaps = overlapsData?.data || [];
    const departments = departmentsData?.data || [];
    const days = getMonthDays(year, month);

    const leavesByDate = useMemo(() => {
        const map = {};
        leaves.forEach((leave) => {
            const start = new Date(leave.start_date);
            const end = new Date(leave.end_date);
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const key = d.toISOString().split('T')[0];
                if (!map[key]) {
                    map[key] = [];
                }
                map[key].push(leave);
            }
        });
        return map;
    }, [leaves]);

    const hasOverlaps = overlaps.length > 0;

    function prevMonth() {
        if (month === 0) {
            setYear(year - 1);
            setMonth(11);
        } else {
            setMonth(month - 1);
        }
        setSelectedDay(null);
    }

    function nextMonth() {
        if (month === 11) {
            setYear(year + 1);
            setMonth(0);
        } else {
            setMonth(month + 1);
        }
        setSelectedDay(null);
    }

    function goToToday() {
        setYear(today.getFullYear());
        setMonth(today.getMonth());
        setSelectedDay(null);
    }

    function handleDayClick(day) {
        if (!day) return;
        const key = formatDateKey(year, month, day);
        const dayLeaves = leavesByDate[key] || [];
        if (dayLeaves.length > 0) {
            setSelectedDay({ day, date: key, leaves: dayLeaves });
        }
    }

    const todayKey = formatDateKey(today.getFullYear(), today.getMonth(), today.getDate());

    return (
        <div>
            <PageHeader
                title="Leave Calendar"
                description="Visual overview of approved leaves across the organization."
            />

            {hasOverlaps && (
                <div className="mb-4 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <AlertTriangle className="h-5 w-5 shrink-0 text-amber-600" />
                    <div className="flex-1">
                        <p className="text-sm font-medium text-amber-800">
                            Overlap Warning: {overlaps.length} department{overlaps.length > 1 ? 's have' : ' has'} multiple employees on leave at the same time.
                        </p>
                        <ul className="mt-1 space-y-0.5">
                            {overlaps.slice(0, 3).map((overlap, i) => (
                                <li key={i} className="text-xs text-amber-600">
                                    {overlap.department} &middot; {overlap.date} &middot; {overlap.count} employees
                                </li>
                            ))}
                            {overlaps.length > 3 && (
                                <li className="text-xs text-amber-600">...and {overlaps.length - 3} more</li>
                            )}
                        </ul>
                    </div>
                </div>
            )}

            <Card>
                <CardContent className="p-6">
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" onClick={prevMonth}>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <h2 className="min-w-[180px] text-center text-lg font-semibold text-zinc-900">
                                {MONTH_NAMES[month]} {year}
                            </h2>
                            <Button variant="outline" size="sm" onClick={nextMonth}>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="sm" onClick={goToToday}>
                                Today
                            </Button>
                        </div>
                        <Select value={departmentFilter} onValueChange={setDepartmentFilter}>
                            <SelectTrigger className="w-full sm:w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Departments</SelectItem>
                                {departments.map((dept) => (
                                    <SelectItem key={dept.id} value={String(dept.id)}>{dept.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {isLoading ? (
                        <SkeletonCalendar />
                    ) : (
                        <div className="grid grid-cols-7 gap-1">
                            {DAYS_OF_WEEK.map((day) => (
                                <div key={day} className="py-2 text-center text-xs font-medium uppercase text-zinc-400">
                                    {day}
                                </div>
                            ))}
                            {days.map((day, i) => {
                                if (day === null) {
                                    return <div key={`empty-${i}`} className="h-24 rounded bg-zinc-50/50" />;
                                }

                                const key = formatDateKey(year, month, day);
                                const dayLeaves = leavesByDate[key] || [];
                                const isToday = key === todayKey;

                                return (
                                    <div
                                        key={key}
                                        onClick={() => handleDayClick(day)}
                                        className={cn(
                                            'h-24 overflow-hidden rounded border p-1.5 transition-colors',
                                            isToday ? 'border-blue-300 bg-blue-50/50' : 'border-zinc-100 bg-white',
                                            dayLeaves.length > 0 && 'cursor-pointer hover:border-zinc-300'
                                        )}
                                    >
                                        <div className={cn(
                                            'mb-1 text-xs font-medium',
                                            isToday ? 'text-blue-600' : 'text-zinc-500'
                                        )}>
                                            {day}
                                        </div>
                                        <div className="space-y-0.5">
                                            {dayLeaves.slice(0, 3).map((leave, li) => (
                                                <div
                                                    key={`${leave.id}-${li}`}
                                                    className="truncate rounded px-1 py-0.5 text-[10px] font-medium leading-tight"
                                                    style={{
                                                        backgroundColor: leave.leave_type?.color
                                                            ? `${leave.leave_type.color}25`
                                                            : '#e5e7eb',
                                                        color: leave.leave_type?.color || '#374151',
                                                    }}
                                                >
                                                    {leave.employee?.full_name?.split(' ')[0] || 'Employee'}
                                                </div>
                                            ))}
                                            {dayLeaves.length > 3 && (
                                                <div className="text-[10px] text-zinc-400">
                                                    +{dayLeaves.length - 3} more
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>

            {selectedDay && (
                <div className="fixed inset-y-0 right-0 z-50 flex">
                    <div className="fixed inset-0 bg-black/20" onClick={() => setSelectedDay(null)} />
                    <div className="relative ml-auto flex w-96 flex-col border-l border-zinc-200 bg-white shadow-xl">
                        <div className="flex items-center justify-between border-b border-zinc-200 p-4">
                            <div>
                                <h3 className="font-semibold text-zinc-900">
                                    {new Date(selectedDay.date).toLocaleDateString('en-MY', {
                                        weekday: 'long',
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                    })}
                                </h3>
                                <p className="text-sm text-zinc-500">
                                    {selectedDay.leaves.length} leave{selectedDay.leaves.length !== 1 ? 's' : ''}
                                </p>
                            </div>
                            <Button variant="ghost" size="sm" onClick={() => setSelectedDay(null)}>
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                        <div className="flex-1 overflow-y-auto p-4">
                            <div className="space-y-3">
                                {selectedDay.leaves.map((leave, i) => (
                                    <div key={`${leave.id}-${i}`} className="rounded-lg border border-zinc-200 p-3">
                                        <div className="flex items-start justify-between">
                                            <div>
                                                <p className="font-medium text-zinc-900">
                                                    {leave.employee?.full_name}
                                                </p>
                                                <p className="text-xs text-zinc-500">
                                                    {leave.employee?.department?.name || '-'}
                                                </p>
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className="border-transparent text-xs"
                                                style={{
                                                    backgroundColor: leave.leave_type?.color
                                                        ? `${leave.leave_type.color}20`
                                                        : undefined,
                                                    color: leave.leave_type?.color || undefined,
                                                }}
                                            >
                                                {leave.leave_type?.name}
                                            </Badge>
                                        </div>
                                        <p className="mt-2 text-xs text-zinc-400">
                                            {new Date(leave.start_date).toLocaleDateString('en-MY', { month: 'short', day: 'numeric' })}
                                            {' - '}
                                            {new Date(leave.end_date).toLocaleDateString('en-MY', { month: 'short', day: 'numeric' })}
                                            {' '}&middot; {leave.total_days} day(s)
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
