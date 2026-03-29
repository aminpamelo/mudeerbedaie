import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    AlertTriangle,
    FileWarning,
    Clock,
    BarChart3,
    Plus,
    Eye,
    Loader2,
} from 'lucide-react';
import {
    fetchDisciplinaryDashboard,
    fetchDisciplinaryActions,
} from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import { cn } from '../../lib/utils';

const STATUS_CONFIG = {
    draft: { label: 'Draft', bg: 'bg-zinc-100', text: 'text-zinc-700' },
    issued: { label: 'Issued', bg: 'bg-blue-100', text: 'text-blue-700' },
    pending_response: { label: 'Pending Response', bg: 'bg-amber-100', text: 'text-amber-700' },
    responded: { label: 'Responded', bg: 'bg-purple-100', text: 'text-purple-700' },
    closed: { label: 'Closed', bg: 'bg-zinc-100', text: 'text-zinc-700' },
};

const TYPE_LABELS = {
    verbal_warning: 'Verbal Warning',
    first_written: '1st Written Warning',
    second_written: '2nd Written Warning',
    show_cause: 'Show Cause',
    termination: 'Termination',
};

function StatusBadge({ status }) {
    const config = STATUS_CONFIG[status] || { label: status, bg: 'bg-zinc-100', text: 'text-zinc-700' };
    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', config.bg, config.text)}>
            {config.label}
        </span>
    );
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function SummaryCard({ title, value, icon: Icon, iconColor, iconBg }) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center gap-3">
                    <div className={cn('flex h-10 w-10 items-center justify-center rounded-lg', iconBg)}>
                        <Icon className={cn('h-5 w-5', iconColor)} />
                    </div>
                    <div>
                        <p className="text-xs font-medium text-zinc-500">{title}</p>
                        <p className="text-lg font-bold text-zinc-900">{value}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function DisciplinaryDashboard() {
    const { data: dashboardData, isLoading: dashboardLoading } = useQuery({
        queryKey: ['hr', 'disciplinary', 'dashboard'],
        queryFn: fetchDisciplinaryDashboard,
    });

    const { data: recentData, isLoading: recentLoading } = useQuery({
        queryKey: ['hr', 'disciplinary', 'actions', 'recent'],
        queryFn: () => fetchDisciplinaryActions({ per_page: 10, sort: '-created_at' }),
    });

    const stats = dashboardData?.data || {};
    const recentCases = recentData?.data || [];

    if (dashboardLoading) {
        return (
            <div className="flex items-center justify-center py-24">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    return (
        <div>
            <PageHeader
                title="Disciplinary Management"
                description="Manage employee disciplinary actions, warnings, and inquiries."
                action={
                    <Link to="/disciplinary/actions/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            New Action
                        </Button>
                    </Link>
                }
            />

            {/* Stats Cards */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <SummaryCard
                    title="Active Cases"
                    value={stats.active_cases ?? 0}
                    icon={AlertTriangle}
                    iconColor="text-red-600"
                    iconBg="bg-red-50"
                />
                <SummaryCard
                    title="Warnings This Month"
                    value={stats.warnings_this_month ?? 0}
                    icon={FileWarning}
                    iconColor="text-amber-600"
                    iconBg="bg-amber-50"
                />
                <SummaryCard
                    title="Pending Responses"
                    value={stats.pending_responses ?? 0}
                    icon={Clock}
                    iconColor="text-blue-600"
                    iconBg="bg-blue-50"
                />
                <SummaryCard
                    title="Cases by Type"
                    value={stats.total_cases ?? 0}
                    icon={BarChart3}
                    iconColor="text-purple-600"
                    iconBg="bg-purple-50"
                />
            </div>

            {/* Cases by Type Breakdown */}
            {stats.by_type && Object.keys(stats.by_type).length > 0 && (
                <Card className="mb-6">
                    <CardContent className="p-5">
                        <h3 className="mb-3 text-sm font-semibold text-zinc-900">Cases by Type</h3>
                        <div className="flex flex-wrap gap-3">
                            {Object.entries(stats.by_type).map(([type, count]) => (
                                <div key={type} className="flex items-center gap-2 rounded-lg bg-zinc-50 px-3 py-2">
                                    <span className="text-sm text-zinc-600">{TYPE_LABELS[type] || type}</span>
                                    <span className="rounded-full bg-zinc-200 px-2 py-0.5 text-xs font-bold text-zinc-700">{count}</span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Recent Cases */}
            <Card>
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-base font-semibold text-zinc-900">Recent Cases</h3>
                        <Link to="/disciplinary/records">
                            <Button variant="outline" size="sm">View All</Button>
                        </Link>
                    </div>

                    {recentLoading ? (
                        <div className="flex justify-center py-12">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : recentCases.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <AlertTriangle className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No disciplinary cases found</p>
                            <p className="text-xs text-zinc-400">Create a new disciplinary action to get started</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Reference #</TableHead>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Incident Date</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentCases.map((action) => (
                                    <TableRow key={action.id}>
                                        <TableCell className="font-medium text-zinc-900">
                                            {action.reference_number || '-'}
                                        </TableCell>
                                        <TableCell>
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {action.employee?.full_name || '-'}
                                                </p>
                                                <p className="text-xs text-zinc-500">
                                                    {action.employee?.employee_id || ''}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {TYPE_LABELS[action.type] || action.type}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={action.status} />
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {formatDate(action.incident_date)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Link to={`/disciplinary/actions/${action.id}`}>
                                                <Button variant="ghost" size="sm">
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                            </Link>
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
