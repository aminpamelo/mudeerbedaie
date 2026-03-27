import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Pencil,
    Loader2,
    ShieldCheck,
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
import { fetchStatutoryRates, updateStatutoryRate } from '../../lib/api';

const TABS = [
    { key: 'epf', label: 'EPF', types: ['epf_employee', 'epf_employer'] },
    { key: 'socso', label: 'SOCSO', types: ['socso_employee', 'socso_employer'] },
    { key: 'eis', label: 'EIS', types: ['eis_employee', 'eis_employer'] },
];

const TYPE_LABELS = {
    epf_employee: 'Employee Contribution',
    epf_employer: 'Employer Contribution',
    socso_employee: 'Employee Contribution (SOCSO)',
    socso_employer: 'Employer Contribution (SOCSO)',
    eis_employee: 'Employee Contribution (EIS)',
    eis_employer: 'Employer Contribution (EIS)',
};

function formatCurrency(amount) {
    if (amount == null) return '-';
    return `RM ${parseFloat(amount).toLocaleString('en-MY', { minimumFractionDigits: 2 })}`;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function StatutoryRates() {
    const queryClient = useQueryClient();
    const [activeTab, setActiveTab] = useState('epf');
    const [editDialog, setEditDialog] = useState(false);
    const [editingRate, setEditingRate] = useState(null);
    const [editForm, setEditForm] = useState({ rate_percentage: '', fixed_amount: '' });

    const currentTabTypes = TABS.find((t) => t.key === activeTab)?.types || [];

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'statutory-rates', activeTab],
        queryFn: () => fetchStatutoryRates({ types: currentTabTypes.join(',') }),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateStatutoryRate(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'statutory-rates'] });
            setEditDialog(false);
        },
    });

    const rates = data?.data || [];

    function openEdit(rate) {
        setEditingRate(rate);
        setEditForm({
            rate_percentage: rate.rate_percentage || '',
            fixed_amount: rate.fixed_amount || '',
        });
        setEditDialog(true);
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Statutory Rates"
                description="EPF, SOCSO, and EIS contribution rates and brackets"
            />

            {/* Tabs */}
            <div className="border-b border-zinc-200">
                <nav className="flex gap-4">
                    {TABS.map((tab) => (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key)}
                            className={`pb-3 text-sm font-medium transition-colors ${
                                activeTab === tab.key
                                    ? 'border-b-2 border-zinc-900 text-zinc-900'
                                    : 'text-zinc-500 hover:text-zinc-700'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Rates Table */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle>{TABS.find((t) => t.key === activeTab)?.label} Rates</CardTitle>
                            <CardDescription>Contribution brackets by salary range</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="space-y-3 p-6">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-2">
                                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                                    <div className="flex-1" />
                                    <div className="h-4 w-16 animate-pulse rounded bg-zinc-200" />
                                </div>
                            ))}
                        </div>
                    ) : rates.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <ShieldCheck className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No statutory rates configured</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Min Salary</TableHead>
                                    <TableHead>Max Salary</TableHead>
                                    <TableHead>Rate %</TableHead>
                                    <TableHead>Fixed Amount</TableHead>
                                    <TableHead>Effective From</TableHead>
                                    <TableHead>Effective To</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rates.map((rate) => (
                                    <TableRow key={rate.id}>
                                        <TableCell className="text-sm text-zinc-700">
                                            {TYPE_LABELS[rate.type] || rate.type}
                                        </TableCell>
                                        <TableCell>{formatCurrency(rate.min_salary)}</TableCell>
                                        <TableCell>{rate.max_salary ? formatCurrency(rate.max_salary) : 'No limit'}</TableCell>
                                        <TableCell>
                                            {rate.rate_percentage ? `${rate.rate_percentage}%` : '-'}
                                        </TableCell>
                                        <TableCell>{formatCurrency(rate.fixed_amount)}</TableCell>
                                        <TableCell className="text-sm text-zinc-500">{formatDate(rate.effective_from)}</TableCell>
                                        <TableCell className="text-sm text-zinc-500">
                                            {rate.effective_to ? formatDate(rate.effective_to) : 'Current'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button variant="ghost" size="sm" onClick={() => openEdit(rate)}>
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Edit Rate Dialog */}
            <Dialog open={editDialog} onOpenChange={setEditDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Statutory Rate</DialogTitle>
                        <DialogDescription>
                            Update the contribution rate for salary range{' '}
                            {editingRate && `${formatCurrency(editingRate.min_salary)} - ${editingRate.max_salary ? formatCurrency(editingRate.max_salary) : 'No limit'}`}.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Rate Percentage (%)</label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                value={editForm.rate_percentage}
                                onChange={(e) => setEditForm((p) => ({ ...p, rate_percentage: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="e.g. 11.00"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Fixed Amount (RM) — if applicable</label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                value={editForm.fixed_amount}
                                onChange={(e) => setEditForm((p) => ({ ...p, fixed_amount: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="0.00"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditDialog(false)}>Cancel</Button>
                        <Button
                            onClick={() => updateMutation.mutate({ id: editingRate.id, data: editForm })}
                            disabled={updateMutation.isPending}
                        >
                            {updateMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Save Rate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
