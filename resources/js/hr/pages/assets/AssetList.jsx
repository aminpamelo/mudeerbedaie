import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Package, ChevronLeft, ChevronRight, Loader2, Eye } from 'lucide-react';
import {
    fetchAssets,
    createAsset,
    updateAsset,
    deleteAsset,
    fetchAssetCategories,
    fetchAssetAssignments,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
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

const CONDITIONS = [
    { value: 'new', label: 'New' },
    { value: 'good', label: 'Good' },
    { value: 'fair', label: 'Fair' },
    { value: 'poor', label: 'Poor' },
    { value: 'damaged', label: 'Damaged' },
    { value: 'disposed', label: 'Disposed' },
];

const STATUSES = [
    { value: 'available', label: 'Available' },
    { value: 'assigned', label: 'Assigned' },
    { value: 'under_maintenance', label: 'Under Maintenance' },
    { value: 'disposed', label: 'Disposed' },
];

const STATUS_BADGE = {
    available: 'bg-emerald-100 text-emerald-700',
    assigned: 'bg-blue-100 text-blue-700',
    under_maintenance: 'bg-amber-100 text-amber-700',
    disposed: 'bg-zinc-100 text-zinc-500',
};

const CONDITION_BADGE = {
    new: 'bg-emerald-50 text-emerald-600',
    good: 'bg-blue-50 text-blue-600',
    fair: 'bg-amber-50 text-amber-600',
    poor: 'bg-orange-50 text-orange-600',
    damaged: 'bg-red-50 text-red-600',
    disposed: 'bg-zinc-50 text-zinc-500',
};

const EMPTY_FORM = {
    asset_tag: '',
    asset_category_id: '',
    name: '',
    brand: '',
    model: '',
    serial_number: '',
    purchase_date: '',
    purchase_price: '',
    warranty_expiry: '',
    condition: 'new',
    notes: '',
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatCurrency(amount) {
    if (amount === null || amount === undefined || amount === '') return '-';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

export default function AssetList() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [categoryFilter, setCategoryFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [formOpen, setFormOpen] = useState(false);
    const [editingAsset, setEditingAsset] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteDialog, setDeleteDialog] = useState({ open: false, asset: null });
    const [historyDialog, setHistoryDialog] = useState({ open: false, asset: null });
    const [errors, setErrors] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'assets', 'list', { search, page, categoryFilter, statusFilter }],
        queryFn: () => fetchAssets({
            search: search || undefined,
            asset_category_id: categoryFilter !== 'all' ? categoryFilter : undefined,
            status: statusFilter !== 'all' ? statusFilter : undefined,
            page,
            per_page: 15,
        }),
    });

    const { data: categoriesData } = useQuery({
        queryKey: ['hr', 'assets', 'categories'],
        queryFn: () => fetchAssetCategories({ per_page: 100 }),
    });

    const { data: assignmentHistoryData, isLoading: historyLoading } = useQuery({
        queryKey: ['hr', 'assets', 'assignments', 'history', historyDialog.asset?.id],
        queryFn: () => fetchAssetAssignments({ asset_id: historyDialog.asset?.id, per_page: 50 }),
        enabled: historyDialog.open && !!historyDialog.asset,
    });

    const saveMutation = useMutation({
        mutationFn: (data) => editingAsset ? updateAsset(editingAsset.id, data) : createAsset(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'assets', 'list'] });
            setFormOpen(false);
            setEditingAsset(null);
            setForm(EMPTY_FORM);
            setErrors({});
        },
        onError: (err) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            }
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => deleteAsset(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'assets', 'list'] });
            setDeleteDialog({ open: false, asset: null });
        },
    });

    const assets = data?.data || [];
    const meta = data?.meta || {};
    const categories = categoriesData?.data || [];
    const assignmentHistory = assignmentHistoryData?.data || [];

    function openCreate() {
        setEditingAsset(null);
        setForm(EMPTY_FORM);
        setErrors({});
        setFormOpen(true);
    }

    function openEdit(asset) {
        setEditingAsset(asset);
        setForm({
            asset_tag: asset.asset_tag || '',
            asset_category_id: String(asset.asset_category_id || ''),
            name: asset.name || '',
            brand: asset.brand || '',
            model: asset.model || '',
            serial_number: asset.serial_number || '',
            purchase_date: asset.purchase_date || '',
            purchase_price: asset.purchase_price ?? '',
            warranty_expiry: asset.warranty_expiry || '',
            condition: asset.condition || 'new',
            notes: asset.notes || '',
        });
        setErrors({});
        setFormOpen(true);
    }

    function handleSubmit(e) {
        e.preventDefault();
        saveMutation.mutate({
            ...form,
            asset_category_id: parseInt(form.asset_category_id),
            purchase_price: form.purchase_price !== '' ? parseFloat(form.purchase_price) : null,
            purchase_date: form.purchase_date || null,
            warranty_expiry: form.warranty_expiry || null,
            serial_number: form.serial_number || null,
        });
    }

    return (
        <div>
            <PageHeader
                title="Asset Inventory"
                description="Track and manage company assets."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Add Asset
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-6">
                    <div className="mb-4 flex flex-wrap items-center gap-3">
                        <div className="flex-1">
                            <SearchInput
                                value={search}
                                onChange={(v) => { setSearch(v); setPage(1); }}
                                placeholder="Search by tag, name, serial number..."
                            />
                        </div>
                        <Select value={categoryFilter} onValueChange={(v) => { setCategoryFilter(v); setPage(1); }}>
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="All Categories" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Categories</SelectItem>
                                {categories.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); setPage(1); }}>
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                {STATUSES.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : assets.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Package className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No assets found</p>
                            <p className="mt-1 text-xs text-zinc-400">Add your first asset to get started.</p>
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Asset Tag</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Condition</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Purchase Price</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assets.map((asset) => (
                                        <TableRow key={asset.id}>
                                            <TableCell className="font-mono text-sm font-medium">
                                                {asset.asset_tag}
                                            </TableCell>
                                            <TableCell>
                                                <p className="font-medium">{asset.name}</p>
                                                {(asset.brand || asset.model) && (
                                                    <p className="text-xs text-zinc-400">
                                                        {[asset.brand, asset.model].filter(Boolean).join(' ')}
                                                    </p>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {asset.asset_category?.name || '-'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium capitalize', CONDITION_BADGE[asset.condition] || 'bg-zinc-100 text-zinc-600')}>
                                                    {asset.condition}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', STATUS_BADGE[asset.status] || 'bg-zinc-100 text-zinc-600')}>
                                                    {STATUSES.find((s) => s.value === asset.status)?.label || asset.status}
                                                </span>
                                            </TableCell>
                                            <TableCell>{formatCurrency(asset.purchase_price)}</TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setHistoryDialog({ open: true, asset })}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                    <Button variant="ghost" size="sm" onClick={() => openEdit(asset)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() => setDeleteDialog({ open: true, asset })}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {meta.last_page > 1 && (
                                <div className="mt-4 flex items-center justify-between text-sm text-zinc-500">
                                    <span>Showing {meta.from}–{meta.to} of {meta.total}</span>
                                    <div className="flex items-center gap-2">
                                        <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <span>Page {meta.current_page} of {meta.last_page}</span>
                                        <Button variant="outline" size="sm" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            {/* Assignment History Dialog */}
            <Dialog open={historyDialog.open} onOpenChange={() => setHistoryDialog({ open: false, asset: null })}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Assignment History — {historyDialog.asset?.asset_tag}</DialogTitle>
                        <DialogDescription>{historyDialog.asset?.name}</DialogDescription>
                    </DialogHeader>
                    {historyLoading ? (
                        <div className="flex justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : assignmentHistory.length === 0 ? (
                        <p className="py-8 text-center text-sm text-zinc-400">No assignment history.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Assigned</TableHead>
                                    <TableHead>Returned</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {assignmentHistory.map((a) => (
                                    <TableRow key={a.id}>
                                        <TableCell className="font-medium">{a.employee?.full_name || '-'}</TableCell>
                                        <TableCell className="text-sm">{formatDate(a.assigned_date)}</TableCell>
                                        <TableCell className="text-sm">{formatDate(a.returned_date)}</TableCell>
                                        <TableCell>
                                            <span className={cn(
                                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                                a.status === 'active' ? 'bg-blue-100 text-blue-700' :
                                                a.status === 'returned' ? 'bg-emerald-100 text-emerald-700' :
                                                'bg-red-100 text-red-700'
                                            )}>
                                                {a.status}
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </DialogContent>
            </Dialog>

            {/* Form Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingAsset ? 'Edit Asset' : 'Add Asset'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Asset Tag *</label>
                                <input
                                    type="text"
                                    value={form.asset_tag}
                                    onChange={(e) => setForm((f) => ({ ...f, asset_tag: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-sm focus:border-zinc-400 focus:outline-none"
                                    required
                                />
                                {errors.asset_tag && <p className="mt-1 text-xs text-red-600">{errors.asset_tag[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Category *</label>
                                <Select value={form.asset_category_id} onValueChange={(v) => setForm((f) => ({ ...f, asset_category_id: v }))}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Name *</label>
                            <input
                                type="text"
                                value={form.name}
                                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                required
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Brand</label>
                                <input
                                    type="text"
                                    value={form.brand}
                                    onChange={(e) => setForm((f) => ({ ...f, brand: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Model</label>
                                <input
                                    type="text"
                                    value={form.model}
                                    onChange={(e) => setForm((f) => ({ ...f, model: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Serial Number</label>
                                <input
                                    type="text"
                                    value={form.serial_number}
                                    onChange={(e) => setForm((f) => ({ ...f, serial_number: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-sm focus:border-zinc-400 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Condition</label>
                                <Select value={form.condition} onValueChange={(v) => setForm((f) => ({ ...f, condition: v }))}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {CONDITIONS.map((c) => (
                                            <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Purchase Date</label>
                                <input
                                    type="date"
                                    value={form.purchase_date}
                                    onChange={(e) => setForm((f) => ({ ...f, purchase_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Price (MYR)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.purchase_price}
                                    onChange={(e) => setForm((f) => ({ ...f, purchase_price: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Warranty Expiry</label>
                                <input
                                    type="date"
                                    value={form.warranty_expiry}
                                    onChange={(e) => setForm((f) => ({ ...f, warranty_expiry: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>Cancel</Button>
                            <Button type="submit" disabled={saveMutation.isPending}>
                                {saveMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                {editingAsset ? 'Update' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, asset: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Asset</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{deleteDialog.asset?.asset_tag} — {deleteDialog.asset?.name}</strong>?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, asset: null })}>Cancel</Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteDialog.asset.id)}
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
