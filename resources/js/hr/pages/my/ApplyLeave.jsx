import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    CalendarOff,
    Loader2,
    AlertCircle,
    CheckCircle2,
    AlertTriangle,
    ChevronLeft,
    Info,
    Calendar,
    FileText,
    Paperclip,
    ArrowRight,
    Sparkles,
} from 'lucide-react';
import {
    fetchMyLeaveBalances,
    applyForLeave,
    calculateLeaveDays,
    fetchLeaveOverlaps,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import { Card, CardContent } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
import { Textarea } from '../../components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../../components/ui/select';

function FieldLabel({ icon: Icon, accent = 'indigo', required, children }) {
    const colors = {
        indigo: { bg: 'bg-indigo-50 dark:bg-indigo-500/15', text: 'text-indigo-600 dark:text-indigo-300' },
        sky: { bg: 'bg-sky-50 dark:bg-sky-500/15', text: 'text-sky-600 dark:text-sky-300' },
        violet: { bg: 'bg-violet-50 dark:bg-violet-500/15', text: 'text-violet-600 dark:text-violet-300' },
        rose: { bg: 'bg-rose-50 dark:bg-rose-500/15', text: 'text-rose-600 dark:text-rose-300' },
        amber: { bg: 'bg-amber-50 dark:bg-amber-500/15', text: 'text-amber-600 dark:text-amber-300' },
    };
    const c = colors[accent] || colors.indigo;
    return (
        <div className="mb-2 flex items-center gap-2">
            {Icon && (
                <div className={cn('flex h-6 w-6 items-center justify-center rounded-lg', c.bg)}>
                    <Icon className={cn('h-3.5 w-3.5', c.text)} strokeWidth={2.25} />
                </div>
            )}
            <span className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                {children}
                {required && <span className="ml-0.5 text-rose-500">*</span>}
            </span>
        </div>
    );
}

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
    const insufficientBalance = availableAfter !== null && availableAfter < 0;

    const submitMut = useMutation({
        mutationFn: (formData) => applyForLeave(formData),
        onSuccess: () => {
            setSuccess(true);
            setError(null);
            queryClient.invalidateQueries({ queryKey: ['my-leave-requests'] });
            queryClient.invalidateQueries({ queryKey: ['my-leave-balances'] });
            setTimeout(() => navigate('/my/leave'), 1800);
        },
        onError: (err) => {
            const data = err?.response?.data;
            if (data?.errors) {
                setError(Object.values(data.errors).flat().join(' '));
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
        if (form.is_half_day) fd.append('half_day_period', form.half_day_period);
        fd.append('reason', form.reason);
        if (form.attachment) fd.append('attachment', form.attachment);
        submitMut.mutate(fd);
    }

    // ─── Success state ────────────────────────────────────────
    if (success) {
        return (
            <div className="flex flex-col items-center justify-center py-20 text-center">
                <div className="relative h-32 w-32">
                    <div className="absolute inset-0 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 opacity-20 blur-2xl" />
                    <div className="relative flex h-full w-full items-center justify-center rounded-full bg-gradient-to-br from-emerald-100 to-emerald-50 ring-1 ring-emerald-200">
                        <CheckCircle2 className="h-16 w-16 text-emerald-500" strokeWidth={1.75} />
                    </div>
                    <Sparkles className="absolute right-2 top-2 h-5 w-5 text-amber-400 hr-twinkle" />
                    <Sparkles className="absolute left-2 bottom-2 h-4 w-4 text-pink-400 hr-twinkle-2" />
                </div>
                <h2 className="mt-6 text-xl font-bold text-slate-900 dark:text-white">Leave applied!</h2>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Your request has been submitted for approval.</p>
                <p className="mt-1 text-xs text-slate-400 dark:text-slate-500">Redirecting in a moment…</p>
            </div>
        );
    }

    const formValid = form.leave_type_id && form.start_date && form.end_date && form.reason && !insufficientBalance;

    return (
        <div className="space-y-4 pb-4">
            {/* Header with back */}
            <div className="flex items-center gap-3">
                <button
                    onClick={() => navigate('/my/leave')}
                    aria-label="Back"
                    className="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition-all hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-white/[0.07] dark:bg-[#0F1626] dark:text-slate-300"
                >
                    <ChevronLeft className="h-4 w-4" />
                </button>
                <div className="flex-1">
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-violet-700 ring-1 ring-violet-100 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/25">
                        <CalendarOff className="h-3 w-3" strokeWidth={2.5} />
                        Apply for Leave
                    </div>
                    <h1 className="mt-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">Time off request</h1>
                </div>
            </div>

            {/* Live balance preview chip */}
            {selectedBalance && (
                <div className="rounded-2xl border border-pink-100 bg-gradient-to-br from-rose-50 via-amber-50 to-indigo-50 p-3.5">
                    <div className="flex items-center justify-between">
                        <div className="min-w-0">
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                                {selectedBalance.leave_type?.name || selectedBalance.type_name}
                            </p>
                            <div className="mt-1 flex items-baseline gap-2">
                                <span className="text-2xl font-bold tabular-nums text-slate-900">
                                    {parseFloat(selectedBalance.available_days) || 0}
                                </span>
                                <span className="text-xs font-semibold text-slate-500">days available</span>
                            </div>
                        </div>
                        {calculatedDays !== null && (
                            <div className="flex items-center gap-2 text-right">
                                <ArrowRight className="h-4 w-4 text-slate-400" />
                                <div>
                                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">After</p>
                                    <p className={cn(
                                        'mt-1 text-2xl font-bold tabular-nums',
                                        insufficientBalance ? 'text-rose-600' : 'text-emerald-600'
                                    )}>
                                        {availableAfter}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-3">
                {/* Leave Type */}
                <Card className="border-slate-200/80 dark:border-white/[0.07]">
                    <CardContent className="pt-4">
                        <FieldLabel icon={CalendarOff} accent="violet" required>Leave type</FieldLabel>
                        {loadingBalances ? (
                            <div className="flex items-center gap-2">
                                <Loader2 className="h-4 w-4 animate-spin text-slate-400 dark:text-slate-500" />
                                <span className="text-sm text-slate-500 dark:text-slate-400">Loading types…</span>
                            </div>
                        ) : (
                            <Select
                                value={form.leave_type_id}
                                onValueChange={(v) => setForm({ ...form, leave_type_id: v })}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Choose a leave type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {balances.map((bal) => {
                                        const available = parseFloat(bal.available_days) || 0;
                                        return (
                                            <SelectItem
                                                key={bal.leave_type_id || bal.id}
                                                value={String(bal.leave_type_id || bal.id)}
                                            >
                                                {bal.leave_type?.name || bal.type_name} · {available} days
                                            </SelectItem>
                                        );
                                    })}
                                </SelectContent>
                            </Select>
                        )}
                    </CardContent>
                </Card>

                {/* Date Range */}
                <Card className="border-slate-200/80 dark:border-white/[0.07]">
                    <CardContent className="space-y-3 pt-4">
                        <FieldLabel icon={Calendar} accent="sky" required>When</FieldLabel>
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <label className="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">From</label>
                                <Input
                                    type="date"
                                    value={form.start_date}
                                    onChange={(e) => setForm({ ...form, start_date: e.target.value, end_date: form.end_date || e.target.value })}
                                    min={new Date().toISOString().split('T')[0]}
                                    required
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">To</label>
                                <Input
                                    type="date"
                                    value={form.end_date}
                                    onChange={(e) => setForm({ ...form, end_date: e.target.value })}
                                    min={form.start_date}
                                    required
                                />
                            </div>
                        </div>

                        {calculatedDays !== null && (
                            <div className="flex items-center gap-3 rounded-xl border border-sky-100 bg-sky-50 p-2.5 dark:border-sky-500/20 dark:bg-sky-500/10">
                                <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-sky-100 dark:bg-sky-500/15">
                                    <Info className="h-3.5 w-3.5 text-sky-600 dark:text-sky-300" strokeWidth={2.25} />
                                </div>
                                <span className="text-xs font-semibold text-sky-800 dark:text-sky-300">
                                    <span className="tabular-nums">{calculatedDays}</span> working day{calculatedDays !== 1 ? 's' : ''}
                                </span>
                            </div>
                        )}

                        {/* Half Day Toggle */}
                        <div>
                            <label className="flex cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition-colors hover:border-indigo-200 dark:border-white/[0.10] dark:bg-white/[0.05]">
                                <input
                                    type="checkbox"
                                    checked={form.is_half_day}
                                    onChange={(e) => setForm({ ...form, is_half_day: e.target.checked })}
                                    className="h-4 w-4 rounded text-indigo-600 focus:ring-2 focus:ring-indigo-500"
                                />
                                <span className="text-sm font-medium text-slate-700 dark:text-slate-200">Half day only</span>
                            </label>
                            {form.is_half_day && (
                                <div className="mt-2 inline-flex rounded-full border border-slate-200 bg-white p-1 shadow-sm dark:border-white/[0.10] dark:bg-white/[0.05]">
                                    <button
                                        type="button"
                                        onClick={() => setForm({ ...form, half_day_period: 'morning' })}
                                        className={cn(
                                            'rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-wider transition-all',
                                            form.half_day_period === 'morning'
                                                ? 'bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 text-white shadow-sm shadow-pink-500/30'
                                                : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                                        )}
                                    >
                                        Morning
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setForm({ ...form, half_day_period: 'afternoon' })}
                                        className={cn(
                                            'rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-wider transition-all',
                                            form.half_day_period === 'afternoon'
                                                ? 'bg-gradient-to-r from-orange-500 via-rose-500 to-fuchsia-500 text-white shadow-sm shadow-rose-500/30'
                                                : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                                        )}
                                    >
                                        Afternoon
                                    </button>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Overlap warning */}
                {overlaps.length > 0 && (
                    <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50/40 p-3">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100">
                            <AlertTriangle className="h-4 w-4 text-amber-600" strokeWidth={2.25} />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold text-amber-800">Department overlap</p>
                            <p className="mt-0.5 text-xs text-amber-700">Colleagues also off during this period:</p>
                            <ul className="mt-1.5 space-y-0.5 text-xs text-amber-700">
                                {overlaps.map((o, i) => (
                                    <li key={i} className="flex items-center gap-1.5">
                                        <span className="h-1 w-1 rounded-full bg-amber-500" />
                                        {o.employee_name || o.name}
                                        <span className="text-amber-600/70">· {o.leave_type || 'Leave'}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {/* Reason */}
                <Card className="border-slate-200/80 dark:border-white/[0.07]">
                    <CardContent className="pt-4">
                        <FieldLabel icon={FileText} accent="indigo" required>Reason</FieldLabel>
                        <Textarea
                            value={form.reason}
                            onChange={(e) => setForm({ ...form, reason: e.target.value })}
                            rows={3}
                            placeholder="Briefly explain your leave reason…"
                            required
                        />
                    </CardContent>
                </Card>

                {/* Attachment */}
                <Card className="border-slate-200/80 dark:border-white/[0.07]">
                    <CardContent className="pt-4">
                        <FieldLabel
                            icon={Paperclip}
                            accent="amber"
                            required={selectedBalance?.leave_type?.requires_attachment}
                        >
                            Attachment
                        </FieldLabel>
                        <Input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            onChange={(e) => setForm({ ...form, attachment: e.target.files[0] })}
                            required={selectedBalance?.leave_type?.requires_attachment}
                        />
                        <p className="mt-1.5 text-[11px] text-slate-400 dark:text-slate-500">PDF, JPG, PNG, DOC · Max 10MB</p>
                    </CardContent>
                </Card>

                {/* Insufficient balance warning */}
                {insufficientBalance && (
                    <div className="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-500/25 dark:bg-rose-500/15">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-100 dark:bg-rose-500/15">
                            <AlertCircle className="h-4 w-4 text-rose-600 dark:text-rose-300" strokeWidth={2.25} />
                        </div>
                        <p className="text-sm font-semibold text-rose-800 dark:text-rose-300">
                            This request exceeds your available days.
                        </p>
                    </div>
                )}

                {/* Error */}
                {error && (
                    <div className="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-500/25 dark:bg-rose-500/15">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-100 dark:bg-rose-500/15">
                            <AlertCircle className="h-4 w-4 text-rose-600 dark:text-rose-300" strokeWidth={2.25} />
                        </div>
                        <p className="text-sm font-medium text-rose-800 dark:text-rose-300">{error}</p>
                    </div>
                )}

                {/* Submit action pill */}
                <button
                    type="submit"
                    disabled={submitMut.isPending || !formValid}
                    className={cn(
                        'group relative h-14 w-full overflow-hidden rounded-2xl text-white transition-all active:scale-[0.97]',
                        'focus:outline-none focus-visible:ring-4 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-[#080C16]',
                        !formValid || submitMut.isPending
                            ? 'cursor-not-allowed bg-slate-300 shadow-md shadow-slate-300/40 dark:bg-white/[0.08] dark:shadow-none'
                            : 'bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 shadow-xl shadow-pink-500/40 hover:shadow-2xl hover:shadow-pink-500/50 focus-visible:ring-pink-300'
                    )}
                >
                    {formValid && !submitMut.isPending && (
                        <>
                            <span className="pointer-events-none absolute inset-x-0 top-0 h-1/2 rounded-t-2xl bg-gradient-to-b from-white/25 to-transparent" aria-hidden />
                            <span className="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/30 to-transparent transition-transform duration-1000 group-hover:translate-x-full" aria-hidden />
                        </>
                    )}
                    {submitMut.isPending ? (
                        <Loader2 className="mx-auto h-5 w-5 animate-spin" />
                    ) : (
                        <div className="relative flex items-center justify-center gap-2.5">
                            <CalendarOff className="h-5 w-5 drop-shadow-sm" strokeWidth={2.5} />
                            <span className="text-sm font-bold tracking-wider drop-shadow-sm">SUBMIT REQUEST</span>
                            <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" strokeWidth={2.5} />
                        </div>
                    )}
                </button>
            </form>
        </div>
    );
}
