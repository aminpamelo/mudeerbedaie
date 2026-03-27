import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Timer,
    Plus,
    Clock,
    CheckCircle2,
    XCircle,
    Hourglass,
    Loader2,
    AlertCircle,
    Trash2,
} from 'lucide-react';
import { fetchMyOvertime, submitMyOvertime, fetchMyOvertimeBalance, cancelMyOvertime } from '../../lib/api';
import { cn } from '../../lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Badge } from '../../components/ui/badge';
import { Textarea } from '../../components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';

// ---- Helpers ----
function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(timeStr) {
    if (!timeStr) return '--:--';
    if (timeStr.length === 5) return timeStr;
    return new Date(`2000-01-01T${timeStr}`).toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit' });
}

const STATUS_CONFIG = {
    pending: { label: 'Pending', variant: 'secondary', icon: Hourglass, color: 'text-amber-600' },
    approved: { label: 'Approved', variant: 'default', icon: CheckCircle2, color: 'text-emerald-600' },
    rejected: { label: 'Rejected', variant: 'destructive', icon: XCircle, color: 'text-red-600' },
    completed: { label: 'Completed', variant: 'outline', icon: CheckCircle2, color: 'text-blue-600' },
    cancelled: { label: 'Cancelled', variant: 'outline', icon: XCircle, color: 'text-zinc-500' },
};

// ========== MAIN COMPONENT ==========
export default function MyOvertime() {
    const queryClient = useQueryClient();
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ date: '', start_time: '', end_time: '', hours: '', reason: '' });
    const [formError, setFormError] = useState(null);

    const { data: otData, isLoading } = useQuery({
        queryKey: ['my-overtime'],
        queryFn: () => fetchMyOvertime(),
    });
    const requests = otData?.data ?? [];

    const { data: balanceData } = useQuery({
        queryKey: ['my-overtime-balance'],
        queryFn: fetchMyOvertimeBalance,
    });
    const balance = balanceData?.data ?? {};

    const submitMut = useMutation({
        mutationFn: submitMyOvertime,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-overtime'] });
            queryClient.invalidateQueries({ queryKey: ['my-overtime-balance'] });
            setShowForm(false);
            resetForm();
        },
        onError: (err) => {
            setFormError(err?.response?.data?.message || 'Failed to submit request');
        },
    });

    const cancelMut = useMutation({
        mutationFn: cancelMyOvertime,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-overtime'] });
            queryClient.invalidateQueries({ queryKey: ['my-overtime-balance'] });
        },
    });

    function resetForm() {
        setForm({ date: '', start_time: '', end_time: '', hours: '', reason: '' });
        setFormError(null);
    }

    function handleSubmit(e) {
        e.preventDefault();
        setFormError(null);
        submitMut.mutate(form);
    }

    // Auto-calculate hours
    function updateTime(field, value) {
        const newForm = { ...form, [field]: value };
        if (newForm.start_time && newForm.end_time) {
            const start = newForm.start_time.split(':').map(Number);
            const end = newForm.end_time.split(':').map(Number);
            const diff = (end[0] * 60 + end[1]) - (start[0] * 60 + start[1]);
            if (diff > 0) {
                newForm.hours = (diff / 60).toFixed(1);
            }
        }
        setForm(newForm);
    }

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-bold text-zinc-900">My Overtime</h1>
                    <p className="text-sm text-zinc-500 mt-0.5">Manage your overtime requests</p>
                </div>
                <Button size="sm" onClick={() => { resetForm(); setShowForm(true); }}>
                    <Plus className="h-4 w-4 mr-1" /> New OT Request
                </Button>
            </div>

            {/* Replacement Balance */}
            <div className="grid grid-cols-3 gap-2">
                <Card>
                    <CardContent className="py-3 text-center">
                        <p className="text-lg font-bold text-emerald-600">{balance.earned ?? 0}h</p>
                        <p className="text-[10px] text-zinc-500">Earned</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="py-3 text-center">
                        <p className="text-lg font-bold text-amber-600">{balance.used ?? 0}h</p>
                        <p className="text-[10px] text-zinc-500">Used</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="py-3 text-center">
                        <p className="text-lg font-bold text-blue-600">{balance.available ?? 0}h</p>
                        <p className="text-[10px] text-zinc-500">Available</p>
                    </CardContent>
                </Card>
            </div>

            {/* OT Requests List */}
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-sm">OT Requests</CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : requests.length === 0 ? (
                        <div className="py-8 text-center">
                            <Timer className="h-8 w-8 text-zinc-300 mx-auto mb-2" />
                            <p className="text-sm text-zinc-500">No overtime requests yet</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {requests.map((req) => {
                                const cfg = STATUS_CONFIG[req.status] || STATUS_CONFIG.pending;
                                return (
                                    <div
                                        key={req.id}
                                        className="flex items-center justify-between rounded-lg border border-zinc-100 p-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {formatDate(req.date)}
                                                </p>
                                                <Badge variant={cfg.variant} className="text-[10px]">
                                                    {cfg.label}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-zinc-500 mt-0.5">
                                                {formatTime(req.start_time)} - {formatTime(req.end_time)} ({req.hours}h)
                                            </p>
                                            {req.reason && (
                                                <p className="text-xs text-zinc-500 mt-0.5 truncate">{req.reason}</p>
                                            )}
                                        </div>
                                        {req.status === 'pending' && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0 text-red-500 hover:text-red-700 shrink-0 ml-2"
                                                onClick={() => {
                                                    if (window.confirm('Cancel this OT request?')) {
                                                        cancelMut.mutate(req.id);
                                                    }
                                                }}
                                                disabled={cancelMut.isPending}
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* New OT Request Dialog */}
            <Dialog open={showForm} onOpenChange={setShowForm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New Overtime Request</DialogTitle>
                        <DialogDescription>Submit a new overtime request for approval.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <Label className="text-xs">Date *</Label>
                            <Input
                                type="date"
                                value={form.date}
                                onChange={(e) => setForm({ ...form, date: e.target.value })}
                                className="mt-1"
                                required
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-xs">Start Time *</Label>
                                <Input
                                    type="time"
                                    value={form.start_time}
                                    onChange={(e) => updateTime('start_time', e.target.value)}
                                    className="mt-1"
                                    required
                                />
                            </div>
                            <div>
                                <Label className="text-xs">End Time *</Label>
                                <Input
                                    type="time"
                                    value={form.end_time}
                                    onChange={(e) => updateTime('end_time', e.target.value)}
                                    className="mt-1"
                                    required
                                />
                            </div>
                        </div>
                        <div>
                            <Label className="text-xs">Hours</Label>
                            <Input
                                type="number"
                                step="0.5"
                                min="0.5"
                                value={form.hours}
                                onChange={(e) => setForm({ ...form, hours: e.target.value })}
                                className="mt-1"
                                placeholder="Auto-calculated"
                            />
                        </div>
                        <div>
                            <Label className="text-xs">Reason *</Label>
                            <Textarea
                                value={form.reason}
                                onChange={(e) => setForm({ ...form, reason: e.target.value })}
                                className="mt-1"
                                rows={3}
                                placeholder="Describe why overtime is needed..."
                                required
                            />
                        </div>
                        {formError && (
                            <div className="flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 p-3">
                                <AlertCircle className="h-4 w-4 text-red-500 shrink-0" />
                                <p className="text-sm text-red-700">{formError}</p>
                            </div>
                        )}
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowForm(false)}>Cancel</Button>
                            <Button type="submit" disabled={submitMut.isPending}>
                                {submitMut.isPending && <Loader2 className="h-4 w-4 animate-spin mr-1" />}
                                Submit Request
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
