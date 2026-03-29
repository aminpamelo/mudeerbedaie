import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    Plus,
    Pencil,
    Trash2,
    Eye,
    CheckCircle2,
    Search,
    Loader2,
    GraduationCap,
} from 'lucide-react';
import {
    fetchTrainingPrograms,
    createTrainingProgram,
    updateTrainingProgram,
    deleteTrainingProgram,
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

const EMPTY_FORM = {
    title: '',
    description: '',
    type: 'internal',
    category: 'technical',
    provider: '',
    location: '',
    start_date: '',
    end_date: '',
    max_participants: '',
    estimated_cost: '',
};

const TYPE_OPTIONS = [
    { value: 'internal', label: 'Internal' },
    { value: 'external', label: 'External' },
];

const CATEGORY_OPTIONS = [
    { value: 'mandatory', label: 'Mandatory' },
    { value: 'technical', label: 'Technical' },
    { value: 'soft_skill', label: 'Soft Skill' },
    { value: 'compliance', label: 'Compliance' },
    { value: 'other', label: 'Other' },
];

const STATUS_OPTIONS = [
    { value: '', label: 'All Statuses' },
    { value: 'planned', label: 'Planned' },
    { value: 'ongoing', label: 'Ongoing' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const STATUS_BADGE_CLASS = {
    planned: 'bg-blue-100 text-blue-700',
    ongoing: 'bg-amber-100 text-amber-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-zinc-100 text-zinc-500',
};

const CATEGORY_BADGE_CLASS = {
    mandatory: 'bg-red-100 text-red-700',
    technical: 'bg-blue-100 text-blue-700',
    soft_skill: 'bg-purple-100 text-purple-700',
    compliance: 'bg-amber-100 text-amber-700',
    other: 'bg-zinc-100 text-zinc-600',
};

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

export default function TrainingPrograms() {
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const [search, setSearch] = useState('');
    const [filterType, setFilterType] = useState('');
    const [filterCategory, setFilterCategory] = useState('');
    const [filterStatus, setFilterStatus] = useState('');
    const [formOpen, setFormOpen] = useState(false);
    const [editingProgram, setEditingProgram] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteDialog, setDeleteDialog] = useState({ open: false, program: null });
    const [completeDialog, setCompleteDialog] = useState({ open: false, program: null });
    const [errors, setErrors] = useState({});

    const params = {
        search: search || undefined,
        type: filterType || undefined,
        category: filterCategory || undefined,
        status: filterStatus || undefined,
        per_page: 50,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'training', 'programs', params],
        queryFn: () => fetchTrainingPrograms(params),
    });

    const saveMutation = useMutation({
        mutationFn: (formData) =>
            editingProgram
                ? updateTrainingProgram(editingProgram.id, formData)
                : createTrainingProgram(formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'programs'] });
            setFormOpen(false);
            setEditingProgram(null);
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
        mutationFn: (id) => deleteTrainingProgram(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'programs'] });
            setDeleteDialog({ open: false, program: null });
        },
    });

    const completeMutation = useMutation({
        mutationFn: (id) => completeTrainingProgram(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'programs'] });
            setCompleteDialog({ open: false, program: null });
        },
    });

    const programs = data?.data || [];

    function openCreate() {
        setEditingProgram(null);
        setForm(EMPTY_FORM);
        setErrors({});
        setFormOpen(true);
    }

    function openEdit(program) {
        setEditingProgram(program);
        setForm({
            title: program.title || '',
            description: program.description || '',
            type: program.type || 'internal',
            category: program.category || 'technical',
            provider: program.provider || '',
            location: program.location || '',
            start_date: program.start_date || '',
            end_date: program.end_date || '',
            max_participants: program.max_participants ?? '',
            estimated_cost: program.estimated_cost ?? '',
        });
        setErrors({});
        setFormOpen(true);
    }

    function handleSubmit(e) {
        e.preventDefault();
        saveMutation.mutate({
            ...form,
            max_participants: form.max_participants !== '' ? parseInt(form.max_participants) : null,
            estimated_cost: form.estimated_cost !== '' ? parseFloat(form.estimated_cost) : null,
        });
    }

    return (
        <div>
            <PageHeader
                title="Training Programs"
                description="Manage and schedule employee training programs."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        New Program
                    </Button>
                }
            />

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 min-w-[200px]">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                            <input
                                type="text"
                                placeholder="Search programs..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 py-1.5 pl-9 pr-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <select
                            value={filterType}
                            onChange={(e) => setFilterType(e.target.value)}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            <option value="">All Types</option>
                            {TYPE_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                        <select
                            value={filterCategory}
                            onChange={(e) => setFilterCategory(e.target.value)}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            <option value="">All Categories</option>
                            {CATEGORY_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                        <select
                            value={filterStatus}
                            onChange={(e) => setFilterStatus(e.target.value)}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            {STATUS_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : programs.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <GraduationCap className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No training programs found</p>
                            <p className="mt-1 text-xs text-zinc-400">Create a new program to get started.</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Date Range</TableHead>
                                    <TableHead>Participants</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {programs.map((program) => (
                                    <TableRow key={program.id}>
                                        <TableCell className="font-medium">{program.title}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className="capitalize">
                                                {program.type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <span className={cn(
                                                'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                                CATEGORY_BADGE_CLASS[program.category] || 'bg-zinc-100 text-zinc-600'
                                            )}>
                                                {(program.category || '').replace('_', ' ')}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-500">
                                            {formatDate(program.start_date)} - {formatDate(program.end_date)}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {program.enrollments_count ?? 0}
                                            {program.max_participants ? ` / ${program.max_participants}` : ''}
                                        </TableCell>
                                        <TableCell>
                                            <span className={cn(
                                                'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                                STATUS_BADGE_CLASS[program.status] || 'bg-zinc-100 text-zinc-600'
                                            )}>
                                                {program.status}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => navigate(`/hr/training/programs/${program.id}`)}
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => openEdit(program)}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                {program.status === 'planned' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() => setDeleteDialog({ open: true, program })}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {(program.status === 'planned' || program.status === 'ongoing') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-emerald-600 hover:text-emerald-700"
                                                        onClick={() => setCompleteDialog({ open: true, program })}
                                                    >
                                                        <CheckCircle2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Create/Edit Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingProgram ? 'Edit Training Program' : 'New Training Program'}</DialogTitle>
                        <DialogDescription>
                            {editingProgram
                                ? 'Update the training program details.'
                                : 'Schedule a new training program for employees.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Title *</label>
                            <input
                                type="text"
                                value={form.title}
                                onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                required
                            />
                            {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Description</label>
                            <textarea
                                value={form.description}
                                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                rows={3}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Type *</label>
                                <select
                                    value={form.type}
                                    onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                >
                                    {TYPE_OPTIONS.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Category *</label>
                                <select
                                    value={form.category}
                                    onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                >
                                    {CATEGORY_OPTIONS.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Provider</label>
                                <input
                                    type="text"
                                    value={form.provider}
                                    onChange={(e) => setForm((f) => ({ ...f, provider: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Location</label>
                                <input
                                    type="text"
                                    value={form.location}
                                    onChange={(e) => setForm((f) => ({ ...f, location: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Start Date *</label>
                                <input
                                    type="date"
                                    value={form.start_date}
                                    onChange={(e) => setForm((f) => ({ ...f, start_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                />
                                {errors.start_date && <p className="mt-1 text-xs text-red-600">{errors.start_date[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">End Date *</label>
                                <input
                                    type="date"
                                    value={form.end_date}
                                    onChange={(e) => setForm((f) => ({ ...f, end_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                />
                                {errors.end_date && <p className="mt-1 text-xs text-red-600">{errors.end_date[0]}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Max Participants</label>
                                <input
                                    type="number"
                                    min="1"
                                    value={form.max_participants}
                                    onChange={(e) => setForm((f) => ({ ...f, max_participants: e.target.value }))}
                                    placeholder="Unlimited"
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Estimated Cost (MYR)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.estimated_cost}
                                    onChange={(e) => setForm((f) => ({ ...f, estimated_cost: e.target.value }))}
                                    placeholder="0.00"
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={saveMutation.isPending}>
                                {saveMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                {editingProgram ? 'Update' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, program: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Training Program</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{deleteDialog.program?.title}</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, program: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteDialog.program.id)}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Complete Dialog */}
            <Dialog open={completeDialog.open} onOpenChange={() => setCompleteDialog({ open: false, program: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Complete Training Program</DialogTitle>
                        <DialogDescription>
                            Mark <strong>{completeDialog.program?.title}</strong> as completed? This will finalize the program.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCompleteDialog({ open: false, program: null })}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => completeMutation.mutate(completeDialog.program.id)}
                            disabled={completeMutation.isPending}
                        >
                            {completeMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Complete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
