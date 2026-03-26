import { useState, useEffect, useMemo } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Upload,
    FileText,
    X,
    Loader2,
    User,
    Briefcase,
    Landmark,
    FolderOpen,
    ClipboardCheck,
} from 'lucide-react';
import { createEmployee, fetchDepartments, fetchPositions } from '../lib/api';
import { cn } from '../lib/utils';
import PageHeader from '../components/PageHeader';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Textarea } from '../components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Separator } from '../components/ui/separator';
import { RadioGroup, RadioGroupItem } from '../components/ui/radio-group';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../components/ui/select';

const STEPS = [
    { id: 1, label: 'Personal', icon: User },
    { id: 2, label: 'Employment', icon: Briefcase },
    { id: 3, label: 'Bank & Statutory', icon: Landmark },
    { id: 4, label: 'Documents', icon: FolderOpen },
    { id: 5, label: 'Review', icon: ClipboardCheck },
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

const RELIGIONS = ['Islam', 'Christian', 'Buddhist', 'Hindu', 'Sikh', 'Other'];
const RACES = ['Malay', 'Chinese', 'Indian', 'Other'];
const MARITAL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed'];

const BANKS = [
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

const EMPLOYMENT_TYPES = [
    { value: 'full-time', label: 'Full-time' },
    { value: 'part-time', label: 'Part-time' },
    { value: 'contract', label: 'Contract' },
    { value: 'intern', label: 'Intern' },
];

const DOCUMENT_FIELDS = [
    { key: 'ic_front', label: 'IC Front', required: true },
    { key: 'ic_back', label: 'IC Back', required: true },
    { key: 'offer_letter', label: 'Offer Letter', required: false },
    { key: 'employment_contract', label: 'Employment Contract', required: false },
    { key: 'bank_statement', label: 'Bank Statement', required: false },
    { key: 'epf_nomination_form', label: 'EPF Nomination Form', required: false },
    { key: 'socso_registration_form', label: 'SOCSO Registration Form', required: false },
];

function extractDobFromIc(ic) {
    const cleaned = ic.replace(/-/g, '');
    if (cleaned.length < 6) return '';

    let year = parseInt(cleaned.substring(0, 2), 10);
    const month = cleaned.substring(2, 4);
    const day = cleaned.substring(4, 6);

    year = year > 30 ? 1900 + year : 2000 + year;

    const monthNum = parseInt(month, 10);
    const dayNum = parseInt(day, 10);
    if (monthNum < 1 || monthNum > 12 || dayNum < 1 || dayNum > 31) return '';

    return `${year}-${month}-${day}`;
}

function addMonths(dateStr, months) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    date.setMonth(date.getMonth() + months);
    return date.toISOString().split('T')[0];
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function StepIndicator({ currentStep, completedSteps }) {
    return (
        <nav className="mb-8">
            <ol className="flex items-center justify-between">
                {STEPS.map((step, index) => {
                    const isActive = currentStep === step.id;
                    const isCompleted = completedSteps.includes(step.id);
                    const Icon = step.icon;

                    return (
                        <li key={step.id} className="flex items-center">
                            <div className="flex flex-col items-center">
                                <div
                                    className={cn(
                                        'flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-semibold transition-colors',
                                        isActive
                                            ? 'border-zinc-900 bg-zinc-900 text-white'
                                            : isCompleted
                                              ? 'border-emerald-500 bg-emerald-500 text-white'
                                              : 'border-zinc-300 bg-white text-zinc-400'
                                    )}
                                >
                                    {isCompleted && !isActive ? (
                                        <Check className="h-5 w-5" />
                                    ) : (
                                        <Icon className="h-5 w-5" />
                                    )}
                                </div>
                                <span
                                    className={cn(
                                        'mt-2 text-xs font-medium',
                                        isActive
                                            ? 'text-zinc-900'
                                            : isCompleted
                                              ? 'text-emerald-600'
                                              : 'text-zinc-400'
                                    )}
                                >
                                    {step.label}
                                </span>
                            </div>
                            {index < STEPS.length - 1 && (
                                <div
                                    className={cn(
                                        'mx-2 hidden h-0.5 w-16 sm:block lg:w-24',
                                        isCompleted
                                            ? 'bg-emerald-500'
                                            : 'bg-zinc-200'
                                    )}
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

function FileInput({ label, required, file, onChange, onRemove, error }) {
    function handleChange(e) {
        const selected = e.target.files?.[0];
        if (selected) {
            if (selected.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }
            onChange(selected);
        }
    }

    return (
        <div>
            <Label className="mb-1.5 block">
                {label} {required && <span className="text-red-500">*</span>}
            </Label>
            {file ? (
                <div className="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <FileText className="h-5 w-5 shrink-0 text-zinc-500" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-zinc-900">
                            {file.name}
                        </p>
                        <p className="text-xs text-zinc-500">
                            {formatFileSize(file.size)}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onRemove}
                        className="shrink-0 rounded-md p-1 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-600"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            ) : (
                <label className="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 px-4 py-4 text-sm text-zinc-500 transition-colors hover:border-zinc-400 hover:bg-zinc-50">
                    <Upload className="h-4 w-4" />
                    Choose file
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        className="hidden"
                        onChange={handleChange}
                    />
                </label>
            )}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

function FormField({ label, required, error, children }) {
    return (
        <div>
            <Label className="mb-1.5 block">
                {label} {required && <span className="text-red-500">*</span>}
            </Label>
            {children}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

export default function EmployeeCreate() {
    const navigate = useNavigate();
    const [currentStep, setCurrentStep] = useState(1);
    const [errors, setErrors] = useState({});

    // Step 1: Personal Information
    const [fullName, setFullName] = useState('');
    const [icNumber, setIcNumber] = useState('');
    const [dateOfBirth, setDateOfBirth] = useState('');
    const [gender, setGender] = useState('');
    const [religion, setReligion] = useState('');
    const [race, setRace] = useState('');
    const [maritalStatus, setMaritalStatus] = useState('');
    const [phone, setPhone] = useState('');
    const [personalEmail, setPersonalEmail] = useState('');
    const [addressLine1, setAddressLine1] = useState('');
    const [addressLine2, setAddressLine2] = useState('');
    const [city, setCity] = useState('');
    const [state, setState] = useState('');
    const [postcode, setPostcode] = useState('');
    const [profilePhoto, setProfilePhoto] = useState(null);

    // Step 2: Employment Details
    const [departmentId, setDepartmentId] = useState('');
    const [positionId, setPositionId] = useState('');
    const [employmentType, setEmploymentType] = useState('');
    const [joinDate, setJoinDate] = useState('');
    const [probationEndDate, setProbationEndDate] = useState('');
    const [contractEndDate, setContractEndDate] = useState('');
    const [notes, setNotes] = useState('');

    // Step 3: Bank & Statutory
    const [bankName, setBankName] = useState('');
    const [bankAccountNumber, setBankAccountNumber] = useState('');
    const [epfNumber, setEpfNumber] = useState('');
    const [socsoNumber, setSocsoNumber] = useState('');
    const [taxReferenceNumber, setTaxReferenceNumber] = useState('');

    // Step 4: Documents
    const [documents, setDocuments] = useState({});

    const completedSteps = useMemo(() => {
        const completed = [];
        if (currentStep > 1) completed.push(1);
        if (currentStep > 2) completed.push(2);
        if (currentStep > 3) completed.push(3);
        if (currentStep > 4) completed.push(4);
        return completed;
    }, [currentStep]);

    // IC -> DOB extraction
    useEffect(() => {
        const dob = extractDobFromIc(icNumber);
        if (dob) {
            setDateOfBirth(dob);
        }
    }, [icNumber]);

    // Auto-suggest probation end date
    useEffect(() => {
        if (joinDate) {
            setProbationEndDate(addMonths(joinDate, 3));
        }
    }, [joinDate]);

    // Fetch departments
    const { data: departmentsData } = useQuery({
        queryKey: ['hr', 'departments', 'list'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });
    const departments = departmentsData?.data || [];

    // Fetch positions filtered by department
    const { data: positionsData } = useQuery({
        queryKey: ['hr', 'positions', { department_id: departmentId }],
        queryFn: () => fetchPositions({ department_id: departmentId, per_page: 100 }),
        enabled: !!departmentId,
    });
    const positions = positionsData?.data || [];

    // Reset position when department changes
    useEffect(() => {
        setPositionId('');
    }, [departmentId]);

    // Create mutation
    const mutation = useMutation({
        mutationFn: createEmployee,
        onSuccess: (data) => {
            const employeeId = data?.data?.id || data?.id;
            navigate(`/employees/${employeeId}`);
        },
        onError: (error) => {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        },
    });

    function validateStep(step) {
        const stepErrors = {};

        if (step === 1) {
            if (!fullName.trim()) stepErrors.full_name = 'Full name is required';
            if (!icNumber.trim()) {
                stepErrors.ic_number = 'IC number is required';
            } else if (!/^\d{6}-\d{2}-\d{4}$/.test(icNumber)) {
                stepErrors.ic_number = 'IC format must be YYMMDD-SS-NNNN';
            }
            if (!dateOfBirth) stepErrors.date_of_birth = 'Date of birth is required';
            if (!gender) stepErrors.gender = 'Gender is required';
            if (!phone.trim()) stepErrors.phone = 'Phone number is required';
            if (!personalEmail.trim()) {
                stepErrors.personal_email = 'Email is required';
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(personalEmail)) {
                stepErrors.personal_email = 'Invalid email format';
            }
            if (!addressLine1.trim()) stepErrors.address_line_1 = 'Address is required';
            if (!city.trim()) stepErrors.city = 'City is required';
            if (!state) stepErrors.state = 'State is required';
            if (!postcode.trim()) {
                stepErrors.postcode = 'Postcode is required';
            } else if (!/^\d{5}$/.test(postcode)) {
                stepErrors.postcode = 'Postcode must be 5 digits';
            }
        }

        if (step === 2) {
            if (!departmentId) stepErrors.department_id = 'Department is required';
            if (!positionId) stepErrors.position_id = 'Position is required';
            if (!employmentType) stepErrors.employment_type = 'Employment type is required';
            if (!joinDate) stepErrors.join_date = 'Join date is required';
        }

        if (step === 3) {
            if (!bankName) stepErrors.bank_name = 'Bank name is required';
            if (!bankAccountNumber.trim()) stepErrors.bank_account_number = 'Account number is required';
        }

        if (step === 4) {
            if (!documents.ic_front) stepErrors.ic_front = 'IC front is required';
            if (!documents.ic_back) stepErrors.ic_back = 'IC back is required';
        }

        setErrors(stepErrors);
        return Object.keys(stepErrors).length === 0;
    }

    function handleNext() {
        if (validateStep(currentStep)) {
            setCurrentStep((s) => Math.min(5, s + 1));
        }
    }

    function handleBack() {
        setErrors({});
        setCurrentStep((s) => Math.max(1, s - 1));
    }

    function goToStep(step) {
        setErrors({});
        setCurrentStep(step);
    }

    function handleSubmit() {
        const formData = new FormData();

        // Personal
        formData.append('full_name', fullName);
        formData.append('ic_number', icNumber);
        formData.append('date_of_birth', dateOfBirth);
        formData.append('gender', gender);
        if (religion) formData.append('religion', religion);
        if (race) formData.append('race', race);
        if (maritalStatus) formData.append('marital_status', maritalStatus);
        formData.append('phone', phone);
        formData.append('personal_email', personalEmail);
        formData.append('address_line_1', addressLine1);
        if (addressLine2) formData.append('address_line_2', addressLine2);
        formData.append('city', city);
        formData.append('state', state);
        formData.append('postcode', postcode);
        if (profilePhoto) formData.append('profile_photo', profilePhoto);

        // Employment
        formData.append('department_id', departmentId);
        formData.append('position_id', positionId);
        formData.append('employment_type', employmentType);
        formData.append('join_date', joinDate);
        if (probationEndDate) formData.append('probation_end_date', probationEndDate);
        if (contractEndDate) formData.append('contract_end_date', contractEndDate);
        if (notes) formData.append('notes', notes);

        // Bank & Statutory
        formData.append('bank_name', bankName);
        formData.append('bank_account_number', bankAccountNumber);
        if (epfNumber) formData.append('epf_number', epfNumber);
        if (socsoNumber) formData.append('socso_number', socsoNumber);
        if (taxReferenceNumber) formData.append('tax_reference_number', taxReferenceNumber);

        // Documents
        Object.entries(documents).forEach(([key, file]) => {
            if (file) formData.append(key, file);
        });

        mutation.mutate(formData);
    }

    function setDocument(key, file) {
        setDocuments((prev) => ({ ...prev, [key]: file }));
    }

    function removeDocument(key) {
        setDocuments((prev) => {
            const next = { ...prev };
            delete next[key];
            return next;
        });
    }

    const showContractEndDate =
        employmentType === 'contract' || employmentType === 'intern';

    return (
        <div>
            <PageHeader
                title="Add New Employee"
                description="Fill in the details to create a new employee record."
                action={
                    <Button variant="outline" onClick={() => navigate('/employees')}>
                        <ArrowLeft className="mr-1.5 h-4 w-4" />
                        Back to Directory
                    </Button>
                }
            />

            <StepIndicator
                currentStep={currentStep}
                completedSteps={completedSteps}
            />

            {/* Step 1: Personal Information */}
            {currentStep === 1 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Personal Information</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Full Name" required error={errors.full_name}>
                                <Input
                                    value={fullName}
                                    onChange={(e) => setFullName(e.target.value)}
                                    placeholder="e.g. Ahmad bin Abdullah"
                                />
                            </FormField>

                            <FormField label="IC Number" required error={errors.ic_number}>
                                <Input
                                    value={icNumber}
                                    onChange={(e) => setIcNumber(e.target.value)}
                                    placeholder="YYMMDD-SS-NNNN"
                                />
                            </FormField>

                            <FormField label="Date of Birth" required error={errors.date_of_birth}>
                                <Input
                                    type="date"
                                    value={dateOfBirth}
                                    onChange={(e) => setDateOfBirth(e.target.value)}
                                />
                            </FormField>

                            <FormField label="Gender" required error={errors.gender}>
                                <RadioGroup
                                    value={gender}
                                    onValueChange={setGender}
                                    className="flex gap-6 pt-2"
                                >
                                    <div className="flex items-center gap-2">
                                        <RadioGroupItem value="male" id="gender-male" />
                                        <Label htmlFor="gender-male" className="font-normal">
                                            Male
                                        </Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <RadioGroupItem value="female" id="gender-female" />
                                        <Label htmlFor="gender-female" className="font-normal">
                                            Female
                                        </Label>
                                    </div>
                                </RadioGroup>
                            </FormField>

                            <FormField label="Religion">
                                <Select value={religion} onValueChange={setReligion}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select religion" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {RELIGIONS.map((r) => (
                                            <SelectItem key={r} value={r.toLowerCase()}>
                                                {r}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Race">
                                <Select value={race} onValueChange={setRace}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select race" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {RACES.map((r) => (
                                            <SelectItem key={r} value={r.toLowerCase()}>
                                                {r}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Marital Status">
                                <Select value={maritalStatus} onValueChange={setMaritalStatus}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {MARITAL_STATUSES.map((s) => (
                                            <SelectItem key={s} value={s.toLowerCase()}>
                                                {s}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Phone" required error={errors.phone}>
                                <Input
                                    value={phone}
                                    onChange={(e) => setPhone(e.target.value)}
                                    placeholder="e.g. 012-3456789"
                                />
                            </FormField>

                            <FormField label="Personal Email" required error={errors.personal_email}>
                                <Input
                                    type="email"
                                    value={personalEmail}
                                    onChange={(e) => setPersonalEmail(e.target.value)}
                                    placeholder="e.g. ahmad@email.com"
                                />
                            </FormField>
                        </div>

                        <Separator />

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <FormField label="Address Line 1" required error={errors.address_line_1}>
                                    <Input
                                        value={addressLine1}
                                        onChange={(e) => setAddressLine1(e.target.value)}
                                        placeholder="Street address"
                                    />
                                </FormField>
                            </div>

                            <div className="sm:col-span-2">
                                <FormField label="Address Line 2">
                                    <Input
                                        value={addressLine2}
                                        onChange={(e) => setAddressLine2(e.target.value)}
                                        placeholder="Apartment, suite, unit, etc. (optional)"
                                    />
                                </FormField>
                            </div>

                            <FormField label="City" required error={errors.city}>
                                <Input
                                    value={city}
                                    onChange={(e) => setCity(e.target.value)}
                                    placeholder="e.g. Kuala Lumpur"
                                />
                            </FormField>

                            <FormField label="State" required error={errors.state}>
                                <Select value={state} onValueChange={setState}>
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

                            <FormField label="Postcode" required error={errors.postcode}>
                                <Input
                                    value={postcode}
                                    onChange={(e) => setPostcode(e.target.value)}
                                    placeholder="e.g. 50000"
                                    maxLength={5}
                                />
                            </FormField>
                        </div>

                        <Separator />

                        <FormField label="Profile Photo">
                            <FileInput
                                label=""
                                file={profilePhoto}
                                onChange={setProfilePhoto}
                                onRemove={() => setProfilePhoto(null)}
                            />
                        </FormField>
                    </CardContent>
                </Card>
            )}

            {/* Step 2: Employment Details */}
            {currentStep === 2 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Employment Details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Department" required error={errors.department_id}>
                                <Select value={departmentId} onValueChange={setDepartmentId}>
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
                            </FormField>

                            <FormField label="Position" required error={errors.position_id}>
                                <Select
                                    value={positionId}
                                    onValueChange={setPositionId}
                                    disabled={!departmentId}
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder={
                                                departmentId
                                                    ? 'Select position'
                                                    : 'Select department first'
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {positions.map((pos) => (
                                            <SelectItem key={pos.id} value={String(pos.id)}>
                                                {pos.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Employment Type" required error={errors.employment_type}>
                                <Select value={employmentType} onValueChange={setEmploymentType}>
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

                            <FormField label="Join Date" required error={errors.join_date}>
                                <Input
                                    type="date"
                                    value={joinDate}
                                    onChange={(e) => setJoinDate(e.target.value)}
                                />
                            </FormField>

                            <FormField label="Probation End Date">
                                <Input
                                    type="date"
                                    value={probationEndDate}
                                    onChange={(e) => setProbationEndDate(e.target.value)}
                                />
                                {joinDate && (
                                    <p className="mt-1 text-xs text-zinc-500">
                                        Auto-suggested: 3 months from join date
                                    </p>
                                )}
                            </FormField>

                            {showContractEndDate && (
                                <FormField label="Contract End Date">
                                    <Input
                                        type="date"
                                        value={contractEndDate}
                                        onChange={(e) => setContractEndDate(e.target.value)}
                                    />
                                </FormField>
                            )}
                        </div>

                        <FormField label="Notes">
                            <Textarea
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder="Any additional notes about the employee..."
                                rows={4}
                            />
                        </FormField>
                    </CardContent>
                </Card>
            )}

            {/* Step 3: Bank & Statutory */}
            {currentStep === 3 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Bank & Statutory Information</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Bank Name" required error={errors.bank_name}>
                                <Select value={bankName} onValueChange={setBankName}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select bank" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {BANKS.map((b) => (
                                            <SelectItem key={b} value={b}>
                                                {b}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Bank Account Number" required error={errors.bank_account_number}>
                                <Input
                                    value={bankAccountNumber}
                                    onChange={(e) => setBankAccountNumber(e.target.value)}
                                    placeholder="e.g. 1234567890"
                                />
                            </FormField>
                        </div>

                        <Separator />

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <FormField label="EPF Number">
                                <Input
                                    value={epfNumber}
                                    onChange={(e) => setEpfNumber(e.target.value)}
                                    placeholder="EPF member number"
                                />
                            </FormField>

                            <FormField label="SOCSO Number">
                                <Input
                                    value={socsoNumber}
                                    onChange={(e) => setSocsoNumber(e.target.value)}
                                    placeholder="SOCSO reference number"
                                />
                            </FormField>

                            <FormField label="Tax Reference Number">
                                <Input
                                    value={taxReferenceNumber}
                                    onChange={(e) => setTaxReferenceNumber(e.target.value)}
                                    placeholder="Income tax reference"
                                />
                            </FormField>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Step 4: Documents */}
            {currentStep === 4 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Documents Upload</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-zinc-500">
                            Accepted formats: PDF, JPG, PNG (max 5MB each)
                        </p>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {DOCUMENT_FIELDS.map((doc) => (
                                <FileInput
                                    key={doc.key}
                                    label={doc.label}
                                    required={doc.required}
                                    file={documents[doc.key]}
                                    onChange={(file) => setDocument(doc.key, file)}
                                    onRemove={() => removeDocument(doc.key)}
                                    error={errors[doc.key]}
                                />
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Step 5: Review & Submit */}
            {currentStep === 5 && (
                <div className="space-y-6">
                    {/* Personal Info Summary */}
                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-base">Personal Information</CardTitle>
                            <Button variant="ghost" size="sm" onClick={() => goToStep(1)}>
                                Edit
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <dt className="text-zinc-500">Full Name</dt>
                                    <dd className="font-medium text-zinc-900">{fullName}</dd>
                                </div>
                                <div>
                                    <dt className="text-zinc-500">IC Number</dt>
                                    <dd className="font-medium text-zinc-900">{icNumber}</dd>
                                </div>
                                <div>
                                    <dt className="text-zinc-500">Date of Birth</dt>
                                    <dd className="font-medium text-zinc-900">{dateOfBirth}</dd>
                                </div>
                                <div>
                                    <dt className="text-zinc-500">Gender</dt>
                                    <dd className="font-medium capitalize text-zinc-900">{gender}</dd>
                                </div>
                                {religion && (
                                    <div>
                                        <dt className="text-zinc-500">Religion</dt>
                                        <dd className="font-medium capitalize text-zinc-900">{religion}</dd>
                                    </div>
                                )}
                                {race && (
                                    <div>
                                        <dt className="text-zinc-500">Race</dt>
                                        <dd className="font-medium capitalize text-zinc-900">{race}</dd>
                                    </div>
                                )}
                                {maritalStatus && (
                                    <div>
                                        <dt className="text-zinc-500">Marital Status</dt>
                                        <dd className="font-medium capitalize text-zinc-900">{maritalStatus}</dd>
                                    </div>
                                )}
                                <div>
                                    <dt className="text-zinc-500">Phone</dt>
                                    <dd className="font-medium text-zinc-900">{phone}</dd>
                                </div>
                                <div>
                                    <dt className="text-zinc-500">Email</dt>
                                    <dd className="font-medium text-zinc-900">{personalEmail}</dd>
                                </div>
                                <div className="sm:col-span-2 lg:col-span-3">
                                    <dt className="text-zinc-500">Address</dt>
                                    <dd className="font-medium text-zinc-900">
                                        {addressLine1}
                                        {addressLine2 && `, ${addressLine2}`}
                                        , {city}, {postcode}, {state}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Employment Summary */}
                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-base">Employment Details</CardTitle>
                            <Button variant="ghost" size="sm" onClick={() => goToStep(2)}>
                                Edit
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <dt className="text-zinc-500">Department</dt>
                                    <dd className="font-medium text-zinc-900">
                                        {departments.find((d) => String(d.id) === departmentId)?.name || '-'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-zinc-500">Position</dt>
                                    <dd className="font-medium text-zinc-900">
                                        {positions.find((p) => String(p.id) === positionId)?.name || '-'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-zinc-500">Employment Type</dt>
                                    <dd className="font-medium capitalize text-zinc-900">
                                        {employmentType.replace('-', ' ')}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-zinc-500">Join Date</dt>
                                    <dd className="font-medium text-zinc-900">{joinDate}</dd>
                                </div>
                                {probationEndDate && (
                                    <div>
                                        <dt className="text-zinc-500">Probation End Date</dt>
                                        <dd className="font-medium text-zinc-900">{probationEndDate}</dd>
                                    </div>
                                )}
                                {showContractEndDate && contractEndDate && (
                                    <div>
                                        <dt className="text-zinc-500">Contract End Date</dt>
                                        <dd className="font-medium text-zinc-900">{contractEndDate}</dd>
                                    </div>
                                )}
                                {notes && (
                                    <div className="sm:col-span-2 lg:col-span-3">
                                        <dt className="text-zinc-500">Notes</dt>
                                        <dd className="font-medium text-zinc-900">{notes}</dd>
                                    </div>
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Bank Summary */}
                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-base">Bank & Statutory</CardTitle>
                            <Button variant="ghost" size="sm" onClick={() => goToStep(3)}>
                                Edit
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <dt className="text-zinc-500">Bank Name</dt>
                                    <dd className="font-medium text-zinc-900">{bankName}</dd>
                                </div>
                                <div>
                                    <dt className="text-zinc-500">Account Number</dt>
                                    <dd className="font-medium text-zinc-900">{bankAccountNumber}</dd>
                                </div>
                                {epfNumber && (
                                    <div>
                                        <dt className="text-zinc-500">EPF Number</dt>
                                        <dd className="font-medium text-zinc-900">{epfNumber}</dd>
                                    </div>
                                )}
                                {socsoNumber && (
                                    <div>
                                        <dt className="text-zinc-500">SOCSO Number</dt>
                                        <dd className="font-medium text-zinc-900">{socsoNumber}</dd>
                                    </div>
                                )}
                                {taxReferenceNumber && (
                                    <div>
                                        <dt className="text-zinc-500">Tax Reference</dt>
                                        <dd className="font-medium text-zinc-900">{taxReferenceNumber}</dd>
                                    </div>
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Documents Summary */}
                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-base">Documents</CardTitle>
                            <Button variant="ghost" size="sm" onClick={() => goToStep(4)}>
                                Edit
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {DOCUMENT_FIELDS.map((doc) => (
                                    <div
                                        key={doc.key}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        {documents[doc.key] ? (
                                            <Check className="h-4 w-4 text-emerald-500" />
                                        ) : (
                                            <X className="h-4 w-4 text-zinc-300" />
                                        )}
                                        <span
                                            className={
                                                documents[doc.key]
                                                    ? 'text-zinc-900'
                                                    : 'text-zinc-400'
                                            }
                                        >
                                            {doc.label}
                                        </span>
                                        {documents[doc.key] && (
                                            <span className="text-xs text-zinc-500">
                                                ({formatFileSize(documents[doc.key].size)})
                                            </span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Server Errors */}
                    {mutation.isError && (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            {mutation.error?.response?.data?.message ||
                                'An error occurred while creating the employee. Please try again.'}
                        </div>
                    )}
                </div>
            )}

            {/* Navigation Buttons */}
            <div className="mt-8 flex items-center justify-between">
                <div>
                    {currentStep > 1 && (
                        <Button variant="outline" onClick={handleBack}>
                            <ArrowLeft className="mr-1.5 h-4 w-4" />
                            Back
                        </Button>
                    )}
                </div>
                <div>
                    {currentStep < 5 ? (
                        <Button onClick={handleNext}>
                            Next
                            <ArrowRight className="ml-1.5 h-4 w-4" />
                        </Button>
                    ) : (
                        <Button
                            onClick={handleSubmit}
                            disabled={mutation.isPending}
                        >
                            {mutation.isPending ? (
                                <>
                                    <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                    Creating...
                                </>
                            ) : (
                                <>
                                    <Check className="mr-1.5 h-4 w-4" />
                                    Create Employee
                                </>
                            )}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
