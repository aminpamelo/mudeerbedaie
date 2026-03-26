import { useQuery } from '@tanstack/react-query';
import {
    Phone,
    Mail,
    Calendar,
    Briefcase,
    Loader2,
    AlertCircle,
} from 'lucide-react';
import { fetchMyProfile } from '../../lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import StatusBadge from '../../components/StatusBadge';

function getInitials(name) {
    if (!name) return '?';
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export default function MyProfileOverview() {
    const { data: profileData, isLoading, isError, error } = useQuery({
        queryKey: ['my-profile'],
        queryFn: fetchMyProfile,
    });
    const employee = profileData?.data;

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-20">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (isError) {
        return (
            <div className="flex flex-col items-center justify-center py-20 text-center">
                <AlertCircle className="h-10 w-10 text-red-400 mb-3" />
                <p className="text-sm text-zinc-600">
                    {error?.response?.data?.message || 'Failed to load profile'}
                </p>
            </div>
        );
    }

    const quickInfo = [
        {
            icon: Phone,
            label: 'Phone',
            value: employee.phone || '-',
        },
        {
            icon: Mail,
            label: 'Email',
            value: employee.email || '-',
        },
        {
            icon: Calendar,
            label: 'Join Date',
            value: formatDate(employee.join_date),
        },
        {
            icon: Briefcase,
            label: 'Employment Type',
            value: employee.employment_type_label || '-',
        },
    ];

    return (
        <div className="space-y-4">
            {/* Profile Header Card */}
            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-col items-center text-center">
                        <div className="h-20 w-20 rounded-full bg-zinc-900 text-white flex items-center justify-center text-2xl font-bold mb-3">
                            {getInitials(employee.full_name)}
                        </div>
                        <h2 className="text-xl font-bold text-zinc-900">
                            {employee.full_name}
                        </h2>
                        <p className="text-sm text-zinc-500 mt-0.5">
                            {employee.employee_id}
                        </p>
                        <div className="flex items-center gap-2 mt-2 text-sm text-zinc-600">
                            {employee.department?.name && (
                                <span>{employee.department.name}</span>
                            )}
                            {employee.department?.name && employee.position?.title && (
                                <span className="text-zinc-300">|</span>
                            )}
                            {employee.position?.title && (
                                <span>{employee.position.title}</span>
                            )}
                        </div>
                        <div className="mt-3">
                            <StatusBadge status={employee.status} />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Quick Info Grid */}
            <div className="grid grid-cols-2 gap-3">
                {quickInfo.map((item) => (
                    <Card key={item.label}>
                        <CardContent className="pt-4 pb-4">
                            <div className="flex items-start gap-3">
                                <div className="rounded-lg bg-zinc-100 p-2 shrink-0">
                                    <item.icon className="h-4 w-4 text-zinc-600" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-xs text-zinc-500">{item.label}</p>
                                    <p className="text-sm font-medium text-zinc-900 truncate">
                                        {item.value}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Status Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Employment Status</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center gap-3">
                        <div
                            className={`h-3 w-3 rounded-full shrink-0 ${
                                employee.status === 'active'
                                    ? 'bg-emerald-500'
                                    : employee.status === 'probation'
                                      ? 'bg-amber-500'
                                      : employee.status === 'on_leave'
                                        ? 'bg-blue-500'
                                        : 'bg-zinc-400'
                            }`}
                        />
                        <div>
                            <p className="text-sm font-medium text-zinc-900">
                                {employee.status === 'active'
                                    ? 'Active Employee'
                                    : employee.status === 'probation'
                                      ? 'Probation Period'
                                      : employee.status === 'on_leave'
                                        ? 'Currently On Leave'
                                        : employee.status?.replace('_', ' ')?.replace(/\b\w/g, (c) => c.toUpperCase()) || '-'}
                            </p>
                            {employee.status === 'probation' && employee.probation_end_date && (
                                <p className="text-xs text-zinc-500 mt-0.5">
                                    Probation ends {formatDate(employee.probation_end_date)}
                                </p>
                            )}
                            {employee.join_date && (
                                <p className="text-xs text-zinc-500 mt-0.5">
                                    Joined {formatDate(employee.join_date)}
                                </p>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
