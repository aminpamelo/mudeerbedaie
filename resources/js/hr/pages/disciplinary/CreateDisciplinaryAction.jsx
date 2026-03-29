import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation } from '@tanstack/react-query';
import {
    ChevronLeft,
    Loader2,
    Save,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import {
    createDisciplinaryAction,
    fetchEmployees,
    fetchEmployeeDisciplinaryHistory,
} from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
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
import { cn } from '../../lib/utils';

const TYPE_OPTIONS = [
    { value: 'verbal_warning', label: 'Verbal Warning' },
    { value: 'first_written', label: '1st Written Warning' },
    { value: 'second_written', label: '2nd Written Warning' },
    { value: 'show_cause', label: 'Show Cause' },
    { value: 'termination', label: 'Termination' },
];

const TYPE_LABELS = {
    verbal_warning: 'Verbal Warning',
    first_written: '1st Written Warning',
    second_written: '2nd Written Warning',
    show_cause: 'Show Cause',
    termination: 'Termination',
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export default function CreateDisciplinaryAction() {
    const navigate = useNavigate();
    const [form, setForm] = useState({
        employee_id: '',
        type: '',
        reason: '',
        incident_date: '',
        response_required: false,
        response_deadline: '',
        linked_action_id: '',
    });
    const [errors, setErrors] = useState({});

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'list'],
        queryFn: () => fetchEmployees({ per_page: 200, status: 'active' }),
    });

    const employees = employeesData?.data || [];

    const { data: historyData } = useQuery({
        queryKey: ['hr', 'disciplinary', 'history', form.employee_id],
        queryFn: () => fetchEmployeeDisciplinaryHistory(form.employee_id),
        enabled: !!form.employee_id,
    });

    const previousActions = historyData?.data || [];

    const createMutation = useMutation({
        mutationFn: (data) => createDisciplinaryAction(data),
        onSuccess: (response) => {
            const newId = response?.data?.id;
            if (newId) {
                navigate(`/disciplinary/actions/${newId}`);
            } else {
                navigate('/disciplinary/records');
            }
        },
        onError: (err) => {
            if (err?.response?.data?.errors) {
                setErrors(err.response.data.errors);
            } else {
                alert('Failed to create action: ' + (err?.response?.data?.message || err.message));
            }
        },
    });

    function handleChange(field, value) {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const next = { ...prev };
                delete next[field];
                return next;
            });
        }
    }

    function handleSubmit(e) {
        e.preventDefault();

        const newErrors = {};
        if (!form.employee_id) newErrors.employee_id = ['Employee is required.'];
        if (!form.type) newErrors.type = ['Type is required.'];
        if (!form.reason.trim()) newErrors.reason = ['Reason is required.'];
        if (!form.incident_date) newErrors.incident_date = ['Incident date is required.'];
        if (form.response_required && !form.response_deadline) {
            newErrors.response_deadline = ['Response deadline is required when response is required.'];
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        const payload = {
            employee_id: form.employee_id,
            type: form.type,
            reason: form.reason,
            incident_date: form.incident_date,
            response_required: form.response_required,
            response_deadline: form.response_required ? form.response_deadline : null,
            linked_action_id: form.linked_action_id || null,
        };

        createMutation.mutate(payload);
    }

    return (
        <div>
            <div className="mb-6 flex items-center gap-3">
                <Link to="/disciplinary/records">
                    <Button variant="ghost" size="sm">
                        <ChevronLeft className="mr-1 h-4 w-4" />
                        Back
                    </Button>
                </Link>
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-zinc-900">
                        New Disciplinary Action
                    </h1>
                    <p className="mt-1 text-sm text-zinc-500">
                        Create a new disciplinary action as a draft.
                    </p>
                </div>
            </div>

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Main Form */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Action Details</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Employee */}
                                <div>
                                    <Label className="mb-1.5 block">Employee <span className="text-red-500">*</span></Label>
                                    <Select
                                        value={form.employee_id}
                                        onValueChange={(v) => {
                                            handleChange('employee_id', v);
                                            handleChange('linked_action_id', '');
                                        }}
                                    >
                                        <SelectTrigger className={cn(errors.employee_id && 'border-red-500')}>
                                            <SelectValue placeholder="Select employee..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {employees.map((emp) => (
                                                <SelectItem key={emp.id} value={String(emp.id)}>
                                                    {emp.full_name} ({emp.employee_id})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.employee_id && (
                                        <p className="mt-1 text-xs text-red-500">{errors.employee_id[0]}</p>
                                    )}
                                </div>

                                {/* Type */}
                                <div>
                                    <Label className="mb-1.5 block">Type <span className="text-red-500">*</span></Label>
                                    <Select
                                        value={form.type}
                                        onValueChange={(v) => handleChange('type', v)}
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

                                {/* Incident Date */}
                                <div>
                                    <Label className="mb-1.5 block">Incident Date <span className="text-red-500">*</span></Label>
                                    <Input
                                        type="date"
                                        value={form.incident_date}
                                        onChange={(e) => handleChange('incident_date', e.target.value)}
                                        className={cn(errors.incident_date && 'border-red-500')}
                                    />
                                    {errors.incident_date && (
                                        <p className="mt-1 text-xs text-red-500">{errors.incident_date[0]}</p>
                                    )}
                                </div>

                                {/* Reason */}
                                <div>
                                    <Label className="mb-1.5 block">Reason <span className="text-red-500">*</span></Label>
                                    <Textarea
                                        placeholder="Describe the reason for this disciplinary action..."
                                        value={form.reason}
                                        onChange={(e) => handleChange('reason', e.target.value)}
                                        rows={5}
                                        className={cn(errors.reason && 'border-red-500')}
                                    />
                                    {errors.reason && (
                                        <p className="mt-1 text-xs text-red-500">{errors.reason[0]}</p>
                                    )}
                                </div>

                                {/* Response Required */}
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="response_required"
                                        checked={form.response_required}
                                        onCheckedChange={(checked) => handleChange('response_required', !!checked)}
                                    />
                                    <div>
                                        <Label htmlFor="response_required" className="cursor-pointer">
                                            Response Required
                                        </Label>
                                        <p className="text-xs text-zinc-500">
                                            Employee must respond to this disciplinary action by the deadline
                                        </p>
                                    </div>
                                </div>

                                {/* Response Deadline */}
                                {form.response_required && (
                                    <div>
                                        <Label className="mb-1.5 block">Response Deadline <span className="text-red-500">*</span></Label>
                                        <Input
                                            type="date"
                                            value={form.response_deadline}
                                            onChange={(e) => handleChange('response_deadline', e.target.value)}
                                            className={cn(errors.response_deadline && 'border-red-500')}
                                        />
                                        {errors.response_deadline && (
                                            <p className="mt-1 text-xs text-red-500">{errors.response_deadline[0]}</p>
                                        )}
                                    </div>
                                )}

                                {/* Link to Previous Action */}
                                {form.employee_id && previousActions.length > 0 && (
                                    <div>
                                        <Label className="mb-1.5 block">Link to Previous Action (Optional)</Label>
                                        <Select
                                            value={form.linked_action_id}
                                            onValueChange={(v) => handleChange('linked_action_id', v)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="No linked action" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="">No linked action</SelectItem>
                                                {previousActions.map((pa) => (
                                                    <SelectItem key={pa.id} value={String(pa.id)}>
                                                        {pa.reference_number} - {TYPE_LABELS[pa.type] || pa.type} ({formatDate(pa.incident_date)})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="mt-1 text-xs text-zinc-400">
                                            Optionally link this action to a previous disciplinary case for this employee
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Submit */}
                        <div className="flex items-center gap-3">
                            <Button
                                type="submit"
                                disabled={createMutation.isPending}
                            >
                                {createMutation.isPending ? (
                                    <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                ) : (
                                    <Save className="mr-1.5 h-4 w-4" />
                                )}
                                Create Draft
                            </Button>
                            <Link to="/disciplinary/records">
                                <Button variant="outline" type="button">Cancel</Button>
                            </Link>
                        </div>
                    </div>

                    {/* Sidebar - Employee History */}
                    <div>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Employee History</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!form.employee_id ? (
                                    <div className="flex flex-col items-center justify-center py-6 text-center">
                                        <p className="text-xs text-zinc-400">
                                            Select an employee to view their disciplinary history
                                        </p>
                                    </div>
                                ) : previousActions.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center py-6 text-center">
                                        <p className="text-sm font-medium text-zinc-500">No previous actions</p>
                                        <p className="text-xs text-zinc-400">
                                            This employee has no prior disciplinary records
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        {previousActions.map((pa) => (
                                            <div
                                                key={pa.id}
                                                className="rounded-lg border border-zinc-200 p-3"
                                            >
                                                <div className="flex items-center justify-between">
                                                    <span className="text-xs font-medium text-zinc-900">
                                                        {pa.reference_number}
                                                    </span>
                                                    <span className={cn(
                                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                                        pa.status === 'closed' ? 'bg-zinc-100 text-zinc-700' : 'bg-amber-100 text-amber-700'
                                                    )}>
                                                        {pa.status}
                                                    </span>
                                                </div>
                                                <p className="mt-1 text-xs text-zinc-600">
                                                    {TYPE_LABELS[pa.type] || pa.type}
                                                </p>
                                                <p className="text-xs text-zinc-400">
                                                    {formatDate(pa.incident_date)}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </div>
    );
}
