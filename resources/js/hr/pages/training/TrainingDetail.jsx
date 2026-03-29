import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ArrowLeft,
    UserPlus,
    Plus,
    CheckCircle2,
    XCircle,
    Trash2,
    Loader2,
    GraduationCap,
    DollarSign,
    Users,
    Calendar,
    MapPin,
    Building2,
} from 'lucide-react';
import {
    fetchTrainingProgram,
    fetchTrainingCosts,
    fetchEmployees,
    enrollEmployees,
    updateTrainingEnrollment,
    deleteTrainingEnrollment,
    createTrainingCost,
    deleteTrainingCost,
    completeTrainingProgram,
} from '../../lib/api';
import { cn } from '../../lib/utils';
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
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';

const STATUS_BADGE_CLASS = {
    planned: 'bg-blue-100 text-blue-700',
    ongoing: 'bg-amber-100 text-amber-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-zinc-100 text-zinc-500',
};

const ENROLLMENT_STATUS_BADGE = {
    enrolled: 'bg-blue-100 text-blue-700',
    attended: 'bg-emerald-100 text-emerald-700',
    absent: 'bg-red-100 text-red-700',
    cancelled: 'bg-zinc-100 text-zinc-500',
};

const EMPTY_COST_FORM = {
    description: '',
    amount: '',
    cost_type: 'venue',
};

const COST_TYPE_OPTIONS = [
    { value: 'venue', label: 'Venue' },
    { value: 'trainer', label: 'Trainer Fee' },
    { value: 'material', label: 'Material' },
    { value: 'travel', label: 'Travel' },
    { value: 'food', label: 'Food & Beverage' },
    { value: 'other', label: 'Other' },
];

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatCurrency(amount) {
    if (amount === null || amount === undefined || amount === '') return '-';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

function SkeletonDetail() {
    return (
        <div className="space-y-6">
            <div className="h-32 animate-pulse rounded-lg bg-zinc-200" />
            <div className="h-48 animate-pulse rounded-lg bg-zinc-200" />
        </div>
    );
}

export default function TrainingDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [enrollOpen, setEnrollOpen] = useState(false);
    const [selectedEmployees, setSelectedEmployees] = useState([]);
    const [costFormOpen, setCostFormOpen] = useState(false);
    const [costForm, setCostForm] = useState(EMPTY_COST_FORM);
    const [costErrors, setCostErrors] = useState({});
    const [completeDialogOpen, setCompleteDialogOpen] = useState(false);
    const [deleteEnrollDialog, setDeleteEnrollDialog] = useState({ open: false, enrollment: null });
    const [deleteCostDialog, setDeleteCostDialog] = useState({ open: false, cost: null });

    const { data: programData, isLoading } = useQuery({
        queryKey: ['hr', 'training', 'programs', id],
        queryFn: () => fetchTrainingProgram(id),
        enabled: !!id,
    });

    const { data: costsData, isLoading: costsLoading } = useQuery({
        queryKey: ['hr', 'training', 'programs', id, 'costs'],
        queryFn: () => fetchTrainingCosts(id),
        enabled: !!id,
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'dropdown'],
        queryFn: () => fetchEmployees({ per_page: 200, status: 'active' }),
    });

    const program = programData?.data || programData;
    const enrollments = program?.enrollments || [];
    const costs = costsData?.data || [];
    const employees = employeesData?.data || [];

    const enrolledIds = enrollments.map((e) => e.employee_id);
    const availableEmployees = employees.filter((e) => !enrolledIds.includes(e.id));

    const totalCost = costs.reduce((sum, c) => sum + (parseFloat(c.amount) || 0), 0);

    const enrollMutation = useMutation({
        mutationFn: (data) => enrollEmployees(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'programs', id] });
            setEnrollOpen(false);
            setSelectedEmployees([]);
        },
    });

    const updateEnrollmentMutation = useMutation({
        mutationFn: ({ enrollmentId, data }) => updateTrainingEnrollment(enrollmentId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'programs', id] });
        },
    });

    const deleteEnrollmentMutation = useMutation({
        mutationFn: (enrollmentId) => deleteTrainingEnrollment(enrollmentId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'programs', id] });
            setDeleteEnrollDialog({ open: false, enrollment: null });
        },
    });

    const addCostMutation = useMutation({
        mutationFn: (data) => createTrainingCost(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'programs', id, 'costs'] });
            setCostFormOpen(false);
            setCostForm(EMPTY_COST_FORM);
            setCostErrors({});
        },
        onError: (err) => {
            if (err.response?.data?.errors) {
                setCostErrors(err.response.data.errors);
            }
        },
    });

    const deleteCostMutation = useMutation({
        mutationFn: (costId) => deleteTrainingCost(costId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'programs', id, 'costs'] });
            setDeleteCostDialog({ open: false, cost: null });
        },
    });

    const completeProgramMutation = useMutation({
        mutationFn: () => completeTrainingProgram(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'programs', id] });
            setCompleteDialogOpen(false);
        },
    });

    function handleEnroll(e) {
        e.preventDefault();
        if (selectedEmployees.length === 0) return;
        enrollMutation.mutate({ employee_ids: selectedEmployees });
    }

    function handleToggleEmployee(employeeId) {
        setSelectedEmployees((prev) =>
            prev.includes(employeeId)
                ? prev.filter((eid) => eid !== employeeId)
                : [...prev, employeeId]
        );
    }

    function handleMarkAttendance(enrollment, status) {
        updateEnrollmentMutation.mutate({
            enrollmentId: enrollment.id,
            data: { status },
        });
    }

    function handleAddCost(e) {
        e.preventDefault();
        addCostMutation.mutate({
            ...costForm,
            amount: parseFloat(costForm.amount) || 0,
        });
    }

    if (isLoading) {
        return (
            <div>
                <PageHeader title="Training Program Detail" />
                <SkeletonDetail />
            </div>
        );
    }

    if (!program) {
        return (
            <div>
                <PageHeader title="Training Program Detail" />
                <Card>
                    <CardContent className="p-6">
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <GraduationCap className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">Program not found</p>
                            <Button variant="outline" className="mt-4" onClick={() => navigate('/hr/training/programs')}>
                                <ArrowLeft className="mr-1.5 h-4 w-4" />
                                Back to Programs
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <div>
            <PageHeader
                title={program.title}
                description="Training program details, enrollments, and costs."
                action={
                    <Button variant="outline" onClick={() => navigate('/hr/training/programs')}>
                        <ArrowLeft className="mr-1.5 h-4 w-4" />
                        Back
                    </Button>
                }
            />

            {/* Program Info */}
            <Card className="mb-6">
                <CardContent className="p-6">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50">
                                <Building2 className="h-5 w-5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-xs text-zinc-500">Type / Category</p>
                                <p className="text-sm font-medium capitalize text-zinc-900">
                                    {program.type} / {(program.category || '').replace('_', ' ')}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50">
                                <Calendar className="h-5 w-5 text-purple-600" />
                            </div>
                            <div>
                                <p className="text-xs text-zinc-500">Date Range</p>
                                <p className="text-sm font-medium text-zinc-900">
                                    {formatDate(program.start_date)} - {formatDate(program.end_date)}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50">
                                <Users className="h-5 w-5 text-emerald-600" />
                            </div>
                            <div>
                                <p className="text-xs text-zinc-500">Participants</p>
                                <p className="text-sm font-medium text-zinc-900">
                                    {enrollments.length}
                                    {program.max_participants ? ` / ${program.max_participants}` : ''}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50">
                                <DollarSign className="h-5 w-5 text-amber-600" />
                            </div>
                            <div>
                                <p className="text-xs text-zinc-500">Total Cost</p>
                                <p className="text-sm font-medium text-zinc-900">{formatCurrency(totalCost)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="mt-4 flex flex-wrap items-center gap-3">
                        <span className={cn(
                            'rounded-full px-2.5 py-0.5 text-xs font-medium capitalize',
                            STATUS_BADGE_CLASS[program.status] || 'bg-zinc-100 text-zinc-600'
                        )}>
                            {program.status}
                        </span>
                        {program.provider && (
                            <span className="text-sm text-zinc-500">Provider: {program.provider}</span>
                        )}
                        {program.location && (
                            <span className="flex items-center gap-1 text-sm text-zinc-500">
                                <MapPin className="h-3.5 w-3.5" />
                                {program.location}
                            </span>
                        )}
                    </div>
                    {program.description && (
                        <p className="mt-3 text-sm text-zinc-600">{program.description}</p>
                    )}
                    {(program.status === 'planned' || program.status === 'ongoing') && (
                        <div className="mt-4">
                            <Button onClick={() => setCompleteDialogOpen(true)}>
                                <CheckCircle2 className="mr-1.5 h-4 w-4" />
                                Complete Program
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Enrollments */}
                <Card>
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-zinc-900">Enrollments</h3>
                            {program.status !== 'completed' && program.status !== 'cancelled' && (
                                <Button size="sm" onClick={() => { setSelectedEmployees([]); setEnrollOpen(true); }}>
                                    <UserPlus className="mr-1.5 h-4 w-4" />
                                    Enroll
                                </Button>
                            )}
                        </div>
                        {enrollments.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-center">
                                <Users className="mb-2 h-8 w-8 text-zinc-300" />
                                <p className="text-sm text-zinc-500">No employees enrolled yet.</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {enrollments.map((enrollment) => (
                                        <TableRow key={enrollment.id}>
                                            <TableCell className="font-medium">
                                                {enrollment.employee?.full_name || '-'}
                                            </TableCell>
                                            <TableCell>
                                                <span className={cn(
                                                    'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                                    ENROLLMENT_STATUS_BADGE[enrollment.status] || 'bg-zinc-100 text-zinc-600'
                                                )}>
                                                    {enrollment.status}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    {enrollment.status === 'enrolled' && (
                                                        <>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-emerald-600 hover:text-emerald-700"
                                                                onClick={() => handleMarkAttendance(enrollment, 'attended')}
                                                                disabled={updateEnrollmentMutation.isPending}
                                                            >
                                                                <CheckCircle2 className="h-4 w-4" />
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-red-600 hover:text-red-700"
                                                                onClick={() => handleMarkAttendance(enrollment, 'absent')}
                                                                disabled={updateEnrollmentMutation.isPending}
                                                            >
                                                                <XCircle className="h-4 w-4" />
                                                            </Button>
                                                        </>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() => setDeleteEnrollDialog({ open: true, enrollment })}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* Costs */}
                <Card>
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-zinc-900">Costs</h3>
                            <Button size="sm" onClick={() => { setCostForm(EMPTY_COST_FORM); setCostErrors({}); setCostFormOpen(true); }}>
                                <Plus className="mr-1.5 h-4 w-4" />
                                Add Cost
                            </Button>
                        </div>
                        {costsLoading ? (
                            <div className="flex justify-center py-8">
                                <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                            </div>
                        ) : costs.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-center">
                                <DollarSign className="mb-2 h-8 w-8 text-zinc-300" />
                                <p className="text-sm text-zinc-500">No costs recorded yet.</p>
                            </div>
                        ) : (
                            <>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Description</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Amount</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {costs.map((cost) => (
                                            <TableRow key={cost.id}>
                                                <TableCell className="font-medium">{cost.description}</TableCell>
                                                <TableCell>
                                                    <Badge variant="outline" className="capitalize">
                                                        {cost.cost_type}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>{formatCurrency(cost.amount)}</TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() => setDeleteCostDialog({ open: true, cost })}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                                <div className="mt-3 flex justify-end border-t border-zinc-100 pt-3">
                                    <p className="text-sm font-semibold text-zinc-900">
                                        Total: {formatCurrency(totalCost)}
                                    </p>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Enroll Dialog */}
            <Dialog open={enrollOpen} onOpenChange={setEnrollOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Enroll Employees</DialogTitle>
                        <DialogDescription>
                            Select employees to enroll in this training program.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleEnroll} className="space-y-4">
                        <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-zinc-200 p-2">
                            {availableEmployees.length === 0 ? (
                                <p className="py-4 text-center text-sm text-zinc-400">
                                    No available employees to enroll.
                                </p>
                            ) : (
                                availableEmployees.map((emp) => (
                                    <label
                                        key={emp.id}
                                        className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-zinc-50"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selectedEmployees.includes(emp.id)}
                                            onChange={() => handleToggleEmployee(emp.id)}
                                            className="rounded"
                                        />
                                        <span className="text-zinc-700">{emp.full_name}</span>
                                        <span className="text-xs text-zinc-400">{emp.employee_id}</span>
                                    </label>
                                ))
                            )}
                        </div>
                        {selectedEmployees.length > 0 && (
                            <p className="text-xs text-zinc-500">
                                {selectedEmployees.length} employee(s) selected
                            </p>
                        )}
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEnrollOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={enrollMutation.isPending || selectedEmployees.length === 0}>
                                {enrollMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Enroll
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Add Cost Dialog */}
            <Dialog open={costFormOpen} onOpenChange={setCostFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add Training Cost</DialogTitle>
                        <DialogDescription>Record an expense for this training program.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleAddCost} className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Description *</label>
                            <input
                                type="text"
                                value={costForm.description}
                                onChange={(e) => setCostForm((f) => ({ ...f, description: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                required
                            />
                            {costErrors.description && <p className="mt-1 text-xs text-red-600">{costErrors.description[0]}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Type *</label>
                                <select
                                    value={costForm.cost_type}
                                    onChange={(e) => setCostForm((f) => ({ ...f, cost_type: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                >
                                    {COST_TYPE_OPTIONS.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Amount (MYR) *</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={costForm.amount}
                                    onChange={(e) => setCostForm((f) => ({ ...f, amount: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                />
                                {costErrors.amount && <p className="mt-1 text-xs text-red-600">{costErrors.amount[0]}</p>}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCostFormOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={addCostMutation.isPending}>
                                {addCostMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Add Cost
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Enrollment Dialog */}
            <Dialog open={deleteEnrollDialog.open} onOpenChange={() => setDeleteEnrollDialog({ open: false, enrollment: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove Enrollment</DialogTitle>
                        <DialogDescription>
                            Remove <strong>{deleteEnrollDialog.enrollment?.employee?.full_name}</strong> from this program? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteEnrollDialog({ open: false, enrollment: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteEnrollmentMutation.mutate(deleteEnrollDialog.enrollment.id)}
                            disabled={deleteEnrollmentMutation.isPending}
                        >
                            {deleteEnrollmentMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Remove
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Cost Dialog */}
            <Dialog open={deleteCostDialog.open} onOpenChange={() => setDeleteCostDialog({ open: false, cost: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Cost</DialogTitle>
                        <DialogDescription>
                            Delete cost <strong>{deleteCostDialog.cost?.description}</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteCostDialog({ open: false, cost: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteCostMutation.mutate(deleteCostDialog.cost.id)}
                            disabled={deleteCostMutation.isPending}
                        >
                            {deleteCostMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Complete Program Dialog */}
            <Dialog open={completeDialogOpen} onOpenChange={setCompleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Complete Training Program</DialogTitle>
                        <DialogDescription>
                            Mark <strong>{program.title}</strong> as completed? This will finalize the program.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCompleteDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => completeProgramMutation.mutate()}
                            disabled={completeProgramMutation.isPending}
                        >
                            {completeProgramMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Complete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
