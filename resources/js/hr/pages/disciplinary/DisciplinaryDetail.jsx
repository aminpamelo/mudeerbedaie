import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ChevronLeft,
    Send,
    XCircle,
    Download,
    Loader2,
    User,
    Calendar,
    FileText,
    Scale,
    Clock,
    MapPin,
    Users,
    CheckCircle,
    AlertTriangle,
} from 'lucide-react';
import {
    fetchDisciplinaryAction,
    issueDisciplinaryAction,
    closeDisciplinaryAction,
    downloadDisciplinaryPdf,
    createDisciplinaryInquiry,
    fetchDisciplinaryInquiry,
    completeDisciplinaryInquiry,
} from '../../lib/api';
import { Button } from '../../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import { cn } from '../../lib/utils';

const STATUS_CONFIG = {
    draft: { label: 'Draft', bg: 'bg-zinc-100', text: 'text-zinc-700' },
    issued: { label: 'Issued', bg: 'bg-blue-100', text: 'text-blue-700' },
    pending_response: { label: 'Pending Response', bg: 'bg-amber-100', text: 'text-amber-700' },
    responded: { label: 'Responded', bg: 'bg-purple-100', text: 'text-purple-700' },
    closed: { label: 'Closed', bg: 'bg-zinc-100', text: 'text-zinc-700' },
};

const TYPE_LABELS = {
    verbal_warning: 'Verbal Warning',
    first_written: '1st Written Warning',
    second_written: '2nd Written Warning',
    show_cause: 'Show Cause',
    termination: 'Termination',
};

const INQUIRY_DECISION_OPTIONS = [
    { value: 'guilty', label: 'Guilty' },
    { value: 'not_guilty', label: 'Not Guilty' },
    { value: 'reduced', label: 'Reduced Penalty' },
    { value: 'dismissed', label: 'Case Dismissed' },
];

const PENALTY_OPTIONS = [
    { value: 'none', label: 'No Penalty' },
    { value: 'warning', label: 'Warning' },
    { value: 'suspension', label: 'Suspension' },
    { value: 'demotion', label: 'Demotion' },
    { value: 'pay_cut', label: 'Pay Cut' },
    { value: 'termination', label: 'Termination' },
];

function StatusBadge({ status }) {
    const config = STATUS_CONFIG[status] || { label: status, bg: 'bg-zinc-100', text: 'text-zinc-700' };
    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', config.bg, config.text)}>
            {config.label}
        </span>
    );
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function DisciplinaryDetail() {
    const { id } = useParams();
    const queryClient = useQueryClient();
    const [confirmDialog, setConfirmDialog] = useState({ open: false, action: null });
    const [inquiryDialog, setInquiryDialog] = useState(false);
    const [completeInquiryDialog, setCompleteInquiryDialog] = useState(false);
    const [downloading, setDownloading] = useState(false);

    const [inquiryForm, setInquiryForm] = useState({
        scheduled_date: '',
        scheduled_time: '',
        location: '',
        panel_members: '',
    });

    const [completeForm, setCompleteForm] = useState({
        decision: '',
        findings: '',
        penalty: '',
    });

    const { data: actionData, isLoading } = useQuery({
        queryKey: ['hr', 'disciplinary', 'action', id],
        queryFn: () => fetchDisciplinaryAction(id),
    });

    const action = actionData?.data;

    const { data: inquiryData } = useQuery({
        queryKey: ['hr', 'disciplinary', 'inquiry', action?.inquiry_id],
        queryFn: () => fetchDisciplinaryInquiry(action.inquiry_id),
        enabled: !!action?.inquiry_id,
    });

    const inquiry = inquiryData?.data;

    const issueMutation = useMutation({
        mutationFn: () => issueDisciplinaryAction(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'disciplinary'] });
            setConfirmDialog({ open: false, action: null });
        },
        onError: (err) => {
            alert('Failed to issue: ' + (err?.response?.data?.message || err.message));
            setConfirmDialog({ open: false, action: null });
        },
    });

    const closeMutation = useMutation({
        mutationFn: () => closeDisciplinaryAction(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'disciplinary'] });
            setConfirmDialog({ open: false, action: null });
        },
        onError: (err) => {
            alert('Failed to close: ' + (err?.response?.data?.message || err.message));
            setConfirmDialog({ open: false, action: null });
        },
    });

    const createInquiryMutation = useMutation({
        mutationFn: (data) => createDisciplinaryInquiry(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'disciplinary'] });
            setInquiryDialog(false);
            setInquiryForm({ scheduled_date: '', scheduled_time: '', location: '', panel_members: '' });
        },
        onError: (err) => {
            alert('Failed to schedule inquiry: ' + (err?.response?.data?.message || err.message));
        },
    });

    const completeInquiryMutation = useMutation({
        mutationFn: (data) => completeDisciplinaryInquiry(action.inquiry_id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'disciplinary'] });
            setCompleteInquiryDialog(false);
            setCompleteForm({ decision: '', findings: '', penalty: '' });
        },
        onError: (err) => {
            alert('Failed to complete inquiry: ' + (err?.response?.data?.message || err.message));
        },
    });

    async function handleDownloadPdf() {
        setDownloading(true);
        try {
            const blob = await downloadDisciplinaryPdf(id);
            const url = window.URL.createObjectURL(new Blob([blob]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Disciplinary_${action?.reference_number || id}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            alert('Failed to download PDF: ' + (err?.response?.data?.message || err.message));
        } finally {
            setDownloading(false);
        }
    }

    function handleConfirmAction() {
        if (confirmDialog.action === 'issue') {
            issueMutation.mutate();
        } else if (confirmDialog.action === 'close') {
            closeMutation.mutate();
        }
    }

    function handleScheduleInquiry() {
        const panelArray = inquiryForm.panel_members
            .split(',')
            .map((m) => m.trim())
            .filter(Boolean);

        createInquiryMutation.mutate({
            disciplinary_action_id: id,
            scheduled_date: inquiryForm.scheduled_date,
            scheduled_time: inquiryForm.scheduled_time,
            location: inquiryForm.location,
            panel_members: panelArray,
        });
    }

    function handleCompleteInquiry() {
        completeInquiryMutation.mutate({
            decision: completeForm.decision,
            findings: completeForm.findings,
            penalty: completeForm.penalty,
        });
    }

    const isAnyPending = issueMutation.isPending || closeMutation.isPending;

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-24">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (!action) {
        return (
            <div className="flex flex-col items-center justify-center py-24">
                <p className="text-sm text-zinc-500">Disciplinary action not found.</p>
                <Link to="/disciplinary/records" className="mt-3">
                    <Button variant="outline" size="sm">Back to Records</Button>
                </Link>
            </div>
        );
    }

    const timeline = action.timeline || [];

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <Link to="/disciplinary/records">
                        <Button variant="ghost" size="sm">
                            <ChevronLeft className="mr-1 h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold text-zinc-900">
                                {action.reference_number || 'Disciplinary Action'}
                            </h1>
                            <StatusBadge status={action.status} />
                        </div>
                        <p className="text-sm text-zinc-500">
                            {TYPE_LABELS[action.type] || action.type} &middot; Created {formatDate(action.created_at)}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleDownloadPdf}
                        disabled={downloading}
                    >
                        {downloading ? (
                            <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                        ) : (
                            <Download className="mr-1.5 h-4 w-4" />
                        )}
                        Download PDF
                    </Button>
                    {action.status === 'draft' && (
                        <Button
                            size="sm"
                            onClick={() => setConfirmDialog({ open: true, action: 'issue' })}
                            disabled={isAnyPending}
                        >
                            <Send className="mr-1.5 h-4 w-4" />
                            Issue
                        </Button>
                    )}
                    {['issued', 'pending_response', 'responded'].includes(action.status) && !action.inquiry_id && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setInquiryDialog(true)}
                        >
                            <Scale className="mr-1.5 h-4 w-4" />
                            Schedule Inquiry
                        </Button>
                    )}
                    {['issued', 'pending_response', 'responded'].includes(action.status) && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setConfirmDialog({ open: true, action: 'close' })}
                            disabled={isAnyPending}
                        >
                            <XCircle className="mr-1.5 h-4 w-4" />
                            Close
                        </Button>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Main Details */}
                <div className="space-y-6 lg:col-span-2">
                    {/* Employee Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Employee Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="flex items-start gap-3">
                                    <User className="mt-0.5 h-4 w-4 text-zinc-400" />
                                    <div>
                                        <p className="text-xs text-zinc-500">Employee</p>
                                        <p className="text-sm font-medium text-zinc-900">
                                            {action.employee?.full_name || '-'}
                                        </p>
                                        <p className="text-xs text-zinc-500">
                                            {action.employee?.employee_id || ''}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <Users className="mt-0.5 h-4 w-4 text-zinc-400" />
                                    <div>
                                        <p className="text-xs text-zinc-500">Department</p>
                                        <p className="text-sm font-medium text-zinc-900">
                                            {action.employee?.department?.name || '-'}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <FileText className="mt-0.5 h-4 w-4 text-zinc-400" />
                                    <div>
                                        <p className="text-xs text-zinc-500">Position</p>
                                        <p className="text-sm font-medium text-zinc-900">
                                            {action.employee?.position?.name || '-'}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <Calendar className="mt-0.5 h-4 w-4 text-zinc-400" />
                                    <div>
                                        <p className="text-xs text-zinc-500">Incident Date</p>
                                        <p className="text-sm font-medium text-zinc-900">
                                            {formatDate(action.incident_date)}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Reason / Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Reason & Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div>
                                    <p className="mb-1 text-xs font-medium text-zinc-500">Reason</p>
                                    <p className="whitespace-pre-wrap text-sm text-zinc-700">
                                        {action.reason || 'No reason provided.'}
                                    </p>
                                </div>
                                {action.response_required && (
                                    <div className="rounded-lg bg-amber-50 p-3">
                                        <p className="text-xs font-semibold text-amber-700">Response Required</p>
                                        <p className="text-xs text-amber-600">
                                            Deadline: {formatDate(action.response_deadline)}
                                        </p>
                                    </div>
                                )}
                                {action.employee_response && (
                                    <div>
                                        <p className="mb-1 text-xs font-medium text-zinc-500">Employee Response</p>
                                        <div className="rounded-lg bg-zinc-50 p-3">
                                            <p className="whitespace-pre-wrap text-sm text-zinc-700">
                                                {action.employee_response}
                                            </p>
                                            <p className="mt-2 text-xs text-zinc-400">
                                                Responded on {formatDateTime(action.responded_at)}
                                            </p>
                                        </div>
                                    </div>
                                )}
                                {action.linked_action && (
                                    <div>
                                        <p className="mb-1 text-xs font-medium text-zinc-500">Previous Action</p>
                                        <Link
                                            to={`/disciplinary/actions/${action.linked_action.id}`}
                                            className="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700"
                                        >
                                            {action.linked_action.reference_number} - {TYPE_LABELS[action.linked_action.type] || action.linked_action.type}
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Inquiry Panel */}
                    {inquiry && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-base">Domestic Inquiry</CardTitle>
                                    {inquiry.status === 'scheduled' && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setCompleteInquiryDialog(true)}
                                        >
                                            <CheckCircle className="mr-1.5 h-4 w-4" />
                                            Complete Inquiry
                                        </Button>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="flex items-start gap-3">
                                            <Calendar className="mt-0.5 h-4 w-4 text-zinc-400" />
                                            <div>
                                                <p className="text-xs text-zinc-500">Date & Time</p>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {formatDate(inquiry.scheduled_date)} at {inquiry.scheduled_time || '-'}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <MapPin className="mt-0.5 h-4 w-4 text-zinc-400" />
                                            <div>
                                                <p className="text-xs text-zinc-500">Location</p>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {inquiry.location || '-'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    {inquiry.panel_members && inquiry.panel_members.length > 0 && (
                                        <div>
                                            <p className="mb-2 text-xs font-medium text-zinc-500">Panel Members</p>
                                            <div className="flex flex-wrap gap-2">
                                                {inquiry.panel_members.map((member, i) => (
                                                    <span
                                                        key={i}
                                                        className="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700"
                                                    >
                                                        {member}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    {inquiry.decision && (
                                        <div className="rounded-lg border border-zinc-200 p-4">
                                            <p className="mb-2 text-xs font-semibold text-zinc-900">Inquiry Result</p>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div>
                                                    <p className="text-xs text-zinc-500">Decision</p>
                                                    <p className="text-sm font-medium capitalize text-zinc-900">
                                                        {inquiry.decision?.replace(/_/g, ' ') || '-'}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-zinc-500">Penalty</p>
                                                    <p className="text-sm font-medium capitalize text-zinc-900">
                                                        {inquiry.penalty?.replace(/_/g, ' ') || 'None'}
                                                    </p>
                                                </div>
                                            </div>
                                            {inquiry.findings && (
                                                <div className="mt-3">
                                                    <p className="text-xs text-zinc-500">Findings</p>
                                                    <p className="mt-1 whitespace-pre-wrap text-sm text-zinc-700">
                                                        {inquiry.findings}
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Sidebar - Timeline */}
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Timeline</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {timeline.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-6 text-center">
                                    <Clock className="mb-2 h-8 w-8 text-zinc-300" />
                                    <p className="text-xs text-zinc-400">No timeline events yet</p>
                                </div>
                            ) : (
                                <div className="relative space-y-0">
                                    {timeline.map((event, index) => (
                                        <div key={index} className="relative pb-6 pl-6 last:pb-0">
                                            {index < timeline.length - 1 && (
                                                <div className="absolute bottom-0 left-[9px] top-4 w-px bg-zinc-200" />
                                            )}
                                            <div className="absolute left-0 top-1 h-[18px] w-[18px] rounded-full border-2 border-zinc-300 bg-white" />
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {event.title || event.action}
                                                </p>
                                                {event.description && (
                                                    <p className="mt-0.5 text-xs text-zinc-500">{event.description}</p>
                                                )}
                                                <p className="mt-1 text-xs text-zinc-400">
                                                    {formatDateTime(event.created_at || event.date)}
                                                </p>
                                                {event.user && (
                                                    <p className="text-xs text-zinc-400">by {event.user}</p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Action Summary */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Summary</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-xs text-zinc-500">Issued By</p>
                                    <p className="text-sm font-medium text-zinc-900">
                                        {action.issued_by?.full_name || '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-zinc-500">Issued Date</p>
                                    <p className="text-sm font-medium text-zinc-900">
                                        {formatDate(action.issued_at)}
                                    </p>
                                </div>
                                {action.closed_at && (
                                    <div>
                                        <p className="text-xs text-zinc-500">Closed Date</p>
                                        <p className="text-sm font-medium text-zinc-900">
                                            {formatDate(action.closed_at)}
                                        </p>
                                    </div>
                                )}
                                {action.closed_by && (
                                    <div>
                                        <p className="text-xs text-zinc-500">Closed By</p>
                                        <p className="text-sm font-medium text-zinc-900">
                                            {action.closed_by?.full_name || '-'}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Confirm Issue / Close Dialog */}
            <Dialog open={confirmDialog.open} onOpenChange={() => setConfirmDialog({ open: false, action: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {confirmDialog.action === 'issue' ? 'Issue Disciplinary Action' : 'Close Disciplinary Action'}
                        </DialogTitle>
                        <DialogDescription>
                            {confirmDialog.action === 'issue'
                                ? 'Are you sure you want to issue this disciplinary action? The employee will be notified.'
                                : 'Are you sure you want to close this disciplinary action? This cannot be undone.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmDialog({ open: false, action: null })}>
                            Cancel
                        </Button>
                        <Button onClick={handleConfirmAction} disabled={isAnyPending}>
                            {isAnyPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {confirmDialog.action === 'issue' ? 'Issue' : 'Close'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Schedule Inquiry Dialog */}
            <Dialog open={inquiryDialog} onOpenChange={setInquiryDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Schedule Domestic Inquiry</DialogTitle>
                        <DialogDescription>
                            Schedule a domestic inquiry session for this disciplinary case.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label className="mb-1.5 block">Date</Label>
                            <Input
                                type="date"
                                value={inquiryForm.scheduled_date}
                                onChange={(e) => setInquiryForm((p) => ({ ...p, scheduled_date: e.target.value }))}
                            />
                        </div>
                        <div>
                            <Label className="mb-1.5 block">Time</Label>
                            <Input
                                type="time"
                                value={inquiryForm.scheduled_time}
                                onChange={(e) => setInquiryForm((p) => ({ ...p, scheduled_time: e.target.value }))}
                            />
                        </div>
                        <div>
                            <Label className="mb-1.5 block">Location</Label>
                            <Input
                                placeholder="e.g. Meeting Room 3, Level 5"
                                value={inquiryForm.location}
                                onChange={(e) => setInquiryForm((p) => ({ ...p, location: e.target.value }))}
                            />
                        </div>
                        <div>
                            <Label className="mb-1.5 block">Panel Members</Label>
                            <Textarea
                                placeholder="Enter panel member names separated by commas"
                                value={inquiryForm.panel_members}
                                onChange={(e) => setInquiryForm((p) => ({ ...p, panel_members: e.target.value }))}
                                rows={3}
                            />
                            <p className="mt-1 text-xs text-zinc-400">Separate multiple names with commas</p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setInquiryDialog(false)}>Cancel</Button>
                        <Button
                            onClick={handleScheduleInquiry}
                            disabled={createInquiryMutation.isPending || !inquiryForm.scheduled_date || !inquiryForm.scheduled_time}
                        >
                            {createInquiryMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Schedule Inquiry
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Complete Inquiry Dialog */}
            <Dialog open={completeInquiryDialog} onOpenChange={setCompleteInquiryDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Complete Domestic Inquiry</DialogTitle>
                        <DialogDescription>
                            Record the outcome and decision of the domestic inquiry.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label className="mb-1.5 block">Decision</Label>
                            <Select
                                value={completeForm.decision}
                                onValueChange={(v) => setCompleteForm((p) => ({ ...p, decision: v }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select decision..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {INQUIRY_DECISION_OPTIONS.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label className="mb-1.5 block">Findings</Label>
                            <Textarea
                                placeholder="Describe the findings of the inquiry..."
                                value={completeForm.findings}
                                onChange={(e) => setCompleteForm((p) => ({ ...p, findings: e.target.value }))}
                                rows={4}
                            />
                        </div>
                        <div>
                            <Label className="mb-1.5 block">Penalty</Label>
                            <Select
                                value={completeForm.penalty}
                                onValueChange={(v) => setCompleteForm((p) => ({ ...p, penalty: v }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select penalty..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {PENALTY_OPTIONS.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCompleteInquiryDialog(false)}>Cancel</Button>
                        <Button
                            onClick={handleCompleteInquiry}
                            disabled={completeInquiryMutation.isPending || !completeForm.decision}
                        >
                            {completeInquiryMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Complete Inquiry
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
