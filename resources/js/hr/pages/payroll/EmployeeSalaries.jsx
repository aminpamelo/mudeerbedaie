import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Users,
    DollarSign,
    Pencil,
    Plus,
    Loader2,
    History,
    ChevronDown,
    ChevronUp,
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
import { Button } from '../../components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';
import PageHeader from '../../components/PageHeader';
import {
    fetchEmployeeSalaries,
    fetchEmployeeSalary,
    createEmployeeSalary,
    updateEmployeeSalary,
    fetchSalaryRevisions,
    fetchSalaryComponents,
} from '../../lib/api';

function formatCurrency(amount) {
    if (amount == null) return 'RM 0.00';
    return `RM ${parseFloat(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' });
}

function EmployeeRow({ employee, onEdit }) {
    const [expanded, setExpanded] = useState(false);

    const totalSalary = (employee.salaries || []).reduce((sum, s) => {
        return s.salary_component?.type === 'earning' ? sum + parseFloat(s.amount || 0) : sum;
    }, 0);

    return (
        <>
            <TableRow className="cursor-pointer hover:bg-zinc-50" onClick={() => setExpanded(!expanded)}>
                <TableCell>
                    <div className="flex items-center gap-2">
                        {expanded ? <ChevronUp className="h-4 w-4 text-zinc-400" /> : <ChevronDown className="h-4 w-4 text-zinc-400" />}
                        <div>
                            <p className="font-medium text-zinc-900">{employee.full_name}</p>
                            <p className="text-xs text-zinc-500">{employee.employee_id}</p>
                        </div>
                    </div>
                </TableCell>
                <TableCell className="text-sm text-zinc-600">{employee.department?.name || '-'}</TableCell>
                <TableCell className="text-sm text-zinc-600">{employee.position?.title || '-'}</TableCell>
                <TableCell className="font-medium">{formatCurrency(totalSalary)}</TableCell>
                <TableCell className="text-right">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => { e.stopPropagation(); onEdit(employee); }}
                    >
                        <Pencil className="h-4 w-4" />
                    </Button>
                </TableCell>
            </TableRow>
            {expanded && (
                <TableRow>
                    <TableCell colSpan={5} className="bg-zinc-50 p-0">
                        <div className="px-8 py-3">
                            <p className="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-500">Salary Components</p>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-zinc-500">
                                        <th className="pb-1 font-medium">Component</th>
                                        <th className="pb-1 font-medium">Amount</th>
                                        <th className="pb-1 font-medium">Effective From</th>
                                        <th className="pb-1 font-medium">Effective To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(employee.salaries || []).map((sal) => (
                                        <tr key={sal.id}>
                                            <td className="py-0.5 text-zinc-700">{sal.salary_component?.name || '-'}</td>
                                            <td className="py-0.5 font-medium">{formatCurrency(sal.amount)}</td>
                                            <td className="py-0.5 text-zinc-500">{formatDate(sal.effective_from)}</td>
                                            <td className="py-0.5 text-zinc-500">{sal.effective_to ? formatDate(sal.effective_to) : 'Current'}</td>
                                        </tr>
                                    ))}
                                    {(!employee.salaries || employee.salaries.length === 0) && (
                                        <tr>
                                            <td colSpan={4} className="py-2 text-zinc-400">No salary records</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </TableCell>
                </TableRow>
            )}
        </>
    );
}

export default function EmployeeSalaries() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [editDialog, setEditDialog] = useState(false);
    const [selectedEmployee, setSelectedEmployee] = useState(null);
    const [revisionsDialog, setRevisionsDialog] = useState(false);
    const [revisionsEmployee, setRevisionsEmployee] = useState(null);
    const [salaryForm, setSalaryForm] = useState({ salary_component_id: '', amount: '', effective_from: '', effective_to: '' });

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'salaries', search],
        queryFn: () => fetchEmployeeSalaries({ search: search || undefined, include: 'salaries,department,position' }),
    });

    const { data: componentsData } = useQuery({
        queryKey: ['hr', 'payroll', 'components'],
        queryFn: () => fetchSalaryComponents({ type: 'earning', is_active: 1 }),
    });

    const { data: revisionsData, isLoading: revisionsLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'revisions', revisionsEmployee?.id],
        queryFn: () => fetchSalaryRevisions(revisionsEmployee.id),
        enabled: !!revisionsEmployee,
    });

    const createMutation = useMutation({
        mutationFn: createEmployeeSalary,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'salaries'] });
            setSalaryForm({ salary_component_id: '', amount: '', effective_from: '', effective_to: '' });
        },
    });

    const employees = data?.data || [];
    const components = componentsData?.data || [];
    const revisions = revisionsData?.data || [];

    function openEdit(employee) {
        setSelectedEmployee(employee);
        setSalaryForm({ salary_component_id: '', amount: '', effective_from: new Date().toISOString().split('T')[0], effective_to: '' });
        setEditDialog(true);
    }

    function openRevisions(employee) {
        setRevisionsEmployee(employee);
        setRevisionsDialog(true);
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Employee Salaries"
                description="Manage salary components and revision history per employee"
            />

            {/* Search */}
            <Card>
                <CardContent className="p-4">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name or employee ID..."
                        className="w-full max-w-sm rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                    />
                </CardContent>
            </Card>

            {/* Employees Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Employee Salaries</CardTitle>
                    <CardDescription>Click a row to expand salary components</CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="space-y-3 p-6">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-2">
                                    <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-28 animate-pulse rounded bg-zinc-200" />
                                    <div className="flex-1" />
                                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                                </div>
                            ))}
                        </div>
                    ) : employees.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <Users className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No employees found</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Position</TableHead>
                                    <TableHead>Total Monthly</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {employees.map((emp) => (
                                    <EmployeeRow key={emp.id} employee={emp} onEdit={openEdit} />
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Edit Salary Dialog */}
            <Dialog open={editDialog} onOpenChange={setEditDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Add Salary Component</DialogTitle>
                        <DialogDescription>
                            Add a salary component for <strong>{selectedEmployee?.full_name}</strong>.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Component</label>
                            <select
                                value={salaryForm.salary_component_id}
                                onChange={(e) => setSalaryForm((p) => ({ ...p, salary_component_id: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                <option value="">Select component...</option>
                                {components.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Amount (RM)</label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                value={salaryForm.amount}
                                onChange={(e) => setSalaryForm((p) => ({ ...p, amount: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="0.00"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Effective From</label>
                                <input
                                    type="date"
                                    value={salaryForm.effective_from}
                                    onChange={(e) => setSalaryForm((p) => ({ ...p, effective_from: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Effective To (optional)</label>
                                <input
                                    type="date"
                                    value={salaryForm.effective_to}
                                    onChange={(e) => setSalaryForm((p) => ({ ...p, effective_to: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setRevisionsEmployee(selectedEmployee);
                                setRevisionsDialog(true);
                            }}
                        >
                            <History className="mr-2 h-4 w-4" />
                            View History
                        </Button>
                        <Button
                            onClick={() => createMutation.mutate({
                                employee_id: selectedEmployee?.id,
                                ...salaryForm,
                            })}
                            disabled={createMutation.isPending || !salaryForm.salary_component_id || !salaryForm.amount}
                        >
                            {createMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Revisions Dialog */}
            <Dialog open={revisionsDialog} onOpenChange={setRevisionsDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Salary Revision History</DialogTitle>
                        <DialogDescription>
                            {revisionsEmployee?.full_name} — all salary changes
                        </DialogDescription>
                    </DialogHeader>
                    {revisionsLoading ? (
                        <div className="space-y-2">
                            {Array.from({ length: 4 }).map((_, i) => (
                                <div key={i} className="h-4 w-full animate-pulse rounded bg-zinc-200" />
                            ))}
                        </div>
                    ) : revisions.length === 0 ? (
                        <p className="text-center text-sm text-zinc-400 py-4">No revisions recorded.</p>
                    ) : (
                        <div className="max-h-80 overflow-y-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-zinc-500">
                                        <th className="pb-2 font-medium">Component</th>
                                        <th className="pb-2 font-medium">Old</th>
                                        <th className="pb-2 font-medium">New</th>
                                        <th className="pb-2 font-medium">Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-100">
                                    {revisions.map((rev) => (
                                        <tr key={rev.id}>
                                            <td className="py-2">{rev.salary_component?.name || '-'}</td>
                                            <td className="py-2 text-red-600">{formatCurrency(rev.old_amount)}</td>
                                            <td className="py-2 text-emerald-600">{formatCurrency(rev.new_amount)}</td>
                                            <td className="py-2 text-zinc-500">{formatDate(rev.effective_date)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setRevisionsDialog(false)}>Close</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
