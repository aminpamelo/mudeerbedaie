import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Phone,
    Mail,
    Calendar,
    Briefcase,
    MapPin,
    User,
    CreditCard,
    FileText,
    Users,
    Upload,
    Download,
    Plus,
    Pencil,
    Trash2,
    X,
    Save,
    Loader2,
    AlertCircle,
} from 'lucide-react';
import {
    fetchMyProfile,
    updateMyProfile,
    fetchMyEmergencyContacts,
    createMyEmergencyContact,
    updateMyEmergencyContact,
    deleteMyEmergencyContact,
    fetchMyDocuments,
    uploadMyDocument,
} from '../lib/api';
import { cn } from '../lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Badge } from '../components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../components/ui/select';
import StatusBadge from '../components/StatusBadge';

// ─── Helpers ────────────────────────────────────────────────
function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function InfoRow({ label, value }) {
    return (
        <div className="flex justify-between py-2.5 border-b border-zinc-100 last:border-0">
            <span className="text-sm text-zinc-500">{label}</span>
            <span className="text-sm font-medium text-zinc-900 text-right">{value || '-'}</span>
        </div>
    );
}

function formatFileSize(bytes) {
    if (!bytes) return '-';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// ─── Tab definitions ────────────────────────────────────────
const TABS = [
    { id: 'overview', label: 'Overview', icon: User },
    { id: 'personal', label: 'Personal', icon: MapPin },
    { id: 'employment', label: 'Employment', icon: Briefcase },
    { id: 'emergency', label: 'Emergency', icon: Phone },
    { id: 'documents', label: 'Documents', icon: FileText },
];

const DOC_TYPES = [
    { value: 'ic_front', label: 'IC Front' },
    { value: 'ic_back', label: 'IC Back' },
    { value: 'offer_letter', label: 'Offer Letter' },
    { value: 'contract', label: 'Contract' },
    { value: 'bank_statement', label: 'Bank Statement' },
    { value: 'epf_form', label: 'EPF Form' },
    { value: 'socso_form', label: 'SOCSO Form' },
];

// ═════════════════════════════════════════════════════════════
// MAIN COMPONENT
// ═════════════════════════════════════════════════════════════
export default function MyProfile() {
    const [activeTab, setActiveTab] = useState('overview');

    const { data: profileData, isLoading, isError, error } = useQuery({
        queryKey: ['my-profile'],
        queryFn: fetchMyProfile,
    });
    const employee = profileData?.data;

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-20">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (isError) {
        return (
            <div className="flex flex-col items-center justify-center py-20 text-center">
                <AlertCircle className="h-10 w-10 text-red-400 mb-3" />
                <p className="text-sm text-zinc-600">
                    {error?.response?.data?.message || 'Failed to load profile'}
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Profile Header */}
            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-col items-center text-center">
                        <div className="h-20 w-20 rounded-full bg-zinc-900 text-white flex items-center justify-center text-2xl font-bold mb-3">
                            {getInitials(employee.full_name)}
                        </div>
                        <h2 className="text-xl font-bold text-zinc-900">{employee.full_name}</h2>
                        <p className="text-sm text-zinc-500 mt-0.5">{employee.employee_id}</p>
                        <div className="flex items-center gap-2 mt-2 text-sm text-zinc-600">
                            {employee.department?.name && <span>{employee.department.name}</span>}
                            {employee.department?.name && employee.position?.title && (
                                <span className="text-zinc-300">|</span>
                            )}
                            {employee.position?.title && <span>{employee.position.title}</span>}
                        </div>
                        <div className="mt-3">
                            <StatusBadge status={employee.status} />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Scrollable Tabs */}
            <div className="overflow-x-auto -mx-4 px-4 scrollbar-hide">
                <div className="flex gap-1 min-w-max bg-zinc-100 rounded-xl p-1">
                    {TABS.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={cn(
                                'flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-medium transition-all whitespace-nowrap',
                                activeTab === tab.id
                                    ? 'bg-white text-zinc-900 shadow-sm'
                                    : 'text-zinc-500 hover:text-zinc-700'
                            )}
                        >
                            <tab.icon className="h-3.5 w-3.5" />
                            {tab.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Tab Content */}
            {activeTab === 'overview' && <OverviewTab employee={employee} />}
            {activeTab === 'personal' && <PersonalTab employee={employee} />}
            {activeTab === 'employment' && <EmploymentTab employee={employee} />}
            {activeTab === 'emergency' && <EmergencyTab />}
            {activeTab === 'documents' && <DocumentsTab />}
        </div>
    );
}

// ═════════════════════════════════════════════════════════════
// OVERVIEW TAB
// ═════════════════════════════════════════════════════════════
function OverviewTab({ employee }) {
    const quickInfo = [
        { icon: Phone, label: 'Phone', value: employee.phone || '-' },
        { icon: Mail, label: 'Email', value: employee.personal_email || '-' },
        { icon: Calendar, label: 'Join Date', value: formatDate(employee.join_date) },
        { icon: Briefcase, label: 'Type', value: employee.employment_type_label || '-' },
    ];

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
                {quickInfo.map((item) => (
                    <Card key={item.label}>
                        <CardContent className="py-3.5 px-3.5">
                            <div className="flex items-start gap-2.5">
                                <div className="rounded-lg bg-zinc-100 p-2 shrink-0">
                                    <item.icon className="h-4 w-4 text-zinc-600" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-[11px] text-zinc-500">{item.label}</p>
                                    <p className="text-sm font-medium text-zinc-900 truncate">{item.value}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Status */}
            <Card>
                <CardContent className="py-4">
                    <div className="flex items-center gap-3">
                        <div className={cn('h-3 w-3 rounded-full shrink-0',
                            employee.status === 'active' ? 'bg-emerald-500' :
                            employee.status === 'probation' ? 'bg-amber-500' :
                            employee.status === 'on_leave' ? 'bg-blue-500' : 'bg-zinc-400'
                        )} />
                        <div>
                            <p className="text-sm font-medium text-zinc-900">
                                {employee.status === 'active' ? 'Active Employee' :
                                 employee.status === 'probation' ? 'Probation Period' :
                                 employee.status === 'on_leave' ? 'Currently On Leave' :
                                 employee.status?.replace('_', ' ')?.replace(/\b\w/g, (c) => c.toUpperCase()) || '-'}
                            </p>
                            {employee.join_date && (
                                <p className="text-xs text-zinc-500 mt-0.5">Joined {formatDate(employee.join_date)}</p>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

// ═════════════════════════════════════════════════════════════
// PERSONAL TAB
// ═════════════════════════════════════════════════════════════
function PersonalTab({ employee }) {
    const queryClient = useQueryClient();
    const [editingSection, setEditingSection] = useState(null);
    const [formData, setFormData] = useState({});
    const [saveError, setSaveError] = useState(null);

    const mutation = useMutation({
        mutationFn: updateMyProfile,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-profile'] });
            setEditingSection(null);
            setFormData({});
            setSaveError(null);
        },
        onError: (err) => {
            setSaveError(err?.response?.data?.message || 'Failed to save changes');
        },
    });

    function startEdit(section, fields) {
        const data = {};
        fields.forEach((f) => { data[f] = employee[f] || ''; });
        setFormData(data);
        setEditingSection(section);
        setSaveError(null);
    }

    function cancelEdit() {
        setEditingSection(null);
        setFormData({});
        setSaveError(null);
    }

    function handleSave() { mutation.mutate(formData); }
    function updateField(field, value) { setFormData((prev) => ({ ...prev, [field]: value })); }

    const isEditing = (s) => editingSection === s;

    function EditActions() {
        return (
            <div className="flex gap-2 pt-2">
                <Button size="sm" onClick={handleSave} disabled={mutation.isPending}>
                    {mutation.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Save className="h-3.5 w-3.5" />}
                    Save
                </Button>
                <Button size="sm" variant="outline" onClick={cancelEdit}>Cancel</Button>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {/* Contact */}
            <Card>
                <CardHeader className="pb-2">
                    <div className="flex items-center justify-between">
                        <CardTitle className="text-sm flex items-center gap-2">
                            <Phone className="h-4 w-4 text-zinc-400" /> Contact
                        </CardTitle>
                        {!isEditing('contact') && (
                            <Button variant="ghost" size="sm" className="h-7 w-7 p-0"
                                onClick={() => startEdit('contact', ['phone', 'personal_email'])}>
                                <Pencil className="h-3.5 w-3.5" />
                            </Button>
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    {isEditing('contact') ? (
                        <div className="space-y-3">
                            <div><Label className="text-xs">Phone</Label><Input value={formData.phone} onChange={(e) => updateField('phone', e.target.value)} className="mt-1" /></div>
                            <div><Label className="text-xs">Personal Email</Label><Input type="email" value={formData.personal_email} onChange={(e) => updateField('personal_email', e.target.value)} className="mt-1" /></div>
                            {saveError && <p className="text-xs text-red-600">{saveError}</p>}
                            <EditActions />
                        </div>
                    ) : (
                        <div><InfoRow label="Phone" value={employee.phone} /><InfoRow label="Personal Email" value={employee.personal_email} /></div>
                    )}
                </CardContent>
            </Card>

            {/* Address */}
            <Card>
                <CardHeader className="pb-2">
                    <div className="flex items-center justify-between">
                        <CardTitle className="text-sm flex items-center gap-2">
                            <MapPin className="h-4 w-4 text-zinc-400" /> Address
                        </CardTitle>
                        {!isEditing('address') && (
                            <Button variant="ghost" size="sm" className="h-7 w-7 p-0"
                                onClick={() => startEdit('address', ['address_line_1', 'address_line_2', 'city', 'state', 'postcode'])}>
                                <Pencil className="h-3.5 w-3.5" />
                            </Button>
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    {isEditing('address') ? (
                        <div className="space-y-3">
                            <div><Label className="text-xs">Address Line 1</Label><Input value={formData.address_line_1} onChange={(e) => updateField('address_line_1', e.target.value)} className="mt-1" /></div>
                            <div><Label className="text-xs">Address Line 2</Label><Input value={formData.address_line_2} onChange={(e) => updateField('address_line_2', e.target.value)} className="mt-1" /></div>
                            <div className="grid grid-cols-2 gap-3">
                                <div><Label className="text-xs">City</Label><Input value={formData.city} onChange={(e) => updateField('city', e.target.value)} className="mt-1" /></div>
                                <div><Label className="text-xs">State</Label><Input value={formData.state} onChange={(e) => updateField('state', e.target.value)} className="mt-1" /></div>
                            </div>
                            <div><Label className="text-xs">Postcode</Label><Input value={formData.postcode} onChange={(e) => updateField('postcode', e.target.value)} className="mt-1 w-1/2" /></div>
                            {saveError && <p className="text-xs text-red-600">{saveError}</p>}
                            <EditActions />
                        </div>
                    ) : (
                        <div>
                            <InfoRow label="Address Line 1" value={employee.address_line_1} />
                            <InfoRow label="Address Line 2" value={employee.address_line_2} />
                            <InfoRow label="City" value={employee.city} />
                            <InfoRow label="State" value={employee.state} />
                            <InfoRow label="Postcode" value={employee.postcode} />
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Personal Details */}
            <Card>
                <CardHeader className="pb-2">
                    <div className="flex items-center justify-between">
                        <CardTitle className="text-sm flex items-center gap-2">
                            <User className="h-4 w-4 text-zinc-400" /> Personal Details
                        </CardTitle>
                        {!isEditing('personal') && (
                            <Button variant="ghost" size="sm" className="h-7 w-7 p-0"
                                onClick={() => startEdit('personal', ['marital_status'])}>
                                <Pencil className="h-3.5 w-3.5" />
                            </Button>
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    {isEditing('personal') ? (
                        <div className="space-y-1">
                            <InfoRow label="Gender" value={employee.gender} />
                            <InfoRow label="Date of Birth" value={formatDate(employee.date_of_birth)} />
                            <InfoRow label="Religion" value={employee.religion} />
                            <InfoRow label="Race" value={employee.race} />
                            <InfoRow label="IC Number" value={employee.masked_ic} />
                            <div className="pt-2">
                                <Label className="text-xs">Marital Status</Label>
                                <Input value={formData.marital_status} onChange={(e) => updateField('marital_status', e.target.value)} className="mt-1" />
                            </div>
                            {saveError && <p className="text-xs text-red-600">{saveError}</p>}
                            <EditActions />
                        </div>
                    ) : (
                        <div>
                            <InfoRow label="Gender" value={employee.gender} />
                            <InfoRow label="Date of Birth" value={formatDate(employee.date_of_birth)} />
                            <InfoRow label="Religion" value={employee.religion} />
                            <InfoRow label="Race" value={employee.race} />
                            <InfoRow label="Marital Status" value={employee.marital_status} />
                            <InfoRow label="IC Number" value={employee.masked_ic} />
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

// ═════════════════════════════════════════════════════════════
// EMPLOYMENT TAB
// ═════════════════════════════════════════════════════════════
function EmploymentTab({ employee }) {
    const isProbation = employee.status === 'probation';
    const isContract = employee.employment_type === 'contract';

    return (
        <div className="space-y-3">
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-sm flex items-center gap-2">
                        <Briefcase className="h-4 w-4 text-zinc-400" /> Employment Details
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex justify-between py-2.5 border-b border-zinc-100">
                        <span className="text-sm text-zinc-500">Employment Type</span>
                        <Badge variant="secondary">{employee.employment_type_label || '-'}</Badge>
                    </div>
                    <InfoRow label="Join Date" value={formatDate(employee.join_date)} />
                    <div className="flex justify-between py-2.5">
                        <span className="text-sm text-zinc-500">Status</span>
                        <StatusBadge status={employee.status} />
                    </div>
                </CardContent>
            </Card>

            {isProbation && (
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm flex items-center gap-2">
                            <Calendar className="h-4 w-4 text-amber-500" /> Probation Period
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <InfoRow label="Probation End Date" value={formatDate(employee.probation_end_date)} />
                        <InfoRow label="Confirmation Date" value={formatDate(employee.confirmation_date)} />
                        {employee.probation_end_date && (
                            <div className="mt-3 rounded-lg bg-amber-50 border border-amber-200 p-3">
                                <p className="text-xs text-amber-800">
                                    Your probation ends on <span className="font-medium">{formatDate(employee.probation_end_date)}</span>.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-sm flex items-center gap-2">
                        <CreditCard className="h-4 w-4 text-zinc-400" /> Bank Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <InfoRow label="Bank Name" value={employee.bank_name} />
                    <InfoRow label="Account Number" value={employee.masked_bank_account} />
                </CardContent>
            </Card>

            {isContract && (
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm flex items-center gap-2">
                            <FileText className="h-4 w-4 text-zinc-400" /> Contract Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <InfoRow label="Contract End Date" value={formatDate(employee.contract_end_date)} />
                        {employee.contract_end_date && (
                            <div className="mt-3 rounded-lg bg-blue-50 border border-blue-200 p-3">
                                <p className="text-xs text-blue-800">
                                    Your contract is valid until <span className="font-medium">{formatDate(employee.contract_end_date)}</span>.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

// ═════════════════════════════════════════════════════════════
// EMERGENCY TAB
// ═════════════════════════════════════════════════════════════
function EmergencyTab() {
    const queryClient = useQueryClient();
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState({ name: '', relationship: '', phone: '', email: '', is_primary: false });

    const { data: contactsData, isLoading } = useQuery({
        queryKey: ['my-emergency-contacts'],
        queryFn: fetchMyEmergencyContacts,
    });
    const contacts = contactsData?.data ?? [];

    const createMut = useMutation({
        mutationFn: createMyEmergencyContact,
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['my-emergency-contacts'] }); resetForm(); },
    });
    const updateMut = useMutation({
        mutationFn: ({ id, data }) => updateMyEmergencyContact(id, data),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['my-emergency-contacts'] }); resetForm(); },
    });
    const deleteMut = useMutation({
        mutationFn: deleteMyEmergencyContact,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['my-emergency-contacts'] }),
    });

    function resetForm() { setShowForm(false); setEditingId(null); setForm({ name: '', relationship: '', phone: '', email: '', is_primary: false }); }
    function startEdit(c) { setForm({ name: c.name, relationship: c.relationship, phone: c.phone, email: c.email || '', is_primary: c.is_primary }); setEditingId(c.id); setShowForm(true); }
    function handleSubmit(e) {
        e.preventDefault();
        if (editingId) { updateMut.mutate({ id: editingId, data: form }); }
        else { createMut.mutate(form); }
    }

    const isSaving = createMut.isPending || updateMut.isPending;

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-zinc-700">Emergency Contacts</h3>
                {!showForm && (
                    <Button size="sm" variant="outline" onClick={() => { resetForm(); setShowForm(true); }}>
                        <Plus className="h-3.5 w-3.5 mr-1" /> Add
                    </Button>
                )}
            </div>

            {showForm && (
                <Card>
                    <CardContent className="pt-4">
                        <form onSubmit={handleSubmit} className="space-y-3">
                            <div><Label className="text-xs">Name *</Label><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="mt-1" required /></div>
                            <div className="grid grid-cols-2 gap-3">
                                <div><Label className="text-xs">Relationship *</Label><Input value={form.relationship} onChange={(e) => setForm({ ...form, relationship: e.target.value })} className="mt-1" required /></div>
                                <div><Label className="text-xs">Phone *</Label><Input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} className="mt-1" required /></div>
                            </div>
                            <div><Label className="text-xs">Email</Label><Input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="mt-1" /></div>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form.is_primary} onChange={(e) => setForm({ ...form, is_primary: e.target.checked })} className="rounded" />
                                Primary contact
                            </label>
                            <div className="flex gap-2">
                                <Button size="sm" type="submit" disabled={isSaving}>
                                    {isSaving ? <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" /> : <Save className="h-3.5 w-3.5 mr-1" />}
                                    {editingId ? 'Update' : 'Save'}
                                </Button>
                                <Button size="sm" variant="outline" type="button" onClick={resetForm}>Cancel</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {isLoading ? (
                <div className="flex justify-center py-8"><Loader2 className="h-6 w-6 animate-spin text-zinc-400" /></div>
            ) : contacts.length === 0 ? (
                <Card><CardContent className="py-8 text-center"><Users className="h-8 w-8 text-zinc-300 mx-auto mb-2" /><p className="text-sm text-zinc-500">No emergency contacts added yet</p></CardContent></Card>
            ) : (
                contacts.map((c) => (
                    <Card key={c.id}>
                        <CardContent className="py-3.5">
                            <div className="flex items-start justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-medium text-zinc-900">{c.name}</p>
                                        {c.is_primary && <Badge variant="secondary" className="text-[10px] px-1.5 py-0">Primary</Badge>}
                                    </div>
                                    <p className="text-xs text-zinc-500 mt-0.5">{c.relationship}</p>
                                    <div className="flex items-center gap-3 mt-1.5">
                                        <span className="flex items-center gap-1 text-xs text-zinc-600"><Phone className="h-3 w-3" />{c.phone}</span>
                                        {c.email && <span className="flex items-center gap-1 text-xs text-zinc-600"><Mail className="h-3 w-3" />{c.email}</span>}
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={() => startEdit(c)}><Pencil className="h-3 w-3" /></Button>
                                    <Button variant="ghost" size="sm" className="h-7 w-7 p-0 text-red-500 hover:text-red-700"
                                        onClick={() => { if (window.confirm('Delete this contact?')) deleteMut.mutate(c.id); }}>
                                        <Trash2 className="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))
            )}
        </div>
    );
}

// ═════════════════════════════════════════════════════════════
// DOCUMENTS TAB
// ═════════════════════════════════════════════════════════════
function DocumentsTab() {
    const queryClient = useQueryClient();
    const [showUpload, setShowUpload] = useState(false);
    const [uploadData, setUploadData] = useState({ title: '', document_type: '', file: null });

    const { data: docsData, isLoading } = useQuery({
        queryKey: ['my-documents'],
        queryFn: fetchMyDocuments,
    });
    const documents = docsData?.data ?? [];

    const uploadMut = useMutation({
        mutationFn: (formData) => uploadMyDocument(formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-documents'] });
            setShowUpload(false);
            setUploadData({ title: '', document_type: '', file: null });
        },
    });

    function handleUpload(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('title', uploadData.title);
        fd.append('document_type', uploadData.document_type);
        fd.append('file', uploadData.file);
        uploadMut.mutate(fd);
    }

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-zinc-700">My Documents</h3>
                {!showUpload && (
                    <Button size="sm" variant="outline" onClick={() => setShowUpload(true)}>
                        <Upload className="h-3.5 w-3.5 mr-1" /> Upload
                    </Button>
                )}
            </div>

            {showUpload && (
                <Card>
                    <CardContent className="pt-4">
                        <form onSubmit={handleUpload} className="space-y-3">
                            <div><Label className="text-xs">Title *</Label><Input value={uploadData.title} onChange={(e) => setUploadData({ ...uploadData, title: e.target.value })} className="mt-1" required /></div>
                            <div>
                                <Label className="text-xs">Document Type *</Label>
                                <Select value={uploadData.document_type} onValueChange={(v) => setUploadData({ ...uploadData, document_type: v })}>
                                    <SelectTrigger className="mt-1"><SelectValue placeholder="Select type" /></SelectTrigger>
                                    <SelectContent>
                                        {DOC_TYPES.map((t) => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="text-xs">File *</Label>
                                <Input type="file" className="mt-1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                    onChange={(e) => setUploadData({ ...uploadData, file: e.target.files[0] })} required />
                                <p className="text-[11px] text-zinc-400 mt-1">Max 10MB. PDF, JPG, PNG, DOC.</p>
                            </div>
                            {uploadMut.isError && <p className="text-xs text-red-600">{uploadMut.error?.response?.data?.message || 'Upload failed'}</p>}
                            <div className="flex gap-2">
                                <Button size="sm" type="submit" disabled={uploadMut.isPending || !uploadData.file || !uploadData.document_type}>
                                    {uploadMut.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" /> : <Upload className="h-3.5 w-3.5 mr-1" />}
                                    Upload
                                </Button>
                                <Button size="sm" variant="outline" type="button" onClick={() => setShowUpload(false)}>Cancel</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {isLoading ? (
                <div className="flex justify-center py-8"><Loader2 className="h-6 w-6 animate-spin text-zinc-400" /></div>
            ) : documents.length === 0 ? (
                <Card><CardContent className="py-8 text-center"><FileText className="h-8 w-8 text-zinc-300 mx-auto mb-2" /><p className="text-sm text-zinc-500">No documents uploaded yet</p></CardContent></Card>
            ) : (
                documents.map((doc) => (
                    <Card key={doc.id}>
                        <CardContent className="py-3.5">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3 min-w-0">
                                    <div className="rounded-lg bg-zinc-100 p-2 shrink-0"><FileText className="h-4 w-4 text-zinc-600" /></div>
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-zinc-900 truncate">{doc.title}</p>
                                        <p className="text-[11px] text-zinc-500">
                                            {doc.document_type?.replace('_', ' ')} {doc.file_size ? `· ${formatFileSize(doc.file_size)}` : ''} · {formatDate(doc.uploaded_at || doc.created_at)}
                                        </p>
                                    </div>
                                </div>
                                {doc.download_url && (
                                    <a href={doc.download_url} target="_blank" rel="noopener noreferrer"
                                        className="shrink-0 rounded-lg p-2 text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition-colors">
                                        <Download className="h-4 w-4" />
                                    </a>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                ))
            )}
        </div>
    );
}
