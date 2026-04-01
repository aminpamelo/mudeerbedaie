import { useState, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    ChevronLeft,
    ChevronRight,
    Clock,
    MapPin,
    Camera,
    MessageSquare,
    Calendar,
    Users,
    TrendingUp,
    Search,
} from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '../../components/ui/dialog';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import { Input } from '../../components/ui/input';
import PageHeader from '../../components/PageHeader';
import { cn } from '../../lib/utils';
import { fetchAttendanceMonthly, fetchDepartments } from '../../lib/api';

const STATUS_CONFIG = {
    present:  { short: 'P',  bg: 'bg-emerald-500', text: 'text-white',      label: 'Present',   ring: 'ring-emerald-300' },
    late:     { short: 'L',  bg: 'bg-amber-400',   text: 'text-white',      label: 'Late',      ring: 'ring-amber-300' },
    absent:   { short: 'A',  bg: 'bg-red-500',     text: 'text-white',      label: 'Absent',    ring: 'ring-red-300' },
    wfh:      { short: 'W',  bg: 'bg-sky-500',     text: 'text-white',      label: 'WFH',       ring: 'ring-sky-300' },
    on_leave: { short: 'OL', bg: 'bg-violet-500',  text: 'text-white',      label: 'On Leave',  ring: 'ring-violet-300' },
    half_day: { short: 'H',  bg: 'bg-orange-400',  text: 'text-white',      label: 'Half Day',  ring: 'ring-orange-300' },
    holiday:  { short: 'HO', bg: 'bg-zinc-300',    text: 'text-zinc-700',   label: 'Holiday',   ring: 'ring-zinc-200' },
};

const MONTH_NAMES = [
    'January','February','March','April','May','June',
    'July','August','September','October','November','December',
];

const DAY_ABBR = ['Su','Mo','Tu','We','Th','Fr','Sa'];

function getDayOfWeek(year, month, day) {
    return new Date(year, month - 1, day).getDay();
}

function isWeekend(year, month, day) {
    const dow = getDayOfWeek(year, month, day);
    return dow === 0 || dow === 6;
}

function formatTime(timeString) {
    if (!timeString) { return '-'; }
    const date = new Date(timeString);
    if (isNaN(date.getTime())) { return timeString.slice(0, 5); }
    return date.toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function formatHours(totalMinutes) {
    if (!totalMinutes && totalMinutes !== 0) { return '-'; }
    const hours = Math.floor(totalMinutes / 60);
    const mins = totalMinutes % 60;
    return `${hours}h ${mins}m`;
}

function StatusDot({ status, onClick, isToday }) {
    const config = STATUS_CONFIG[status] || { short: '?', bg: 'bg-zinc-200', text: 'text-zinc-500', ring: 'ring-zinc-200' };
    return (
        <button
            onClick={onClick}
            className={cn(
                'flex h-7 w-7 items-center justify-center rounded-full text-[10px] font-bold transition-all',
                'hover:scale-110 hover:shadow-md focus:outline-none',
                config.bg,
                config.text,
                isToday && `ring-2 ring-offset-1 ${config.ring}`,
            )}
            title={config.label}
        >
            {config.short}
        </button>
    );
}

function EmptyDot({ isWeekend: weekend, isToday }) {
    return (
        <div
            className={cn(
                'flex h-7 w-7 items-center justify-center rounded-full',
                weekend ? 'bg-zinc-100' : 'bg-transparent',
                isToday && 'ring-2 ring-offset-1 ring-zinc-300',
            )}
        >
            {weekend && <span className="text-[10px] text-zinc-300">—</span>}
        </div>
    );
}

function SummaryPill({ count, variant }) {
    const styles = {
        present: 'bg-emerald-50 text-emerald-700',
        absent:  'bg-red-50 text-red-700',
        late:    'bg-amber-50 text-amber-700',
        leave:   'bg-violet-50 text-violet-700',
    };
    if (count === 0) { return null; }
    return (
        <span className={cn('inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold', styles[variant])}>
            {count}
        </span>
    );
}

function DetailModal({ dayData, employeeName, date, onClose }) {
    if (!dayData) { return null; }
    const config = STATUS_CONFIG[dayData.status] || { label: dayData.status, bg: 'bg-zinc-100', text: 'text-zinc-700' };

    return (
        <Dialog open onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold text-white', config.bg)}>
                            {config.label}
                        </span>
                        {employeeName}
                    </DialogTitle>
                    <DialogDescription className="flex items-center gap-1.5">
                        <Calendar className="h-3.5 w-3.5" />
                        {date}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Time grid */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                            <p className="mb-1 flex items-center gap-1 text-xs font-medium text-zinc-400">
                                <Clock className="h-3 w-3" /> Clock In
                            </p>
                            <p className="text-lg font-semibold text-zinc-900">{formatTime(dayData.clock_in)}</p>
                        </div>
                        <div className="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                            <p className="mb-1 flex items-center gap-1 text-xs font-medium text-zinc-400">
                                <Clock className="h-3 w-3" /> Clock Out
                            </p>
                            <p className="text-lg font-semibold text-zinc-900">{formatTime(dayData.clock_out)}</p>
                        </div>
                    </div>

                    {/* Stats row */}
                    <div className="grid grid-cols-3 gap-2">
                        <div className="rounded-lg bg-zinc-50 p-2.5 text-center">
                            <p className="text-xs text-zinc-400">Total</p>
                            <p className="text-sm font-semibold text-zinc-800">{formatHours(dayData.total_work_minutes)}</p>
                        </div>
                        <div className={cn('rounded-lg p-2.5 text-center', dayData.late_minutes > 0 ? 'bg-amber-50' : 'bg-zinc-50')}>
                            <p className="text-xs text-zinc-400">Late</p>
                            <p className={cn('text-sm font-semibold', dayData.late_minutes > 0 ? 'text-amber-700' : 'text-zinc-800')}>
                                {dayData.late_minutes > 0 ? `+${formatHours(dayData.late_minutes)}` : 'On Time'}
                            </p>
                        </div>
                        <div className={cn('rounded-lg p-2.5 text-center', dayData.early_leave_minutes > 0 ? 'bg-orange-50' : 'bg-zinc-50')}>
                            <p className="text-xs text-zinc-400">Early Leave</p>
                            <p className={cn('text-sm font-semibold', dayData.early_leave_minutes > 0 ? 'text-orange-700' : 'text-zinc-800')}>
                                {dayData.early_leave_minutes > 0 ? formatHours(dayData.early_leave_minutes) : '-'}
                            </p>
                        </div>
                    </div>

                    {dayData.is_overtime && (
                        <div className="flex items-center gap-2 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-700">
                            <TrendingUp className="h-4 w-4" />
                            Overtime recorded
                        </div>
                    )}

                    {dayData.remarks && (
                        <div className="flex items-start gap-2 rounded-lg bg-zinc-50 px-3 py-2.5">
                            <MessageSquare className="mt-0.5 h-4 w-4 shrink-0 text-zinc-400" />
                            <div>
                                <p className="text-xs font-medium text-zinc-500">Remarks</p>
                                <p className="text-sm text-zinc-700">{dayData.remarks}</p>
                            </div>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function SkeletonRow() {
    return (
        <div className="flex items-center gap-2 border-b border-zinc-100 px-4 py-2.5">
            <div className="w-52 shrink-0">
                <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                <div className="mt-1 h-3 w-24 animate-pulse rounded bg-zinc-100" />
            </div>
            <div className="flex gap-1">
                {Array.from({ length: 31 }).map((_, i) => (
                    <div key={i} className="h-7 w-7 animate-pulse rounded-full bg-zinc-100" />
                ))}
            </div>
        </div>
    );
}

export default function AttendanceMonthlyView() {
    const today = new Date();
    const [year, setYear] = useState(today.getFullYear());
    const [month, setMonth] = useState(today.getMonth() + 1);
    const [department, setDepartment] = useState('all');
    const [search, setSearch] = useState('');
    const [selectedCell, setSelectedCell] = useState(null);
    const scrollRef = useRef(null);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'monthly', year, month, department, search],
        queryFn: () => fetchAttendanceMonthly({
            year,
            month,
            department_id: department !== 'all' ? department : undefined,
            search: search || undefined,
        }),
        keepPreviousData: true,
    });

    const { data: departmentsData } = useQuery({
        queryKey: ['hr', 'departments'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const departments = departmentsData?.data || [];
    const employees = data?.data || [];
    const meta = data?.meta || {};
    const daysInMonth = meta.days_in_month || 31;

    function prevMonth() {
        if (month === 1) { setYear(y => y - 1); setMonth(12); }
        else { setMonth(m => m - 1); }
    }

    function nextMonth() {
        if (month === 12) { setYear(y => y + 1); setMonth(1); }
        else { setMonth(m => m + 1); }
    }

    function openCell(employee, day, dayData) {
        const dateObj = new Date(year, month - 1, day);
        const dateStr = dateObj.toLocaleDateString('en-MY', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        setSelectedCell({ employee, day, dayData, dateStr });
    }

    // Legend items
    const legendItems = Object.entries(STATUS_CONFIG).filter(([k]) => k !== 'holiday');

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title="Monthly Attendance"
                description="Overview of employee attendance by month"
            />

            {/* Controls */}
            <div className="flex flex-wrap items-center gap-3">
                {/* Month navigator */}
                <div className="flex items-center gap-1 rounded-lg border border-zinc-200 bg-white p-1 shadow-xs">
                    <button
                        onClick={prevMonth}
                        className="flex h-8 w-8 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900"
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </button>
                    <span className="min-w-[140px] text-center text-sm font-semibold text-zinc-800">
                        {MONTH_NAMES[month - 1]} {year}
                    </span>
                    <button
                        onClick={nextMonth}
                        className="flex h-8 w-8 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900"
                    >
                        <ChevronRight className="h-4 w-4" />
                    </button>
                </div>

                {/* Department filter */}
                <div className="w-48">
                    <Select value={department} onValueChange={setDepartment}>
                        <SelectTrigger className="h-9">
                            <SelectValue placeholder="All Departments" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Departments</SelectItem>
                            {departments.map((dept) => (
                                <SelectItem key={dept.id} value={String(dept.id)}>
                                    {dept.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Search */}
                <div className="relative min-w-[180px]">
                    <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-400" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search employee..."
                        className="h-9 pl-8 text-sm"
                    />
                </div>

                {/* Summary badge */}
                {!isLoading && (
                    <div className="ml-auto flex items-center gap-1.5 rounded-lg bg-zinc-50 px-3 py-1.5">
                        <Users className="h-4 w-4 text-zinc-400" />
                        <span className="text-sm text-zinc-600">{employees.length} employees</span>
                    </div>
                )}
            </div>

            {/* Legend */}
            <div className="flex flex-wrap items-center gap-3 rounded-lg border border-zinc-100 bg-white px-4 py-2.5 shadow-xs">
                <span className="text-xs font-medium text-zinc-400 uppercase tracking-wide">Legend</span>
                {legendItems.map(([key, cfg]) => (
                    <div key={key} className="flex items-center gap-1.5">
                        <span className={cn('flex h-5 w-5 items-center justify-center rounded-full text-[9px] font-bold text-white', cfg.bg)}>
                            {cfg.short}
                        </span>
                        <span className="text-xs text-zinc-500">{cfg.label}</span>
                    </div>
                ))}
                <div className="flex items-center gap-1.5">
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-zinc-100 text-[9px] text-zinc-300">—</span>
                    <span className="text-xs text-zinc-500">Weekend</span>
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-5 w-5 rounded-full border-2 border-zinc-300 bg-transparent" />
                    <span className="text-xs text-zinc-500">No record</span>
                </div>
            </div>

            {/* Table */}
            <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs">
                {/* Sticky header row */}
                <div className="sticky top-0 z-10 flex border-b border-zinc-200 bg-zinc-50">
                    {/* Employee column header */}
                    <div className="w-52 shrink-0 border-r border-zinc-200 px-4 py-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-zinc-400">Employee</p>
                    </div>
                    {/* Day headers - scrollable */}
                    <div
                        ref={scrollRef}
                        className="flex flex-1 overflow-x-auto"
                        style={{ scrollbarWidth: 'none' }}
                    >
                        {Array.from({ length: daysInMonth }, (_, i) => i + 1).map((day) => {
                            const dow = getDayOfWeek(year, month, day);
                            const weekend = isWeekend(year, month, day);
                            const isToday = today.getFullYear() === year && today.getMonth() + 1 === month && today.getDate() === day;
                            return (
                                <div
                                    key={day}
                                    className={cn(
                                        'flex w-9 shrink-0 flex-col items-center justify-center py-2',
                                        weekend ? 'bg-zinc-100/60' : '',
                                        isToday ? 'bg-blue-50' : '',
                                    )}
                                >
                                    <span className={cn(
                                        'text-[10px] font-medium',
                                        weekend ? 'text-zinc-400' : 'text-zinc-500',
                                        isToday ? 'text-blue-600 font-bold' : '',
                                    )}>
                                        {DAY_ABBR[dow]}
                                    </span>
                                    <span className={cn(
                                        'text-xs font-semibold',
                                        weekend ? 'text-zinc-400' : 'text-zinc-700',
                                        isToday ? 'text-blue-600' : '',
                                    )}>
                                        {day}
                                    </span>
                                </div>
                            );
                        })}
                        {/* Summary columns header */}
                        <div className="flex shrink-0 items-center border-l border-zinc-200 bg-zinc-50 px-2">
                            <span className="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">Summary</span>
                        </div>
                    </div>
                </div>

                {/* Rows */}
                <div className="divide-y divide-zinc-100">
                    {isLoading ? (
                        Array.from({ length: 8 }).map((_, i) => <SkeletonRow key={i} />)
                    ) : employees.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Calendar className="mb-3 h-10 w-10 text-zinc-200" />
                            <p className="text-sm font-medium text-zinc-400">No employees found</p>
                        </div>
                    ) : (
                        employees.map((emp) => (
                            <div key={emp.id} className="flex items-center hover:bg-zinc-50/60">
                                {/* Employee info - fixed */}
                                <div className="w-52 shrink-0 border-r border-zinc-100 px-4 py-2.5">
                                    <p className="truncate text-sm font-medium text-zinc-900">{emp.full_name}</p>
                                    <p className="truncate text-xs text-zinc-400">{emp.employee_id} · {emp.department}</p>
                                </div>
                                {/* Day cells - scrollable (synced with header) */}
                                <div className="flex flex-1 overflow-x-auto" style={{ scrollbarWidth: 'none' }}>
                                    {Array.from({ length: daysInMonth }, (_, i) => i + 1).map((day) => {
                                        const dayData = emp.days?.[day];
                                        const weekend = isWeekend(year, month, day);
                                        const isToday = today.getFullYear() === year && today.getMonth() + 1 === month && today.getDate() === day;
                                        return (
                                            <div
                                                key={day}
                                                className={cn(
                                                    'flex w-9 shrink-0 items-center justify-center py-1.5',
                                                    weekend ? 'bg-zinc-50/80' : '',
                                                    isToday ? 'bg-blue-50/40' : '',
                                                )}
                                            >
                                                {dayData ? (
                                                    <StatusDot
                                                        status={dayData.status}
                                                        isToday={isToday}
                                                        onClick={() => openCell(emp, day, dayData)}
                                                    />
                                                ) : (
                                                    <EmptyDot isWeekend={weekend} isToday={isToday} />
                                                )}
                                            </div>
                                        );
                                    })}
                                    {/* Summary pills */}
                                    <div className="flex shrink-0 items-center gap-1 border-l border-zinc-100 px-2">
                                        <SummaryPill count={emp.summary?.present} variant="present" />
                                        <SummaryPill count={emp.summary?.absent} variant="absent" />
                                        <SummaryPill count={emp.summary?.late} variant="late" />
                                        <SummaryPill count={emp.summary?.leave} variant="leave" />
                                    </div>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>

            {/* Detail Modal */}
            {selectedCell && selectedCell.dayData && (
                <DetailModal
                    dayData={selectedCell.dayData}
                    employeeName={selectedCell.employee.full_name}
                    date={selectedCell.dateStr}
                    onClose={() => setSelectedCell(null)}
                />
            )}
        </div>
    );
}
