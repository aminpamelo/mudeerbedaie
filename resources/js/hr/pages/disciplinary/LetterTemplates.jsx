import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Loader2,
    FileText,
} from 'lucide-react';
import {
    fetchLetterTemplates,
    createLetterTemplate,
    updateLetterTemplate,
    deleteLetterTemplate,
} from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/textarea';
import { Checkbox } from '../../components/ui/checkbox';
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
import { cn } from '../../lib/utils';

const TYPE_OPTIONS = [
    { value: 'verbal_warning', label: 'Verbal Warning' },
    { value: 'first_written', label: '1st Written Warning' },
    { value: 'second_written', label: '2nd Written Warning' },
    { value: 'show_cause', label: 'Show Cause' },
    { value: 'termination', label: 'Termination' },
    { value: 'suspension', label: 'Suspension' },
    { value: 'inquiry_notice', label: 'Inquiry Notice' },
];

const TYPE_LABELS = {
    verbal_warning: 'Verbal Warning',
    first_written: '1st Written Warning',
    second_written: '2nd Written Warning',
    show_cause: 'Show Cause',
    termination: 'Termination',
    suspension: 'Suspension',
    inquiry_notice: 'Inquiry Notice',
};

const EMPTY_FORM = {
    name: '',
    type: '',
    content: '',
    is_active: true,
};

export default function LetterTemplates() {
    const queryClient = useQueryClient();
    const [formDialog, setFormDialog] = useState(false);
    const [deleteDialog, setDeleteDialog] = useState({ open: false, id: null, name: '' });
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState({ ...EMPTY_FORM });
    const [errors, setErrors] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'disciplinary', 'letter-templates'],
        queryFn: () => fetchLetterTemplates({ per_page: 100 }),
    });

    const templates = data?.data || [];

    const createMutation = useMutation({
        mutationFn: (data) => createLetterTemplate(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'disciplinary', 'letter-templates'] });
            closeFormDialog();
        },
        onError: (err) => {
            if (err?.response?.data?.errors) {
                setErrors(err.response.data.errors);
            } else {
                alert('Failed to create template: ' + (err?.response?.data?.message || err.message));
            }
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateLetterTemplate(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'disciplinary', 'letter-templates'] });
            closeFormDialog();
        },
        onError: (err) => {
            if (err?.response?.data?.errors) {
                setErrors(err.response.data.errors);
            } else {
                alert('Failed to update template: ' + (err?.response?.data?.message || err.message));
            }
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => deleteLetterTemplate(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'disciplinary', 'letter-templates'] });
            setDeleteDialog({ open: false, id: null, name: '' });
        },
        onError: (err) => {
            alert('Failed to delete template: ' + (err?.response?.data?.message || err.message));
            setDeleteDialog({ open: false, id: null, name: '' });
        },
    });

    function openCreate() {
        setEditingId(null);
        setForm({ ...EMPTY_FORM });
        setErrors({});
        setFormDialog(true);
    }

    function openEdit(template) {
        setEditingId(template.id);
        setForm({
            name: template.name || '',
            type: template.type || '',
            content: template.content || '',
            is_active: template.is_active ?? true,
        });
        setErrors({});
        setFormDialog(true);
    }

    function closeFormDialog() {
        setFormDialog(false);
        setEditingId(null);
        setForm({ ...EMPTY_FORM });
        setErrors({});
    }

    function handleFormChange(field, value) {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const next = { ...prev };
                delete next[field];
                return next;
            });
        }
    }

    function handleSubmit() {
        const newErrors = {};
        if (!form.name.trim()) newErrors.name = ['Name is required.'];
        if (!form.type) newErrors.type = ['Type is required.'];
        if (!form.content.trim()) newErrors.content = ['Content is required.'];

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        const payload = {
            name: form.name,
            type: form.type,
            content: form.content,
            is_active: form.is_active,
        };

        if (editingId) {
            updateMutation.mutate({ id: editingId, data: payload });
        } else {
            createMutation.mutate(payload);
        }
    }

    const isSaving = createMutation.isPending || updateMutation.isPending;

    return (
        <div>
            <PageHeader
                title="Letter Templates"
                description="Manage disciplinary letter templates for warnings, show cause, and other notices."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        New Template
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : templates.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <FileText className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No letter templates</p>
                            <p className="text-xs text-zinc-400">Create your first template to get started</p>
                            <Button className="mt-4" size="sm" onClick={openCreate}>
                                <Plus className="mr-1.5 h-4 w-4" />
                                Create Template
                            </Button>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Active</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {templates.map((template) => (
                                    <TableRow key={template.id}>
                                        <TableCell className="font-medium text-zinc-900">
                                            {template.name}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {TYPE_LABELS[template.type] || template.type}
                                        </TableCell>
                                        <TableCell>
                                            {template.is_active ? (
                                                <Badge variant="success">Active</Badge>
                                            ) : (
                                                <Badge variant="secondary">Inactive</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => openEdit(template)}
                                                    title="Edit"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setDeleteDialog({ open: true, id: template.id, name: template.name })}
                                                    title="Delete"
                                                >
                                                    <Trash2 className="h-4 w-4 text-red-500" />
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

            {/* Create / Edit Dialog */}
            <Dialog open={formDialog} onOpenChange={closeFormDialog}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{editingId ? 'Edit Template' : 'New Template'}</DialogTitle>
                        <DialogDescription>
                            {editingId
                                ? 'Update the letter template details below.'
                                : 'Create a new letter template for disciplinary notices.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label className="mb-1.5 block">Name <span className="text-red-500">*</span></Label>
                            <Input
                                placeholder="e.g. Standard First Written Warning"
                                value={form.name}
                                onChange={(e) => handleFormChange('name', e.target.value)}
                                className={cn(errors.name && 'border-red-500')}
                            />
                            {errors.name && (
                                <p className="mt-1 text-xs text-red-500">{errors.name[0]}</p>
                            )}
                        </div>

                        <div>
                            <Label className="mb-1.5 block">Type <span className="text-red-500">*</span></Label>
                            <Select
                                value={form.type}
                                onValueChange={(v) => handleFormChange('type', v)}
                            >
                                <SelectTrigger className={cn(errors.type && 'border-red-500')}>
                                    <SelectValue placeholder="Select type..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {TYPE_OPTIONS.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.type && (
                                <p className="mt-1 text-xs text-red-500">{errors.type[0]}</p>
                            )}
                        </div>

                        <div>
                            <Label className="mb-1.5 block">Content (HTML) <span className="text-red-500">*</span></Label>
                            <Textarea
                                placeholder="Enter the letter template content. You can use HTML and placeholders like {{employee_name}}, {{incident_date}}, {{reason}}..."
                                value={form.content}
                                onChange={(e) => handleFormChange('content', e.target.value)}
                                rows={10}
                                className={cn('font-mono text-xs', errors.content && 'border-red-500')}
                            />
                            {errors.content && (
                                <p className="mt-1 text-xs text-red-500">{errors.content[0]}</p>
                            )}
                            <p className="mt-1 text-xs text-zinc-400">
                                Available placeholders: {'{{employee_name}}'}, {'{{employee_id}}'}, {'{{department}}'}, {'{{position}}'}, {'{{incident_date}}'}, {'{{reason}}'}, {'{{response_deadline}}'}, {'{{company_name}}'}
                            </p>
                        </div>

                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="is_active"
                                checked={form.is_active}
                                onCheckedChange={(checked) => handleFormChange('is_active', !!checked)}
                            />
                            <Label htmlFor="is_active" className="cursor-pointer">
                                Active
                            </Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeFormDialog}>Cancel</Button>
                        <Button
                            onClick={handleSubmit}
                            disabled={isSaving}
                        >
                            {isSaving && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {editingId ? 'Update Template' : 'Create Template'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, id: null, name: '' })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Template</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete the template "{deleteDialog.name}"? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteDialog({ open: false, id: null, name: '' })}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteDialog.id)}
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
