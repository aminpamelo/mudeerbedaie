import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    RadialBarChart,
    RadialBar,
    LineChart,
    Line,
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
} from 'recharts';
import {
    UserCheck,
    Clock,
    UserX,
    Home,
    CalendarOff,
    RefreshCw,
    TrendingDown,
    Building2,
} from 'lucide-react';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
} from '../../components/ui/card';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import PageHeader from '../../components/PageHeader';
import { cn } from '../../lib/utils';
import {
    fetchAnalyticsOverview,
    fetchAnalyticsTrends,
    fetchAnalyticsDepartment,
    fetchTodayAttendance,
} from '../../lib/api';

function formatTime(timeString) {
    if (!timeString) {
        return '-';
    }
    return timeString.slice(0, 5);
}

const STATUS_COLORS = {
    present: { label: 'Present', bg: 'bg-emerald-100', text: 'text-emerald-800' },
    late: { label: 'Late', bg: 'bg-amber-100', text: 'text-amber-800' },
    absent: { label: 'Absent', bg: 'bg-red-100', text: 'text-red-800' },
    wfh: { label: 'WFH', bg: 'bg-blue-100', text: 'text-blue-800' },
    on_leave: { label: 'On Leave', bg: 'bg-purple-100', text: 'text-purple-800' },
    half_day: { label: 'Half Day', bg: 'bg-orange-100', text: 'text-orange-800' },
};

function AttendanceStatusBadge({ status }) {
    const config = STATUS_COLORS[status] || { label: status, bg: 'bg-zinc-100', text: 'text-zinc-800' };
    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', config.bg, config.text)}>
            {config.label}
        </span>
    );
}

function SkeletonCard() {
    return (
        <Card>
            <CardContent className="p-6">
                <div className="flex items-center justify-between">
                    <div className="space-y-3">
                        <div className="h-3 w-24 animate-pulse rounded bg-zinc-200" />
                        <div className="h-8 w-16 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-12 w-12 animate-pulse rounded-lg bg-zinc-200" />
                </div>
            </CardContent>
        </Card>
    );
}

function SkeletonChart() {
    return (
        <Card>
            <CardHeader>
                <div className="h-5 w-40 animate-pulse rounded bg-zinc-200" />
                <div className="h-3 w-56 animate-pulse rounded bg-zinc-200" />
            </CardHeader>
            <CardContent>
                <div className="h-[300px] animate-pulse rounded bg-zinc-100" />
            </CardContent>
        </Card>
    );
}

function StatCard({ title, value, icon: Icon, iconColor, iconBg, subtitle }) {
    return (
        <Card className="transition-shadow hover:shadow-md">
            <CardContent className="p-6">
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <p className="text-sm font-medium text-zinc-500">{title}</p>
                        <p className="text-3xl font-bold tracking-tight text-zinc-900">{value}</p>
                        {subtitle && <p className="text-xs text-zinc-400">{subtitle}</p>}
                    </div>
                    <div className={cn('flex h-12 w-12 items-center justify-center rounded-lg', iconBg)}>
                        <Icon className={cn('h-6 w-6', iconColor)} />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function CustomTooltip({ active, payload, label }) {
    if (!active || !payload?.length) {
        return null;
    }
    return (
        <div className="rounded-lg border border-zinc-200 bg-white px-3 py-2 shadow-lg">
            <p className="text-sm font-medium text-zinc-900">{label}</p>
            {payload.map((entry) => (
                <p key={entry.name} className="text-sm text-zinc-500">
                    {entry.name}: {entry.value}
                </p>
            ))}
        </div>
    );
}

export default function AttendanceDashboard() {
    const [autoRefresh, setAutoRefresh] = useState(true);

    const { data: overview, isLoading: overviewLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'overview'],
        queryFn: fetchAnalyticsOverview,
        refetchInterval: autoRefresh ? 60000 : false,
    });

    const { data: trends, isLoading: trendsLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'trends'],
        queryFn: fetchAnalyticsTrends,
    });

    const { data: departmentData, isLoading: deptLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'department'],
        queryFn: fetchAnalyticsDepartment,
    });

    const { data: todayData, isLoading: todayLoading, refetch: refetchToday } = useQuery({
        queryKey: ['hr', 'attendance', 'today'],
        queryFn: fetchTodayAttendance,
        refetchInterval: autoRefresh ? 60000 : false,
    });

    const stats = overview?.data || {};
    const trendData = trends?.data || [];
    const deptChartData = departmentData?.data || [];
    const todayRecords = todayData?.data || [];

    const attendanceRate = stats.attendance_rate ?? 0;
    const gaugeData = [
        { name: 'Rate', value: attendanceRate, fill: attendanceRate >= 90 ? '#10b981' : attendanceRate >= 75 ? '#f59e0b' : '#ef4444' },
    ];

    return (
        <div className="space-y-6">
            <PageHeader
                title="Attendance Dashboard"
                description="Real-time overview of today's attendance and trends"
                action={
                    <Button
                        variant={autoRefresh ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setAutoRefresh(!autoRefresh)}
                    >
                        <RefreshCw className={cn('mr-2 h-4 w-4', autoRefresh && 'animate-spin')} />
                        {autoRefresh ? 'Auto-refresh ON' : 'Auto-refresh OFF'}
                    </Button>
                }
            />

            {/* Stats Cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                {overviewLoading ? (
                    <>
                        <SkeletonCard />
                        <SkeletonCard />
                        <SkeletonCard />
                        <SkeletonCard />
                        <SkeletonCard />
                    </>
                ) : (
                    <>
                        <StatCard
                            title="Present"
                            value={stats.present ?? 0}
                            subtitle="on time today"
                            icon={UserCheck}
                            iconColor="text-emerald-600"
                            iconBg="bg-emerald-50"
                        />
                        <StatCard
                            title="Late"
                            value={stats.late ?? 0}
                            subtitle="arrived late"
                            icon={Clock}
                            iconColor="text-amber-600"
                            iconBg="bg-amber-50"
                        />
                        <StatCard
                            title="Absent"
                            value={stats.absent ?? 0}
                            subtitle="not checked in"
                            icon={UserX}
                            iconColor="text-red-600"
                            iconBg="bg-red-50"
                        />
                        <StatCard
                            title="WFH"
                            value={stats.wfh ?? 0}
                            subtitle="working from home"
                            icon={Home}
                            iconColor="text-blue-600"
                            iconBg="bg-blue-50"
                        />
                        <StatCard
                            title="On Leave"
                            value={stats.on_leave ?? 0}
                            subtitle="approved leave"
                            icon={CalendarOff}
                            iconColor="text-purple-600"
                            iconBg="bg-purple-50"
                        />
                    </>
                )}
            </div>

            {/* Charts Row */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Attendance Rate Gauge */}
                {overviewLoading ? (
                    <SkeletonChart />
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Attendance Rate</CardTitle>
                            <CardDescription>Today's overall attendance rate</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col items-center">
                                <ResponsiveContainer width="100%" height={200}>
                                    <RadialBarChart
                                        cx="50%"
                                        cy="50%"
                                        innerRadius="60%"
                                        outerRadius="90%"
                                        startAngle={180}
                                        endAngle={0}
                                        data={gaugeData}
                                    >
                                        <RadialBar
                                            background
                                            dataKey="value"
                                            cornerRadius={10}
                                        />
                                    </RadialBarChart>
                                </ResponsiveContainer>
                                <p className="-mt-16 text-4xl font-bold text-zinc-900">
                                    {attendanceRate}%
                                </p>
                                <p className="mt-1 text-sm text-zinc-500">
                                    {attendanceRate >= 90 ? 'Excellent' : attendanceRate >= 75 ? 'Good' : 'Needs Improvement'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Late Trend Chart */}
                {trendsLoading ? (
                    <SkeletonChart />
                ) : (
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <TrendingDown className="h-4 w-4 text-amber-500" />
                                Late Arrivals Trend
                            </CardTitle>
                            <CardDescription>Number of late arrivals over the last 30 days</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {trendData.length === 0 ? (
                                <div className="flex h-[250px] items-center justify-center text-sm text-zinc-400">
                                    No trend data available
                                </div>
                            ) : (
                                <ResponsiveContainer width="100%" height={250}>
                                    <LineChart data={trendData} margin={{ top: 5, right: 30, left: 0, bottom: 5 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
                                        <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#71717a' }} />
                                        <YAxis tick={{ fontSize: 12, fill: '#71717a' }} allowDecimals={false} />
                                        <Tooltip content={<CustomTooltip />} />
                                        <Line type="monotone" dataKey="late_count" name="Late" stroke="#f59e0b" strokeWidth={2} dot={false} />
                                        <Line type="monotone" dataKey="present_count" name="Present" stroke="#10b981" strokeWidth={2} dot={false} />
                                    </LineChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Department Comparison */}
            {deptLoading ? (
                <SkeletonChart />
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-4 w-4 text-blue-500" />
                            Department Attendance Comparison
                        </CardTitle>
                        <CardDescription>Attendance rate by department for today</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {deptChartData.length === 0 ? (
                            <div className="flex h-[300px] items-center justify-center text-sm text-zinc-400">
                                No department data available
                            </div>
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart data={deptChartData} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
                                    <XAxis dataKey="name" tick={{ fontSize: 11, fill: '#71717a' }} />
                                    <YAxis tick={{ fontSize: 12, fill: '#71717a' }} domain={[0, 100]} unit="%" />
                                    <Tooltip content={<CustomTooltip />} />
                                    <Legend />
                                    <Bar dataKey="attendance_rate" name="Attendance %" fill="#2563eb" radius={[4, 4, 0, 0]} barSize={32} />
                                    <Bar dataKey="late_rate" name="Late %" fill="#f59e0b" radius={[4, 4, 0, 0]} barSize={32} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Today's Attendance Table */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle>Today's Attendance</CardTitle>
                            <CardDescription>Real-time attendance log for today</CardDescription>
                        </div>
                        <Button variant="outline" size="sm" onClick={() => refetchToday()}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Refresh
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    {todayLoading ? (
                        <div className="space-y-3">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 px-4 py-3">
                                    <div className="h-9 w-9 animate-pulse rounded-full bg-zinc-200" />
                                    <div className="flex-1 space-y-2">
                                        <div className="h-4 w-48 animate-pulse rounded bg-zinc-200" />
                                        <div className="h-3 w-32 animate-pulse rounded bg-zinc-200" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : todayRecords.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <UserCheck className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No attendance records yet</p>
                            <p className="text-xs text-zinc-400">Records will appear as employees clock in</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Clock In</TableHead>
                                    <TableHead>Clock Out</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Late (min)</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {todayRecords.map((record) => (
                                    <TableRow key={record.id}>
                                        <TableCell>
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {record.employee?.full_name || 'Unknown'}
                                                </p>
                                                <p className="text-xs text-zinc-500">
                                                    {record.employee?.employee_id || ''}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {record.employee?.department?.name || '-'}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-900">
                                            {formatTime(record.clock_in)}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-900">
                                            {formatTime(record.clock_out)}
                                        </TableCell>
                                        <TableCell>
                                            <AttendanceStatusBadge status={record.status} />
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {record.late_minutes > 0 ? (
                                                <span className="font-medium text-amber-600">{record.late_minutes}</span>
                                            ) : (
                                                '-'
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
