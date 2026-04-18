import { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    ChevronLeft,
    ChevronRight,
    AlertTriangle,
    X,
    Clock,
    Calendar,
    Users,
    Sun,
    Moon,
    Briefcase,
    Building2,
    FileText,
} from 'lucide-react';
import { fetchLeaveCalendar, fetchLeaveOverlaps, fetchDepartments } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Avatar, AvatarImage, AvatarFallback } from '../../components/ui/avatar';
import {
    Tooltip,
    TooltipTrigger,
    TooltipContent,
    TooltipProvider,
} from '../../components/ui/tooltip';
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
                <div key={i} className="h-28 animate-pulse rounded-lg bg-zinc-100" />
            ))}
        </div>
    );
}

function StatCard({ icon: Icon, label, value, color = 'zinc' }) {
    const colors = {
        blue: 'bg-blue-50 text-blue-600',
        amber: 'bg-amber-50 text-amber-600',
        emerald: 'bg-emerald-50 text-emerald-600',
        rose: 'bg-rose-50 text-rose-600',
        zinc: 'bg-zinc-50 text-zinc-600',
    };
    return (
        <div className="flex items-center gap-3 rounded-xl border border-zinc-100 bg-white px-4 py-3">
            <div className={cn('flex h-9 w-9 items-center justify-center rounded-lg', colors[color])}>
                <Icon className="h-4.5 w-4.5" />
            </div>
            <div>
                <p className="text-xs font-medium text-zinc-400 uppercase tracking-wide">{label}</p>
                <p className="text-lg font-bold text-zinc-900 leading-tight">{value}</p>
            </div>
        </div>
    );
}

function LeaveTypeLegend({ leaves }) {
    const leaveTypes = useMemo(() => {
        const types = {};
        leaves.forEach((leave) => {
            const name = leave.leave_type?.name;
            const color = leave.leave_type?.color;
            if (name && !types[name]) {
                types[name] = { name, color, count: 0 };
            }
            if (name) {
                types[name].count++;
            }
        });
        return Object.values(types).sort((a, b) => b.count - a.count);
    }, [leaves]);

    if (leaveTypes.length === 0) return null;

    return (
        <div className="flex flex-wrap items-center gap-3">
            {leaveTypes.map((type) => (
                <div key={type.name} className="flex items-center gap-1.5">
                    <span
                        className="inline-block h-2.5 w-2.5 rounded-full"
                        style={{ backgroundColor: type.color || '#94a3b8' }}
                    />
                    <span className="text-xs text-zinc-500">{type.name}</span>
                    <span className="text-[10px] font-medium text-zinc-400">({type.count})</span>
                </div>
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

    // Compute summary stats
    const stats = useMemo(() => {
        const todayKey = formatDateKey(today.getFullYear(), today.getMonth(), today.getDate());
        const onLeaveToday = leavesByDate[todayKey]?.length || 0;
        const uniqueEmployees = new Set(leaves.map((l) => l.employee?.employee_id)).size;
        const uniqueDepartments = new Set(
            leaves.map((l) => l.employee?.department?.name).filter(Boolean)
        ).size;
        return { totalLeaves: leaves.length, onLeaveToday, uniqueEmployees, uniqueDepartments };
    }, [leaves, leavesByDate, today]);

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
    const isCurrentMonth = year === today.getFullYear() && month === today.getMonth();

    return (
        <TooltipProvider>
            <div>
                <PageHeader
                    title="Leave Calendar"
                    description="Visual overview of approved leaves across the organization."
                />

                {/* Summary Stats */}
                <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard
                        icon={FileText}
                        label="Total Leaves"
                        value={stats.totalLeaves}
                        color="blue"
                    />
                    <StatCard
                        icon={Users}
                        label="On Leave Today"
                        value={stats.onLeaveToday}
                        color={stats.onLeaveToday > 0 ? 'amber' : 'emerald'}
                    />
                    <StatCard
                        icon={Briefcase}
                        label="Employees"
                        value={stats.uniqueEmployees}
                        color="zinc"
                    />
                    <StatCard
                        icon={Building2}
                        label="Departments"
                        value={stats.uniqueDepartments}
                        color="zinc"
                    />
                </div>

                {/* Overlap Warning */}
                {hasOverlaps && (
                    <div className="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/80 p-4">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100">
                            <AlertTriangle className="h-4 w-4 text-amber-600" />
                        </div>
                        <div className="flex-1">
                            <p className="text-sm font-semibold text-amber-800">
                                Overlap Warning
                            </p>
                            <p className="text-xs text-amber-600 mt-0.5">
                                {overlaps.length} department{overlaps.length > 1 ? 's have' : ' has'} multiple employees on leave at the same time.
                            </p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {overlaps.slice(0, 4).map((overlap, i) => (
                                    <span
                                        key={i}
                                        className="inline-flex items-center gap-1.5 rounded-md bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800"
                                    >
                                        <span>{overlap.department}</span>
                                        <span className="text-amber-500">&middot;</span>
                                        <span>{overlap.count} staff</span>
                                    </span>
                                ))}
                                {overlaps.length > 4 && (
                                    <span className="inline-flex items-center rounded-md bg-amber-100/60 px-2 py-1 text-xs text-amber-600">
                                        +{overlaps.length - 4} more
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* Calendar Card */}
                <Card>
                    <CardContent className="p-5">
                        {/* Toolbar */}
                        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <Button variant="outline" size="sm" onClick={prevMonth}>
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <h2 className="min-w-[180px] text-center text-lg font-bold text-zinc-900">
                                    {MONTH_NAMES[month]} {year}
                                </h2>
                                <Button variant="outline" size="sm" onClick={nextMonth}>
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                                {!isCurrentMonth && (
                                    <Button variant="ghost" size="sm" onClick={goToToday} className="text-blue-600 hover:text-blue-700">
                                        Today
                                    </Button>
                                )}
                            </div>
                            <div className="flex items-center gap-3">
                                <LeaveTypeLegend leaves={leaves} />
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
                        </div>

                        {/* Calendar Grid */}
                        {isLoading ? (
                            <SkeletonCalendar />
                        ) : (
                            <div className="grid grid-cols-7 gap-1">
                                {/* Day Headers */}
                                {DAYS_OF_WEEK.map((day, i) => (
                                    <div
                                        key={day}
                                        className={cn(
                                            'py-2.5 text-center text-xs font-semibold uppercase tracking-wider',
                                            i === 0 || i === 6 ? 'text-zinc-300' : 'text-zinc-400'
                                        )}
                                    >
                                        {day}
                                    </div>
                                ))}

                                {/* Day Cells */}
                                {days.map((day, i) => {
                                    if (day === null) {
                                        return <div key={`empty-${i}`} className="h-28 rounded-lg bg-zinc-50/30" />;
                                    }

                                    const key = formatDateKey(year, month, day);
                                    const dayLeaves = leavesByDate[key] || [];
                                    const isToday = key === todayKey;
                                    const dayOfWeek = new Date(year, month, day).getDay();
                                    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
                                    const hasLeaves = dayLeaves.length > 0;

                                    return (
                                        <div
                                            key={key}
                                            onClick={() => handleDayClick(day)}
                                            className={cn(
                                                'group relative h-28 overflow-hidden rounded-lg border p-1.5 transition-all duration-150',
                                                isToday
                                                    ? 'border-blue-300 bg-blue-50/40 ring-1 ring-blue-200'
                                                    : isWeekend
                                                        ? 'border-zinc-100 bg-zinc-50/40'
                                                        : 'border-zinc-100 bg-white',
                                                hasLeaves && 'cursor-pointer hover:border-zinc-300 hover:shadow-sm'
                                            )}
                                        >
                                            {/* Day Number */}
                                            <div className="flex items-center justify-between mb-1">
                                                <span
                                                    className={cn(
                                                        'inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold',
                                                        isToday
                                                            ? 'bg-blue-600 text-white'
                                                            : isWeekend
                                                                ? 'text-zinc-300'
                                                                : 'text-zinc-500'
                                                    )}
                                                >
                                                    {day}
                                                </span>
                                                {hasLeaves && (
                                                    <span className="text-[10px] font-medium text-zinc-400">
                                                        {dayLeaves.length}
                                                    </span>
                                                )}
                                            </div>

                                            {/* Leave Entries */}
                                            <div className="space-y-0.5">
                                                {dayLeaves.slice(0, 3).map((leave, li) => {
                                                    const color = leave.leave_type?.color || '#94a3b8';
                                                    return (
                                                        <Tooltip key={`${leave.id}-${li}`}>
                                                            <TooltipTrigger asChild>
                                                                <div
                                                                    className="flex items-center gap-1 truncate rounded px-1 py-0.5 text-[10px] font-medium leading-tight transition-colors"
                                                                    style={{
                                                                        backgroundColor: `${color}18`,
                                                                        color: color,
                                                                        borderLeft: `2px solid ${color}`,
                                                                    }}
                                                                >
                                                                    {leave.is_half_day && (
                                                                        leave.half_day_period === 'morning'
                                                                            ? <Sun className="h-2.5 w-2.5 shrink-0 opacity-70" />
                                                                            : <Moon className="h-2.5 w-2.5 shrink-0 opacity-70" />
                                                                    )}
                                                                    <span className="truncate">
                                                                        {leave.employee?.full_name?.split(' ')[0] || 'Employee'}
                                                                    </span>
                                                                </div>
                                                            </TooltipTrigger>
                                                            <TooltipContent side="top" className="max-w-xs">
                                                                <div className="space-y-0.5">
                                                                    <p className="font-semibold">{leave.employee?.full_name}</p>
                                                                    <p className="text-zinc-300">
                                                                        {leave.employee?.department?.name}
                                                                        {leave.employee?.position && ` · ${leave.employee.position}`}
                                                                    </p>
                                                                    <p className="text-zinc-300">
                                                                        {leave.leave_type?.name} · {leave.total_days} day(s)
                                                                        {leave.is_half_day && ` (${leave.half_day_period})`}
                                                                    </p>
                                                                </div>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    );
                                                })}
                                                {dayLeaves.length > 3 && (
                                                    <div className="px-1 text-[10px] font-medium text-zinc-400">
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

                {/* Detail Slide-Out Panel */}
                {selectedDay && (
                    <div className="fixed inset-y-0 right-0 z-50 flex">
                        <div
                            className="fixed inset-0 bg-black/25 backdrop-blur-[2px] transition-opacity"
                            onClick={() => setSelectedDay(null)}
                        />
                        <div className="relative ml-auto flex w-full max-w-md flex-col border-l border-zinc-200 bg-white shadow-2xl">
                            {/* Panel Header */}
                            <div className="border-b border-zinc-100 bg-zinc-50/80 px-5 py-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="text-base font-bold text-zinc-900">
                                            {new Date(selectedDay.date + 'T00:00:00').toLocaleDateString('en-MY', {
                                                weekday: 'long',
                                                day: 'numeric',
                                                month: 'long',
                                                year: 'numeric',
                                            })}
                                        </h3>
                                        <p className="mt-0.5 text-sm text-zinc-500">
                                            {selectedDay.leaves.length} leave{selectedDay.leaves.length !== 1 ? 's' : ''} on this day
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setSelectedDay(null)}
                                        className="h-8 w-8 p-0 text-zinc-400 hover:text-zinc-600"
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>

                                {/* Department summary for the day */}
                                {(() => {
                                    const deptCounts = {};
                                    selectedDay.leaves.forEach((l) => {
                                        const dept = l.employee?.department?.name || 'Unknown';
                                        deptCounts[dept] = (deptCounts[dept] || 0) + 1;
                                    });
                                    const deptEntries = Object.entries(deptCounts);
                                    if (deptEntries.length <= 1) return null;
                                    return (
                                        <div className="mt-3 flex flex-wrap gap-1.5">
                                            {deptEntries.map(([dept, count]) => (
                                                <span
                                                    key={dept}
                                                    className="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-xs font-medium text-zinc-600 border border-zinc-200"
                                                >
                                                    <Building2 className="h-3 w-3 text-zinc-400" />
                                                    {dept}
                                                    <span className="text-zinc-400">({count})</span>
                                                </span>
                                            ))}
                                        </div>
                                    );
                                })()}
                            </div>

                            {/* Leave Cards */}
                            <div className="flex-1 overflow-y-auto p-5">
                                <div className="space-y-3">
                                    {selectedDay.leaves.map((leave, i) => {
                                        const color = leave.leave_type?.color || '#94a3b8';
                                        return (
                                            <div
                                                key={`${leave.id}-${i}`}
                                                className="overflow-hidden rounded-xl border border-zinc-200 bg-white transition-shadow hover:shadow-sm"
                                            >
                                                {/* Color bar at top */}
                                                <div className="h-1" style={{ backgroundColor: color }} />

                                                <div className="p-4">
                                                    {/* Employee Info */}
                                                    <div className="flex items-start gap-3">
                                                        <Avatar className="h-9 w-9 shrink-0">
                                                            {leave.employee?.profile_photo_url && (
                                                                <AvatarImage
                                                                    src={leave.employee.profile_photo_url}
                                                                    alt={leave.employee?.full_name}
                                                                />
                                                            )}
                                                            <AvatarFallback className="text-xs font-semibold bg-zinc-100 text-zinc-600">
                                                                {leave.employee?.initials || '??'}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="flex-1 min-w-0">
                                                            <p className="font-semibold text-sm text-zinc-900 truncate">
                                                                {leave.employee?.full_name}
                                                            </p>
                                                            <p className="text-xs text-zinc-500 truncate">
                                                                {leave.employee?.employee_id}
                                                                {leave.employee?.position && ` · ${leave.employee.position}`}
                                                            </p>
                                                        </div>
                                                        <Badge
                                                            variant="outline"
                                                            className="shrink-0 border-transparent text-xs"
                                                            style={{
                                                                backgroundColor: `${color}15`,
                                                                color: color,
                                                            }}
                                                        >
                                                            {leave.leave_type?.name}
                                                        </Badge>
                                                    </div>

                                                    {/* Detail Grid */}
                                                    <div className="mt-3 grid grid-cols-2 gap-2">
                                                        <div className="flex items-center gap-1.5 rounded-lg bg-zinc-50 px-2.5 py-1.5">
                                                            <Building2 className="h-3.5 w-3.5 text-zinc-400" />
                                                            <span className="text-xs text-zinc-600 truncate">
                                                                {leave.employee?.department?.name || '-'}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center gap-1.5 rounded-lg bg-zinc-50 px-2.5 py-1.5">
                                                            <Clock className="h-3.5 w-3.5 text-zinc-400" />
                                                            <span className="text-xs text-zinc-600">
                                                                {leave.total_days} day{leave.total_days !== '1.0' && leave.total_days !== 1 ? 's' : ''}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center gap-1.5 rounded-lg bg-zinc-50 px-2.5 py-1.5">
                                                            <Calendar className="h-3.5 w-3.5 text-zinc-400" />
                                                            <span className="text-xs text-zinc-600">
                                                                {new Date(leave.start_date + 'T00:00:00').toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })}
                                                                {leave.start_date !== leave.end_date && (
                                                                    <> – {new Date(leave.end_date + 'T00:00:00').toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })}</>
                                                                )}
                                                            </span>
                                                        </div>
                                                        {leave.is_half_day && (
                                                            <div className="flex items-center gap-1.5 rounded-lg bg-zinc-50 px-2.5 py-1.5">
                                                                {leave.half_day_period === 'morning'
                                                                    ? <Sun className="h-3.5 w-3.5 text-amber-500" />
                                                                    : <Moon className="h-3.5 w-3.5 text-indigo-500" />
                                                                }
                                                                <span className="text-xs text-zinc-600 capitalize">
                                                                    {leave.half_day_period} half
                                                                </span>
                                                            </div>
                                                        )}
                                                        {leave.leave_type?.is_paid === false && (
                                                            <div className="flex items-center gap-1.5 rounded-lg bg-rose-50 px-2.5 py-1.5">
                                                                <span className="text-xs font-medium text-rose-600">
                                                                    Unpaid
                                                                </span>
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* Reason */}
                                                    {leave.reason && (
                                                        <div className="mt-3 rounded-lg bg-zinc-50 p-2.5">
                                                            <p className="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 mb-1">
                                                                Reason
                                                            </p>
                                                            <p className="text-xs text-zinc-600 leading-relaxed">
                                                                {leave.reason}
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </TooltipProvider>
    );
}
