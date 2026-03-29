import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Target,
    AlertTriangle,
    Loader2,
    X,
} from 'lucide-react';
import {
    fetchKpiTemplates,
    createKpiTemplate,
    updateKpiTemplate,
    deleteKpiTemplate,
    fetchDepartments,
    fetchPositions,
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
    DialogFooter,
} from '../../components/ui/dialog';

const CATEGORY_BADGE = {
    productivity: 'bg-blue-100 text-blue-700',
    quality: 'bg-emerald-100 text-emerald-700',
    attendance: 'bg-amber-100 text-amber-700',
    leadership: 'bg-purple-100 text-purple-700',
    teamwork: 'bg-pink-100 text-pink-700',
    communication: 'bg-cyan-100 text-cyan-700',
    other: 'bg-zinc-100 text-zinc-600',
};

const CATEGORIES = [
    'productivity', 'quality', 'attendance', 'leadership', 'teamwork', 'communication', 'other',
];

const EMPTY_FORM = {
    title: '',
    description: '',
    target: '',
    weight: '',
    category: 'productivity',
    department_id: '',
    position_id: '',
};

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-12 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1" />
                    <div className="h-6 w-20 animate-pulse rounded-full bg-zinc-200" />
                    <div className="h-8 w-16 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function KpiTemplates() {
    const queryClient = useQueryClient();
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [positionFilter, setPositionFilter] = useState('all');
    const [categoryFilter, setCategoryFilter] = useState('all');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [formError, setFormError] = useState('');
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'performance', 'kpis', { departmentFilter, positionFilter, categoryFilter }],
        queryFn: () => fetchKpiTemplates({
            department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
            position_id: positionFilter !== 'all' ? positionFilter : undefined,
            category: categoryFilter !== 'all' ? categoryFilter : undefined,
            per_page: 100,
        }),
    });

    const { data: departmentsData } = useQuery({
        queryKey: ['hr', 'departments', 'list'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const { data: positionsData } = useQuery({
        queryKey: ['hr', 'positions', 'list'],
        queryFn: () => fetchPositions({ per_page: 100 }),
    });

    const createMutation = useMutation({
        mutationFn: createKpiTemplate,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'kpis'] });
            setDialogOpen(false);
            setForm(EMPTY_FORM);
            setFormError('');
        },
        onError: (err) => setFormError(err?.response?.data?.message || 'Failed to save KPI template.'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateKpiTemplate(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'kpis'] });
            setDialogOpen(false);
            setEditingId(null);
            setForm(EMPTY_FORM);
            setFormError('');
        },
        onError: (err) => setFormError(err?.response?.data?.message || 'Failed to save KPI template.'),
    });

    const deleteMutation = useMutation({
        mutationFn: deleteKpiTemplate,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'kpis'] });
            setDeleteConfirm(null);
        },
    });

    const kpis = data?.data || [];
    const departments = departmentsData?.data || [];
    const positions = positionsData?.data || [];

    function handleOpenCreate() {
        setEditingId(null);
        setForm(EMPTY_FORM);
        setFormError('');
        setDialogOpen(true);
    }

    function handleOpenEdit(kpi) {
        setEditingId(kpi.id);
        setForm({
            title: kpi.title || '',
            description: kpi.description || '',
            target: kpi.target || '',
            weight: kpi.weight || '',
            category: kpi.category || 'productivity',
            department_id: kpi.department_id ? String(kpi.department_id) : '',
            position_id: kpi.position_id ? String(kpi.position_id) : '',
        });
        setFormError('');
        setDialogOpen(true);
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (!form.title.trim()) {
            setFormError('Title is required.');
            return;
        }
        const payload = {
            ...form,
            department_id: form.department_id || null,
            position_id: form.position_id || null,
            weight: form.weight ? parseFloat(form.weight) : null,
        };
        if (editingId) {
            updateMutation.mutate({ id: editingId, data: payload });
        } else {
            createMutation.mutate(payload);
        }
    }

    const isMutating = createMutation.isPending || updateMutation.isPending;

    return (
        <div>
            <PageHeader
                title="KPI Templates"
                description="Manage key performance indicator templates for reviews."
                action={
                    <Button onClick={handleOpenCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        New KPI Template
                    </Button>
                }
            />

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-wrap gap-3">
                        <select
                            value={departmentFilter}
                            onChange={(e) => setDepartmentFilter(e.target.value)}
                            className="rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                        >
                            <option value="all">All Departments</option>
                            {departments.map((d) => (
                                <option key={d.id} value={String(d.id)}>{d.name}</option>
                            ))}
                        </select>
                        <select
                            value={positionFilter}
                            onChange={(e) => setPositionFilter(e.target.value)}
                            className="rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                        >
                            <option value="all">All Positions</option>
                            {positions.map((p) => (
                                <option key={p.id} value={String(p.id)}>{p.name}</option>
                            ))}
                        </select>
                        <select
                            value={categoryFilter}
                            onChange={(e) => setCategoryFilter(e.target.value)}
                            className="rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                        >
                            <option value="all">All Categories</option>
                            {CATEGORIES.map((c) => (
                                <option key={c} value={c} className="capitalize">{c.charAt(0).toUpperCase() + c.slice(1)}</option>
                            ))}
                        </select>
                        {(departmentFilter !== 'all' || positionFilter !== 'all' || categoryFilter !== 'all') && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setDepartmentFilter('all');
                                    setPositionFilter('all');
                                    setCategoryFilter('all');
                                }}
                            >
                                <X className="mr-1 h-4 w-4" />
                                Clear
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                {isLoading ? (
                    <SkeletonTable />
                ) : isError ? (
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <AlertTriangle className="mb-3 h-10 w-10 text-red-300" />
                        <p className="text-sm font-medium text-zinc-600">Failed to load KPI templates.</p>
                    </CardContent>
                ) : kpis.length === 0 ? (
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <Target className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">No KPI templates found</h3>
                        <p className="mt-1 text-sm text-zinc-500">Create templates to use in performance reviews.</p>
                        <Button className="mt-4" onClick={handleOpenCreate}>
                            <Plus className="mr-1.5 h-4 w-4" />
                            New KPI Template
                        </Button>
                    </CardContent>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Title</TableHead>
                                <TableHead>Target</TableHead>
                                <TableHead>Weight</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Position</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {kpis.map((kpi) => (
                                <TableRow key={kpi.id}>
                                    <TableCell className="font-medium text-zinc-900">
                                        {kpi.title}
                                    </TableCell>
                                    <TableCell className="text-sm text-zinc-600">
                                        {kpi.target || '-'}
                                    </TableCell>
                                    <TableCell className="text-sm text-zinc-600">
                                        {kpi.weight != null ? `${kpi.weight}%` : '-'}
                                    </TableCell>
                                    <TableCell>
                                        <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', CATEGORY_BADGE[kpi.category] || 'bg-zinc-100 text-zinc-600')}>
                                            {kpi.category || '-'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-sm text-zinc-600">
                                        {kpi.position?.name || '-'}
                                    </TableCell>
                                    <TableCell className="text-sm text-zinc-600">
                                        {kpi.department?.name || '-'}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            <Button variant="ghost" size="sm" onClick={() => handleOpenEdit(kpi)}>
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-600 hover:text-red-700"
                                                onClick={() => setDeleteConfirm(kpi)}
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
            </Card>

            {/* Create / Edit Dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingId ? 'Edit KPI Template' : 'New KPI Template'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {formError && (
                            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{formError}</p>
                        )}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Title *</label>
                            <input
                                type="text"
                                value={form.title}
                                onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                                placeholder="e.g. Monthly Sales Target"
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Target</label>
                            <input
                                type="text"
                                value={form.target}
                                onChange={(e) => setForm((f) => ({ ...f, target: e.target.value }))}
                                placeholder="e.g. RM 50,000 per month"
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Weight (%)</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={form.weight}
                                    onChange={(e) => setForm((f) => ({ ...f, weight: e.target.value }))}
                                    placeholder="e.g. 20"
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Category</label>
                                <select
                                    value={form.category}
                                    onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                >
                                    {CATEGORIES.map((c) => (
                                        <option key={c} value={c} className="capitalize">
                                            {c.charAt(0).toUpperCase() + c.slice(1)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Department</label>
                                <select
                                    value={form.department_id}
                                    onChange={(e) => setForm((f) => ({ ...f, department_id: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                >
                                    <option value="">All Departments</option>
                                    {departments.map((d) => (
                                        <option key={d.id} value={String(d.id)}>{d.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Position</label>
                                <select
                                    value={form.position_id}
                                    onChange={(e) => setForm((f) => ({ ...f, position_id: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                >
                                    <option value="">All Positions</option>
                                    {positions.map((p) => (
                                        <option key={p.id} value={String(p.id)}>{p.name}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Description</label>
                            <textarea
                                value={form.description}
                                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                placeholder="Optional description..."
                                rows={3}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={isMutating}>
                                {isMutating && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                {editingId ? 'Save Changes' : 'Create Template'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm Dialog */}
            <Dialog open={!!deleteConfirm} onOpenChange={() => setDeleteConfirm(null)}>
                <DialogContent className="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete KPI Template</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-zinc-600">
                        Are you sure you want to delete <span className="font-medium">{deleteConfirm?.title}</span>? This action cannot be undone.
                    </p>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteConfirm(null)}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={deleteMutation.isPending}
                            onClick={() => deleteMutation.mutate(deleteConfirm.id)}
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
