import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    BarChart,
    Bar,
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
} from 'recharts';
import {
    TrendingUp,
    Building2,
    Clock,
    Award,
    Timer,
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
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Badge } from '../../components/ui/badge';
import PageHeader from '../../components/PageHeader';
import { cn } from '../../lib/utils';
import {
    fetchAnalyticsTrends,
    fetchAnalyticsDepartment,
    fetchAnalyticsPunctuality,
    fetchAnalyticsOvertime,
    fetchDepartments,
} from '../../lib/api';

function CustomTooltip({ active, payload, label }) {
    if (!active || !payload?.length) {
        return null;
    }
    return (
        <div className="rounded-lg border border-zinc-200 bg-white px-3 py-2 shadow-lg">
            <p className="text-sm font-medium text-zinc-900">{label}</p>
            {payload.map((entry) => (
                <p key={entry.name} className="text-sm" style={{ color: entry.color }}>
                    {entry.name}: {entry.value}{typeof entry.value === 'number' && entry.value <= 100 ? '%' : ''}
                </p>
            ))}
        </div>
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

function SkeletonTable() {
    return (
        <Card>
            <CardHeader>
                <div className="h-5 w-40 animate-pulse rounded bg-zinc-200" />
            </CardHeader>
            <CardContent>
                <div className="space-y-3">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <div key={i} className="flex items-center gap-4">
                            <div className="h-4 w-8 animate-pulse rounded bg-zinc-200" />
                            <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                            <div className="flex-1" />
                            <div className="h-4 w-16 animate-pulse rounded bg-zinc-200" />
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

export default function AttendanceAnalytics() {
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

    const [dateFrom, setDateFrom] = useState(thirtyDaysAgo.toISOString().slice(0, 10));
    const [dateTo, setDateTo] = useState(today.toISOString().slice(0, 10));
    const [departmentFilter, setDepartmentFilter] = useState('all');

    const filters = {
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
    };

    const { data: trendsData, isLoading: trendsLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'analytics', 'trends', filters],
        queryFn: () => fetchAnalyticsTrends(filters),
    });

    const { data: deptData, isLoading: deptLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'analytics', 'department', filters],
        queryFn: () => fetchAnalyticsDepartment(filters),
    });

    const { data: punctualityData, isLoading: punctualityLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'analytics', 'punctuality', filters],
        queryFn: () => fetchAnalyticsPunctuality(filters),
    });

    const { data: otData, isLoading: otLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'analytics', 'overtime', filters],
        queryFn: () => fetchAnalyticsOvertime(filters),
    });

    const { data: departmentsRes } = useQuery({
        queryKey: ['hr', 'departments'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const trends = trendsData?.data || [];
    const departments = deptData?.data || [];
    const punctuality = punctualityData?.data || {};
    const overtimeStats = otData?.data || [];
    const departmentsList = departmentsRes?.data || [];

    const topLateEmployees = punctuality.top_late || [];
    const punctualityRanking = punctuality.ranking || [];

    return (
        <div className="space-y-6">
            <PageHeader
                title="Attendance Analytics"
                description="Detailed attendance analytics and insights"
            />

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div>
                            <Label className="mb-1 block text-xs text-zinc-500">From</Label>
                            <Input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="w-40"
                            />
                        </div>
                        <div>
                            <Label className="mb-1 block text-xs text-zinc-500">To</Label>
                            <Input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-40"
                            />
                        </div>
                        <div className="w-48">
                            <Select value={departmentFilter} onValueChange={setDepartmentFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Department" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Departments</SelectItem>
                                    {departmentsList.map((dept) => (
                                        <SelectItem key={dept.id} value={String(dept.id)}>
                                            {dept.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Attendance Rate by Department */}
            {deptLoading ? (
                <SkeletonChart />
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-4 w-4 text-blue-500" />
                            Attendance Rate by Department
                        </CardTitle>
                        <CardDescription>Average attendance rate per department</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {departments.length === 0 ? (
                            <div className="flex h-[300px] items-center justify-center text-sm text-zinc-400">
                                No department data available
                            </div>
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart
                                    data={departments}
                                    layout="vertical"
                                    margin={{ top: 5, right: 30, left: 20, bottom: 5 }}
                                >
                                    <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="#e4e4e7" />
                                    <XAxis type="number" domain={[0, 100]} unit="%" tick={{ fontSize: 12, fill: '#71717a' }} />
                                    <YAxis type="category" dataKey="name" width={120} tick={{ fontSize: 11, fill: '#71717a' }} />
                                    <Tooltip content={<CustomTooltip />} />
                                    <Bar dataKey="attendance_rate" name="Attendance %" fill="#2563eb" radius={[0, 4, 4, 0]} barSize={20} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Monthly Trend */}
            {trendsLoading ? (
                <SkeletonChart />
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <TrendingUp className="h-4 w-4 text-emerald-500" />
                            Monthly Attendance Trend
                        </CardTitle>
                        <CardDescription>Attendance and late rates over time</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {trends.length === 0 ? (
                            <div className="flex h-[300px] items-center justify-center text-sm text-zinc-400">
                                No trend data available
                            </div>
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <LineChart data={trends} margin={{ top: 5, right: 30, left: 0, bottom: 5 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
                                    <XAxis dataKey="month" tick={{ fontSize: 11, fill: '#71717a' }} />
                                    <YAxis tick={{ fontSize: 12, fill: '#71717a' }} domain={[0, 100]} unit="%" />
                                    <Tooltip content={<CustomTooltip />} />
                                    <Legend />
                                    <Line type="monotone" dataKey="attendance_rate" name="Attendance %" stroke="#2563eb" strokeWidth={2} dot={{ r: 3 }} />
                                    <Line type="monotone" dataKey="late_rate" name="Late %" stroke="#f59e0b" strokeWidth={2} dot={{ r: 3 }} />
                                </LineChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Bottom Row: Tables */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Top Late Employees */}
                {punctualityLoading ? (
                    <SkeletonTable />
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Clock className="h-4 w-4 text-amber-500" />
                                Top Late Employees
                            </CardTitle>
                            <CardDescription>Employees with the most late arrivals</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {topLateEmployees.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <Clock className="mb-2 h-8 w-8 text-zinc-300" />
                                    <p className="text-sm text-zinc-400">No late records found</p>
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-10">#</TableHead>
                                            <TableHead>Employee</TableHead>
                                            <TableHead>Department</TableHead>
                                            <TableHead>Late Count</TableHead>
                                            <TableHead>Total Late Min</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {topLateEmployees.map((emp, idx) => (
                                            <TableRow key={emp.employee_id}>
                                                <TableCell className="text-sm font-medium text-zinc-500">
                                                    {idx + 1}
                                                </TableCell>
                                                <TableCell>
                                                    <p className="text-sm font-medium text-zinc-900">{emp.full_name}</p>
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-600">
                                                    {emp.department || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <span className="font-medium text-amber-600">{emp.late_count}</span>
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-600">
                                                    {emp.total_late_minutes} min
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Punctuality Ranking */}
                {punctualityLoading ? (
                    <SkeletonTable />
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Award className="h-4 w-4 text-emerald-500" />
                                Punctuality Ranking
                            </CardTitle>
                            <CardDescription>Most punctual employees</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {punctualityRanking.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <Award className="mb-2 h-8 w-8 text-zinc-300" />
                                    <p className="text-sm text-zinc-400">No data available</p>
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-10">#</TableHead>
                                            <TableHead>Employee</TableHead>
                                            <TableHead>Department</TableHead>
                                            <TableHead>On-time Rate</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {punctualityRanking.map((emp, idx) => (
                                            <TableRow key={emp.employee_id}>
                                                <TableCell>
                                                    {idx < 3 ? (
                                                        <span className={cn(
                                                            'inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold',
                                                            idx === 0 && 'bg-yellow-100 text-yellow-700',
                                                            idx === 1 && 'bg-zinc-200 text-zinc-700',
                                                            idx === 2 && 'bg-orange-100 text-orange-700'
                                                        )}>
                                                            {idx + 1}
                                                        </span>
                                                    ) : (
                                                        <span className="text-sm text-zinc-500">{idx + 1}</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <p className="text-sm font-medium text-zinc-900">{emp.full_name}</p>
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-600">
                                                    {emp.department || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <span className={cn(
                                                        'text-sm font-medium',
                                                        emp.on_time_rate >= 95 ? 'text-emerald-600' :
                                                        emp.on_time_rate >= 80 ? 'text-amber-600' : 'text-red-600'
                                                    )}>
                                                        {emp.on_time_rate}%
                                                    </span>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* OT Summary by Department */}
            {otLoading ? (
                <SkeletonChart />
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Timer className="h-4 w-4 text-purple-500" />
                            Overtime Summary by Department
                        </CardTitle>
                        <CardDescription>Total overtime hours per department</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {overtimeStats.length === 0 ? (
                            <div className="flex h-[250px] items-center justify-center text-sm text-zinc-400">
                                No overtime data available
                            </div>
                        ) : (
                            <ResponsiveContainer width="100%" height={250}>
                                <BarChart data={overtimeStats} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
                                    <XAxis dataKey="name" tick={{ fontSize: 11, fill: '#71717a' }} />
                                    <YAxis tick={{ fontSize: 12, fill: '#71717a' }} />
                                    <Tooltip content={<CustomTooltip />} />
                                    <Legend />
                                    <Bar dataKey="total_hours" name="Total OT Hours" fill="#7c3aed" radius={[4, 4, 0, 0]} barSize={32} />
                                    <Bar dataKey="request_count" name="Requests" fill="#a78bfa" radius={[4, 4, 0, 0]} barSize={32} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
