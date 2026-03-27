import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Users,
    Pencil,
    Loader2,
    FileText,
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
import { fetchTaxProfiles, fetchTaxProfile, updateTaxProfile } from '../../lib/api';

const MARITAL_STATUS_LABELS = {
    single: 'Single',
    married_spouse_not_working: 'Married (Spouse Not Working)',
    married_spouse_working: 'Married (Spouse Working)',
};

function formatCurrency(amount) {
    if (amount == null) return '-';
    return `RM ${parseFloat(amount).toLocaleString('en-MY', { minimumFractionDigits: 2 })}`;
}

const EMPTY_FORM = {
    tax_number: '',
    marital_status: 'single',
    num_children: 0,
    num_children_studying: 0,
    disabled_individual: false,
    disabled_spouse: false,
    is_pcb_manual: false,
    manual_pcb_amount: '',
};

export default function TaxProfiles() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [editDialog, setEditDialog] = useState(false);
    const [selectedEmployee, setSelectedEmployee] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'tax-profiles', search],
        queryFn: () => fetchTaxProfiles({ search: search || undefined }),
    });

    const updateMutation = useMutation({
        mutationFn: ({ employeeId, data }) => updateTaxProfile(employeeId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'tax-profiles'] });
            setEditDialog(false);
        },
    });

    const employees = data?.data || [];

    function openEdit(employee) {
        setSelectedEmployee(employee);
        const profile = employee.tax_profile || {};
        setForm({
            tax_number: profile.tax_number || '',
            marital_status: profile.marital_status || 'single',
            num_children: profile.num_children || 0,
            num_children_studying: profile.num_children_studying || 0,
            disabled_individual: profile.disabled_individual || false,
            disabled_spouse: profile.disabled_spouse || false,
            is_pcb_manual: profile.is_pcb_manual || false,
            manual_pcb_amount: profile.manual_pcb_amount || '',
        });
        setEditDialog(true);
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Tax Profiles"
                description="Manage employee PCB tax profiles and statutory deduction settings"
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

            {/* Tax Profiles Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Employee Tax Profiles</CardTitle>
                    <CardDescription>PCB settings and tax information per employee</CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="space-y-3 p-6">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-2">
                                    <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                                    <div className="flex-1" />
                                    <div className="h-6 w-24 animate-pulse rounded bg-zinc-200" />
                                </div>
                            ))}
                        </div>
                    ) : employees.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <FileText className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No employees found</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Tax Number</TableHead>
                                    <TableHead>Marital Status</TableHead>
                                    <TableHead>Children</TableHead>
                                    <TableHead>Disabled</TableHead>
                                    <TableHead>PCB Method</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {employees.map((emp) => {
                                    const profile = emp.tax_profile || {};
                                    return (
                                        <TableRow key={emp.id}>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium text-zinc-900">{emp.full_name}</p>
                                                    <p className="text-xs text-zinc-500">{emp.employee_id}</p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-600">
                                                {profile.tax_number || <span className="text-zinc-400">Not set</span>}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-600">
                                                {MARITAL_STATUS_LABELS[profile.marital_status] || 'Single'}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-600">
                                                {profile.num_children || 0}
                                                {profile.num_children_studying > 0 && (
                                                    <span className="ml-1 text-xs text-zinc-400">({profile.num_children_studying} studying)</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-600">
                                                {profile.disabled_individual ? 'Self' : ''}
                                                {profile.disabled_individual && profile.disabled_spouse ? ' + ' : ''}
                                                {profile.disabled_spouse ? 'Spouse' : ''}
                                                {!profile.disabled_individual && !profile.disabled_spouse ? (
                                                    <span className="text-zinc-400">None</span>
                                                ) : null}
                                            </TableCell>
                                            <TableCell>
                                                {profile.is_pcb_manual ? (
                                                    <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                                        Manual: {formatCurrency(profile.manual_pcb_amount)}
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                                        Auto (PCB Table)
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(emp)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Edit Tax Profile Dialog */}
            <Dialog open={editDialog} onOpenChange={setEditDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit Tax Profile</DialogTitle>
                        <DialogDescription>
                            Update PCB settings for <strong>{selectedEmployee?.full_name}</strong>.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Tax Number (IC/Passport)</label>
                            <input
                                type="text"
                                value={form.tax_number}
                                onChange={(e) => setForm((p) => ({ ...p, tax_number: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="e.g. 900101-01-1234"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Marital Status</label>
                            <select
                                value={form.marital_status}
                                onChange={(e) => setForm((p) => ({ ...p, marital_status: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                {Object.entries(MARITAL_STATUS_LABELS).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Number of Children</label>
                                <input
                                    type="number"
                                    min="0"
                                    value={form.num_children}
                                    onChange={(e) => setForm((p) => ({ ...p, num_children: parseInt(e.target.value) || 0 }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Children Studying</label>
                                <input
                                    type="number"
                                    min="0"
                                    value={form.num_children_studying}
                                    onChange={(e) => setForm((p) => ({ ...p, num_children_studying: parseInt(e.target.value) || 0 }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <p className="text-sm font-medium text-zinc-700">Disability Status</p>
                            <label className="flex items-center gap-2 text-sm text-zinc-700">
                                <input
                                    type="checkbox"
                                    checked={form.disabled_individual}
                                    onChange={(e) => setForm((p) => ({ ...p, disabled_individual: e.target.checked }))}
                                    className="rounded"
                                />
                                Disabled Individual
                            </label>
                            <label className="flex items-center gap-2 text-sm text-zinc-700">
                                <input
                                    type="checkbox"
                                    checked={form.disabled_spouse}
                                    onChange={(e) => setForm((p) => ({ ...p, disabled_spouse: e.target.checked }))}
                                    className="rounded"
                                />
                                Disabled Spouse
                            </label>
                        </div>
                        <div className="rounded-lg border border-zinc-200 p-3">
                            <label className="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <input
                                    type="checkbox"
                                    checked={form.is_pcb_manual}
                                    onChange={(e) => setForm((p) => ({ ...p, is_pcb_manual: e.target.checked }))}
                                    className="rounded"
                                />
                                Use Manual PCB Amount
                            </label>
                            {form.is_pcb_manual && (
                                <div className="mt-3">
                                    <label className="mb-1.5 block text-sm font-medium text-zinc-700">Manual PCB Amount (RM/month)</label>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.manual_pcb_amount}
                                        onChange={(e) => setForm((p) => ({ ...p, manual_pcb_amount: e.target.value }))}
                                        className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                        placeholder="0.00"
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditDialog(false)}>Cancel</Button>
                        <Button
                            onClick={() => updateMutation.mutate({ employeeId: selectedEmployee?.id, data: form })}
                            disabled={updateMutation.isPending}
                        >
                            {updateMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Save Profile
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
