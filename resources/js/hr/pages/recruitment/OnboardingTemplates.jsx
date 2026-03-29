import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    GripVertical,
    ClipboardList,
    Loader2,
    X,
} from 'lucide-react';
import {
    fetchOnboardingTemplates,
    createOnboardingTemplate,
    updateOnboardingTemplate,
    deleteOnboardingTemplate,
    fetchDepartments,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '../../components/ui/dialog';

const ROLE_OPTIONS = [
    { value: 'hr', label: 'HR' },
    { value: 'manager', label: 'Manager' },
    { value: 'it', label: 'IT' },
    { value: 'finance', label: 'Finance' },
    { value: 'employee', label: 'Employee' },
];

const EMPTY_ITEM = { title: '', assigned_role: 'hr', due_days: 1 };

const EMPTY_FORM = {
    name: '',
    department_id: '',
    description: '',
    items: [{ ...EMPTY_ITEM }],
};

function SkeletonCards() {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 3 }).map((_, i) => (
                <Card key={i}>
                    <CardContent className="p-6 space-y-3">
                        <div className="h-5 w-40 animate-pulse rounded bg-zinc-200" />
                        <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                        <div className="h-3 w-32 animate-pulse rounded bg-zinc-200" />
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

export default function OnboardingTemplates() {
    const queryClient = useQueryClient();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingTemplate, setEditingTemplate] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteTarget, setDeleteTarget] = useState(null);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'recruitment', 'onboarding', 'templates'],
        queryFn: fetchOnboardingTemplates,
    });

    const { data: deptsData } = useQuery({
        queryKey: ['hr', 'departments', 'list'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const createMutation = useMutation({
        mutationFn: createOnboardingTemplate,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'onboarding', 'templates'] });
            closeDialog();
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateOnboardingTemplate(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'onboarding', 'templates'] });
            closeDialog();
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => deleteOnboardingTemplate(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'onboarding', 'templates'] });
            setDeleteTarget(null);
        },
    });

    const templates = data?.data || [];
    const departments = deptsData?.data || [];
    const isSaving = createMutation.isPending || updateMutation.isPending;

    function openCreate() {
        setEditingTemplate(null);
        setForm(EMPTY_FORM);
        setDialogOpen(true);
    }

    function openEdit(template) {
        setEditingTemplate(template);
        setForm({
            name: template.name || '',
            department_id: template.department_id ? String(template.department_id) : '',
            description: template.description || '',
            items: template.items?.length
                ? template.items.map((item) => ({
                    title: item.title || '',
                    assigned_role: item.assigned_role || 'hr',
                    due_days: item.due_days || 1,
                }))
                : [{ ...EMPTY_ITEM }],
        });
        setDialogOpen(true);
    }

    function closeDialog() {
        setDialogOpen(false);
        setEditingTemplate(null);
        setForm(EMPTY_FORM);
    }

    function addItem() {
        setForm((f) => ({ ...f, items: [...f.items, { ...EMPTY_ITEM }] }));
    }

    function removeItem(index) {
        setForm((f) => ({
            ...f,
            items: f.items.filter((_, i) => i !== index),
        }));
    }

    function updateItem(index, field, value) {
        setForm((f) => ({
            ...f,
            items: f.items.map((item, i) =>
                i === index ? { ...item, [field]: value } : item
            ),
        }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        const payload = {
            ...form,
            items: form.items.map((item) => ({ ...item, due_days: Number(item.due_days) })),
        };
        if (editingTemplate) {
            updateMutation.mutate({ id: editingTemplate.id, data: payload });
        } else {
            createMutation.mutate(payload);
        }
    }

    return (
        <div>
            <PageHeader
                title="Onboarding Templates"
                description="Create and manage onboarding checklists for new hires."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        New Template
                    </Button>
                }
            />

            {isLoading ? (
                <SkeletonCards />
            ) : templates.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <ClipboardList className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">No onboarding templates yet</h3>
                        <p className="mt-1 text-sm text-zinc-500">
                            Create a template to define the tasks new hires need to complete.
                        </p>
                        <Button className="mt-4" onClick={openCreate}>
                            <Plus className="mr-1.5 h-4 w-4" />
                            New Template
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {templates.map((template) => (
                        <Card key={template.id} className="flex flex-col">
                            <CardContent className="flex flex-1 flex-col p-6">
                                <div className="flex items-start justify-between">
                                    <div className="flex-1 min-w-0">
                                        <h3 className="truncate text-base font-semibold text-zinc-900">
                                            {template.name}
                                        </h3>
                                        {template.department && (
                                            <p className="mt-0.5 text-sm text-zinc-500">{template.department.name}</p>
                                        )}
                                    </div>
                                    <div className="ml-2 flex items-center gap-1 shrink-0">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8"
                                            onClick={() => openEdit(template)}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8 text-red-600 hover:text-red-700"
                                            onClick={() => setDeleteTarget(template)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                {template.description && (
                                    <p className="mt-2 line-clamp-2 text-sm text-zinc-500">{template.description}</p>
                                )}

                                <div className="mt-4 flex-1">
                                    <p className="mb-2 text-xs font-medium text-zinc-500 uppercase tracking-wide">
                                        Tasks ({template.items?.length || 0})
                                    </p>
                                    {template.items?.slice(0, 4).map((item, i) => (
                                        <div key={i} className="mb-1.5 flex items-center gap-2 text-sm">
                                            <div className="h-1.5 w-1.5 shrink-0 rounded-full bg-zinc-400" />
                                            <span className="flex-1 truncate text-zinc-700">{item.title}</span>
                                            <span className="shrink-0 text-xs text-zinc-400">Day {item.due_days}</span>
                                        </div>
                                    ))}
                                    {(template.items?.length || 0) > 4 && (
                                        <p className="mt-1 text-xs text-zinc-400">
                                            +{template.items.length - 4} more tasks
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {/* Create/Edit Dialog */}
            <Dialog open={dialogOpen} onOpenChange={closeDialog}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editingTemplate ? 'Edit Template' : 'New Onboarding Template'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="space-y-4 py-2">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="tmpl-name">Template Name</Label>
                                    <Input
                                        id="tmpl-name"
                                        value={form.name}
                                        onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                                        placeholder="e.g. Standard Onboarding"
                                        required
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label>Department (optional)</Label>
                                    <Select
                                        value={form.department_id}
                                        onValueChange={(v) => setForm((f) => ({ ...f, department_id: v }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All departments" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">All departments</SelectItem>
                                            {departments.map((dept) => (
                                                <SelectItem key={dept.id} value={String(dept.id)}>
                                                    {dept.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="tmpl-desc">Description</Label>
                                <textarea
                                    id="tmpl-desc"
                                    value={form.description}
                                    onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                    rows={2}
                                    placeholder="Brief description of this template..."
                                    className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>

                            {/* Items */}
                            <div>
                                <div className="mb-3 flex items-center justify-between">
                                    <Label>Checklist Items</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addItem}
                                    >
                                        <Plus className="mr-1 h-3.5 w-3.5" />
                                        Add Item
                                    </Button>
                                </div>

                                {form.items.length === 0 ? (
                                    <div className="rounded-lg border border-dashed border-zinc-300 py-6 text-center">
                                        <p className="text-sm text-zinc-400">No items yet. Add your first checklist item.</p>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {form.items.map((item, index) => (
                                            <div
                                                key={index}
                                                className="flex items-start gap-2 rounded-lg border border-zinc-200 bg-zinc-50 p-3"
                                            >
                                                <GripVertical className="mt-2 h-4 w-4 shrink-0 text-zinc-400" />

                                                <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                                                    <Input
                                                        value={item.title}
                                                        onChange={(e) => updateItem(index, 'title', e.target.value)}
                                                        placeholder="Task title"
                                                        className="flex-1"
                                                        required
                                                    />

                                                    <Select
                                                        value={item.assigned_role}
                                                        onValueChange={(v) => updateItem(index, 'assigned_role', v)}
                                                    >
                                                        <SelectTrigger className="w-full sm:w-32">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {ROLE_OPTIONS.map((opt) => (
                                                                <SelectItem key={opt.value} value={opt.value}>
                                                                    {opt.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>

                                                    <div className="flex items-center gap-1">
                                                        <Input
                                                            type="number"
                                                            min={1}
                                                            value={item.due_days}
                                                            onChange={(e) => updateItem(index, 'due_days', e.target.value)}
                                                            className="w-20"
                                                        />
                                                        <span className="shrink-0 text-xs text-zinc-500">days</span>
                                                    </div>
                                                </div>

                                                <button
                                                    type="button"
                                                    onClick={() => removeItem(index)}
                                                    className="mt-2 shrink-0 text-zinc-400 hover:text-red-500 transition-colors"
                                                >
                                                    <X className="h-4 w-4" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        <DialogFooter className="mt-4">
                            <Button type="button" variant="outline" onClick={closeDialog}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={isSaving}>
                                {isSaving && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                {editingTemplate ? 'Save Changes' : 'Create Template'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm Dialog */}
            <Dialog open={!!deleteTarget} onOpenChange={() => setDeleteTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Template</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-zinc-600">
                        Are you sure you want to delete{' '}
                        <span className="font-medium">{deleteTarget?.name}</span>? This action cannot be undone.
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
