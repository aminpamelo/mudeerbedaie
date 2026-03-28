import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    CalendarOff,
    Upload,
    Loader2,
    AlertCircle,
    CheckCircle2,
    AlertTriangle,
    ChevronLeft,
    Info,
} from 'lucide-react';
import {
    fetchMyLeaveBalances,
    applyForLeave,
    calculateLeaveDays,
    fetchLeaveOverlaps,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../../components/ui/select';

// ========== MAIN COMPONENT ==========
export default function ApplyLeave() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [form, setForm] = useState({
        leave_type_id: '',
        start_date: '',
        end_date: '',
        is_half_day: false,
        half_day_period: 'morning',
        reason: '',
        attachment: null,
    });
    const [calculatedDays, setCalculatedDays] = useState(null);
    const [overlaps, setOverlaps] = useState([]);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);

    const { data: balancesData, isLoading: loadingBalances } = useQuery({
        queryKey: ['my-leave-balances'],
        queryFn: fetchMyLeaveBalances,
    });
    const balances = balancesData?.data ?? [];

    // Calculate working days when dates change
    useEffect(() => {
        if (!form.start_date || !form.end_date) {
            setCalculatedDays(null);
            return;
        }
        const params = {
            start_date: form.start_date,
            end_date: form.end_date,
            is_half_day: form.is_half_day ? '1' : '0',
        };
        calculateLeaveDays(params)
            .then((res) => setCalculatedDays(res.data?.working_days ?? null))
            .catch(() => setCalculatedDays(null));
    }, [form.start_date, form.end_date, form.is_half_day]);

    // Fetch overlaps when dates change
    useEffect(() => {
        if (!form.start_date || !form.end_date) {
            setOverlaps([]);
            return;
        }
        fetchLeaveOverlaps({ start_date: form.start_date, end_date: form.end_date })
            .then((res) => setOverlaps(res.data ?? []))
            .catch(() => setOverlaps([]));
    }, [form.start_date, form.end_date]);

    const selectedBalance = balances.find(
        (b) => String(b.leave_type_id || b.id) === String(form.leave_type_id)
    );
    const availableAfter = selectedBalance
        ? (parseFloat(selectedBalance.available_days) || 0) - (calculatedDays || 0)
        : null;

    const submitMut = useMutation({
        mutationFn: (formData) => applyForLeave(formData),
        onSuccess: () => {
            setSuccess(true);
            setError(null);
            queryClient.invalidateQueries({ queryKey: ['my-leave-requests'] });
            queryClient.invalidateQueries({ queryKey: ['my-leave-balances'] });
            setTimeout(() => navigate('/my/leave'), 1500);
        },
        onError: (err) => {
            const data = err?.response?.data;
            if (data?.errors) {
                const allErrors = Object.values(data.errors).flat();
                setError(allErrors.join(' '));
            } else {
                setError(data?.message || 'Failed to submit leave application');
            }
        },
    });

    function handleSubmit(e) {
        e.preventDefault();
        setError(null);
        const fd = new FormData();
        fd.append('leave_type_id', form.leave_type_id);
        fd.append('start_date', form.start_date);
        fd.append('end_date', form.end_date);
        fd.append('is_half_day', form.is_half_day ? '1' : '0');
        if (form.is_half_day) {
            fd.append('half_day_period', form.half_day_period);
        }
        fd.append('reason', form.reason);
        if (form.attachment) {
            fd.append('attachment', form.attachment);
        }
        submitMut.mutate(fd);
    }

    if (success) {
        return (
            <div className="flex flex-col items-center justify-center py-20 text-center">
                <CheckCircle2 className="h-12 w-12 text-emerald-500 mb-3" />
                <h2 className="text-lg font-semibold text-zinc-900">Leave Applied!</h2>
                <p className="text-sm text-zinc-500 mt-1">Your request has been submitted for approval.</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="flex items-center gap-3">
                <Button variant="ghost" size="sm" onClick={() => navigate('/my/leave')}>
                    <ChevronLeft className="h-4 w-4" />
                </Button>
                <div>
                    <h1 className="text-xl font-bold text-zinc-900">Apply for Leave</h1>
                    <p className="text-sm text-zinc-500 mt-0.5">Submit a new leave application</p>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Leave Type */}
                <Card>
                    <CardContent className="pt-4">
                        <Label className="text-xs font-medium">Leave Type *</Label>
                        {loadingBalances ? (
                            <div className="flex items-center gap-2 mt-2">
                                <Loader2 className="h-4 w-4 animate-spin text-zinc-400" />
                                <span className="text-sm text-zinc-500">Loading types...</span>
                            </div>
                        ) : (
                            <Select
                                value={form.leave_type_id}
                                onValueChange={(v) => setForm({ ...form, leave_type_id: v })}
                            >
                                <SelectTrigger className="mt-1">
                                    <SelectValue placeholder="Select leave type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {balances.map((bal) => {
                                        const available = parseFloat(bal.available_days) || 0;
                                        return (
                                            <SelectItem
                                                key={bal.leave_type_id || bal.id}
                                                value={String(bal.leave_type_id || bal.id)}
                                            >
                                                {bal.leave_type?.name || bal.type_name} ({available} days remaining)
                                            </SelectItem>
                                        );
                                    })}
                                </SelectContent>
                            </Select>
                        )}
                    </CardContent>
                </Card>

                {/* Date Range */}
                <Card>
                    <CardContent className="pt-4 space-y-3">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-xs font-medium">Start Date *</Label>
                                <Input
                                    type="date"
                                    value={form.start_date}
                                    onChange={(e) => setForm({ ...form, start_date: e.target.value, end_date: form.end_date || e.target.value })}
                                    className="mt-1"
                                    min={new Date().toISOString().split('T')[0]}
                                    required
                                />
                            </div>
                            <div>
                                <Label className="text-xs font-medium">End Date *</Label>
                                <Input
                                    type="date"
                                    value={form.end_date}
                                    onChange={(e) => setForm({ ...form, end_date: e.target.value })}
                                    className="mt-1"
                                    min={form.start_date}
                                    required
                                />
                            </div>
                        </div>

                        {calculatedDays !== null && (
                            <div className="flex items-center gap-2 rounded-lg bg-blue-50 border border-blue-200 p-2.5">
                                <Info className="h-4 w-4 text-blue-500 shrink-0" />
                                <span className="text-sm text-blue-700">
                                    {calculatedDays} working day{calculatedDays !== 1 ? 's' : ''}
                                </span>
                            </div>
                        )}

                        {/* Half Day Toggle */}
                        <div className="space-y-2">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.is_half_day}
                                    onChange={(e) => setForm({ ...form, is_half_day: e.target.checked })}
                                    className="rounded"
                                />
                                Half Day
                            </label>
                            {form.is_half_day && (
                                <div className="flex gap-2 ml-6">
                                    <button
                                        type="button"
                                        onClick={() => setForm({ ...form, half_day_period: 'morning' })}
                                        className={cn(
                                            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                            form.half_day_period === 'morning'
                                                ? 'bg-zinc-900 text-white'
                                                : 'bg-zinc-100 text-zinc-600'
                                        )}
                                    >
                                        Morning
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setForm({ ...form, half_day_period: 'afternoon' })}
                                        className={cn(
                                            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                            form.half_day_period === 'afternoon'
                                                ? 'bg-zinc-900 text-white'
                                                : 'bg-zinc-100 text-zinc-600'
                                        )}
                                    >
                                        Afternoon
                                    </button>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Overlap Warning */}
                {overlaps.length > 0 && (
                    <div className="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 p-3">
                        <AlertTriangle className="h-4 w-4 text-amber-500 shrink-0 mt-0.5" />
                        <div>
                            <p className="text-sm font-medium text-amber-800">Department Overlap</p>
                            <p className="text-xs text-amber-700 mt-0.5">
                                The following colleagues are also on leave during this period:
                            </p>
                            <ul className="text-xs text-amber-700 mt-1 space-y-0.5">
                                {overlaps.map((o, i) => (
                                    <li key={i}>
                                        {o.employee_name || o.name} ({o.leave_type || 'Leave'})
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {/* Reason */}
                <Card>
                    <CardContent className="pt-4">
                        <Label className="text-xs font-medium">Reason *</Label>
                        <Textarea
                            value={form.reason}
                            onChange={(e) => setForm({ ...form, reason: e.target.value })}
                            className="mt-1"
                            rows={3}
                            placeholder="Enter leave reason..."
                            required
                        />
                    </CardContent>
                </Card>

                {/* Attachment */}
                <Card>
                    <CardContent className="pt-4">
                        <Label className="text-xs font-medium">
                            Attachment
                            {selectedBalance?.leave_type?.requires_attachment && (
                                <span className="text-red-500 ml-0.5">*</span>
                            )}
                        </Label>
                        <Input
                            type="file"
                            className="mt-1"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            onChange={(e) => setForm({ ...form, attachment: e.target.files[0] })}
                            required={selectedBalance?.leave_type?.requires_attachment}
                        />
                        <p className="text-[11px] text-zinc-400 mt-1">PDF, JPG, PNG, DOC. Max 10MB.</p>
                    </CardContent>
                </Card>

                {/* Balance Preview */}
                {selectedBalance && calculatedDays !== null && (
                    <Card>
                        <CardContent className="py-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-zinc-600">After this request:</span>
                                <span className={cn(
                                    'text-sm font-bold',
                                    availableAfter < 0 ? 'text-red-600' : 'text-emerald-600'
                                )}>
                                    {availableAfter} days remaining
                                </span>
                            </div>
                            {availableAfter < 0 && (
                                <p className="text-xs text-red-500 mt-1">
                                    Insufficient leave balance. This request exceeds your available days.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Error */}
                {error && (
                    <div className="flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 p-3">
                        <AlertCircle className="h-4 w-4 text-red-500 shrink-0" />
                        <p className="text-sm text-red-700">{error}</p>
                    </div>
                )}

                {/* Submit */}
                <Button
                    type="submit"
                    className="w-full"
                    disabled={submitMut.isPending || !form.leave_type_id || !form.start_date || !form.end_date || !form.reason}
                >
                    {submitMut.isPending && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
                    Submit Leave Application
                </Button>
            </form>
        </div>
    );
}
