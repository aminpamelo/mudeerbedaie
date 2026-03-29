import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Calendar,
    Star,
    Loader2,
} from 'lucide-react';
import { fetchInterviews, createInterview, submitInterviewFeedback } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '../../components/ui/dialog';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Status' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'no_show', label: 'No Show' },
];

const TYPE_OPTIONS = [
    { value: 'phone', label: 'Phone' },
    { value: 'video', label: 'Video' },
    { value: 'onsite', label: 'On-site' },
    { value: 'technical', label: 'Technical' },
    { value: 'panel', label: 'Panel' },
];

const STATUS_BADGE = {
    scheduled: 'bg-blue-100 text-blue-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-zinc-100 text-zinc-600',
    no_show: 'bg-red-100 text-red-700',
};

const EMPTY_FORM = {
    applicant_id: '',
    interviewer_employee_id: '',
    type: 'video',
    scheduled_at: '',
    notes: '',
};

const EMPTY_FEEDBACK = {
    rating: '',
    feedback: '',
    recommendation: 'proceed',
};

function formatDateTime(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function StarRating({ rating }) {
    const value = rating ? Math.round(rating) : 0;
    return (
        <div className="flex items-center gap-0.5">
            {Array.from({ length: 5 }).map((_, i) => (
                <Star
                    key={i}
                    className={cn(
                        'h-3.5 w-3.5',
                        i < value ? 'fill-amber-400 text-amber-400' : 'text-zinc-300'
                    )}
                />
            ))}
        </div>
    );
}

function SkeletonTable() {
    return (
        <div className="space-y-3 p-4">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 py-2">
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1" />
                    <div className="h-6 w-16 animate-pulse rounded-full bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function Interviews() {
    const queryClient = useQueryClient();
    const [statusFilter, setStatusFilter] = useState('all');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [scheduleDialogOpen, setScheduleDialogOpen] = useState(false);
    const [feedbackDialog, setFeedbackDialog] = useState({ open: false, interview: null });
    const [form, setForm] = useState(EMPTY_FORM);
    const [feedback, setFeedback] = useState(EMPTY_FEEDBACK);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'recruitment', 'interviews', { statusFilter, dateFrom, dateTo }],
        queryFn: () =>
            fetchInterviews({
                status: statusFilter !== 'all' ? statusFilter : undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                per_page: 50,
            }),
    });

    const createMutation = useMutation({
        mutationFn: createInterview,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'interviews'] });
            setScheduleDialogOpen(false);
            setForm(EMPTY_FORM);
        },
    });

    const feedbackMutation = useMutation({
        mutationFn: ({ id, data }) => submitInterviewFeedback(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'interviews'] });
            setFeedbackDialog({ open: false, interview: null });
            setFeedback(EMPTY_FEEDBACK);
        },
    });

    const interviews = data?.data || [];

    function handleScheduleSubmit(e) {
        e.preventDefault();
        createMutation.mutate(form);
    }

    function handleFeedbackSubmit(e) {
        e.preventDefault();
        feedbackMutation.mutate({
            id: feedbackDialog.interview.id,
            data: { ...feedback, rating: feedback.rating ? Number(feedback.rating) : undefined },
        });
    }

    return (
        <div>
            <PageHeader
                title="Interviews"
                description="Schedule and manage candidate interviews."
                action={
                    <Button onClick={() => setScheduleDialogOpen(true)}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Schedule Interview
                    </Button>
                }
            />

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <Select value={statusFilter} onValueChange={setStatusFilter}>
                            <SelectTrigger className="w-full lg:w-40">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <div className="flex items-center gap-2">
                            <Input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="w-full lg:w-36"
                            />
                            <span className="text-sm text-zinc-400">to</span>
                            <Input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-full lg:w-36"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            {isLoading ? (
                <Card>
                    <SkeletonTable />
                </Card>
            ) : interviews.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <Calendar className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">No interviews found</h3>
                        <p className="mt-1 text-sm text-zinc-500">
                            Schedule an interview with a candidate to get started.
                        </p>
                        <Button className="mt-4" onClick={() => setScheduleDialogOpen(true)}>
                            <Plus className="mr-1.5 h-4 w-4" />
                            Schedule Interview
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Applicant</TableHead>
                                <TableHead>Interviewer</TableHead>
                                <TableHead>Date & Time</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Rating</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {interviews.map((interview) => (
                                <TableRow key={interview.id}>
                                    <TableCell className="font-medium">
                                        {interview.applicant?.name || '-'}
                                    </TableCell>
                                    <TableCell>
                                        {interview.interviewer?.full_name || '-'}
                                    </TableCell>
                                    <TableCell className="text-sm text-zinc-500">
                                        {formatDateTime(interview.scheduled_at)}
                                    </TableCell>
                                    <TableCell className="capitalize text-sm">
                                        {interview.type || '-'}
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            className={cn(
                                                'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                                STATUS_BADGE[interview.status] || 'bg-zinc-100 text-zinc-600'
                                            )}
                                        >
                                            {interview.status?.replace('_', ' ') || '-'}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        {interview.rating ? (
                                            <StarRating rating={interview.rating} />
                                        ) : (
                                            <span className="text-xs text-zinc-400">—</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            {interview.status === 'completed' && !interview.rating && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setFeedbackDialog({ open: true, interview })}
                                                >
                                                    Add Feedback
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            {/* Schedule Dialog */}
            <Dialog open={scheduleDialogOpen} onOpenChange={() => { setScheduleDialogOpen(false); setForm(EMPTY_FORM); }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Schedule Interview</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleScheduleSubmit}>
                        <div className="space-y-4 py-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="int-applicant">Applicant ID</Label>
                                <Input
                                    id="int-applicant"
                                    value={form.applicant_id}
                                    onChange={(e) => setForm((f) => ({ ...f, applicant_id: e.target.value }))}
                                    placeholder="Applicant ID"
                                    required
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="int-interviewer">Interviewer Employee ID</Label>
                                <Input
                                    id="int-interviewer"
                                    value={form.interviewer_employee_id}
                                    onChange={(e) => setForm((f) => ({ ...f, interviewer_employee_id: e.target.value }))}
                                    placeholder="Employee ID"
                                    required
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label>Interview Type</Label>
                                <Select
                                    value={form.type}
                                    onValueChange={(v) => setForm((f) => ({ ...f, type: v }))}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {TYPE_OPTIONS.map((opt) => (
                                            <SelectItem key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="int-datetime">Date & Time</Label>
                                <Input
                                    id="int-datetime"
                                    type="datetime-local"
                                    value={form.scheduled_at}
                                    onChange={(e) => setForm((f) => ({ ...f, scheduled_at: e.target.value }))}
                                    required
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="int-notes">Notes</Label>
                                <textarea
                                    id="int-notes"
                                    value={form.notes}
                                    onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
                                    rows={3}
                                    placeholder="Interview notes or instructions..."
                                    className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                        </div>

                        <DialogFooter className="mt-4">
                            <Button type="button" variant="outline" onClick={() => { setScheduleDialogOpen(false); setForm(EMPTY_FORM); }}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createMutation.isPending}>
                                {createMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Schedule
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Feedback Dialog */}
            <Dialog open={feedbackDialog.open} onOpenChange={() => { setFeedbackDialog({ open: false, interview: null }); setFeedback(EMPTY_FEEDBACK); }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Submit Interview Feedback</DialogTitle>
                    </DialogHeader>
                    {feedbackDialog.interview && (
                        <form onSubmit={handleFeedbackSubmit}>
                            <div className="mb-3 rounded-lg bg-zinc-50 p-3 text-sm">
                                <p className="font-medium">{feedbackDialog.interview.applicant?.name}</p>
                                <p className="text-zinc-500">{formatDateTime(feedbackDialog.interview.scheduled_at)}</p>
                            </div>

                            <div className="space-y-4 py-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="fb-rating">Rating (1–5)</Label>
                                    <Input
                                        id="fb-rating"
                                        type="number"
                                        min={1}
                                        max={5}
                                        value={feedback.rating}
                                        onChange={(e) => setFeedback((f) => ({ ...f, rating: e.target.value }))}
                                        placeholder="1–5"
                                        required
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label>Recommendation</Label>
                                    <Select
                                        value={feedback.recommendation}
                                        onValueChange={(v) => setFeedback((f) => ({ ...f, recommendation: v }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="proceed">Proceed</SelectItem>
                                            <SelectItem value="hold">Hold</SelectItem>
                                            <SelectItem value="reject">Reject</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="fb-feedback">Feedback Notes</Label>
                                    <textarea
                                        id="fb-feedback"
                                        value={feedback.feedback}
                                        onChange={(e) => setFeedback((f) => ({ ...f, feedback: e.target.value }))}
                                        rows={4}
                                        placeholder="Detailed feedback about the candidate..."
                                        className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                        required
                                    />
                                </div>
                            </div>

                            <DialogFooter className="mt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => { setFeedbackDialog({ open: false, interview: null }); setFeedback(EMPTY_FEEDBACK); }}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={feedbackMutation.isPending}>
                                    {feedbackMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                    Submit Feedback
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
