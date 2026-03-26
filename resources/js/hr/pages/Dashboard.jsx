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
    PieChart,
    Pie,
    Cell,
    Legend,
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
} from 'lucide-react';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
} from '../components/ui/card';
import PageHeader from '../components/PageHeader';
import { cn } from '../lib/utils';
import {
    fetchDashboardStats,
    fetchRecentActivity,
    fetchHeadcountByDepartment,
} from '../lib/api';

const PIE_COLORS = ['#2563eb', '#7c3aed', '#f59e0b', '#10b981'];

const EMPLOYMENT_TYPE_LABELS = {
    full_time: 'Full Time',
    part_time: 'Part Time',
    contract: 'Contract',
    intern: 'Intern',
};

const CHANGE_TYPE_CONFIG = {
    status_change: {
        icon: RefreshCw,
        color: 'text-blue-600',
        bg: 'bg-blue-100',
    },
    promotion: {
        icon: TrendingUp,
        color: 'text-green-600',
        bg: 'bg-green-100',
    },
    department_change: {
        icon: Building2,
        color: 'text-purple-600',
        bg: 'bg-purple-100',
    },
    position_change: {
        icon: Briefcase,
        color: 'text-orange-600',
        bg: 'bg-orange-100',
    },
    salary_change: {
        icon: DollarSign,
        color: 'text-emerald-600',
        bg: 'bg-emerald-100',
    },
    termination: {
        icon: UserMinus,
        color: 'text-red-600',
        bg: 'bg-red-100',
    },
    hired: {
        icon: UserPlus,
        color: 'text-sky-600',
        bg: 'bg-sky-100',
    },
    info_update: {
        icon: Pencil,
        color: 'text-zinc-600',
        bg: 'bg-zinc-100',
    },
};

function getChangeTypeConfig(changeType) {
    return (
        CHANGE_TYPE_CONFIG[changeType] || {
            icon: RefreshCw,
            color: 'text-zinc-600',
            bg: 'bg-zinc-100',
        }
    );
}

function formatDate(dateString) {
    if (!dateString) {
        return '';
    }
    const date = new Date(dateString);
    return date.toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatRelativeDate(dateString) {
    if (!dateString) {
        return '';
    }
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return 'Today';
    }
    if (diffDays === 1) {
        return 'Yesterday';
    }
    if (diffDays < 7) {
        return `${diffDays} days ago`;
    }
    return formatDate(dateString);
}

function daysUntil(dateString) {
    if (!dateString) {
        return null;
    }
    const date = new Date(dateString);
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    date.setHours(0, 0, 0, 0);
    return Math.ceil((date - now) / (1000 * 60 * 60 * 24));
}

function SkeletonCard() {
    return (
        <Card>
            <CardContent className="p-6">
                <div className="flex items-center justify-between">
                    <div className="space-y-3">
                        <div className="h-3 w-24 animate-pulse rounded bg-zinc-200" />
                        <div className="h-8 w-16 animate-pulse rounded bg-zinc-200" />
                        <div className="h-3 w-32 animate-pulse rounded bg-zinc-200" />
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

function SkeletonList() {
    return (
        <Card>
            <CardHeader>
                <div className="h-5 w-40 animate-pulse rounded bg-zinc-200" />
                <div className="h-3 w-56 animate-pulse rounded bg-zinc-200" />
            </CardHeader>
            <CardContent>
                <div className="space-y-4">
                    {[1, 2, 3].map((i) => (
                        <div key={i} className="flex items-center gap-3">
                            <div className="h-8 w-8 animate-pulse rounded-full bg-zinc-200" />
                            <div className="flex-1 space-y-2">
                                <div className="h-3 w-32 animate-pulse rounded bg-zinc-200" />
                                <div className="h-3 w-48 animate-pulse rounded bg-zinc-200" />
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function StatCard({ title, value, subtitle, icon: Icon, iconColor, iconBg }) {
    return (
        <Card className="transition-shadow hover:shadow-md">
            <CardContent className="p-6">
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <p className="text-sm font-medium text-zinc-500">
                            {title}
                        </p>
                        <p className="text-3xl font-bold tracking-tight text-zinc-900">
                            {value}
                        </p>
                        <p className="text-xs text-zinc-400">{subtitle}</p>
                    </div>
                    <div
                        className={cn(
                            'flex h-12 w-12 items-center justify-center rounded-lg',
                            iconBg
                        )}
                    >
                        <Icon className={cn('h-6 w-6', iconColor)} />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function CustomBarTooltip({ active, payload, label }) {
    if (!active || !payload?.length) {
        return null;
    }
    return (
        <div className="rounded-lg border border-zinc-200 bg-white px-3 py-2 shadow-lg">
            <p className="text-sm font-medium text-zinc-900">{label}</p>
            <p className="text-sm text-zinc-500">
                {payload[0].value} employee{payload[0].value !== 1 ? 's' : ''}
            </p>
        </div>
    );
}

function CustomPieTooltip({ active, payload }) {
    if (!active || !payload?.length) {
        return null;
    }
    return (
        <div className="rounded-lg border border-zinc-200 bg-white px-3 py-2 shadow-lg">
            <p className="text-sm font-medium text-zinc-900">
                {payload[0].name}
            </p>
            <p className="text-sm text-zinc-500">
                {payload[0].value} employee{payload[0].value !== 1 ? 's' : ''}
            </p>
        </div>
    );
}

function CustomPieLegend({ payload }) {
    return (
        <div className="flex flex-wrap justify-center gap-x-4 gap-y-1 pt-2">
            {payload?.map((entry) => (
                <div key={entry.value} className="flex items-center gap-1.5">
                    <div
                        className="h-2.5 w-2.5 rounded-full"
                        style={{ backgroundColor: entry.color }}
                    />
                    <span className="text-xs text-zinc-600">{entry.value}</span>
                </div>
            ))}
        </div>
    );
}

export default function Dashboard() {
    const navigate = useNavigate();

    const { data: stats, isLoading: statsLoading } = useQuery({
        queryKey: ['hr', 'dashboard', 'stats'],
        queryFn: fetchDashboardStats,
    });

    const { data: recentActivity, isLoading: activityLoading } = useQuery({
        queryKey: ['hr', 'dashboard', 'recent-activity'],
        queryFn: fetchRecentActivity,
    });

    const { data: headcount, isLoading: headcountLoading } = useQuery({
        queryKey: ['hr', 'dashboard', 'headcount'],
        queryFn: fetchHeadcountByDepartment,
    });

    const statsData = stats?.data;
    const activityData = recentActivity?.data || [];
    const headcountData = headcount?.data || [];

    const currentMonth = new Date().toLocaleDateString('en-MY', {
        month: 'long',
        year: 'numeric',
    });

    // Build employment type breakdown from stats if available
    const employmentTypeData = statsData?.employment_type_breakdown
        ? Object.entries(statsData.employment_type_breakdown).map(
              ([key, value]) => ({
                  name: EMPLOYMENT_TYPE_LABELS[key] || key,
                  value,
              })
          )
        : [
              { name: 'Full Time', value: statsData?.total_employees || 0 },
              { name: 'Part Time', value: 0 },
              { name: 'Contract', value: 0 },
              { name: 'Intern', value: 0 },
          ];

    const probationEndingSoon = statsData?.probation_ending_soon || [];

    return (
        <div className="space-y-6">
            <PageHeader
                title="HR Dashboard"
                description="Overview of your workforce and recent HR activities"
            />

            {/* Stats Cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {statsLoading ? (
                    <>
                        <SkeletonCard />
                        <SkeletonCard />
                        <SkeletonCard />
                        <SkeletonCard />
                    </>
                ) : (
                    <>
                        <StatCard
                            title="Total Employees"
                            value={statsData?.total_employees ?? 0}
                            subtitle={`${statsData?.active_employees ?? 0} active`}
                            icon={Users}
                            iconColor="text-blue-600"
                            iconBg="bg-blue-50"
                        />
                        <StatCard
                            title="New Hires This Month"
                            value={statsData?.new_hires_this_month ?? 0}
                            subtitle={currentMonth}
                            icon={UserPlus}
                            iconColor="text-green-600"
                            iconBg="bg-green-50"
                        />
                        <StatCard
                            title="On Probation"
                            value={statsData?.on_probation ?? 0}
                            subtitle="pending confirmation"
                            icon={Clock}
                            iconColor="text-amber-600"
                            iconBg="bg-amber-50"
                        />
                        <StatCard
                            title="Departments"
                            value={statsData?.departments_count ?? 0}
                            subtitle="organizational units"
                            icon={Building2}
                            iconColor="text-purple-600"
                            iconBg="bg-purple-50"
                        />
                    </>
                )}
            </div>

            {/* Charts Row */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Department Headcount Chart */}
                {headcountLoading ? (
                    <SkeletonChart />
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Department Headcount</CardTitle>
                            <CardDescription>
                                Number of employees per department
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {headcountData.length === 0 ? (
                                <div className="flex h-[300px] items-center justify-center text-sm text-zinc-400">
                                    No department data available
                                </div>
                            ) : (
                                <ResponsiveContainer width="100%" height={300}>
                                    <BarChart
                                        data={headcountData}
                                        layout="vertical"
                                        margin={{
                                            top: 5,
                                            right: 30,
                                            left: 20,
                                            bottom: 5,
                                        }}
                                    >
                                        <CartesianGrid
                                            strokeDasharray="3 3"
                                            horizontal={false}
                                            stroke="#e4e4e7"
                                        />
                                        <XAxis
                                            type="number"
                                            tick={{ fontSize: 12, fill: '#71717a' }}
                                            allowDecimals={false}
                                        />
                                        <YAxis
                                            type="category"
                                            dataKey="name"
                                            tick={{ fontSize: 12, fill: '#71717a' }}
                                            width={100}
                                        />
                                        <Tooltip
                                            content={<CustomBarTooltip />}
                                        />
                                        <Bar
                                            dataKey="count"
                                            fill="#2563eb"
                                            radius={[0, 4, 4, 0]}
                                            barSize={24}
                                        />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Employment Type Breakdown */}
                {statsLoading ? (
                    <SkeletonChart />
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Employment Type Breakdown</CardTitle>
                            <CardDescription>
                                Distribution by employment type
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {employmentTypeData.every(
                                (d) => d.value === 0
                            ) ? (
                                <div className="flex h-[300px] items-center justify-center text-sm text-zinc-400">
                                    No employee data available
                                </div>
                            ) : (
                                <ResponsiveContainer width="100%" height={300}>
                                    <PieChart>
                                        <Pie
                                            data={employmentTypeData}
                                            cx="50%"
                                            cy="50%"
                                            innerRadius={70}
                                            outerRadius={110}
                                            paddingAngle={3}
                                            dataKey="value"
                                        >
                                            {employmentTypeData.map(
                                                (entry, index) => (
                                                    <Cell
                                                        key={entry.name}
                                                        fill={
                                                            PIE_COLORS[
                                                                index %
                                                                    PIE_COLORS.length
                                                            ]
                                                        }
                                                    />
                                                )
                                            )}
                                        </Pie>
                                        <Tooltip
                                            content={<CustomPieTooltip />}
                                        />
                                        <Legend
                                            content={<CustomPieLegend />}
                                        />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Bottom Row */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Probation Ending Soon */}
                {statsLoading ? (
                    <SkeletonList />
                ) : (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <AlertCircle className="h-4 w-4 text-amber-500" />
                                        Probation Ending Soon
                                    </CardTitle>
                                    <CardDescription>
                                        Employees with probation ending within
                                        30 days
                                    </CardDescription>
                                </div>
                                {probationEndingSoon.length > 0 && (
                                    <span className="inline-flex h-6 items-center rounded-full bg-amber-100 px-2.5 text-xs font-medium text-amber-800">
                                        {probationEndingSoon.length}
                                    </span>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {probationEndingSoon.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <UserCheck className="mb-2 h-8 w-8 text-zinc-300" />
                                    <p className="text-sm text-zinc-400">
                                        No probation periods ending soon
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {probationEndingSoon.map((employee) => {
                                        const days = daysUntil(
                                            employee.probation_end_date
                                        );
                                        const isUrgent =
                                            days !== null && days <= 7;
                                        return (
                                            <button
                                                key={employee.id}
                                                onClick={() =>
                                                    navigate(
                                                        `/employees/${employee.id}`
                                                    )
                                                }
                                                className="group flex w-full items-center justify-between rounded-lg border border-zinc-100 px-4 py-3 text-left transition-colors hover:border-zinc-200 hover:bg-zinc-50"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-zinc-900 group-hover:text-blue-600">
                                                        {employee.full_name}
                                                    </p>
                                                    <p className="truncate text-xs text-zinc-500">
                                                        {employee.position
                                                            ?.title || 'N/A'}
                                                        {employee.department
                                                            ?.name &&
                                                            ` - ${employee.department.name}`}
                                                    </p>
                                                </div>
                                                <div className="ml-3 flex items-center gap-2">
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                            isUrgent
                                                                ? 'bg-red-100 text-red-700'
                                                                : 'bg-amber-100 text-amber-700'
                                                        )}
                                                    >
                                                        {days !== null
                                                            ? days <= 0
                                                                ? 'Overdue'
                                                                : `${days}d left`
                                                            : formatDate(
                                                                  employee.probation_end_date
                                                              )}
                                                    </span>
                                                    <ArrowRight className="h-4 w-4 text-zinc-300 transition-colors group-hover:text-blue-500" />
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
                    <SkeletonList />
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Activity</CardTitle>
                            <CardDescription>
                                Latest changes in the HR system
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {activityData.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <RefreshCw className="mb-2 h-8 w-8 text-zinc-300" />
                                    <p className="text-sm text-zinc-400">
                                        No recent activity
                                    </p>
                                </div>
                            ) : (
                                <div className="relative space-y-4">
                                    {/* Timeline line */}
                                    <div className="absolute bottom-0 left-4 top-0 w-px bg-zinc-200" />

                                    {activityData.slice(0, 10).map((activity) => {
                                        const config = getChangeTypeConfig(
                                            activity.change_type
                                        );
                                        const Icon = config.icon;
                                        return (
                                            <div
                                                key={activity.id}
                                                className="relative flex gap-3 pl-1"
                                            >
                                                <div
                                                    className={cn(
                                                        'relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                                                        config.bg
                                                    )}
                                                >
                                                    <Icon
                                                        className={cn(
                                                            'h-3.5 w-3.5',
                                                            config.color
                                                        )}
                                                    />
                                                </div>
                                                <div className="min-w-0 flex-1 pt-0.5">
                                                    <div className="flex items-baseline justify-between gap-2">
                                                        <p className="truncate text-sm font-medium text-zinc-900">
                                                            {activity.employee
                                                                ?.full_name ||
                                                                'Unknown'}
                                                        </p>
                                                        <span className="shrink-0 text-xs text-zinc-400">
                                                            {formatRelativeDate(
                                                                activity.effective_date ||
                                                                    activity.created_at
                                                            )}
                                                        </span>
                                                    </div>
                                                    <p className="text-xs text-zinc-500">
                                                        {activity.field_name &&
                                                            `${activity.field_name.replace(/_/g, ' ')}: `}
                                                        {activity.old_value && (
                                                            <span className="text-zinc-400 line-through">
                                                                {activity.old_value}
                                                            </span>
                                                        )}
                                                        {activity.old_value &&
                                                            activity.new_value &&
                                                            ' '}
                                                        {activity.new_value && (
                                                            <span className="font-medium text-zinc-700">
                                                                {activity.new_value}
                                                            </span>
                                                        )}
                                                        {activity.remarks && (
                                                            <span className="text-zinc-400">
                                                                {' '}
                                                                &mdash;{' '}
                                                                {
                                                                    activity.remarks
                                                                }
                                                            </span>
                                                        )}
                                                    </p>
                                                    {activity.changed_by_user && (
                                                        <p className="mt-0.5 text-xs text-zinc-400">
                                                            by{' '}
                                                            {
                                                                activity
                                                                    .changed_by_user
                                                                    .name
                                                            }
                                                        </p>
                                                    )}
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
