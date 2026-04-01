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
import { fetchMyOvertime, submitMyOvertime, fetchMyOvertimeBalance, cancelMyOvertime, fetchMyOvertimeClaims, submitMyOvertimeClaim, cancelMyOvertimeClaim } from '../../lib/api';
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

const DURATION_PRESETS = [30, 60, 90, 120, 180, 240];

const CLAIM_DURATION_PRESETS = [30, 60, 90, 120, 150, 180, 210, 230];

function formatDuration(minutes) {
    if (!minutes) return '-';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h === 0) return `${m}min`;
    if (m === 0) return `${h}h`;
    return `${h}h ${m}min`;
}

function calcEndTime(startTime, durationMinutes) {
    if (!startTime || !durationMinutes) return null;
    const [h, m] = startTime.split(':').map(Number);
    const totalMins = h * 60 + m + parseInt(durationMinutes);
    const endH = Math.floor(totalMins / 60) % 24;
    const endM = totalMins % 60;
    return `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;
}

function calcDurationMins(startTime, endTime) {
    if (!startTime || !endTime) return null;
    const [sh, sm] = startTime.split(':').map(Number);
    const [eh, em] = endTime.split(':').map(Number);
    let diff = (eh * 60 + em) - (sh * 60 + sm);
    if (diff <= 0) diff += 24 * 60; // handle overnight
    return diff > 0 ? diff : null;
}

// ========== MAIN COMPONENT ==========
export default function MyOvertime() {
    const queryClient = useQueryClient();
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ date: '', start_time: '', duration_minutes: '', reason: '' });
    const [formError, setFormError] = useState(null);

    const [activeTab, setActiveTab] = useState('requests'); // 'requests' | 'claims'
    const [showClaimForm, setShowClaimForm] = useState(false);
    const [claimForm, setClaimForm] = useState({ claim_date: '', start_time: '', duration_minutes: '', notes: '' });
    const [claimFormError, setClaimFormError] = useState(null);

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

    const { data: claimsData, isLoading: claimsLoading } = useQuery({
        queryKey: ['my-overtime-claims'],
        queryFn: () => fetchMyOvertimeClaims(),
    });
    const claims = claimsData?.data ?? [];

    const submitClaimMut = useMutation({
        mutationFn: submitMyOvertimeClaim,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-overtime-claims'] });
            queryClient.invalidateQueries({ queryKey: ['my-overtime-balance'] });
            setShowClaimForm(false);
            setClaimForm({ claim_date: '', start_time: '', duration_minutes: '', notes: '' });
            setClaimFormError(null);
        },
        onError: (err) => {
            setClaimFormError(err?.response?.data?.message || 'Failed to submit claim');
        },
    });

    const cancelClaimMut = useMutation({
        mutationFn: cancelMyOvertimeClaim,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-overtime-claims'] });
            queryClient.invalidateQueries({ queryKey: ['my-overtime-balance'] });
        },
    });

    function resetForm() {
        setForm({ date: '', start_time: '', duration_minutes: '', reason: '' });
        setFormError(null);
    }

    function handleSubmit(e) {
        e.preventDefault();
        setFormError(null);
        const durationMins = parseInt(form.duration_minutes);
        if (!durationMins || durationMins < 30) {
            setFormError('Minimum overtime duration is 30 minutes.');
            return;
        }
        const endTime = calcEndTime(form.start_time, durationMins);
        submitMut.mutate({
            requested_date: form.date,
            start_time: form.start_time,
            end_time: endTime,
            estimated_hours: (durationMins / 60).toFixed(1),
            reason: form.reason,
        });
    }

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-bold text-zinc-900">My Overtime</h1>
                    <p className="text-sm text-zinc-500 mt-0.5">Manage your overtime requests</p>
                </div>
                {activeTab === 'requests' ? (
                    <Button size="sm" onClick={() => { resetForm(); setShowForm(true); }}>
                        <Plus className="h-4 w-4 mr-1" /> New OT Request
                    </Button>
                ) : (
                    <Button size="sm" onClick={() => setShowClaimForm(true)}>
                        <Plus className="h-4 w-4 mr-1" /> New Claim
                    </Button>
                )}
            </div>

            {/* Tab switcher */}
            <div className="flex rounded-lg border border-zinc-200 p-0.5 bg-zinc-50 w-fit">
                <button
                    onClick={() => setActiveTab('requests')}
                    className={cn(
                        'px-4 py-1.5 rounded-md text-sm font-medium transition-colors',
                        activeTab === 'requests'
                            ? 'bg-white text-zinc-900 shadow-sm'
                            : 'text-zinc-500 hover:text-zinc-700'
                    )}
                >
                    OT Requests
                </button>
                <button
                    onClick={() => setActiveTab('claims')}
                    className={cn(
                        'px-4 py-1.5 rounded-md text-sm font-medium transition-colors',
                        activeTab === 'claims'
                            ? 'bg-white text-zinc-900 shadow-sm'
                            : 'text-zinc-500 hover:text-zinc-700'
                    )}
                >
                    My Claims
                </button>
            </div>

            {/* Balance cards — always visible */}
            <div className="grid grid-cols-3 gap-2">
                <Card>
                    <CardContent className="py-3 text-center">
                        <p className="text-lg font-bold text-emerald-600">{balance.total_earned ?? 0}h</p>
                        <p className="text-[10px] text-zinc-500">Earned</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="py-3 text-center">
                        <p className="text-lg font-bold text-amber-600">{balance.total_used ?? 0}h</p>
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

            {/* Tab content */}
            {activeTab === 'requests' ? (
                /* OT Requests List */
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
                                                        {formatDate(req.requested_date)}
                                                    </p>
                                                    <Badge variant={cfg.variant} className="text-[10px]">
                                                        {cfg.label}
                                                    </Badge>
                                                </div>
                                                <p className="text-xs text-zinc-500 mt-0.5">
                                                    {formatTime(req.start_time)}
                                                    {calcDurationMins(req.start_time, req.end_time) && (
                                                        <span> • {formatDuration(calcDurationMins(req.start_time, req.end_time))}</span>
                                                    )}
                                                    {req.estimated_hours && (
                                                        <span> ({req.estimated_hours}h)</span>
                                                    )}
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
            ) : (
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm">OT Claims</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {claimsLoading ? (
                            <div className="flex justify-center py-8">
                                <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                            </div>
                        ) : claims.length === 0 ? (
                            <div className="py-8 text-center">
                                <Clock className="h-8 w-8 text-zinc-300 mx-auto mb-2" />
                                <p className="text-sm text-zinc-500">No claim requests yet</p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {claims.map((claim) => {
                                    const cfg = STATUS_CONFIG[claim.status] || STATUS_CONFIG.pending;
                                    return (
                                        <div
                                            key={claim.id}
                                            className="flex items-center justify-between rounded-lg border border-zinc-100 p-3"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <p className="text-sm font-medium text-zinc-900">
                                                        {formatDate(claim.claim_date)}
                                                    </p>
                                                    <Badge variant={cfg.variant} className="text-[10px]">
                                                        {cfg.label}
                                                    </Badge>
                                                </div>
                                                <p className="text-xs text-zinc-500 mt-0.5">
                                                    {formatTime(claim.start_time)} • {formatDuration(claim.duration_minutes)}
                                                </p>
                                                {claim.notes && (
                                                    <p className="text-xs text-zinc-500 mt-0.5 truncate">{claim.notes}</p>
                                                )}
                                                {claim.rejection_reason && (
                                                    <p className="text-xs text-red-500 mt-0.5">{claim.rejection_reason}</p>
                                                )}
                                            </div>
                                            {claim.status === 'pending' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-7 w-7 p-0 text-red-500 hover:text-red-700 shrink-0 ml-2"
                                                    onClick={() => {
                                                        if (window.confirm('Cancel this OT claim?')) {
                                                            cancelClaimMut.mutate(claim.id);
                                                        }
                                                    }}
                                                    disabled={cancelClaimMut.isPending}
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
            )}

            {/* New OT Request Dialog */}
            <Dialog open={showForm} onOpenChange={setShowForm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New Overtime Request</DialogTitle>
                        <DialogDescription>Submit a new overtime request for approval.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-xs">Date *</Label>
                                <Input
                                    type="date"
                                    value={form.date}
                                    min={new Date().toISOString().split('T')[0]}
                                    onChange={(e) => setForm({ ...form, date: e.target.value })}
                                    className="mt-1"
                                    required
                                />
                            </div>
                            <div>
                                <Label className="text-xs">Start Time *</Label>
                                <Input
                                    type="time"
                                    value={form.start_time}
                                    onChange={(e) => setForm({ ...form, start_time: e.target.value })}
                                    className="mt-1"
                                    required
                                />
                            </div>
                        </div>
                        <div>
                            <Label className="text-xs">Duration *</Label>
                            <div className="mt-1 space-y-2">
                                <div className="flex flex-wrap gap-1.5">
                                    {DURATION_PRESETS.map((mins) => (
                                        <button
                                            key={mins}
                                            type="button"
                                            onClick={() => setForm({ ...form, duration_minutes: String(mins) })}
                                            className={cn(
                                                'px-2.5 py-1 rounded text-xs font-medium border transition-colors',
                                                form.duration_minutes === String(mins)
                                                    ? 'bg-zinc-900 text-white border-zinc-900'
                                                    : 'bg-white text-zinc-600 border-zinc-200 hover:border-zinc-400'
                                            )}
                                        >
                                            {formatDuration(mins)}
                                        </button>
                                    ))}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Input
                                        type="number"
                                        min="30"
                                        max="720"
                                        step="15"
                                        value={form.duration_minutes}
                                        onChange={(e) => setForm({ ...form, duration_minutes: e.target.value })}
                                        placeholder="Custom minutes"
                                        required
                                    />
                                    <span className="text-xs text-zinc-400 shrink-0">min</span>
                                </div>
                                {form.start_time && form.duration_minutes && (() => {
                                    const [sh, sm] = form.start_time.split(':').map(Number);
                                    const crossesMidnight = (sh * 60 + sm) + parseInt(form.duration_minutes) >= 24 * 60;
                                    return (
                                        <p className="text-xs text-zinc-500">
                                            Ends at <span className="font-medium text-zinc-700">{calcEndTime(form.start_time, form.duration_minutes)}</span>
                                            {crossesMidnight && <span className="ml-1 text-amber-600">(next day)</span>}
                                        </p>
                                    );
                                })()}
                            </div>
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

            {/* New Claim Dialog */}
            <Dialog open={showClaimForm} onOpenChange={setShowClaimForm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New OT Claim</DialogTitle>
                        <DialogDescription>
                            Claim your OT hours as time off. Available: <span className="font-semibold">{balance.available ?? 0}h</span>
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={(e) => {
                        e.preventDefault();
                        setClaimFormError(null);
                        const mins = parseInt(claimForm.duration_minutes);
                        const availableMins = Math.round((balance.available ?? 0) * 60);
                        if (!mins || mins < 30) { setClaimFormError('Minimum claim is 30 minutes.'); return; }
                        if (mins > 230) { setClaimFormError('Maximum claim is 230 minutes.'); return; }
                        if (mins > availableMins) { setClaimFormError(`Not enough balance. You have ${availableMins} minutes available.`); return; }
                        submitClaimMut.mutate(claimForm);
                    }} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-xs">Date *</Label>
                                <Input
                                    type="date"
                                    value={claimForm.claim_date}
                                    onChange={(e) => setClaimForm({ ...claimForm, claim_date: e.target.value })}
                                    className="mt-1"
                                    required
                                />
                            </div>
                            <div>
                                <Label className="text-xs">Start Time *</Label>
                                <Input
                                    type="time"
                                    value={claimForm.start_time}
                                    onChange={(e) => setClaimForm({ ...claimForm, start_time: e.target.value })}
                                    className="mt-1"
                                    required
                                />
                            </div>
                        </div>
                        <div>
                            <Label className="text-xs">Duration *</Label>
                            <div className="mt-1 space-y-2">
                                <div className="flex flex-wrap gap-1.5">
                                    {CLAIM_DURATION_PRESETS.map((mins) => (
                                        <button
                                            key={mins}
                                            type="button"
                                            onClick={() => setClaimForm({ ...claimForm, duration_minutes: String(mins) })}
                                            className={cn(
                                                'px-2.5 py-1 rounded text-xs font-medium border transition-colors',
                                                claimForm.duration_minutes === String(mins)
                                                    ? 'bg-zinc-900 text-white border-zinc-900'
                                                    : 'bg-white text-zinc-600 border-zinc-200 hover:border-zinc-400'
                                            )}
                                        >
                                            {formatDuration(mins)}
                                        </button>
                                    ))}
                                </div>
                                <Input
                                    type="number"
                                    min="30"
                                    max="230"
                                    step="15"
                                    value={claimForm.duration_minutes}
                                    onChange={(e) => setClaimForm({ ...claimForm, duration_minutes: e.target.value })}
                                    placeholder="Custom minutes (30–230)"
                                    required
                                />
                            </div>
                        </div>
                        <div>
                            <Label className="text-xs">Notes (optional)</Label>
                            <Textarea
                                value={claimForm.notes}
                                onChange={(e) => setClaimForm({ ...claimForm, notes: e.target.value })}
                                className="mt-1"
                                rows={2}
                                placeholder="Optional note..."
                            />
                        </div>
                        {claimFormError && (
                            <div className="flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 p-3">
                                <AlertCircle className="h-4 w-4 text-red-500 shrink-0" />
                                <p className="text-sm text-red-700">{claimFormError}</p>
                            </div>
                        )}
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowClaimForm(false)}>Cancel</Button>
                            <Button type="submit" disabled={submitClaimMut.isPending}>
                                {submitClaimMut.isPending && <Loader2 className="h-4 w-4 animate-spin mr-1" />}
                                Submit Claim
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
