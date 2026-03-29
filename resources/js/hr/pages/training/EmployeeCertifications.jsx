import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Award, AlertTriangle } from 'lucide-react';
import {
    fetchEmployeeCertifications,
    createEmployeeCertification,
    updateEmployeeCertification,
    deleteEmployeeCertification,
    fetchCertifications,
    fetchEmployees,
} from '../../lib/api';
import { cn } from '../../lib/utils';
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
    employee_id: '',
    certification_id: '',
    certificate_number: '',
    issued_date: '',
    expiry_date: '',
};

const STATUS_BADGE_CLASS = {
    active: 'bg-emerald-100 text-emerald-700',
    expired: 'bg-red-100 text-red-700',
    revoked: 'bg-zinc-100 text-zinc-500',
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getDaysUntilExpiry(expiryDate) {
    if (!expiryDate) return null;
    const now = new Date();
    const expiry = new Date(expiryDate);
    const diff = Math.ceil((expiry - now) / (1000 * 60 * 60 * 24));
    return diff;
}

export default function EmployeeCertifications() {
    const queryClient = useQueryClient();
    const [filterCertification, setFilterCertification] = useState('');
    const [filterStatus, setFilterStatus] = useState('');
    const [formOpen, setFormOpen] = useState(false);
    const [editingRecord, setEditingRecord] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteDialog, setDeleteDialog] = useState({ open: false, record: null });
    const [errors, setErrors] = useState({});

    const params = {
        certification_id: filterCertification || undefined,
        status: filterStatus || undefined,
        per_page: 50,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'training', 'employee-certifications', params],
        queryFn: () => fetchEmployeeCertifications(params),
    });

    const { data: certificationsData } = useQuery({
        queryKey: ['hr', 'training', 'certifications', 'dropdown'],
        queryFn: () => fetchCertifications({ per_page: 100, is_active: true }),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'dropdown'],
        queryFn: () => fetchEmployees({ per_page: 200, status: 'active' }),
    });

    const records = data?.data || [];
    const certifications = certificationsData?.data || [];
    const employees = employeesData?.data || [];

    const saveMutation = useMutation({
        mutationFn: (formData) =>
            editingRecord
                ? updateEmployeeCertification(editingRecord.id, formData)
                : createEmployeeCertification(formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'employee-certifications'] });
            setFormOpen(false);
            setEditingRecord(null);
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
        mutationFn: (id) => deleteEmployeeCertification(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'employee-certifications'] });
            setDeleteDialog({ open: false, record: null });
        },
    });

    function openCreate() {
        setEditingRecord(null);
        setForm(EMPTY_FORM);
        setErrors({});
        setFormOpen(true);
    }

    function openEdit(record) {
        setEditingRecord(record);
        setForm({
            employee_id: record.employee_id || '',
            certification_id: record.certification_id || '',
            certificate_number: record.certificate_number || '',
            issued_date: record.issued_date || '',
            expiry_date: record.expiry_date || '',
        });
        setErrors({});
        setFormOpen(true);
    }

    function handleSubmit(e) {
        e.preventDefault();
        saveMutation.mutate({
            ...form,
            employee_id: parseInt(form.employee_id),
            certification_id: parseInt(form.certification_id),
        });
    }

    function getExpiryRowClass(record) {
        if (record.status === 'expired') return 'bg-red-50/50';
        const days = getDaysUntilExpiry(record.expiry_date);
        if (days !== null && days <= 90 && days > 0) return 'bg-amber-50/50';
        return '';
    }

    return (
        <div>
            <PageHeader
                title="Employee Certifications"
                description="Track employee certifications, expiry dates, and renewal status."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Add Record
                    </Button>
                }
            />

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <select
                            value={filterCertification}
                            onChange={(e) => setFilterCertification(e.target.value)}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            <option value="">All Certifications</option>
                            {certifications.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                        <select
                            value={filterStatus}
                            onChange={(e) => setFilterStatus(e.target.value)}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="revoked">Revoked</option>
                        </select>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : records.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Award className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No employee certifications found</p>
                            <p className="mt-1 text-xs text-zinc-400">Add certification records to track employee qualifications.</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Certification</TableHead>
                                    <TableHead>Certificate #</TableHead>
                                    <TableHead>Issued Date</TableHead>
                                    <TableHead>Expiry Date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {records.map((record) => {
                                    const daysLeft = getDaysUntilExpiry(record.expiry_date);
                                    const isExpiringSoon = daysLeft !== null && daysLeft <= 90 && daysLeft > 0;

                                    return (
                                        <TableRow key={record.id} className={getExpiryRowClass(record)}>
                                            <TableCell className="font-medium">
                                                {record.employee?.full_name || '-'}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {record.certification?.name || '-'}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm text-zinc-500">
                                                {record.certificate_number || '-'}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-500">
                                                {formatDate(record.issued_date)}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                <div className="flex items-center gap-1.5">
                                                    <span className={cn(
                                                        record.status === 'expired' ? 'text-red-600 font-medium' :
                                                        isExpiringSoon ? 'text-amber-600 font-medium' :
                                                        'text-zinc-500'
                                                    )}>
                                                        {formatDate(record.expiry_date)}
                                                    </span>
                                                    {isExpiringSoon && (
                                                        <AlertTriangle className="h-3.5 w-3.5 text-amber-500" />
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <span className={cn(
                                                    'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                                    STATUS_BADGE_CLASS[record.status] || 'bg-zinc-100 text-zinc-600'
                                                )}>
                                                    {record.status}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="sm" onClick={() => openEdit(record)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() => setDeleteDialog({ open: true, record })}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Form Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingRecord ? 'Edit Certification Record' : 'Add Certification Record'}</DialogTitle>
                        <DialogDescription>
                            {editingRecord
                                ? 'Update this employee certification record.'
                                : 'Record a new certification for an employee.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Employee *</label>
                            <select
                                value={form.employee_id}
                                onChange={(e) => setForm((f) => ({ ...f, employee_id: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                required
                            >
                                <option value="">Select Employee</option>
                                {employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>{emp.full_name}</option>
                                ))}
                            </select>
                            {errors.employee_id && <p className="mt-1 text-xs text-red-600">{errors.employee_id[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Certification *</label>
                            <select
                                value={form.certification_id}
                                onChange={(e) => setForm((f) => ({ ...f, certification_id: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                required
                            >
                                <option value="">Select Certification</option>
                                {certifications.map((cert) => (
                                    <option key={cert.id} value={cert.id}>{cert.name}</option>
                                ))}
                            </select>
                            {errors.certification_id && <p className="mt-1 text-xs text-red-600">{errors.certification_id[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Certificate Number</label>
                            <input
                                type="text"
                                value={form.certificate_number}
                                onChange={(e) => setForm((f) => ({ ...f, certificate_number: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                            {errors.certificate_number && <p className="mt-1 text-xs text-red-600">{errors.certificate_number[0]}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Issued Date *</label>
                                <input
                                    type="date"
                                    value={form.issued_date}
                                    onChange={(e) => setForm((f) => ({ ...f, issued_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                />
                                {errors.issued_date && <p className="mt-1 text-xs text-red-600">{errors.issued_date[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Expiry Date</label>
                                <input
                                    type="date"
                                    value={form.expiry_date}
                                    onChange={(e) => setForm((f) => ({ ...f, expiry_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                                {errors.expiry_date && <p className="mt-1 text-xs text-red-600">{errors.expiry_date[0]}</p>}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={saveMutation.isPending}>
                                {saveMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                {editingRecord ? 'Update' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, record: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Certification Record</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this certification record for{' '}
                            <strong>{deleteDialog.record?.employee?.full_name}</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, record: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteDialog.record.id)}
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
