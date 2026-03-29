import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Globe,
    XCircle,
    Loader2,
    Briefcase,
} from 'lucide-react';
import {
    fetchJobPostings,
    createJobPosting,
    updateJobPosting,
    publishJobPosting,
    closeJobPosting,
    deleteJobPosting,
    fetchDepartments,
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
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Status' },
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'closed', label: 'Closed' },
];

const TYPE_OPTIONS = [
    { value: 'all', label: 'All Types' },
    { value: 'full-time', label: 'Full-time' },
    { value: 'part-time', label: 'Part-time' },
    { value: 'contract', label: 'Contract' },
    { value: 'intern', label: 'Intern' },
];

const STATUS_BADGE = {
    draft: 'bg-zinc-100 text-zinc-600',
    published: 'bg-emerald-100 text-emerald-700',
    closed: 'bg-red-100 text-red-700',
};

const EMPTY_FORM = {
    title: '',
    department_id: '',
    employment_type: 'full-time',
    vacancies: 1,
    description: '',
    requirements: '',
    status: 'draft',
};

function SkeletonTable() {
    return (
        <div className="space-y-3 p-4">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 py-2">
                    <div className="h-4 w-48 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1" />
                    <div className="h-6 w-16 animate-pulse rounded-full bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function JobPostings() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [deptFilter, setDeptFilter] = useState('all');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingPosting, setEditingPosting] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteTarget, setDeleteTarget] = useState(null);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'recruitment', 'job-postings', { search, statusFilter, deptFilter }],
        queryFn: () =>
            fetchJobPostings({
                search: search || undefined,
                status: statusFilter !== 'all' ? statusFilter : undefined,
                department_id: deptFilter !== 'all' ? deptFilter : undefined,
                per_page: 50,
            }),
    });

    const { data: deptsData } = useQuery({
        queryKey: ['hr', 'departments', 'list'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const createMutation = useMutation({
        mutationFn: createJobPosting,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'job-postings'] });
            closeDialog();
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateJobPosting(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'job-postings'] });
            closeDialog();
        },
    });

    const publishMutation = useMutation({
        mutationFn: (id) => publishJobPosting(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'job-postings'] }),
    });

    const closeMutation = useMutation({
        mutationFn: (id) => closeJobPosting(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'job-postings'] }),
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => deleteJobPosting(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'job-postings'] });
            setDeleteTarget(null);
        },
    });

    const postings = data?.data || [];
    const departments = deptsData?.data || [];

    function openCreate() {
        setEditingPosting(null);
        setForm(EMPTY_FORM);
        setDialogOpen(true);
    }

    function openEdit(posting) {
        setEditingPosting(posting);
        setForm({
            title: posting.title || '',
            department_id: posting.department_id ? String(posting.department_id) : '',
            employment_type: posting.employment_type || 'full-time',
            vacancies: posting.vacancies || 1,
            description: posting.description || '',
            requirements: posting.requirements || '',
            status: posting.status || 'draft',
        });
        setDialogOpen(true);
    }

    function closeDialog() {
        setDialogOpen(false);
        setEditingPosting(null);
        setForm(EMPTY_FORM);
    }

    function handleSubmit(e) {
        e.preventDefault();
        const payload = { ...form, vacancies: Number(form.vacancies) };
        if (editingPosting) {
            updateMutation.mutate({ id: editingPosting.id, data: payload });
        } else {
            createMutation.mutate(payload);
        }
    }

    const isSaving = createMutation.isPending || updateMutation.isPending;

    return (
        <div>
            <PageHeader
                title="Job Postings"
                description="Manage open positions and job advertisements."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        New Posting
                    </Button>
                }
            />

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search job title..."
                            className="w-full lg:w-64"
                        />

                        <Select value={deptFilter} onValueChange={setDeptFilter}>
                            <SelectTrigger className="w-full lg:w-44">
                                <SelectValue placeholder="Department" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Departments</SelectItem>
                                {departments.map((dept) => (
                                    <SelectItem key={dept.id} value={String(dept.id)}>
                                        {dept.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={statusFilter} onValueChange={setStatusFilter}>
                            <SelectTrigger className="w-full lg:w-36">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            {isLoading ? (
                <Card>
                    <SkeletonTable />
                </Card>
            ) : postings.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <Briefcase className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">No job postings found</h3>
                        <p className="mt-1 text-sm text-zinc-500">Create your first job posting to start recruiting.</p>
                        <Button className="mt-4" onClick={openCreate}>
                            <Plus className="mr-1.5 h-4 w-4" />
                            New Posting
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Title</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Vacancies</TableHead>
                                <TableHead>Applicants</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {postings.map((posting) => (
                                <TableRow key={posting.id}>
                                    <TableCell className="font-medium">{posting.title}</TableCell>
                                    <TableCell>{posting.department?.name || '-'}</TableCell>
                                    <TableCell className="capitalize">
                                        {posting.employment_type?.replace('-', ' ') || '-'}
                                    </TableCell>
                                    <TableCell>{posting.vacancies ?? '-'}</TableCell>
                                    <TableCell>{posting.applicants_count ?? 0}</TableCell>
                                    <TableCell>
                                        <span
                                            className={cn(
                                                'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                                STATUS_BADGE[posting.status] || 'bg-zinc-100 text-zinc-600'
                                            )}
                                        >
                                            {posting.status}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => openEdit(posting)}
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            {posting.status === 'draft' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-emerald-600 hover:text-emerald-700"
                                                    onClick={() => publishMutation.mutate(posting.id)}
                                                    disabled={publishMutation.isPending}
                                                >
                                                    <Globe className="h-4 w-4" />
                                                </Button>
                                            )}
                                            {posting.status === 'published' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-amber-600 hover:text-amber-700"
                                                    onClick={() => closeMutation.mutate(posting.id)}
                                                    disabled={closeMutation.isPending}
                                                >
                                                    <XCircle className="h-4 w-4" />
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-600 hover:text-red-700"
                                                onClick={() => setDeleteTarget(posting)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            {/* Create/Edit Dialog */}
            <Dialog open={dialogOpen} onOpenChange={closeDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingPosting ? 'Edit Job Posting' : 'New Job Posting'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="space-y-4 py-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="jp-title">Job Title</Label>
                                <Input
                                    id="jp-title"
                                    value={form.title}
                                    onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                                    placeholder="e.g. Senior Software Engineer"
                                    required
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <Label>Department</Label>
                                    <Select
                                        value={form.department_id}
                                        onValueChange={(v) => setForm((f) => ({ ...f, department_id: v }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select department" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {departments.map((dept) => (
                                                <SelectItem key={dept.id} value={String(dept.id)}>
                                                    {dept.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label>Employment Type</Label>
                                    <Select
                                        value={form.employment_type}
                                        onValueChange={(v) => setForm((f) => ({ ...f, employment_type: v }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {TYPE_OPTIONS.filter((o) => o.value !== 'all').map((opt) => (
                                                <SelectItem key={opt.value} value={opt.value}>
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="jp-vacancies">Number of Vacancies</Label>
                                <Input
                                    id="jp-vacancies"
                                    type="number"
                                    min={1}
                                    value={form.vacancies}
                                    onChange={(e) => setForm((f) => ({ ...f, vacancies: e.target.value }))}
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="jp-desc">Description</Label>
                                <textarea
                                    id="jp-desc"
                                    value={form.description}
                                    onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                    rows={3}
                                    placeholder="Job description..."
                                    className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="jp-req">Requirements</Label>
                                <textarea
                                    id="jp-req"
                                    value={form.requirements}
                                    onChange={(e) => setForm((f) => ({ ...f, requirements: e.target.value }))}
                                    rows={3}
                                    placeholder="Candidate requirements..."
                                    className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                        </div>

                        <DialogFooter className="mt-4">
                            <Button type="button" variant="outline" onClick={closeDialog}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={isSaving}>
                                {isSaving && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                {editingPosting ? 'Save Changes' : 'Create Posting'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm Dialog */}
            <Dialog open={!!deleteTarget} onOpenChange={() => setDeleteTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Job Posting</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-zinc-600">
                        Are you sure you want to delete <span className="font-medium">{deleteTarget?.title}</span>?
                        This action cannot be undone.
                    </p>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteTarget.id)}
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
