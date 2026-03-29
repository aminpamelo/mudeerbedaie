import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, ShieldCheck } from 'lucide-react';
import {
    fetchCertifications,
    createCertification,
    updateCertification,
    deleteCertification,
} from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
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
    name: '',
    issuing_body: '',
    description: '',
    validity_months: '',
    is_active: true,
};

export default function Certifications() {
    const queryClient = useQueryClient();
    const [formOpen, setFormOpen] = useState(false);
    const [editingCert, setEditingCert] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteDialog, setDeleteDialog] = useState({ open: false, cert: null });
    const [errors, setErrors] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'training', 'certifications'],
        queryFn: () => fetchCertifications({ per_page: 100 }),
    });

    const saveMutation = useMutation({
        mutationFn: (formData) =>
            editingCert
                ? updateCertification(editingCert.id, formData)
                : createCertification(formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'certifications'] });
            setFormOpen(false);
            setEditingCert(null);
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
        mutationFn: (id) => deleteCertification(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'certifications'] });
            setDeleteDialog({ open: false, cert: null });
        },
    });

    const certifications = data?.data || [];

    function openCreate() {
        setEditingCert(null);
        setForm(EMPTY_FORM);
        setErrors({});
        setFormOpen(true);
    }

    function openEdit(cert) {
        setEditingCert(cert);
        setForm({
            name: cert.name || '',
            issuing_body: cert.issuing_body || '',
            description: cert.description || '',
            validity_months: cert.validity_months ?? '',
            is_active: cert.is_active ?? true,
        });
        setErrors({});
        setFormOpen(true);
    }

    function handleSubmit(e) {
        e.preventDefault();
        saveMutation.mutate({
            ...form,
            validity_months: form.validity_months !== '' ? parseInt(form.validity_months) : null,
        });
    }

    return (
        <div>
            <PageHeader
                title="Certifications"
                description="Manage certification types and requirements."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Add Certification
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : certifications.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <ShieldCheck className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No certifications configured</p>
                            <p className="mt-1 text-xs text-zinc-400">Add certification types to track employee qualifications.</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Issuing Body</TableHead>
                                    <TableHead>Validity (months)</TableHead>
                                    <TableHead>Active</TableHead>
                                    <TableHead>Employees</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {certifications.map((cert) => (
                                    <TableRow key={cert.id}>
                                        <TableCell className="font-medium">{cert.name}</TableCell>
                                        <TableCell className="text-sm text-zinc-600">{cert.issuing_body || '-'}</TableCell>
                                        <TableCell className="text-sm">
                                            {cert.validity_months ? `${cert.validity_months} months` : 'No expiry'}
                                        </TableCell>
                                        <TableCell>
                                            {cert.is_active ? (
                                                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                    Active
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-500">
                                                    Inactive
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm">{cert.employee_certifications_count ?? 0}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(cert)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-red-600 hover:text-red-700"
                                                    onClick={() => setDeleteDialog({ open: true, cert })}
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
                </CardContent>
            </Card>

            {/* Form Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingCert ? 'Edit Certification' : 'Add Certification'}</DialogTitle>
                        <DialogDescription>
                            {editingCert
                                ? 'Update this certification type.'
                                : 'Create a new certification type for employee tracking.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Name *</label>
                            <input
                                type="text"
                                value={form.name}
                                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                required
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Issuing Body</label>
                            <input
                                type="text"
                                value={form.issuing_body}
                                onChange={(e) => setForm((f) => ({ ...f, issuing_body: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                            {errors.issuing_body && <p className="mt-1 text-xs text-red-600">{errors.issuing_body[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Description</label>
                            <textarea
                                value={form.description}
                                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                rows={2}
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Validity (months)</label>
                            <input
                                type="number"
                                min="1"
                                value={form.validity_months}
                                onChange={(e) => setForm((f) => ({ ...f, validity_months: e.target.value }))}
                                placeholder="Leave empty for no expiry"
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <div className="flex items-center gap-6">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-zinc-700">
                                <input
                                    type="checkbox"
                                    checked={form.is_active}
                                    onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
                                    className="rounded"
                                />
                                Active
                            </label>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={saveMutation.isPending}>
                                {saveMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                {editingCert ? 'Update' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, cert: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Certification</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{deleteDialog.cert?.name}</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, cert: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteDialog.cert.id)}
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
