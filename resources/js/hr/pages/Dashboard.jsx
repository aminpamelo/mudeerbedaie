import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';
import {
    Users,
    UserPlus,
    Clock,
    Building2,
    ArrowRight,
    TrendingUp,
    RefreshCw,
    Pencil,
    UserCheck,
    UserMinus,
    DollarSign,
    Briefcase,
    AlertCircle,
    CalendarCheck,
    FileText,
    Timer,
    Cake,
    Award,
    MapPin,
    Coffee,
    LogIn,
    Palmtree,
    CircleAlert,
    ClipboardList,
    ChevronRight,
    Video,
} from 'lucide-react';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
} from '../components/ui/card';
import { Avatar, AvatarImage, AvatarFallback } from '../components/ui/avatar';
import { Badge } from '../components/ui/badge';
import { cn } from '../lib/utils';
import {
    fetchDashboardStats,
    fetchRecentActivity,
    fetchHeadcountByDepartment,
    fetchDashboardTodayAttendance,
    fetchPendingApprovals,
    fetchOnLeaveToday,
    fetchUpcomingEvents,
    fetchTodayMeetings,
} from '../lib/api';

// ─── Constants ──────────────────────────────────────────────

const CHANGE_TYPE_CONFIG = {
    status_change: { icon: RefreshCw, color: 'text-blue-600', bg: 'bg-blue-50' },
    promotion: { icon: TrendingUp, color: 'text-emerald-600', bg: 'bg-emerald-50' },
    department_change: { icon: Building2, color: 'text-violet-600', bg: 'bg-violet-50' },
    position_change: { icon: Briefcase, color: 'text-amber-600', bg: 'bg-amber-50' },
    salary_change: { icon: DollarSign, color: 'text-teal-600', bg: 'bg-teal-50' },
    termination: { icon: UserMinus, color: 'text-red-600', bg: 'bg-red-50' },
    hired: { icon: UserPlus, color: 'text-sky-600', bg: 'bg-sky-50' },
    info_update: { icon: Pencil, color: 'text-slate-500', bg: 'bg-slate-50' },
};

function getChangeConfig(type) {
    return CHANGE_TYPE_CONFIG[type] || { icon: RefreshCw, color: 'text-slate-500', bg: 'bg-slate-50' };
}

// ─── Helpers ────────────────────────────────────────────────

function getGreeting() {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
}

function formatTime(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function formatRelativeDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString('en-MY', { day: 'numeric', month: 'short' });
}

function daysUntil(dateString) {
    if (!dateString) return null;
    const date = new Date(dateString);
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    date.setHours(0, 0, 0, 0);
    return Math.ceil((date - now) / (1000 * 60 * 60 * 24));
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
}

// ─── Skeleton Components ────────────────────────────────────

function Skeleton({ className }) {
    return <div className={cn('animate-pulse rounded bg-slate-200/70', className)} />;
}

function SkeletonStatRow() {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {[1, 2, 3, 4].map(i => (
                <div key={i} className="rounded-xl border border-slate-200/60 bg-white p-4">
                    <Skeleton className="mb-2 h-3 w-20" />
                    <Skeleton className="mb-1 h-7 w-12" />
                    <Skeleton className="h-2.5 w-16" />
                </div>
            ))}
        </div>
    );
}

function SkeletonCard({ lines = 3, height = 'h-[200px]' }) {
    return (
        <Card>
            <CardHeader className="pb-3">
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-3 w-48" />
            </CardHeader>
            <CardContent>
                <div className={cn('space-y-3', height)}>
                    {Array.from({ length: lines }).map((_, i) => (
                        <div key={i} className="flex items-center gap-3">
                            <Skeleton className="h-8 w-8 rounded-full" />
                            <div className="flex-1 space-y-1.5">
                                <Skeleton className="h-3 w-28" />
                                <Skeleton className="h-2.5 w-40" />
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

// ─── Attendance Ring ────────────────────────────────────────

function AttendanceRing({ rate, size = 120, strokeWidth = 10 }) {
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (rate / 100) * circumference;
    const center = size / 2;

    let strokeColor = '#10b981'; // green
    if (rate < 60) strokeColor = '#ef4444'; // red
    else if (rate < 80) strokeColor = '#f59e0b'; // amber

    return (
        <div className="relative" style={{ width: size, height: size }}>
            <svg width={size} height={size} className="-rotate-90">
                <circle
                    cx={center}
                    cy={center}
                    r={radius}
                    fill="none"
                    stroke="#f1f5f9"
                    strokeWidth={strokeWidth}
                />
                <circle
                    cx={center}
                    cy={center}
                    r={radius}
                    fill="none"
                    stroke={strokeColor}
                    strokeWidth={strokeWidth}
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    strokeLinecap="round"
                    className="transition-all duration-1000 ease-out"
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-2xl font-bold text-slate-800">{Math.round(rate)}%</span>
                <span className="text-[10px] font-medium uppercase tracking-wider text-slate-400">Present</span>
            </div>
        </div>
    );
}

// ─── Attendance Status Dot ──────────────────────────────────

function StatusDot({ color, label, count }) {
    return (
        <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
                <div className={cn('h-2.5 w-2.5 rounded-full', color)} />
                <span className="text-xs text-slate-500">{label}</span>
            </div>
            <span className="text-sm font-semibold text-slate-700">{count}</span>
        </div>
    );
}

// ─── Custom Bar Tooltip ─────────────────────────────────────

function CustomBarTooltip({ active, payload, label }) {
    if (!active || !payload?.length) return null;
    return (
        <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-lg">
            <p className="text-xs font-medium text-slate-700">{label}</p>
            <p className="text-sm font-semibold text-slate-900">
                {payload[0].value} {payload[0].value !== 1 ? 'employees' : 'employee'}
            </p>
        </div>
    );
}

// ─── Quick Nav Button ───────────────────────────────────────

function QuickLink({ icon: Icon, label, to, count, color = 'text-slate-600', bg = 'bg-slate-50' }) {
    const navigate = useNavigate();
    return (
        <button
            onClick={() => navigate(to)}
            className="group flex items-center gap-3 rounded-lg border border-slate-200/60 bg-white px-3 py-2.5 text-left transition-all hover:border-slate-300 hover:shadow-sm"
        >
            <div className={cn('flex h-8 w-8 items-center justify-center rounded-lg', bg)}>
                <Icon className={cn('h-4 w-4', color)} />
            </div>
            <div className="min-w-0 flex-1">
                <span className="text-sm font-medium text-slate-700 group-hover:text-slate-900">{label}</span>
            </div>
            {count > 0 && (
                <span className="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">
                    {count}
                </span>
            )}
        </button>
    );
}

// ═══════════════════════════════════════════════════════════
// MAIN DASHBOARD
// ═══════════════════════════════════════════════════════════

export default function Dashboard() {
    const navigate = useNavigate();

    // ─── Queries ────────────────────────────────────────────
    const { data: stats, isLoading: statsLoading } = useQuery({
        queryKey: ['hr', 'dashboard', 'stats'],
        queryFn: fetchDashboardStats,
    });

    const { data: attendance, isLoading: attendanceLoading } = useQuery({
        queryKey: ['hr', 'dashboard', 'today-attendance'],
        queryFn: fetchDashboardTodayAttendance,
        refetchInterval: 60000, // refresh every minute
    });

    const { data: approvals, isLoading: approvalsLoading } = useQuery({
        queryKey: ['hr', 'dashboard', 'pending-approvals'],
        queryFn: fetchPendingApprovals,
    });

    const { data: onLeave, isLoading: onLeaveLoading } = useQuery({
        queryKey: ['hr', 'dashboard', 'on-leave-today'],
        queryFn: fetchOnLeaveToday,
    });

    const { data: events, isLoading: eventsLoading } = useQuery({
        queryKey: ['hr', 'dashboard', 'upcoming-events'],
        queryFn: fetchUpcomingEvents,
    });

    const { data: meetings, isLoading: meetingsLoading } = useQuery({
        queryKey: ['hr', 'dashboard', 'today-meetings'],
        queryFn: fetchTodayMeetings,
    });

    const { data: recentActivity, isLoading: activityLoading } = useQuery({
        queryKey: ['hr', 'dashboard', 'recent-activity'],
        queryFn: fetchRecentActivity,
    });

    const { data: headcount, isLoading: headcountLoading } = useQuery({
        queryKey: ['hr', 'dashboard', 'headcount'],
        queryFn: fetchHeadcountByDepartment,
    });

    const s = stats?.data;
    const att = attendance?.data;
    const app = approvals?.data;
    const leaveData = onLeave?.data || [];
    const eventsData = events?.data;
    const meetingsData = meetings?.data || [];
    const activityData = recentActivity?.data || [];
    const headcountData = headcount?.data || [];
    const probationEndingSoon = s?.probation_ending_soon || [];

    const userName = window.hrConfig?.user?.name?.split(' ')[0] || 'Admin';
    const todayStr = new Date().toLocaleDateString('en-MY', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    const totalPending = app?.total_pending ?? 0;

    return (
        <div className="space-y-6 pb-8">
            {/* ─── Header ─────────────────────────────────────── */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-800">
                        {getGreeting()}, {userName}
                    </h1>
                    <p className="mt-1 text-sm text-slate-400">{todayStr}</p>
                </div>
                <div className="flex gap-2">
                    <button
                        onClick={() => navigate('/attendance')}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50"
                    >
                        <CalendarCheck className="h-3.5 w-3.5" />
                        Attendance
                    </button>
                    <button
                        onClick={() => navigate('/leave/requests')}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50"
                    >
                        <FileText className="h-3.5 w-3.5" />
                        Leave
                    </button>
                    <button
                        onClick={() => navigate('/employees')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-slate-700"
                    >
                        <Users className="h-3.5 w-3.5" />
                        Employees
                    </button>
                </div>
            </div>

            {/* ─── Stat Cards Row ─────────────────────────────── */}
            {statsLoading ? (
                <SkeletonStatRow />
            ) : (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div
                        className="group cursor-pointer rounded-xl border border-slate-200/60 bg-white p-4 transition-all hover:border-blue-200 hover:shadow-sm"
                        onClick={() => navigate('/employees')}
                    >
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium uppercase tracking-wider text-slate-400">Employees</p>
                            <Users className="h-4 w-4 text-blue-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-slate-800">{s?.total_employees ?? 0}</p>
                        <p className="text-[11px] text-slate-400">{s?.active_employees ?? 0} active</p>
                    </div>
                    <div
                        className="group cursor-pointer rounded-xl border border-slate-200/60 bg-white p-4 transition-all hover:border-emerald-200 hover:shadow-sm"
                        onClick={() => navigate('/employees?sort=join_date&order=desc')}
                    >
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium uppercase tracking-wider text-slate-400">New Hires</p>
                            <UserPlus className="h-4 w-4 text-emerald-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-slate-800">{s?.new_hires_this_month ?? 0}</p>
                        <p className="text-[11px] text-slate-400">this month</p>
                    </div>
                    <div
                        className="group cursor-pointer rounded-xl border border-slate-200/60 bg-white p-4 transition-all hover:border-amber-200 hover:shadow-sm"
                        onClick={() => navigate('/employees?status=probation')}
                    >
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium uppercase tracking-wider text-slate-400">Probation</p>
                            <Clock className="h-4 w-4 text-amber-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-slate-800">{s?.on_probation ?? 0}</p>
                        <p className="text-[11px] text-slate-400">pending confirmation</p>
                    </div>
                    <div
                        className="group cursor-pointer rounded-xl border border-slate-200/60 bg-white p-4 transition-all hover:border-violet-200 hover:shadow-sm"
                        onClick={() => navigate('/departments')}
                    >
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium uppercase tracking-wider text-slate-400">Departments</p>
                            <Building2 className="h-4 w-4 text-violet-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-slate-800">{s?.departments_count ?? 0}</p>
                        <p className="text-[11px] text-slate-400">organizational units</p>
                    </div>
                </div>
            )}

            {/* ─── Main Grid: Attendance + Approvals ──────────── */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Today's Attendance — takes 2 cols */}
                <div className="lg:col-span-2">
                    {attendanceLoading ? (
                        <SkeletonCard lines={4} height="h-[280px]" />
                    ) : (
                        <Card>
                            <CardHeader className="pb-2">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <div className="flex h-6 w-6 items-center justify-center rounded-md bg-emerald-50">
                                                <CalendarCheck className="h-3.5 w-3.5 text-emerald-600" />
                                            </div>
                                            Today's Attendance
                                        </CardTitle>
                                        <CardDescription className="mt-0.5">Real-time workforce presence</CardDescription>
                                    </div>
                                    <button
                                        onClick={() => navigate('/attendance')}
                                        className="text-xs font-medium text-slate-400 transition-colors hover:text-slate-600"
                                    >
                                        View all &rarr;
                                    </button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col gap-6 sm:flex-row sm:items-center">
                                    {/* Ring */}
                                    <div className="flex shrink-0 justify-center">
                                        <AttendanceRing rate={att?.attendance_rate ?? 0} />
                                    </div>

                                    {/* Breakdown */}
                                    <div className="flex-1 space-y-2.5">
                                        <StatusDot color="bg-emerald-500" label="Present" count={att?.present ?? 0} />
                                        <StatusDot color="bg-amber-500" label="Late" count={att?.late ?? 0} />
                                        <StatusDot color="bg-sky-500" label="Work from Home" count={att?.wfh ?? 0} />
                                        <StatusDot color="bg-orange-400" label="Early Leave" count={att?.early_leave ?? 0} />
                                        <StatusDot color="bg-violet-500" label="On Leave" count={att?.on_leave ?? 0} />
                                        <StatusDot color="bg-slate-300" label="Not Clocked In" count={att?.not_clocked_in ?? 0} />
                                        <div className="border-t border-slate-100 pt-2">
                                            <div className="flex items-center justify-between">
                                                <span className="text-xs font-medium text-slate-500">Total Active</span>
                                                <span className="text-sm font-bold text-slate-800">{att?.total_active ?? 0}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Recent Clock-ins */}
                                    <div className="shrink-0 border-l border-slate-100 pl-5 sm:w-48">
                                        <p className="mb-2 flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                                            <LogIn className="h-3 w-3" />
                                            Recent Clock-ins
                                        </p>
                                        <div className="space-y-2">
                                            {(att?.recent_clock_ins || []).slice(0, 5).map((log) => (
                                                <div key={log.id} className="flex items-center gap-2">
                                                    <Avatar className="h-6 w-6">
                                                        <AvatarImage src={log.employee?.profile_photo_url} />
                                                        <AvatarFallback className="text-[9px]">
                                                            {getInitials(log.employee?.full_name)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate text-xs font-medium text-slate-700">
                                                            {log.employee?.full_name}
                                                        </p>
                                                    </div>
                                                    <span className={cn(
                                                        'shrink-0 text-[10px] font-medium',
                                                        log.status === 'late' ? 'text-amber-600' : 'text-slate-400'
                                                    )}>
                                                        {formatTime(log.clock_in)}
                                                    </span>
                                                </div>
                                            ))}
                                            {(!att?.recent_clock_ins || att.recent_clock_ins.length === 0) && (
                                                <p className="py-4 text-center text-xs text-slate-300">No clock-ins yet</p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Pending Approvals */}
                {approvalsLoading ? (
                    <SkeletonCard lines={4} height="h-[280px]" />
                ) : (
                    <Card className={totalPending > 0 ? 'border-l-2 border-l-red-400' : ''}>
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <div className="flex h-6 w-6 items-center justify-center rounded-md bg-red-50">
                                        <ClipboardList className="h-3.5 w-3.5 text-red-500" />
                                    </div>
                                    Pending Approvals
                                    {totalPending > 0 && (
                                        <span className="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">
                                            {totalPending}
                                        </span>
                                    )}
                                </CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {/* Counts */}
                            <div className="mb-4 grid grid-cols-3 gap-2">
                                <button
                                    onClick={() => navigate('/leave/requests?status=pending')}
                                    className="rounded-lg bg-amber-50/70 px-3 py-2.5 text-center transition-colors hover:bg-amber-100/70"
                                >
                                    <p className="text-lg font-bold text-amber-700">{app?.pending_leave ?? 0}</p>
                                    <p className="text-[10px] font-medium uppercase tracking-wider text-amber-500">Leave</p>
                                </button>
                                <button
                                    onClick={() => navigate('/attendance/overtime?status=pending')}
                                    className="rounded-lg bg-blue-50/70 px-3 py-2.5 text-center transition-colors hover:bg-blue-100/70"
                                >
                                    <p className="text-lg font-bold text-blue-700">{app?.pending_overtime ?? 0}</p>
                                    <p className="text-[10px] font-medium uppercase tracking-wider text-blue-500">Overtime</p>
                                </button>
                                <button
                                    onClick={() => navigate('/claims/requests?status=pending')}
                                    className="rounded-lg bg-violet-50/70 px-3 py-2.5 text-center transition-colors hover:bg-violet-100/70"
                                >
                                    <p className="text-lg font-bold text-violet-700">{app?.pending_claims ?? 0}</p>
                                    <p className="text-[10px] font-medium uppercase tracking-wider text-violet-500">Claims</p>
                                </button>
                            </div>

                            {/* Recent pending items */}
                            <div className="space-y-2">
                                {(app?.latest_pending || []).slice(0, 4).map((item) => (
                                    <div
                                        key={`${item.type}-${item.id}`}
                                        className="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-slate-50"
                                    >
                                        <div className={cn(
                                            'flex h-7 w-7 shrink-0 items-center justify-center rounded-full',
                                            item.type === 'leave' ? 'bg-amber-50' : item.type === 'overtime' ? 'bg-blue-50' : 'bg-violet-50'
                                        )}>
                                            {item.type === 'leave' && <Palmtree className="h-3.5 w-3.5 text-amber-600" />}
                                            {item.type === 'overtime' && <Timer className="h-3.5 w-3.5 text-blue-600" />}
                                            {item.type === 'claim' && <DollarSign className="h-3.5 w-3.5 text-violet-600" />}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-xs font-medium text-slate-700">{item.employee_name}</p>
                                            <p className="truncate text-[11px] text-slate-400">{item.label} &middot; {item.detail}</p>
                                        </div>
                                    </div>
                                ))}
                                {totalPending === 0 && (
                                    <div className="flex flex-col items-center py-6 text-center">
                                        <UserCheck className="mb-1.5 h-7 w-7 text-emerald-300" />
                                        <p className="text-xs text-slate-400">All caught up</p>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* ─── Second Row: On Leave + Meetings + Quick Nav ── */}
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                {/* Who's On Leave Today */}
                {onLeaveLoading ? (
                    <SkeletonCard lines={4} />
                ) : (
                    <Card>
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <div className="flex h-6 w-6 items-center justify-center rounded-md bg-violet-50">
                                        <Palmtree className="h-3.5 w-3.5 text-violet-600" />
                                    </div>
                                    On Leave Today
                                    {leaveData.length > 0 && (
                                        <Badge variant="secondary" className="text-[10px]">{leaveData.length}</Badge>
                                    )}
                                </CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {leaveData.length === 0 ? (
                                <div className="flex flex-col items-center py-6 text-center">
                                    <Coffee className="mb-1.5 h-7 w-7 text-slate-200" />
                                    <p className="text-xs text-slate-400">Everyone's in today</p>
                                </div>
                            ) : (
                                <div className="space-y-2.5">
                                    {leaveData.slice(0, 6).map((item) => (
                                        <div key={item.id} className="flex items-center gap-2.5">
                                            <Avatar className="h-7 w-7">
                                                <AvatarImage src={item.employee_photo} />
                                                <AvatarFallback className="text-[9px]">
                                                    {getInitials(item.employee_name)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-xs font-medium text-slate-700">{item.employee_name}</p>
                                                <p className="text-[11px] text-slate-400">
                                                    {item.leave_type}
                                                    {item.is_half_day ? ` (${item.half_day_period})` : ''}
                                                </p>
                                            </div>
                                            {item.total_days > 1 && (
                                                <span className="text-[10px] text-slate-400">
                                                    {item.start_date} - {item.end_date}
                                                </span>
                                            )}
                                        </div>
                                    ))}
                                    {leaveData.length > 6 && (
                                        <button
                                            onClick={() => navigate('/leave/calendar')}
                                            className="w-full pt-1 text-center text-[11px] font-medium text-slate-400 transition-colors hover:text-slate-600"
                                        >
                                            +{leaveData.length - 6} more &rarr;
                                        </button>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Today's Meetings */}
                {meetingsLoading ? (
                    <SkeletonCard lines={3} />
                ) : (
                    <Card>
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <div className="flex h-6 w-6 items-center justify-center rounded-md bg-sky-50">
                                        <Video className="h-3.5 w-3.5 text-sky-600" />
                                    </div>
                                    Today's Meetings
                                </CardTitle>
                                <button
                                    onClick={() => navigate('/meetings')}
                                    className="text-xs text-slate-400 hover:text-slate-600"
                                >
                                    View all &rarr;
                                </button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {meetingsData.length === 0 ? (
                                <div className="flex flex-col items-center py-6 text-center">
                                    <Video className="mb-1.5 h-7 w-7 text-slate-200" />
                                    <p className="text-xs text-slate-400">No meetings today</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {meetingsData.map((meeting) => (
                                        <button
                                            key={meeting.id}
                                            onClick={() => navigate(`/meetings/${meeting.id}`)}
                                            className="group flex w-full items-start gap-3 rounded-lg px-2 py-2 text-left transition-colors hover:bg-slate-50"
                                        >
                                            <div className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sky-50 text-[10px] font-bold text-sky-600">
                                                {meeting.start_time?.slice(0, 5)}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-xs font-medium text-slate-700 group-hover:text-slate-900">
                                                    {meeting.title}
                                                </p>
                                                <p className="text-[11px] text-slate-400">
                                                    {meeting.location && <span>{meeting.location} &middot; </span>}
                                                    {meeting.attendees_count} attendees
                                                </p>
                                            </div>
                                            <Badge
                                                variant={meeting.status === 'in_progress' ? 'success' : 'secondary'}
                                                className="mt-0.5 text-[9px]"
                                            >
                                                {meeting.status === 'in_progress' ? 'Live' : 'Upcoming'}
                                            </Badge>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Quick Navigation */}
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">Quick Actions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            <QuickLink icon={CalendarCheck} label="Attendance Records" to="/attendance" color="text-emerald-600" bg="bg-emerald-50" />
                            <QuickLink icon={Palmtree} label="Leave Requests" to="/leave/requests" count={app?.pending_leave} color="text-amber-600" bg="bg-amber-50" />
                            <QuickLink icon={Timer} label="Overtime Requests" to="/attendance/overtime" count={app?.pending_overtime} color="text-blue-600" bg="bg-blue-50" />
                            <QuickLink icon={DollarSign} label="Claims" to="/claims/requests" count={app?.pending_claims} color="text-violet-600" bg="bg-violet-50" />
                            <QuickLink icon={FileText} label="Payroll" to="/payroll" color="text-teal-600" bg="bg-teal-50" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* ─── Third Row: Headcount + Events + Activity ───── */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Department Headcount */}
                {headcountLoading ? (
                    <div className="lg:col-span-2">
                        <SkeletonCard lines={0} height="h-[300px]" />
                    </div>
                ) : (
                    <Card className="lg:col-span-2">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <div className="flex h-6 w-6 items-center justify-center rounded-md bg-blue-50">
                                            <Building2 className="h-3.5 w-3.5 text-blue-600" />
                                        </div>
                                        Department Headcount
                                    </CardTitle>
                                    <CardDescription className="mt-0.5">Active employees per department</CardDescription>
                                </div>
                                <button
                                    onClick={() => navigate('/departments')}
                                    className="text-xs text-slate-400 hover:text-slate-600"
                                >
                                    Manage &rarr;
                                </button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {headcountData.length === 0 ? (
                                <div className="flex h-[260px] items-center justify-center text-sm text-slate-300">
                                    No department data
                                </div>
                            ) : (
                                <ResponsiveContainer width="100%" height={Math.max(260, headcountData.length * 32)}>
                                    <BarChart
                                        data={headcountData}
                                        layout="vertical"
                                        margin={{ top: 0, right: 24, left: 8, bottom: 0 }}
                                    >
                                        <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="#f1f5f9" />
                                        <XAxis
                                            type="number"
                                            tick={{ fontSize: 11, fill: '#94a3b8' }}
                                            allowDecimals={false}
                                            axisLine={false}
                                            tickLine={false}
                                        />
                                        <YAxis
                                            type="category"
                                            dataKey="name"
                                            tick={{ fontSize: 11, fill: '#64748b' }}
                                            width={110}
                                            axisLine={false}
                                            tickLine={false}
                                        />
                                        <Tooltip content={<CustomBarTooltip />} />
                                        <Bar dataKey="count" fill="#3b82f6" radius={[0, 6, 6, 0]} barSize={20} />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Upcoming Events */}
                {eventsLoading ? (
                    <SkeletonCard lines={5} />
                ) : (
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <div className="flex h-6 w-6 items-center justify-center rounded-md bg-pink-50">
                                    <Cake className="h-3.5 w-3.5 text-pink-500" />
                                </div>
                                Upcoming Events
                            </CardTitle>
                            <CardDescription className="mt-0.5">Birthdays & work anniversaries</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {(eventsData?.birthdays?.length === 0 && eventsData?.anniversaries?.length === 0) ? (
                                <div className="flex flex-col items-center py-6 text-center">
                                    <Cake className="mb-1.5 h-7 w-7 text-slate-200" />
                                    <p className="text-xs text-slate-400">No upcoming events</p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {/* Birthdays */}
                                    {(eventsData?.birthdays || []).map((b) => (
                                        <div
                                            key={`bday-${b.id}`}
                                            className={cn(
                                                'flex items-center gap-2.5 rounded-lg px-2 py-1.5',
                                                b.is_today && 'bg-pink-50/60'
                                            )}
                                        >
                                            <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-pink-50">
                                                <Cake className="h-3.5 w-3.5 text-pink-500" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-xs font-medium text-slate-700">{b.full_name}</p>
                                                <p className="text-[11px] text-slate-400">
                                                    {b.is_today ? 'Today!' : b.date}
                                                </p>
                                            </div>
                                            {b.is_today ? (
                                                <Badge className="bg-pink-100 text-[9px] text-pink-700">Birthday!</Badge>
                                            ) : (
                                                <span className="text-[10px] text-slate-400">{b.days_away}d</span>
                                            )}
                                        </div>
                                    ))}

                                    {/* Divider if both exist */}
                                    {(eventsData?.birthdays?.length > 0 && eventsData?.anniversaries?.length > 0) && (
                                        <div className="border-t border-slate-100" />
                                    )}

                                    {/* Anniversaries */}
                                    {(eventsData?.anniversaries || []).map((a) => (
                                        <div
                                            key={`ann-${a.id}`}
                                            className={cn(
                                                'flex items-center gap-2.5 rounded-lg px-2 py-1.5',
                                                a.is_today && 'bg-amber-50/60'
                                            )}
                                        >
                                            <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-50">
                                                <Award className="h-3.5 w-3.5 text-amber-500" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-xs font-medium text-slate-700">{a.full_name}</p>
                                                <p className="text-[11px] text-slate-400">
                                                    {a.is_today ? `${a.years} years today!` : `${a.date} &middot; ${a.years} years`}
                                                </p>
                                            </div>
                                            {a.is_today ? (
                                                <Badge className="bg-amber-100 text-[9px] text-amber-700">Anniversary!</Badge>
                                            ) : (
                                                <span className="text-[10px] text-slate-400">{a.days_away}d</span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* ─── Bottom Row: Probation + Recent Activity ────── */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Probation Ending Soon */}
                {statsLoading ? (
                    <SkeletonCard lines={3} />
                ) : (
                    <Card className={probationEndingSoon.length > 0 ? 'border-l-2 border-l-amber-400' : ''}>
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <div className="flex h-6 w-6 items-center justify-center rounded-md bg-amber-50">
                                        <AlertCircle className="h-3.5 w-3.5 text-amber-500" />
                                    </div>
                                    Probation Ending Soon
                                    {probationEndingSoon.length > 0 && (
                                        <Badge variant="warning" className="text-[10px]">{probationEndingSoon.length}</Badge>
                                    )}
                                </CardTitle>
                            </div>
                            <CardDescription className="mt-0.5">Within 30 days</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {probationEndingSoon.length === 0 ? (
                                <div className="flex flex-col items-center py-6 text-center">
                                    <UserCheck className="mb-1.5 h-7 w-7 text-slate-200" />
                                    <p className="text-xs text-slate-400">No probation periods ending soon</p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {probationEndingSoon.map((emp) => {
                                        const days = daysUntil(emp.probation_end_date);
                                        const isUrgent = days !== null && days <= 7;
                                        return (
                                            <button
                                                key={emp.id}
                                                onClick={() => navigate(`/employees/${emp.id}`)}
                                                className="group flex w-full items-center justify-between rounded-lg px-2 py-2 text-left transition-colors hover:bg-slate-50"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-xs font-medium text-slate-700 group-hover:text-blue-600">
                                                        {emp.full_name}
                                                    </p>
                                                    <p className="truncate text-[11px] text-slate-400">
                                                        {emp.position?.title || 'N/A'}
                                                        {emp.department?.name && ` - ${emp.department.name}`}
                                                    </p>
                                                </div>
                                                <div className="ml-2 flex items-center gap-2">
                                                    <Badge
                                                        variant={isUrgent ? 'destructive' : 'warning'}
                                                        className="text-[10px]"
                                                    >
                                                        {days !== null
                                                            ? days <= 0 ? 'Overdue' : `${days}d left`
                                                            : 'N/A'}
                                                    </Badge>
                                                    <ChevronRight className="h-3.5 w-3.5 text-slate-300 group-hover:text-blue-500" />
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Recent Activity */}
                {activityLoading ? (
                    <SkeletonCard lines={5} />
                ) : (
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <div className="flex h-6 w-6 items-center justify-center rounded-md bg-slate-100">
                                    <RefreshCw className="h-3.5 w-3.5 text-slate-500" />
                                </div>
                                Recent Activity
                            </CardTitle>
                            <CardDescription className="mt-0.5">Latest HR system changes</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {activityData.length === 0 ? (
                                <div className="flex flex-col items-center py-6 text-center">
                                    <RefreshCw className="mb-1.5 h-7 w-7 text-slate-200" />
                                    <p className="text-xs text-slate-400">No recent activity</p>
                                </div>
                            ) : (
                                <div className="relative space-y-3">
                                    <div className="absolute bottom-0 left-[15px] top-0 w-px bg-slate-100" />
                                    {activityData.slice(0, 8).map((activity) => {
                                        const config = getChangeConfig(activity.change_type);
                                        const Icon = config.icon;
                                        return (
                                            <div key={activity.id} className="relative flex gap-3 pl-0">
                                                <div className={cn(
                                                    'relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                                                    config.bg
                                                )}>
                                                    <Icon className={cn('h-3.5 w-3.5', config.color)} />
                                                </div>
                                                <div className="min-w-0 flex-1 pt-0.5">
                                                    <div className="flex items-baseline justify-between gap-2">
                                                        <p className="truncate text-xs font-medium text-slate-700">
                                                            {activity.employee?.full_name || 'Unknown'}
                                                        </p>
                                                        <span className="shrink-0 text-[10px] text-slate-400">
                                                            {formatRelativeDate(activity.effective_date || activity.created_at)}
                                                        </span>
                                                    </div>
                                                    <p className="text-[11px] text-slate-400">
                                                        {activity.field_name && `${activity.field_name.replace(/_/g, ' ')}: `}
                                                        {activity.old_value && (
                                                            <span className="line-through">{activity.old_value}</span>
                                                        )}
                                                        {activity.old_value && activity.new_value && ' '}
                                                        {activity.new_value && (
                                                            <span className="font-medium text-slate-600">{activity.new_value}</span>
                                                        )}
                                                        {activity.remarks && (
                                                            <span> &mdash; {activity.remarks}</span>
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </div>
    );
}
