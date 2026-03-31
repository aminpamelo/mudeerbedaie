import { useState, useEffect, useMemo } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Save, Loader2 } from 'lucide-react';
import {
    fetchEmployee,
    updateEmployee,
    fetchDepartments,
    fetchPositions,
} from '../lib/api';
import { cn } from '../lib/utils';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../components/ui/select';

const TABS = [
    { id: 'personal', label: 'Personal Info' },
    { id: 'employment', label: 'Employment' },
    { id: 'bank', label: 'Bank & Statutory' },
];

const MALAYSIAN_STATES = [
    'Johor',
    'Kedah',
    'Kelantan',
    'Melaka',
    'Negeri Sembilan',
    'Pahang',
    'Perak',
    'Perlis',
    'Pulau Pinang',
    'Sabah',
    'Sarawak',
    'Selangor',
    'Terengganu',
    'W.P. Kuala Lumpur',
    'W.P. Putrajaya',
    'W.P. Labuan',
];

const MALAYSIAN_BANKS = [
    'Maybank',
    'CIMB Bank',
    'Public Bank',
    'RHB Bank',
    'Hong Leong Bank',
    'AmBank',
    'Bank Islam',
    'Bank Rakyat',
    'OCBC Bank',
    'UOB Bank',
    'HSBC Bank',
    'Standard Chartered',
    'Affin Bank',
    'Alliance Bank',
    'Bank Muamalat',
    'Agrobank',
    'BSN (Bank Simpanan Nasional)',
];

const GENDER_OPTIONS = ['Male', 'Female'];

const RELIGION_OPTIONS = ['Islam', 'Christianity', 'Buddhism', 'Hinduism', 'Sikhism', 'Other'];

const RACE_OPTIONS = ['Malay', 'Chinese', 'Indian', 'Bumiputera Sabah', 'Bumiputera Sarawak', 'Other'];

const MARITAL_OPTIONS = ['Single', 'Married', 'Divorced', 'Widowed'];

const EMPLOYMENT_TYPES = [
    { value: 'full_time', label: 'Full Time' },
    { value: 'part_time', label: 'Part Time' },
    { value: 'contract', label: 'Contract' },
    { value: 'internship', label: 'Internship' },
];

// Fields that require effective date and remarks when changed
const TRACKED_FIELDS = ['department_id', 'position_id', 'status', 'employment_type'];

function getDefaultForm() {
    return {
        // Personal
        first_name: '',
        last_name: '',
        ic_number: '',
        date_of_birth: '',
        gender: '',
        religion: '',
        race: '',
        marital_status: '',
        phone: '',
        personal_email: '',
        address_line_1: '',
        address_line_2: '',
        postcode: '',
        city: '',
        state: '',
        // Employment
        department_id: '',
        position_id: '',
        employment_type: '',
        join_date: '',
        probation_end_date: '',
        contract_end_date: '',
        notes: '',
        // Bank & Statutory
        bank_name: '',
        bank_account_number: '',
        epf_number: '',
        socso_number: '',
        tax_number: '',
    };
}

export default function EmployeeEdit() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [activeTab, setActiveTab] = useState('personal');
    const [form, setForm] = useState(getDefaultForm);
    const [originalValues, setOriginalValues] = useState({});
    const [trackedChanges, setTrackedChanges] = useState({});
    const [errors, setErrors] = useState({});

    // Queries
    const { data: employee, isLoading } = useQuery({
        queryKey: ['hr', 'employee', id],
        queryFn: () => fetchEmployee(id),
    });

    const { data: departmentsData } = useQuery({
        queryKey: ['hr', 'departments', { per_page: 100 }],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const { data: positionsData } = useQuery({
        queryKey: ['hr', 'positions', { per_page: 100, department_id: form.department_id || undefined }],
        queryFn: () =>
            fetchPositions({
                per_page: 100,
                department_id: form.department_id || undefined,
            }),
        enabled: true,
    });

    const departments = useMemo(() => {
        const raw = departmentsData?.data || departmentsData || [];
        return Array.isArray(raw) ? raw : [];
    }, [departmentsData]);

    const positions = useMemo(() => {
        const raw = positionsData?.data || positionsData || [];
        return Array.isArray(raw) ? raw : [];
    }, [positionsData]);

    // Populate form when employee data loads
    useEffect(() => {
        const emp = employee?.data || employee;
        if (!emp?.id) return;

        const values = {
            first_name: emp.first_name || '',
            last_name: emp.last_name || '',
            ic_number: emp.ic_number || '',
            date_of_birth: emp.date_of_birth || '',
            gender: emp.gender || '',
            religion: emp.religion || '',
            race: emp.race || '',
            marital_status: emp.marital_status || '',
            phone: emp.phone || '',
            personal_email: emp.personal_email || '',
            address_line_1: emp.address_line_1 || '',
            address_line_2: emp.address_line_2 || '',
            postcode: emp.postcode || '',
            city: emp.city || '',
            state: emp.state || '',
            department_id: emp.department_id ? String(emp.department_id) : '',
            position_id: emp.position_id ? String(emp.position_id) : '',
            employment_type: emp.employment_type || '',
            join_date: emp.join_date || '',
            probation_end_date: emp.probation_end_date || '',
            contract_end_date: emp.contract_end_date || '',
            notes: emp.notes || '',
            bank_name: emp.bank_name || '',
            bank_account_number: emp.bank_account_number || '',
            epf_number: emp.epf_number || '',
            socso_number: emp.socso_number || '',
            tax_number: emp.tax_number || '',
        };
        setForm(values);
        setOriginalValues(values);
        setTrackedChanges({});
    }, [employee]);

    // Mutation
    const mutation = useMutation({
        mutationFn: (data) => updateEmployee(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'employee', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'employees'] });
            navigate(`/employees/${id}`);
        },
        onError: (error) => {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        },
    });

    function setField(field, value) {
        setForm((prev) => ({ ...prev, [field]: value }));

        // Track changes for monitored fields
        if (TRACKED_FIELDS.includes(field) && value !== originalValues[field]) {
            setTrackedChanges((prev) => ({
                ...prev,
                [field]: {
                    effective_date: prev[field]?.effective_date || new Date().toISOString().split('T')[0],
                    remarks: prev[field]?.remarks || '',
                },
            }));
        } else if (TRACKED_FIELDS.includes(field) && value === originalValues[field]) {
            setTrackedChanges((prev) => {
                const next = { ...prev };
                delete next[field];
                return next;
            });
        }

        // Clear error for field
        if (errors[field]) {
            setErrors((prev) => {
                const next = { ...prev };
                delete next[field];
                return next;
            });
        }
    }

    function setTrackedField(field, key, value) {
        setTrackedChanges((prev) => ({
            ...prev,
            [field]: { ...prev[field], [key]: value },
        }));
    }

    function handleSubmit(e) {
        e.preventDefault();

        // Build submission data with only changed fields (plus always include all for simplicity)
        const payload = { ...form };

        // Include tracked changes metadata
        if (Object.keys(trackedChanges).length > 0) {
            payload._tracked_changes = trackedChanges;
        }

        // Convert empty strings to null for optional fields
        for (const key of Object.keys(payload)) {
            if (payload[key] === '' && key !== '_tracked_changes') {
                payload[key] = null;
            }
        }

        mutation.mutate(payload);
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
                to={`/employees/${id}`}
                className="inline-flex items-center gap-1.5 text-sm text-zinc-500 hover:text-zinc-900 transition-colors"
            >
                <ArrowLeft className="h-4 w-4" />
                Back to Employee Profile
            </Link>

            {/* Header */}
            <div>
                <h1 className="text-2xl font-bold tracking-tight text-zinc-900">
                    Edit Employee: {fullName}
                </h1>
                <p className="mt-1 text-sm text-zinc-500">
                    Update employee information. Changes to department, position, employment type, or status will be tracked.
                </p>
            </div>

            {/* Mutation error */}
            {mutation.isError && !mutation.error?.response?.data?.errors && (
                <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    {mutation.error?.response?.data?.message || 'An error occurred while saving. Please try again.'}
                </div>
            )}

            {/* Tabs */}
            <div className="border-b border-zinc-200">
                <nav className="-mb-px flex gap-4" aria-label="Tabs">
                    {TABS.map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
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

            {/* Form */}
            <form onSubmit={handleSubmit}>
                {/* Personal Info Tab */}
                {activeTab === 'personal' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Personal Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <FormField
                                    label="First Name"
                                    error={errors.first_name}
                                    required
                                >
                                    <Input
                                        value={form.first_name}
                                        onChange={(e) => setField('first_name', e.target.value)}
                                    />
                                </FormField>
                                <FormField label="Last Name" error={errors.last_name}>
                                    <Input
                                        value={form.last_name}
                                        onChange={(e) => setField('last_name', e.target.value)}
                                    />
                                </FormField>
                                <FormField
                                    label="IC Number"
                                    error={errors.ic_number}
                                    required
                                >
                                    <Input
                                        value={form.ic_number}
                                        onChange={(e) => setField('ic_number', e.target.value)}
                                        placeholder="e.g. 901215-14-1234"
                                    />
                                </FormField>
                                <FormField
                                    label="Date of Birth"
                                    error={errors.date_of_birth}
                                >
                                    <Input
                                        type="date"
                                        value={form.date_of_birth}
                                        onChange={(e) => setField('date_of_birth', e.target.value)}
                                    />
                                </FormField>
                                <FormField label="Gender" error={errors.gender}>
                                    <Select
                                        value={form.gender}
                                        onValueChange={(val) => setField('gender', val)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select gender" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {GENDER_OPTIONS.map((g) => (
                                                <SelectItem key={g} value={g}>
                                                    {g}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField label="Religion" error={errors.religion}>
                                    <Select
                                        value={form.religion}
                                        onValueChange={(val) => setField('religion', val)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select religion" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {RELIGION_OPTIONS.map((r) => (
                                                <SelectItem key={r} value={r}>
                                                    {r}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField label="Race" error={errors.race}>
                                    <Select
                                        value={form.race}
                                        onValueChange={(val) => setField('race', val)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select race" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {RACE_OPTIONS.map((r) => (
                                                <SelectItem key={r} value={r}>
                                                    {r}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField label="Marital Status" error={errors.marital_status}>
                                    <Select
                                        value={form.marital_status}
                                        onValueChange={(val) => setField('marital_status', val)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {MARITAL_OPTIONS.map((m) => (
                                                <SelectItem key={m} value={m}>
                                                    {m}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField label="Phone" error={errors.phone}>
                                    <Input
                                        value={form.phone}
                                        onChange={(e) => setField('phone', e.target.value)}
                                        placeholder="e.g. 012-3456789"
                                    />
                                </FormField>
                                <FormField label="Personal Email" error={errors.personal_email}>
                                    <Input
                                        type="email"
                                        value={form.personal_email}
                                        onChange={(e) => setField('personal_email', e.target.value)}
                                    />
                                </FormField>
                            </div>

                            {/* Address */}
                            <div className="border-t border-zinc-200 pt-6">
                                <h4 className="mb-4 text-sm font-medium text-zinc-900">Address</h4>
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <div className="sm:col-span-2">
                                        <FormField
                                            label="Address Line 1"
                                            error={errors.address_line_1}
                                        >
                                            <Input
                                                value={form.address_line_1}
                                                onChange={(e) =>
                                                    setField('address_line_1', e.target.value)
                                                }
                                            />
                                        </FormField>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <FormField
                                            label="Address Line 2"
                                            error={errors.address_line_2}
                                        >
                                            <Input
                                                value={form.address_line_2}
                                                onChange={(e) =>
                                                    setField('address_line_2', e.target.value)
                                                }
                                            />
                                        </FormField>
                                    </div>
                                    <FormField label="Postcode" error={errors.postcode}>
                                        <Input
                                            value={form.postcode}
                                            onChange={(e) => setField('postcode', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="City" error={errors.city}>
                                        <Input
                                            value={form.city}
                                            onChange={(e) => setField('city', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="State" error={errors.state}>
                                        <Select
                                            value={form.state}
                                            onValueChange={(val) => setField('state', val)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select state" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {MALAYSIAN_STATES.map((s) => (
                                                    <SelectItem key={s} value={s}>
                                                        {s}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </FormField>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Employment Tab */}
                {activeTab === 'employment' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Employment Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <FormField
                                        label="Department"
                                        error={errors.department_id}
                                    >
                                        <Select
                                            value={form.department_id}
                                            onValueChange={(val) => {
                                                setField('department_id', val);
                                                // Reset position when department changes
                                                setField('position_id', '');
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select department" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {departments.map((dept) => (
                                                    <SelectItem
                                                        key={dept.id}
                                                        value={String(dept.id)}
                                                    >
                                                        {dept.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </FormField>
                                    {trackedChanges.department_id && (
                                        <TrackedChangeFields
                                            field="department_id"
                                            values={trackedChanges.department_id}
                                            onChange={setTrackedField}
                                        />
                                    )}
                                </div>

                                <div>
                                    <FormField
                                        label="Position"
                                        error={errors.position_id}
                                    >
                                        <Select
                                            value={form.position_id}
                                            onValueChange={(val) => setField('position_id', val)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select position" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {positions.map((pos) => (
                                                    <SelectItem
                                                        key={pos.id}
                                                        value={String(pos.id)}
                                                    >
                                                        {pos.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </FormField>
                                    {trackedChanges.position_id && (
                                        <TrackedChangeFields
                                            field="position_id"
                                            values={trackedChanges.position_id}
                                            onChange={setTrackedField}
                                        />
                                    )}
                                </div>

                                <div>
                                    <FormField
                                        label="Employment Type"
                                        error={errors.employment_type}
                                    >
                                        <Select
                                            value={form.employment_type}
                                            onValueChange={(val) =>
                                                setField('employment_type', val)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {EMPLOYMENT_TYPES.map((t) => (
                                                    <SelectItem key={t.value} value={t.value}>
                                                        {t.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </FormField>
                                    {trackedChanges.employment_type && (
                                        <TrackedChangeFields
                                            field="employment_type"
                                            values={trackedChanges.employment_type}
                                            onChange={setTrackedField}
                                        />
                                    )}
                                </div>

                                <FormField label="Join Date" error={errors.join_date}>
                                    <Input
                                        type="date"
                                        value={form.join_date}
                                        onChange={(e) => setField('join_date', e.target.value)}
                                    />
                                </FormField>
                                <FormField
                                    label="Probation End Date"
                                    error={errors.probation_end_date}
                                >
                                    <Input
                                        type="date"
                                        value={form.probation_end_date}
                                        onChange={(e) =>
                                            setField('probation_end_date', e.target.value)
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="Contract End Date"
                                    error={errors.contract_end_date}
                                >
                                    <Input
                                        type="date"
                                        value={form.contract_end_date}
                                        onChange={(e) =>
                                            setField('contract_end_date', e.target.value)
                                        }
                                    />
                                </FormField>
                            </div>
                            <div>
                                <FormField label="Notes" error={errors.notes}>
                                    <textarea
                                        className="flex min-h-[100px] w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 focus-visible:ring-offset-2"
                                        value={form.notes}
                                        onChange={(e) => setField('notes', e.target.value)}
                                        placeholder="Any additional notes..."
                                    />
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Bank & Statutory Tab */}
                {activeTab === 'bank' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Bank & Statutory Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <FormField label="Bank Name" error={errors.bank_name}>
                                    <Select
                                        value={form.bank_name}
                                        onValueChange={(val) => setField('bank_name', val)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select bank" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {MALAYSIAN_BANKS.map((b) => (
                                                <SelectItem key={b} value={b}>
                                                    {b}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField
                                    label="Account Number"
                                    error={errors.bank_account_number}
                                >
                                    <Input
                                        value={form.bank_account_number}
                                        onChange={(e) =>
                                            setField('bank_account_number', e.target.value)
                                        }
                                        placeholder="Bank account number"
                                    />
                                </FormField>
                                <FormField label="EPF Number" error={errors.epf_number}>
                                    <Input
                                        value={form.epf_number}
                                        onChange={(e) => setField('epf_number', e.target.value)}
                                        placeholder="EPF number"
                                    />
                                </FormField>
                                <FormField label="SOCSO Number" error={errors.socso_number}>
                                    <Input
                                        value={form.socso_number}
                                        onChange={(e) => setField('socso_number', e.target.value)}
                                        placeholder="SOCSO number"
                                    />
                                </FormField>
                                <FormField label="Tax Reference Number" error={errors.tax_number}>
                                    <Input
                                        value={form.tax_number}
                                        onChange={(e) => setField('tax_number', e.target.value)}
                                        placeholder="Tax reference number"
                                    />
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Save Button */}
                <div className="mt-6 flex items-center justify-end gap-3">
                    <Button type="button" variant="outline" asChild>
                        <Link to={`/employees/${id}`}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={mutation.isPending}>
                        {mutation.isPending ? (
                            <>
                                <Loader2 className="h-4 w-4 animate-spin" />
                                Saving...
                            </>
                        ) : (
                            <>
                                <Save className="h-4 w-4" />
                                Save Changes
                            </>
                        )}
                    </Button>
                </div>
            </form>
        </div>
    );
}

/* ======================== Helper Components ======================== */

function FormField({ label, error, required, children }) {
    return (
        <div className="space-y-2">
            <Label>
                {label}
                {required && <span className="ml-0.5 text-red-500">*</span>}
            </Label>
            {children}
            {error && (
                <p className="text-xs text-red-600">
                    {Array.isArray(error) ? error[0] : error}
                </p>
            )}
        </div>
    );
}

function TrackedChangeFields({ field, values, onChange }) {
    return (
        <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 space-y-3">
            <p className="text-xs font-medium text-amber-800">
                This change will be tracked in employee history.
            </p>
            <div className="space-y-2">
                <Label className="text-xs">Effective Date</Label>
                <Input
                    type="date"
                    value={values.effective_date || ''}
                    onChange={(e) => onChange(field, 'effective_date', e.target.value)}
                    className="h-8 text-xs"
                />
            </div>
            <div className="space-y-2">
                <Label className="text-xs">Remarks</Label>
                <Input
                    value={values.remarks || ''}
                    onChange={(e) => onChange(field, 'remarks', e.target.value)}
                    placeholder="Reason for change..."
                    className="h-8 text-xs"
                />
            </div>
        </div>
    );
}
