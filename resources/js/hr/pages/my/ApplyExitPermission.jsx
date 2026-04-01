import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    Loader2,
    AlertCircle,
    CheckCircle2,
    ChevronLeft,
} from 'lucide-react';
import { submitExitPermission } from '../../lib/api';
import { Card, CardContent } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/textarea';

const TODAY = new Date().toISOString().split('T')[0];

const ERRAND_OPTIONS = [
    { value: 'company', label: 'Urusan Syarikat (Company Business)' },
    { value: 'personal', label: 'Urusan Peribadi (Personal Business)' },
];

// ========== MAIN COMPONENT ==========
export default function ApplyExitPermission() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [form, setForm] = useState({
        addressed_to: '',
        exit_date: '',
        exit_time: '',
        return_time: '',
        errand_type: '',
        purpose: '',
    });
    const [fieldErrors, setFieldErrors] = useState({});
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);

    const submitMut = useMutation({
        mutationFn: (data) => submitExitPermission(data),
        onSuccess: () => {
            setSuccess(true);
            setError(null);
            queryClient.invalidateQueries({ queryKey: ['my-exit-permissions'] });
            setTimeout(() => navigate('/my/exit-permissions'), 1500);
        },
        onError: (err) => {
            const data = err?.response?.data;
            if (data?.errors) {
                setFieldErrors(data.errors);
                const allErrors = Object.values(data.errors).flat();
                setError(allErrors[0] || 'Please fix the errors below.');
            } else {
                setFieldErrors({});
                setError(data?.message || 'Failed to submit exit permission request.');
            }
        },
    });

    function handleSubmit(e) {
        e.preventDefault();
        setError(null);
        setFieldErrors({});
        submitMut.mutate(form);
    }

    if (success) {
        return (
            <div className="flex flex-col items-center justify-center py-20 text-center">
                <CheckCircle2 className="h-12 w-12 text-emerald-500 mb-3" />
                <h2 className="text-lg font-semibold text-zinc-900">Request Submitted!</h2>
                <p className="text-sm text-zinc-500 mt-1">Your exit permission request has been submitted for approval.</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="flex items-center gap-3">
                <Button variant="ghost" size="sm" onClick={() => navigate('/my/exit-permissions')}>
                    <ChevronLeft className="h-4 w-4" />
                </Button>
                <div>
                    <h1 className="text-xl font-bold text-zinc-900">Apply for Exit Permission</h1>
                    <p className="text-sm text-zinc-500 mt-0.5">Submit a new exit permission request</p>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Addressed To */}
                <Card>
                    <CardContent className="pt-4">
                        <Label className="text-xs font-medium">Kepada (Addressed To) *</Label>
                        <Input
                            type="text"
                            value={form.addressed_to}
                            onChange={(e) => setForm({ ...form, addressed_to: e.target.value })}
                            className="mt-1"
                            placeholder="e.g. HR Manager / Department Head"
                            required
                        />
                        {fieldErrors.addressed_to && (
                            <p className="text-xs text-red-500 mt-1">{fieldErrors.addressed_to[0]}</p>
                        )}
                    </CardContent>
                </Card>

                {/* Exit Date + Times */}
                <Card>
                    <CardContent className="pt-4 space-y-3">
                        <div>
                            <Label className="text-xs font-medium">Exit Date *</Label>
                            <Input
                                type="date"
                                value={form.exit_date}
                                onChange={(e) => setForm({ ...form, exit_date: e.target.value })}
                                className="mt-1"
                                min={TODAY}
                                required
                            />
                            {fieldErrors.exit_date && (
                                <p className="text-xs text-red-500 mt-1">{fieldErrors.exit_date[0]}</p>
                            )}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-xs font-medium">Exit Time (HH:MM) *</Label>
                                <Input
                                    type="time"
                                    value={form.exit_time}
                                    onChange={(e) => setForm({ ...form, exit_time: e.target.value })}
                                    className="mt-1"
                                    required
                                />
                                {fieldErrors.exit_time && (
                                    <p className="text-xs text-red-500 mt-1">{fieldErrors.exit_time[0]}</p>
                                )}
                            </div>
                            <div>
                                <Label className="text-xs font-medium">Return Time (HH:MM) *</Label>
                                <Input
                                    type="time"
                                    value={form.return_time}
                                    onChange={(e) => setForm({ ...form, return_time: e.target.value })}
                                    className="mt-1"
                                    required
                                />
                                {fieldErrors.return_time && (
                                    <p className="text-xs text-red-500 mt-1">{fieldErrors.return_time[0]}</p>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Errand Type */}
                <Card>
                    <CardContent className="pt-4">
                        <Label className="text-xs font-medium">Errand Type *</Label>
                        <div className="mt-2 space-y-2">
                            {ERRAND_OPTIONS.map((option) => (
                                <label key={option.value} className="flex items-center gap-2 text-sm cursor-pointer">
                                    <input
                                        type="radio"
                                        name="errand_type"
                                        value={option.value}
                                        checked={form.errand_type === option.value}
                                        onChange={(e) => setForm({ ...form, errand_type: e.target.value })}
                                        required
                                        className="accent-zinc-800"
                                    />
                                    {option.label}
                                </label>
                            ))}
                        </div>
                        {fieldErrors.errand_type && (
                            <p className="text-xs text-red-500 mt-1">{fieldErrors.errand_type[0]}</p>
                        )}
                    </CardContent>
                </Card>

                {/* Purpose */}
                <Card>
                    <CardContent className="pt-4">
                        <Label className="text-xs font-medium">Purpose / Reason *</Label>
                        <Textarea
                            value={form.purpose}
                            onChange={(e) => setForm({ ...form, purpose: e.target.value })}
                            className="mt-1"
                            rows={3}
                            placeholder="Describe the purpose or reason for leaving (min 10 characters)..."
                            minLength={10}
                            required
                        />
                        {fieldErrors.purpose && (
                            <p className="text-xs text-red-500 mt-1">{fieldErrors.purpose[0]}</p>
                        )}
                    </CardContent>
                </Card>

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
                    disabled={
                        submitMut.isPending ||
                        !form.addressed_to ||
                        !form.exit_date ||
                        !form.exit_time ||
                        !form.return_time ||
                        !form.errand_type ||
                        form.purpose.length < 10
                    }
                >
                    {submitMut.isPending && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
                    Submit Exit Permission Request
                </Button>
            </form>
        </div>
    );
}
