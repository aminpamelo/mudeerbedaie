import { useState } from 'react';
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
    ExternalLink,
    Wifi,
    Timer,
    DoorOpen,
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
    const hasClockInLocation = dayData.clock_in_latitude && dayData.clock_in_longitude;
    const hasClockOutLocation = dayData.clock_out_latitude && dayData.clock_out_longitude;

    return (
        <Dialog open onOpenChange={onClose}>
            <DialogContent className="max-w-md max-h-[90vh] overflow-y-auto">
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
                    {/* Clock In / Clock Out */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                            <p className="mb-1 flex items-center gap-1 text-xs font-medium text-zinc-400">
                                <Clock className="h-3 w-3" /> Clock In
                            </p>
                            <p className="text-lg font-semibold text-zinc-900">{formatTime(dayData.clock_in)}</p>
                            {dayData.clock_in_photo_url ? (
                                <img
                                    src={dayData.clock_in_photo_url}
                                    alt="Clock in photo"
                                    className="mt-2 h-28 w-full rounded-md object-cover"
                                />
                            ) : (
                                <div className="mt-2 flex h-28 w-full items-center justify-center rounded-md bg-zinc-100">
                                    <Camera className="h-6 w-6 text-zinc-300" />
                                </div>
                            )}
                        </div>
                        <div className="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                            <p className="mb-1 flex items-center gap-1 text-xs font-medium text-zinc-400">
                                <Clock className="h-3 w-3" /> Clock Out
                            </p>
                            <p className="text-lg font-semibold text-zinc-900">{formatTime(dayData.clock_out)}</p>
                            {dayData.clock_out_photo_url ? (
                                <img
                                    src={dayData.clock_out_photo_url}
                                    alt="Clock out photo"
                                    className="mt-2 h-28 w-full rounded-md object-cover"
                                />
                            ) : (
                                <div className="mt-2 flex h-28 w-full items-center justify-center rounded-md bg-zinc-100">
                                    <Camera className="h-6 w-6 text-zinc-300" />
                                </div>
                            )}
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

                    {/* Overtime */}
                    {dayData.is_overtime && (
                        <div className="flex items-center gap-2 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-700">
                            <TrendingUp className="h-4 w-4" />
                            Overtime recorded
                        </div>
                    )}

                    {/* Location Map */}
                    {(hasClockInLocation || hasClockOutLocation) && (
                        <div className="space-y-2">
                            <p className="text-xs font-medium text-zinc-500 uppercase tracking-wide">Location</p>

                            {hasClockInLocation && (
                                <div className="overflow-hidden rounded-lg border border-zinc-200">
                                    <a
                                        href={`https://www.google.com/maps?q=${dayData.clock_in_latitude},${dayData.clock_in_longitude}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="block relative group"
                                    >
                                        <img
                                            src={`https://maps.googleapis.com/maps/api/staticmap?center=${dayData.clock_in_latitude},${dayData.clock_in_longitude}&zoom=15&size=480x120&scale=2&markers=color:red%7C${dayData.clock_in_latitude},${dayData.clock_in_longitude}&key=${window.hrConfig?.googleMapsKey || ''}`}
                                            alt="Clock-in location"
                                            className="w-full h-28 object-cover bg-zinc-100"
                                            onError={(e) => {
                                                e.target.style.display = 'none';
                                                e.target.nextElementSibling?.classList.remove('hidden');
                                            }}
                                        />
                                        <div className="hidden w-full h-28 bg-gradient-to-br from-blue-50 via-sky-50 to-indigo-50 flex items-center justify-center">
                                            <div className="text-center">
                                                <MapPin className="h-6 w-6 text-blue-500 mx-auto mb-1" />
                                                <p className="text-xs font-medium text-blue-700">
                                                    {Number(dayData.clock_in_latitude).toFixed(5)}, {Number(dayData.clock_in_longitude).toFixed(5)}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center">
                                            <span className="opacity-0 group-hover:opacity-100 transition-opacity inline-flex items-center gap-1 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-sm backdrop-blur-sm">
                                                <ExternalLink className="h-3 w-3" />
                                                Open in Google Maps
                                            </span>
                                        </div>
                                    </a>
                                    <div className="flex items-center justify-between bg-zinc-50 px-3 py-2">
                                        <div className="flex items-center gap-1.5">
                                            <MapPin className="h-3.5 w-3.5 text-blue-500" />
                                            <span className="text-xs font-medium text-zinc-600">
                                                {dayData.status === 'wfh' ? 'WFH Location' : 'Clock-in Location'}
                                            </span>
                                        </div>
                                        <span className="text-[10px] font-mono text-zinc-400">
                                            {Number(dayData.clock_in_latitude).toFixed(5)}, {Number(dayData.clock_in_longitude).toFixed(5)}
                                        </span>
                                    </div>
                                </div>
                            )}

                            {hasClockOutLocation && (
                                <div className="overflow-hidden rounded-lg border border-zinc-200">
                                    <a
                                        href={`https://www.google.com/maps?q=${dayData.clock_out_latitude},${dayData.clock_out_longitude}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="block relative group"
                                    >
                                        <div className="w-full h-10 bg-gradient-to-r from-zinc-50 to-zinc-100 flex items-center justify-center">
                                            <div className="flex items-center gap-1.5">
                                                <MapPin className="h-3.5 w-3.5 text-zinc-500" />
                                                <span className="text-xs font-medium text-zinc-600">Clock-out Location</span>
                                                <ExternalLink className="h-3 w-3 text-zinc-400 group-hover:text-blue-500 transition-colors" />
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            )}
                        </div>
                    )}

                    {/* IP Address */}
                    {(dayData.clock_in_ip || dayData.clock_out_ip) && (
                        <div className="space-y-1.5">
                            <p className="text-xs font-medium text-zinc-500 uppercase tracking-wide">Network</p>
                            <div className="flex flex-wrap gap-2">
                                {dayData.clock_in_ip && (
                                    <div className="flex items-center gap-1.5 rounded-lg bg-zinc-50 px-3 py-1.5">
                                        <Wifi className="h-3 w-3 text-zinc-400" />
                                        <span className="text-xs text-zinc-500">In:</span>
                                        <span className="text-xs font-mono text-zinc-700">{dayData.clock_in_ip}</span>
                                    </div>
                                )}
                                {dayData.clock_out_ip && (
                                    <div className="flex items-center gap-1.5 rounded-lg bg-zinc-50 px-3 py-1.5">
                                        <Wifi className="h-3 w-3 text-zinc-400" />
                                        <span className="text-xs text-zinc-500">Out:</span>
                                        <span className="text-xs font-mono text-zinc-700">{dayData.clock_out_ip}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* OT Claim */}
                    {dayData.ot_claim && (
                        <div className="space-y-1.5">
                            <p className="text-xs font-medium text-zinc-500 uppercase tracking-wide">OT Claim</p>
                            <div className="rounded-lg border border-blue-100 bg-blue-50/50 p-3">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-2">
                                        <Timer className="h-4 w-4 text-blue-500" />
                                        <div>
                                            <p className="text-sm font-medium text-zinc-800">
                                                {formatHours(dayData.ot_claim.duration_minutes)}
                                            </p>
                                            {dayData.ot_claim.start_time && (
                                                <p className="text-xs text-zinc-500">
                                                    Starting at {dayData.ot_claim.start_time.slice(0, 5)}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <span className={cn(
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                        dayData.ot_claim.status === 'approved' && 'bg-emerald-100 text-emerald-700',
                                        dayData.ot_claim.status === 'pending' && 'bg-amber-100 text-amber-700',
                                        dayData.ot_claim.status === 'rejected' && 'bg-red-100 text-red-700',
                                        dayData.ot_claim.status === 'cancelled' && 'bg-zinc-100 text-zinc-500',
                                    )}>
                                        {dayData.ot_claim.status.charAt(0).toUpperCase() + dayData.ot_claim.status.slice(1)}
                                    </span>
                                </div>
                                {dayData.ot_claim.notes && (
                                    <p className="mt-2 text-xs text-zinc-500 border-t border-blue-100 pt-2">{dayData.ot_claim.notes}</p>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Exit Permissions */}
                    {dayData.exit_permissions?.length > 0 && (
                        <div className="space-y-1.5">
                            <p className="text-xs font-medium text-zinc-500 uppercase tracking-wide">
                                Exit Permission{dayData.exit_permissions.length > 1 ? 's' : ''}
                            </p>
                            <div className="space-y-2">
                                {dayData.exit_permissions.map((perm) => (
                                    <div key={perm.id} className="rounded-lg border border-violet-100 bg-violet-50/50 p-3">
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-center gap-2">
                                                <DoorOpen className="h-4 w-4 text-violet-500" />
                                                <div>
                                                    <p className="text-sm font-medium text-zinc-800">
                                                        {perm.exit_time?.slice(0, 5)} — {perm.return_time?.slice(0, 5)}
                                                    </p>
                                                    <p className="text-xs text-zinc-500">
                                                        <span className={cn(
                                                            'inline-flex items-center rounded px-1 py-0.5 text-[10px] font-medium mr-1',
                                                            perm.errand_type === 'company' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700',
                                                        )}>
                                                            {perm.errand_type === 'company' ? 'Company' : 'Personal'}
                                                        </span>
                                                        {perm.permission_number}
                                                    </p>
                                                </div>
                                            </div>
                                            <span className={cn(
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                                perm.status === 'approved' && 'bg-emerald-100 text-emerald-700',
                                                perm.status === 'pending' && 'bg-amber-100 text-amber-700',
                                                perm.status === 'rejected' && 'bg-red-100 text-red-700',
                                                perm.status === 'cancelled' && 'bg-zinc-100 text-zinc-500',
                                            )}>
                                                {perm.status.charAt(0).toUpperCase() + perm.status.slice(1)}
                                            </span>
                                        </div>
                                        {perm.purpose && (
                                            <p className="mt-2 text-xs text-zinc-500 border-t border-violet-100 pt-2">{perm.purpose}</p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Remarks */}
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

            {/* Table — single scroll container so header + rows move together */}
            <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs">
                <div className="overflow-x-auto">
                    <div style={{ minWidth: `${208 + daysInMonth * 36 + 100}px` }}>

                        {/* Sticky header row */}
                        <div className="sticky top-0 z-20 flex border-b border-zinc-200 bg-zinc-50">
                            {/* Employee column header — also sticky left */}
                            <div className="sticky left-0 z-30 w-52 shrink-0 border-r border-zinc-200 bg-zinc-50 px-4 py-3">
                                <p className="text-xs font-semibold uppercase tracking-wide text-zinc-400">Employee</p>
                            </div>
                            {/* Day headers */}
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
                                            isToday && 'text-blue-600 font-bold',
                                        )}>
                                            {DAY_ABBR[dow]}
                                        </span>
                                        <span className={cn(
                                            'text-xs font-semibold',
                                            weekend ? 'text-zinc-400' : 'text-zinc-700',
                                            isToday && 'text-blue-600',
                                        )}>
                                            {day}
                                        </span>
                                    </div>
                                );
                            })}
                            {/* Summary header */}
                            <div className="flex shrink-0 items-center border-l border-zinc-200 px-3">
                                <span className="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">Summary</span>
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
                                        {/* Employee info — sticky left */}
                                        <div className="sticky left-0 z-10 w-52 shrink-0 border-r border-zinc-100 bg-white px-4 py-2.5 group-hover:bg-zinc-50/60">
                                            <p className="truncate text-sm font-medium text-zinc-900">{emp.full_name}</p>
                                            <p className="truncate text-xs text-zinc-400">{emp.employee_id}{emp.department ? ` · ${emp.department}` : ''}</p>
                                        </div>
                                        {/* Day cells */}
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
                                        <div className="flex shrink-0 items-center gap-1 border-l border-zinc-100 px-3">
                                            <SummaryPill count={emp.summary?.present} variant="present" />
                                            <SummaryPill count={emp.summary?.absent} variant="absent" />
                                            <SummaryPill count={emp.summary?.late} variant="late" />
                                            <SummaryPill count={emp.summary?.leave} variant="leave" />
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>

                    </div>
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
