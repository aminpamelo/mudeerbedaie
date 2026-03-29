import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    ClipboardList,
    Users,
    Loader2,
} from 'lucide-react';
import { fetchOnboardingDashboard, assignOnboarding, fetchEmployees } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '../../components/ui/dialog';
import { Label } from '../../components/ui/label';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';

function ProgressBar({ value, total }) {
    const pct = total > 0 ? Math.round((value / total) * 100) : 0;
    const color =
        pct >= 100 ? 'bg-emerald-500' : pct >= 50 ? 'bg-blue-500' : 'bg-amber-400';

    return (
        <div className="flex items-center gap-3">
            <div className="flex-1 overflow-hidden rounded-full bg-zinc-100">
                <div
                    className={cn('h-2 rounded-full transition-all duration-500', color)}
                    style={{ width: `${pct}%` }}
                />
            </div>
            <span className="w-20 shrink-0 text-right text-xs text-zinc-500">
                {value}/{total} ({pct}%)
            </span>
        </div>
    );
}

function SkeletonTable() {
    return (
        <div className="space-y-3 p-4">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 py-2">
                    <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1">
                        <div className="h-2 animate-pulse rounded-full bg-zinc-200" />
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function OnboardingDashboard() {
    const queryClient = useQueryClient();
    const [assignDialog, setAssignDialog] = useState(false);
    const [assignForm, setAssignForm] = useState({ employee_id: '', template_id: '' });

    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'recruitment', 'onboarding', 'dashboard'],
        queryFn: fetchOnboardingDashboard,
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', { status: 'active', per_page: 200 }],
        queryFn: () => fetchEmployees({ per_page: 200, status: 'active' }),
    });

    const assignMutation = useMutation({
        mutationFn: assignOnboarding,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'onboarding'] });
            setAssignDialog(false);
            setAssignForm({ employee_id: '', template_id: '' });
        },
    });

    const onboardingList = data?.data || [];
    const templates = data?.templates || [];
    const employees = employeesData?.data || [];

    const inProgress = onboardingList.filter((o) => o.completed_tasks < o.total_tasks).length;
    const completed = onboardingList.filter((o) => o.total_tasks > 0 && o.completed_tasks >= o.total_tasks).length;

    if (isError) {
        return (
            <div>
                <PageHeader title="Onboarding" description="Track new employee onboarding progress." />
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <p className="text-sm font-medium text-red-600">Failed to load onboarding data.</p>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <div>
            <PageHeader
                title="Onboarding"
                description="Track new employee onboarding progress."
                action={
                    <Button onClick={() => setAssignDialog(true)}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Assign Onboarding
                    </Button>
                }
            />

            {/* Summary Cards */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50">
                                <Users className="h-6 w-6 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-sm text-zinc-500">Total Onboarding</p>
                                <p className="text-2xl font-bold text-zinc-900">{onboardingList.length}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-50">
                                <ClipboardList className="h-6 w-6 text-amber-600" />
                            </div>
                            <div>
                                <p className="text-sm text-zinc-500">In Progress</p>
                                <p className="text-2xl font-bold text-zinc-900">{inProgress}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-50">
                                <ClipboardList className="h-6 w-6 text-emerald-600" />
                            </div>
                            <div>
                                <p className="text-sm text-zinc-500">Completed</p>
                                <p className="text-2xl font-bold text-zinc-900">{completed}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Onboarding List */}
            {isLoading ? (
                <Card>
                    <SkeletonTable />
                </Card>
            ) : onboardingList.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <ClipboardList className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">No onboarding in progress</h3>
                        <p className="mt-1 text-sm text-zinc-500">
                            Assign onboarding to a newly hired employee to get started.
                        </p>
                        <Button className="mt-4" onClick={() => setAssignDialog(true)}>
                            <Plus className="mr-1.5 h-4 w-4" />
                            Assign Onboarding
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                <TableHead>Template</TableHead>
                                <TableHead>Start Date</TableHead>
                                <TableHead>Progress</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {onboardingList.map((record) => {
                                const pct = record.total_tasks > 0
                                    ? Math.round((record.completed_tasks / record.total_tasks) * 100)
                                    : 0;
                                const isDone = record.total_tasks > 0 && record.completed_tasks >= record.total_tasks;
                                return (
                                    <TableRow key={record.id}>
                                        <TableCell className="font-medium">
                                            {record.employee?.full_name || '-'}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-500">
                                            {record.template?.name || '-'}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-500">
                                            {record.start_date
                                                ? new Date(record.start_date).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' })
                                                : '-'}
                                        </TableCell>
                                        <TableCell className="min-w-48">
                                            <ProgressBar value={record.completed_tasks} total={record.total_tasks} />
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                className={cn(
                                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                                    isDone
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : pct > 0
                                                        ? 'bg-blue-100 text-blue-700'
                                                        : 'bg-zinc-100 text-zinc-600'
                                                )}
                                            >
                                                {isDone ? 'Completed' : pct > 0 ? 'In Progress' : 'Not Started'}
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </Card>
            )}

            {/* Assign Dialog */}
            <Dialog open={assignDialog} onOpenChange={() => setAssignDialog(false)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Assign Onboarding</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <div className="space-y-1.5">
                            <Label>Employee</Label>
                            <Select
                                value={assignForm.employee_id}
                                onValueChange={(v) => setAssignForm((f) => ({ ...f, employee_id: v }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select employee" />
                                </SelectTrigger>
                                <SelectContent>
                                    {employees.map((emp) => (
                                        <SelectItem key={emp.id} value={String(emp.id)}>
                                            {emp.full_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Onboarding Template</Label>
                            <Select
                                value={assignForm.template_id}
                                onValueChange={(v) => setAssignForm((f) => ({ ...f, template_id: v }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select template" />
                                </SelectTrigger>
                                <SelectContent>
                                    {templates.map((tmpl) => (
                                        <SelectItem key={tmpl.id} value={String(tmpl.id)}>
                                            {tmpl.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setAssignDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => assignMutation.mutate(assignForm)}
                            disabled={!assignForm.employee_id || !assignForm.template_id || assignMutation.isPending}
                        >
                            {assignMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Assign
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
