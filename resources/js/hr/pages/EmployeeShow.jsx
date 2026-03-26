import { useState, useMemo } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ArrowLeft,
    Pencil,
    MoreHorizontal,
    RefreshCw,
    Trash2,
    Eye,
    EyeOff,
    Upload,
    Download,
    Plus,
    Building2,
    TrendingUp,
    FileText,
    Phone,
    Mail,
    MapPin,
    Calendar,
    User,
    Clock,
} from 'lucide-react';
import {
    fetchEmployee,
    fetchEmployeeHistory,
    fetchEmployeeDocuments,
    uploadEmployeeDocument,
    deleteEmployeeDocument,
    fetchEmergencyContacts,
    createEmergencyContact,
    updateEmergencyContact,
    deleteEmergencyContact,
    updateEmployeeStatus,
    deleteEmployee,
} from '../lib/api';
import { cn } from '../lib/utils';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../components/ui/dialog';
import StatusBadge from '../components/StatusBadge';
import ConfirmDialog from '../components/ConfirmDialog';

function getInitials(name) {
    if (!name) return '?';
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function calculateTenure(joinDate) {
    if (!joinDate) return '';
    const join = new Date(joinDate);
    const now = new Date();
    let years = now.getFullYear() - join.getFullYear();
    let months = now.getMonth() - join.getMonth();
    if (months < 0) {
        years--;
        months += 12;
    }
    const parts = [];
    if (years > 0) parts.push(`${years} year${years !== 1 ? 's' : ''}`);
    if (months > 0) parts.push(`${months} month${months !== 1 ? 's' : ''}`);
    return parts.join(' ') || 'Less than a month';
}

function calculateAge(dob) {
    if (!dob) return '';
    const birth = new Date(dob);
    const now = new Date();
    let age = now.getFullYear() - birth.getFullYear();
    const m = now.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) {
        age--;
    }
    return `${age} years old`;
}

function maskIc(ic) {
    if (!ic || ic.length < 6) return ic || '-';
    return ic.slice(0, -4) + '****';
}

function maskAccount(account) {
    if (!account || account.length < 4) return account || '-';
    return '****' + account.slice(-4);
}

function formatFileSize(bytes) {
    if (!bytes) return '-';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function InfoRow({ label, value }) {
    return (
        <div className="py-3">
            <dt className="text-sm font-medium text-zinc-500">{label}</dt>
            <dd className="mt-1 text-sm text-zinc-900">{value || '-'}</dd>
        </div>
    );
}

const TABS = [
    { id: 'personal', label: 'Personal Info' },
    { id: 'employment', label: 'Employment' },
    { id: 'bank', label: 'Bank & Statutory' },
    { id: 'documents', label: 'Documents' },
    { id: 'contacts', label: 'Emergency Contacts' },
    { id: 'history', label: 'History' },
];

const DOCUMENT_TYPES = [
    'IC Copy',
    'Resume',
    'Offer Letter',
    'Contract',
    'Certificate',
    'Academic Transcript',
    'Medical Report',
    'Other',
];

const STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
    { value: 'probation', label: 'Probation' },
    { value: 'resigned', label: 'Resigned' },
    { value: 'terminated', label: 'Terminated' },
    { value: 'on_leave', label: 'On Leave' },
];

const CHANGE_TYPE_CONFIG = {
    status_change: { icon: RefreshCw, color: 'text-blue-600 bg-blue-100' },
    department_transfer: { icon: Building2, color: 'text-purple-600 bg-purple-100' },
    position_change: { icon: TrendingUp, color: 'text-emerald-600 bg-emerald-100' },
    promotion: { icon: TrendingUp, color: 'text-emerald-600 bg-emerald-100' },
};

export default function EmployeeShow() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [activeTab, setActiveTab] = useState('personal');
    const [showIc, setShowIc] = useState(false);
    const [showAccount, setShowAccount] = useState(false);

    // Dialogs
    const [showStatusDialog, setShowStatusDialog] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [showUploadDialog, setShowUploadDialog] = useState(false);
    const [showContactDialog, setShowContactDialog] = useState(false);
    const [showDeleteDocDialog, setShowDeleteDocDialog] = useState(false);
    const [showDeleteContactDialog, setShowDeleteContactDialog] = useState(false);
    const [showActionsMenu, setShowActionsMenu] = useState(false);

    // Form state
    const [statusForm, setStatusForm] = useState({ status: '', effective_date: '', remarks: '' });
    const [uploadForm, setUploadForm] = useState({ document_type: '', file: null, notes: '' });
    const [contactForm, setContactForm] = useState({ name: '', relationship: '', phone: '', address: '' });
    const [editingContactId, setEditingContactId] = useState(null);
    const [deletingDocId, setDeletingDocId] = useState(null);
    const [deletingContactId, setDeletingContactId] = useState(null);

    // Queries
    const { data: employee, isLoading } = useQuery({
        queryKey: ['hr', 'employee', id],
        queryFn: () => fetchEmployee(id),
    });

    const { data: documents = [] } = useQuery({
        queryKey: ['hr', 'employee', id, 'documents'],
        queryFn: () => fetchEmployeeDocuments(id),
        enabled: activeTab === 'documents',
    });

    const { data: contacts = [] } = useQuery({
        queryKey: ['hr', 'employee', id, 'contacts'],
        queryFn: () => fetchEmergencyContacts(id),
        enabled: activeTab === 'contacts',
    });

    const { data: history = [] } = useQuery({
        queryKey: ['hr', 'employee', id, 'history'],
        queryFn: () => fetchEmployeeHistory(id),
        enabled: activeTab === 'history',
    });

    // Mutations
    const statusMutation = useMutation({
        mutationFn: (data) => updateEmployeeStatus(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'employee', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'employee', id, 'history'] });
            setShowStatusDialog(false);
            setStatusForm({ status: '', effective_date: '', remarks: '' });
        },
    });

    const deleteMutation = useMutation({
        mutationFn: () => deleteEmployee(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'employees'] });
            navigate('/employees');
        },
    });

    const uploadMutation = useMutation({
        mutationFn: (formData) => uploadEmployeeDocument(id, formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'employee', id, 'documents'] });
            setShowUploadDialog(false);
            setUploadForm({ document_type: '', file: null, notes: '' });
        },
    });

    const deleteDocMutation = useMutation({
        mutationFn: (docId) => deleteEmployeeDocument(id, docId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'employee', id, 'documents'] });
            setShowDeleteDocDialog(false);
            setDeletingDocId(null);
        },
    });

    const createContactMutation = useMutation({
        mutationFn: (data) => createEmergencyContact(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'employee', id, 'contacts'] });
            resetContactDialog();
        },
    });

    const updateContactMutation = useMutation({
        mutationFn: ({ contactId, data }) => updateEmergencyContact(id, contactId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'employee', id, 'contacts'] });
            resetContactDialog();
        },
    });

    const deleteContactMutation = useMutation({
        mutationFn: (contactId) => deleteEmergencyContact(id, contactId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'employee', id, 'contacts'] });
            setShowDeleteContactDialog(false);
            setDeletingContactId(null);
        },
    });

    function resetContactDialog() {
        setShowContactDialog(false);
        setContactForm({ name: '', relationship: '', phone: '', address: '' });
        setEditingContactId(null);
    }

    function handleStatusSubmit(e) {
        e.preventDefault();
        statusMutation.mutate(statusForm);
    }

    function handleUploadSubmit(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('document_type', uploadForm.document_type);
        formData.append('file', uploadForm.file);
        if (uploadForm.notes) formData.append('notes', uploadForm.notes);
        uploadMutation.mutate(formData);
    }

    function handleContactSubmit(e) {
        e.preventDefault();
        if (editingContactId) {
            updateContactMutation.mutate({ contactId: editingContactId, data: contactForm });
        } else {
            createContactMutation.mutate(contactForm);
        }
    }

    function openEditContact(contact) {
        setContactForm({
            name: contact.name || '',
            relationship: contact.relationship || '',
            phone: contact.phone || '',
            address: contact.address || '',
        });
        setEditingContactId(contact.id);
        setShowContactDialog(true);
    }

    const emp = employee?.data || employee || {};

    const fullName = [emp.first_name, emp.last_name].filter(Boolean).join(' ') || emp.name || '';

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-20">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-zinc-300 border-t-zinc-900" />
            </div>
        );
    }

    if (!emp.id) {
        return (
            <div className="py-20 text-center">
                <p className="text-zinc-500">Employee not found.</p>
                <Link to="/employees" className="mt-4 inline-block text-sm text-zinc-900 underline">
                    Back to Employees
                </Link>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Back link */}
            <Link
                to="/employees"
                className="inline-flex items-center gap-1.5 text-sm text-zinc-500 hover:text-zinc-900 transition-colors"
            >
                <ArrowLeft className="h-4 w-4" />
                Back to Employees
            </Link>

            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex items-start gap-4">
                    {/* Avatar */}
                    <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-lg font-semibold text-white">
                        {emp.photo_url ? (
                            <img
                                src={emp.photo_url}
                                alt={fullName}
                                className="h-16 w-16 rounded-full object-cover"
                            />
                        ) : (
                            getInitials(fullName)
                        )}
                    </div>
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight text-zinc-900">
                            {fullName}
                        </h1>
                        <p className="text-sm text-zinc-500">
                            {[emp.employee_id, emp.department?.name, emp.position?.name]
                                .filter(Boolean)
                                .join(' \u00B7 ')}
                        </p>
                        <div className="flex items-center gap-2">
                            <StatusBadge status={emp.status} />
                        </div>
                        {emp.join_date && (
                            <p className="text-xs text-zinc-400">
                                Joined {formatDate(emp.join_date)} &middot; {calculateTenure(emp.join_date)}
                            </p>
                        )}
                    </div>
                </div>

                {/* Actions */}
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link to={`/employees/${id}/edit`}>
                            <Pencil className="h-4 w-4" />
                            Edit
                        </Link>
                    </Button>
                    <div className="relative">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => setShowActionsMenu(!showActionsMenu)}
                        >
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                        {showActionsMenu && (
                            <>
                                <div
                                    className="fixed inset-0 z-40"
                                    onClick={() => setShowActionsMenu(false)}
                                />
                                <div className="absolute right-0 z-50 mt-2 w-48 rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                                    <button
                                        className="flex w-full items-center gap-2 px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100"
                                        onClick={() => {
                                            setShowActionsMenu(false);
                                            setStatusForm({
                                                status: emp.status || '',
                                                effective_date: new Date().toISOString().split('T')[0],
                                                remarks: '',
                                            });
                                            setShowStatusDialog(true);
                                        }}
                                    >
                                        <RefreshCw className="h-4 w-4" />
                                        Change Status
                                    </button>
                                    <button
                                        className="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                                        onClick={() => {
                                            setShowActionsMenu(false);
                                            setShowDeleteDialog(true);
                                        }}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        Delete Employee
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </div>

            {/* Tabs */}
            <div className="border-b border-zinc-200">
                <nav className="-mb-px flex gap-4 overflow-x-auto" aria-label="Tabs">
                    {TABS.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={cn(
                                'whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors',
                                activeTab === tab.id
                                    ? 'border-zinc-900 text-zinc-900'
                                    : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700'
                            )}
                        >
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Tab Content */}
            <div>
                {activeTab === 'personal' && <PersonalTab employee={emp} showIc={showIc} setShowIc={setShowIc} />}
                {activeTab === 'employment' && <EmploymentTab employee={emp} />}
                {activeTab === 'bank' && (
                    <BankTab employee={emp} showAccount={showAccount} setShowAccount={setShowAccount} />
                )}
                {activeTab === 'documents' && (
                    <DocumentsTab
                        documents={documents?.data || documents || []}
                        onUpload={() => setShowUploadDialog(true)}
                        onDelete={(docId) => {
                            setDeletingDocId(docId);
                            setShowDeleteDocDialog(true);
                        }}
                    />
                )}
                {activeTab === 'contacts' && (
                    <ContactsTab
                        contacts={contacts?.data || contacts || []}
                        onAdd={() => {
                            setContactForm({ name: '', relationship: '', phone: '', address: '' });
                            setEditingContactId(null);
                            setShowContactDialog(true);
                        }}
                        onEdit={openEditContact}
                        onDelete={(contactId) => {
                            setDeletingContactId(contactId);
                            setShowDeleteContactDialog(true);
                        }}
                    />
                )}
                {activeTab === 'history' && <HistoryTab history={history?.data || history || []} />}
            </div>

            {/* Status Change Dialog */}
            <Dialog open={showStatusDialog} onOpenChange={setShowStatusDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Change Employee Status</DialogTitle>
                        <DialogDescription>
                            Update the employment status for {fullName}.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleStatusSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label>New Status</Label>
                            <Select
                                value={statusForm.status}
                                onValueChange={(val) =>
                                    setStatusForm((prev) => ({ ...prev, status: val }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
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
                        <div className="space-y-2">
                            <Label>Effective Date</Label>
                            <Input
                                type="date"
                                value={statusForm.effective_date}
                                onChange={(e) =>
                                    setStatusForm((prev) => ({
                                        ...prev,
                                        effective_date: e.target.value,
                                    }))
                                }
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Remarks</Label>
                            <textarea
                                className="flex min-h-[80px] w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 focus-visible:ring-offset-2"
                                value={statusForm.remarks}
                                onChange={(e) =>
                                    setStatusForm((prev) => ({
                                        ...prev,
                                        remarks: e.target.value,
                                    }))
                                }
                                placeholder="Optional remarks..."
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowStatusDialog(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={statusMutation.isPending || !statusForm.status}>
                                {statusMutation.isPending ? 'Updating...' : 'Update Status'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Employee Confirm */}
            <ConfirmDialog
                open={showDeleteDialog}
                onOpenChange={setShowDeleteDialog}
                title="Delete Employee"
                description={`Are you sure you want to delete ${fullName}? This action cannot be undone.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={deleteMutation.isPending}
                onConfirm={() => deleteMutation.mutate()}
            />

            {/* Upload Document Dialog */}
            <Dialog open={showUploadDialog} onOpenChange={setShowUploadDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Upload Document</DialogTitle>
                        <DialogDescription>
                            Upload a document for {fullName}.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleUploadSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label>Document Type</Label>
                            <Select
                                value={uploadForm.document_type}
                                onValueChange={(val) =>
                                    setUploadForm((prev) => ({ ...prev, document_type: val }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {DOCUMENT_TYPES.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>File</Label>
                            <Input
                                type="file"
                                onChange={(e) =>
                                    setUploadForm((prev) => ({
                                        ...prev,
                                        file: e.target.files[0] || null,
                                    }))
                                }
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Notes</Label>
                            <textarea
                                className="flex min-h-[80px] w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 focus-visible:ring-offset-2"
                                value={uploadForm.notes}
                                onChange={(e) =>
                                    setUploadForm((prev) => ({ ...prev, notes: e.target.value }))
                                }
                                placeholder="Optional notes..."
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowUploadDialog(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    uploadMutation.isPending ||
                                    !uploadForm.document_type ||
                                    !uploadForm.file
                                }
                            >
                                {uploadMutation.isPending ? 'Uploading...' : 'Upload'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Document Confirm */}
            <ConfirmDialog
                open={showDeleteDocDialog}
                onOpenChange={setShowDeleteDocDialog}
                title="Delete Document"
                description="Are you sure you want to delete this document? This action cannot be undone."
                confirmLabel="Delete"
                variant="destructive"
                loading={deleteDocMutation.isPending}
                onConfirm={() => deleteDocMutation.mutate(deletingDocId)}
            />

            {/* Contact Add/Edit Dialog */}
            <Dialog open={showContactDialog} onOpenChange={(open) => !open && resetContactDialog()}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingContactId ? 'Edit Emergency Contact' : 'Add Emergency Contact'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingContactId
                                ? 'Update the emergency contact details.'
                                : 'Add a new emergency contact for this employee.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleContactSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label>Name</Label>
                            <Input
                                value={contactForm.name}
                                onChange={(e) =>
                                    setContactForm((prev) => ({ ...prev, name: e.target.value }))
                                }
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Relationship</Label>
                            <Input
                                value={contactForm.relationship}
                                onChange={(e) =>
                                    setContactForm((prev) => ({
                                        ...prev,
                                        relationship: e.target.value,
                                    }))
                                }
                                placeholder="e.g. Spouse, Parent, Sibling"
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Phone</Label>
                            <Input
                                value={contactForm.phone}
                                onChange={(e) =>
                                    setContactForm((prev) => ({ ...prev, phone: e.target.value }))
                                }
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Address</Label>
                            <textarea
                                className="flex min-h-[80px] w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 focus-visible:ring-offset-2"
                                value={contactForm.address}
                                onChange={(e) =>
                                    setContactForm((prev) => ({
                                        ...prev,
                                        address: e.target.value,
                                    }))
                                }
                                placeholder="Full address"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={resetContactDialog}>
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    createContactMutation.isPending ||
                                    updateContactMutation.isPending ||
                                    !contactForm.name ||
                                    !contactForm.phone
                                }
                            >
                                {createContactMutation.isPending || updateContactMutation.isPending
                                    ? 'Saving...'
                                    : editingContactId
                                      ? 'Update'
                                      : 'Add Contact'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Contact Confirm */}
            <ConfirmDialog
                open={showDeleteContactDialog}
                onOpenChange={setShowDeleteContactDialog}
                title="Delete Emergency Contact"
                description="Are you sure you want to remove this emergency contact?"
                confirmLabel="Delete"
                variant="destructive"
                loading={deleteContactMutation.isPending}
                onConfirm={() => deleteContactMutation.mutate(deletingContactId)}
            />
        </div>
    );
}

/* ======================== Tab Components ======================== */

function PersonalTab({ employee, showIc, setShowIc }) {
    const fullName =
        [employee.first_name, employee.last_name].filter(Boolean).join(' ') ||
        employee.name ||
        '';
    const fullAddress = [
        employee.address_line_1,
        employee.address_line_2,
        employee.postcode,
        employee.city,
        employee.state,
    ]
        .filter(Boolean)
        .join(', ');

    return (
        <Card>
            <CardHeader>
                <CardTitle>Personal Information</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="grid grid-cols-1 gap-x-8 gap-y-1 sm:grid-cols-2">
                    <InfoRow label="Full Name" value={fullName} />
                    <InfoRow
                        label="IC Number"
                        value={
                            <span className="flex items-center gap-2">
                                {showIc ? employee.ic_number || '-' : maskIc(employee.ic_number)}
                                {employee.ic_number && (
                                    <button
                                        type="button"
                                        onClick={() => setShowIc(!showIc)}
                                        className="text-zinc-400 hover:text-zinc-700"
                                    >
                                        {showIc ? (
                                            <EyeOff className="h-4 w-4" />
                                        ) : (
                                            <Eye className="h-4 w-4" />
                                        )}
                                    </button>
                                )}
                            </span>
                        }
                    />
                    <InfoRow
                        label="Date of Birth"
                        value={
                            employee.date_of_birth
                                ? `${formatDate(employee.date_of_birth)} (${calculateAge(employee.date_of_birth)})`
                                : '-'
                        }
                    />
                    <InfoRow label="Gender" value={employee.gender} />
                    <InfoRow label="Religion" value={employee.religion} />
                    <InfoRow label="Race" value={employee.race} />
                    <InfoRow label="Marital Status" value={employee.marital_status} />
                    <InfoRow label="Phone" value={employee.phone} />
                    <InfoRow label="Personal Email" value={employee.personal_email} />
                    <div className="sm:col-span-2">
                        <InfoRow label="Address" value={fullAddress} />
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}

function EmploymentTab({ employee }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Employment Details</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="grid grid-cols-1 gap-x-8 gap-y-1 sm:grid-cols-2">
                    <InfoRow label="Employee ID" value={employee.employee_id} />
                    <InfoRow label="Department" value={employee.department?.name} />
                    <InfoRow label="Position" value={employee.position?.name} />
                    <InfoRow label="Employment Type" value={employee.employment_type} />
                    <InfoRow label="Join Date" value={formatDate(employee.join_date)} />
                    <InfoRow label="Probation End" value={formatDate(employee.probation_end_date)} />
                    <InfoRow label="Confirmation Date" value={formatDate(employee.confirmation_date)} />
                    <InfoRow label="Contract End" value={formatDate(employee.contract_end_date)} />
                    <InfoRow
                        label="Status"
                        value={<StatusBadge status={employee.status} />}
                    />
                    <InfoRow label="Work Email" value={employee.work_email} />
                    <div className="sm:col-span-2">
                        <InfoRow label="Notes" value={employee.notes} />
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}

function BankTab({ employee, showAccount, setShowAccount }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Bank & Statutory Information</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="grid grid-cols-1 gap-x-8 gap-y-1 sm:grid-cols-2">
                    <InfoRow label="Bank Name" value={employee.bank_name} />
                    <InfoRow
                        label="Account Number"
                        value={
                            <span className="flex items-center gap-2">
                                {showAccount
                                    ? employee.bank_account_number || '-'
                                    : maskAccount(employee.bank_account_number)}
                                {employee.bank_account_number && (
                                    <button
                                        type="button"
                                        onClick={() => setShowAccount(!showAccount)}
                                        className="text-zinc-400 hover:text-zinc-700"
                                    >
                                        {showAccount ? (
                                            <EyeOff className="h-4 w-4" />
                                        ) : (
                                            <Eye className="h-4 w-4" />
                                        )}
                                    </button>
                                )}
                            </span>
                        }
                    />
                    <InfoRow label="EPF Number" value={employee.epf_number} />
                    <InfoRow label="SOCSO Number" value={employee.socso_number} />
                    <InfoRow label="Tax Reference" value={employee.tax_number} />
                </dl>
            </CardContent>
        </Card>
    );
}

function DocumentsTab({ documents, onUpload, onDelete }) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Documents</CardTitle>
                <Button size="sm" onClick={onUpload}>
                    <Upload className="h-4 w-4" />
                    Upload Document
                </Button>
            </CardHeader>
            <CardContent>
                {documents.length === 0 ? (
                    <div className="py-8 text-center text-sm text-zinc-500">
                        <FileText className="mx-auto h-8 w-8 text-zinc-300" />
                        <p className="mt-2">No documents uploaded yet.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-zinc-200 text-left">
                                    <th className="pb-3 pr-4 font-medium text-zinc-500">Type</th>
                                    <th className="pb-3 pr-4 font-medium text-zinc-500">File Name</th>
                                    <th className="pb-3 pr-4 font-medium text-zinc-500">Upload Date</th>
                                    <th className="pb-3 pr-4 font-medium text-zinc-500">Size</th>
                                    <th className="pb-3 font-medium text-zinc-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {documents.map((doc) => (
                                    <tr key={doc.id} className="border-b border-zinc-100">
                                        <td className="py-3 pr-4">
                                            <Badge variant="secondary">{doc.document_type}</Badge>
                                        </td>
                                        <td className="py-3 pr-4 text-zinc-900">
                                            {doc.original_name || doc.file_name}
                                        </td>
                                        <td className="py-3 pr-4 text-zinc-500">
                                            {formatDate(doc.created_at)}
                                        </td>
                                        <td className="py-3 pr-4 text-zinc-500">
                                            {formatFileSize(doc.file_size)}
                                        </td>
                                        <td className="py-3">
                                            <div className="flex items-center gap-2">
                                                {doc.download_url && (
                                                    <a
                                                        href={doc.download_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-zinc-500 hover:text-zinc-900"
                                                    >
                                                        <Download className="h-4 w-4" />
                                                    </a>
                                                )}
                                                <button
                                                    className="text-zinc-500 hover:text-red-600"
                                                    onClick={() => onDelete(doc.id)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ContactsTab({ contacts, onAdd, onEdit, onDelete }) {
    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-zinc-900">Emergency Contacts</h3>
                <Button size="sm" onClick={onAdd}>
                    <Plus className="h-4 w-4" />
                    Add Contact
                </Button>
            </div>
            {contacts.length === 0 ? (
                <Card>
                    <CardContent className="py-8 text-center text-sm text-zinc-500">
                        <Phone className="mx-auto h-8 w-8 text-zinc-300" />
                        <p className="mt-2">No emergency contacts added yet.</p>
                    </CardContent>
                </Card>
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {contacts.map((contact) => (
                        <Card key={contact.id}>
                            <CardContent className="p-5">
                                <div className="flex items-start justify-between">
                                    <div className="space-y-2">
                                        <div>
                                            <p className="font-medium text-zinc-900">
                                                {contact.name}
                                            </p>
                                            <p className="text-sm text-zinc-500">
                                                {contact.relationship}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-1.5 text-sm text-zinc-600">
                                            <Phone className="h-3.5 w-3.5" />
                                            {contact.phone}
                                        </div>
                                        {contact.address && (
                                            <div className="flex items-start gap-1.5 text-sm text-zinc-600">
                                                <MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                                {contact.address}
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <button
                                            className="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700"
                                            onClick={() => onEdit(contact)}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                        <button
                                            className="rounded p-1 text-zinc-400 hover:bg-red-50 hover:text-red-600"
                                            onClick={() => onDelete(contact.id)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}

function HistoryTab({ history }) {
    if (!history || history.length === 0) {
        return (
            <Card>
                <CardContent className="py-8 text-center text-sm text-zinc-500">
                    <Clock className="mx-auto h-8 w-8 text-zinc-300" />
                    <p className="mt-2">No history records yet.</p>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-semibold text-zinc-900">Change History</h3>
            <div className="relative space-y-0">
                {/* Timeline line */}
                <div className="absolute left-5 top-0 h-full w-px bg-zinc-200" />

                {history.map((entry, index) => {
                    const config =
                        CHANGE_TYPE_CONFIG[entry.change_type] || {
                            icon: Pencil,
                            color: 'text-zinc-600 bg-zinc-100',
                        };
                    const Icon = config.icon;

                    return (
                        <div key={entry.id || index} className="relative flex gap-4 pb-6">
                            <div
                                className={cn(
                                    'z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                                    config.color
                                )}
                            >
                                <Icon className="h-4 w-4" />
                            </div>
                            <div className="flex-1 pt-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm font-medium text-zinc-900">
                                        {formatDate(entry.effective_date || entry.created_at)}
                                    </span>
                                    <Badge variant="secondary">
                                        {(entry.change_type || '').replace(/_/g, ' ')}
                                    </Badge>
                                </div>
                                {entry.field_name && (
                                    <p className="mt-1 text-sm text-zinc-600">
                                        <span className="font-medium">
                                            {entry.field_name.replace(/_/g, ' ')}:
                                        </span>{' '}
                                        <span className="text-zinc-400">
                                            {entry.old_value || '(empty)'}
                                        </span>{' '}
                                        <span className="text-zinc-400">&rarr;</span>{' '}
                                        <span className="font-medium text-zinc-900">
                                            {entry.new_value || '(empty)'}
                                        </span>
                                    </p>
                                )}
                                {entry.changed_by_name && (
                                    <p className="mt-0.5 text-xs text-zinc-400">
                                        Changed by: {entry.changed_by_name}
                                    </p>
                                )}
                                {entry.remarks && (
                                    <p className="mt-1 text-sm italic text-zinc-500">
                                        {entry.remarks}
                                    </p>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
