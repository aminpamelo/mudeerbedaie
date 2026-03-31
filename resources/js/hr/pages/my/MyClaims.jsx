import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Trash2,
    Receipt,
    Loader2,
    ChevronLeft,
    ChevronRight,
    Upload,
    Paperclip,
} from 'lucide-react';
import {
    fetchMyClaims,
    fetchMyClaimLimits,
    createMyClaim,
    submitMyClaim,
    deleteMyClaim,
    fetchClaimTypes,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';

const STATUS_CONFIG = {
    draft: { label: 'Draft', className: 'bg-zinc-100 text-zinc-600' },
    pending: { label: 'Pending', className: 'bg-amber-100 text-amber-700' },
    approved: { label: 'Approved', className: 'bg-emerald-100 text-emerald-700' },
    rejected: { label: 'Rejected', className: 'bg-red-100 text-red-700' },
    paid: { label: 'Paid', className: 'bg-blue-100 text-blue-700' },
};

const EMPTY_FORM = {
    claim_type_id: '',
    amount: '',
    claim_date: new Date().toISOString().split('T')[0],
    description: '',
    receipt: null,
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatCurrency(amount) {
    if (amount === null || amount === undefined) return '-';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

export default function MyClaims() {
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [statusFilter, setStatusFilter] = useState('all');
    const [formOpen, setFormOpen] = useState(false);
    const [form, setForm] = useState(EMPTY_FORM);
    const [errors, setErrors] = useState({});
    const [deleteDialog, setDeleteDialog] = useState({ open: false, claim: null });
    const [submitDialog, setSubmitDialog] = useState({ open: false, claim: null });

    const { data: claimsData, isLoading } = useQuery({
        queryKey: ['my-claims', { page, statusFilter }],
        queryFn: () => fetchMyClaims({
            page,
            per_page: 10,
            status: statusFilter !== 'all' ? statusFilter : undefined,
        }),
    });

    const { data: limitsData } = useQuery({
        queryKey: ['my-claim-limits'],
        queryFn: fetchMyClaimLimits,
    });

    const { data: claimTypesData } = useQuery({
        queryKey: ['hr', 'claims', 'types', 'list'],
        queryFn: () => fetchClaimTypes({ per_page: 100 }),
    });

    const createMutation = useMutation({
        mutationFn: (formData) => createMyClaim(formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-claims'] });
            queryClient.invalidateQueries({ queryKey: ['my-claim-limits'] });
            setFormOpen(false);
            setForm(EMPTY_FORM);
            setErrors({});
        },
        onError: (err) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            }
        },
    });

    const submitMutation = useMutation({
        mutationFn: (id) => submitMyClaim(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-claims'] });
            setSubmitDialog({ open: false, claim: null });
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => deleteMyClaim(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-claims'] });
            setDeleteDialog({ open: false, claim: null });
        },
    });

    const claims = claimsData?.data || [];
    const meta = claimsData?.meta || {};
    const limits = limitsData?.data || [];
    const claimTypes = claimTypesData?.data || [];

    function handleSubmitForm(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('claim_type_id', form.claim_type_id);
        formData.append('amount', form.amount);
        formData.append('claim_date', form.claim_date);
        formData.append('description', form.description);
        if (form.receipt) {
            formData.append('receipt', form.receipt);
        }
        createMutation.mutate(formData);
    }

    return (
        <div className="space-y-6">
            {/* Usage Limits */}
            {limits.length > 0 && (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {limits.map((limit) => {
                        const usedPercent = limit.monthly_limit
                            ? Math.min(100, (limit.used_this_month / limit.monthly_limit) * 100)
                            : 0;
                        return (
                            <Card key={limit.claim_type_id}>
                                <CardContent className="p-4">
                                    <p className="text-sm font-medium text-zinc-700">{limit.name}</p>
                                    {limit.monthly_limit ? (
                                        <>
                                            <div className="mt-2 flex items-center justify-between text-xs text-zinc-500">
                                                <span>{formatCurrency(limit.used_this_month)} used</span>
                                                <span>{formatCurrency(limit.monthly_limit)} limit</span>
                                            </div>
                                            <div className="mt-1.5 h-1.5 w-full rounded-full bg-zinc-100">
                                                <div
                                                    className={cn(
                                                        'h-1.5 rounded-full transition-all',
                                                        usedPercent >= 90 ? 'bg-red-500' : usedPercent >= 70 ? 'bg-amber-500' : 'bg-emerald-500'
                                                    )}
                                                    style={{ width: `${usedPercent}%` }}
                                                />
                                            </div>
                                            <p className="mt-1 text-xs text-zinc-500">
                                                {formatCurrency(Math.max(0, limit.monthly_limit - limit.used_this_month))} remaining this month
                                            </p>
                                        </>
                                    ) : (
                                        <p className="mt-1 text-xs text-zinc-400">No monthly limit</p>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            {/* Claims List */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between pb-4">
                    <CardTitle className="text-base">My Claims</CardTitle>
                    <div className="flex items-center gap-3">
                        <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); setPage(1); }}>
                            <SelectTrigger className="w-36 text-sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="approved">Approved</SelectItem>
                                <SelectItem value="rejected">Rejected</SelectItem>
                                <SelectItem value="paid">Paid</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button size="sm" onClick={() => { setFormOpen(true); setForm(EMPTY_FORM); setErrors({}); }}>
                            <Plus className="mr-1.5 h-4 w-4" />
                            New Claim
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="pt-0">
                    {isLoading ? (
                        <div className="flex justify-center py-12">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : claims.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Receipt className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No claims yet</p>
                            <p className="mt-1 text-xs text-zinc-400">Submit your first expense claim.</p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {claims.map((claim) => {
                                const status = STATUS_CONFIG[claim.status] || { label: claim.status, className: 'bg-zinc-100 text-zinc-600' };
                                return (
                                    <div
                                        key={claim.id}
                                        className="flex items-center justify-between rounded-lg border border-zinc-100 bg-white p-4"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-xs text-zinc-400">{claim.claim_number}</span>
                                                <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', status.className)}>
                                                    {status.label}
                                                </span>
                                            </div>
                                            <p className="mt-0.5 text-sm font-medium text-zinc-900">
                                                {claim.claim_type?.name || '-'}
                                            </p>
                                            <p className="text-xs text-zinc-500">
                                                {formatDate(claim.claim_date)} &middot; {claim.description}
                                            </p>
                                            {claim.receipt_url && (
                                                <a
                                                    href={claim.receipt_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700"
                                                >
                                                    <Paperclip className="h-3 w-3" />
                                                    View Receipt
                                                </a>
                                            )}
                                        </div>
                                        <div className="ml-4 flex items-center gap-3">
                                            <span className="text-base font-semibold text-zinc-900">
                                                {formatCurrency(claim.amount)}
                                            </span>
                                            {claim.status === 'draft' && (
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => setSubmitDialog({ open: true, claim })}
                                                    >
                                                        Submit
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() => setDeleteDialog({ open: true, claim })}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}

                            {meta.last_page > 1 && (
                                <div className="flex items-center justify-between pt-2 text-sm text-zinc-500">
                                    <span>Page {meta.current_page} of {meta.last_page}</span>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page <= 1}
                                            onClick={() => setPage((p) => p - 1)}
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page >= meta.last_page}
                                            onClick={() => setPage((p) => p + 1)}
                                        >
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* New Claim Form Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Submit New Claim</DialogTitle>
                        <DialogDescription>
                            Fill in the details for your expense claim.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmitForm} className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Claim Type *</label>
                            <Select
                                value={form.claim_type_id}
                                onValueChange={(v) => setForm((f) => ({ ...f, claim_type_id: v }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select type..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {claimTypes.filter((t) => t.is_active).map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>
                                            {t.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.claim_type_id && <p className="mt-1 text-xs text-red-600">{errors.claim_type_id[0]}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Amount (MYR) *</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={form.amount}
                                    onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                />
                                {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Claim Date *</label>
                                <input
                                    type="date"
                                    value={form.claim_date}
                                    onChange={(e) => setForm((f) => ({ ...f, claim_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                />
                                {errors.claim_date && <p className="mt-1 text-xs text-red-600">{errors.claim_date[0]}</p>}
                            </div>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Description *</label>
                            <textarea
                                value={form.description}
                                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                rows={3}
                                required
                            />
                            {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Receipt</label>
                            <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-zinc-300 px-4 py-3 text-sm text-zinc-500 hover:border-zinc-400 hover:text-zinc-600">
                                <Upload className="h-4 w-4" />
                                {form.receipt ? form.receipt.name : 'Upload receipt (PDF, JPG, PNG)'}
                                <input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    className="hidden"
                                    onChange={(e) => setForm((f) => ({ ...f, receipt: e.target.files[0] || null }))}
                                />
                            </label>
                            {errors.receipt && <p className="mt-1 text-xs text-red-600">{errors.receipt[0]}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createMutation.isPending}>
                                {createMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Save as Draft
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Submit Dialog */}
            <Dialog open={submitDialog.open} onOpenChange={() => setSubmitDialog({ open: false, claim: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Submit Claim</DialogTitle>
                        <DialogDescription>
                            Submit this claim for approval? You won't be able to edit it after submission.
                        </DialogDescription>
                    </DialogHeader>
                    {submitDialog.claim && (
                        <div className="rounded-lg bg-zinc-50 p-3 text-sm">
                            <p className="font-medium">{submitDialog.claim.claim_type?.name}</p>
                            <p className="text-zinc-500">
                                {formatCurrency(submitDialog.claim.amount)} &middot; {formatDate(submitDialog.claim.claim_date)}
                            </p>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setSubmitDialog({ open: false, claim: null })}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => submitMutation.mutate(submitDialog.claim.id)}
                            disabled={submitMutation.isPending}
                        >
                            {submitMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Submit for Approval
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, claim: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Claim</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this draft claim? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, claim: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteDialog.claim.id)}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
