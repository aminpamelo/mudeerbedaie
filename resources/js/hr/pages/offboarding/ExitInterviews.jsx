import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Loader2,
    MessageSquare,
    Star,
    ThumbsUp,
    ThumbsDown,
    BarChart3,
    Eye,
    Users,
    TrendingUp,
} from 'lucide-react';
import {
    fetchExitInterviews,
    createExitInterview,
    fetchExitInterview,
    fetchExitInterviewAnalytics,
    fetchEmployees,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Button } from '../../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
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
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';

const REASON_OPTIONS = [
    { value: 'better_opportunity', label: 'Better Opportunity' },
    { value: 'career_change', label: 'Career Change' },
    { value: 'compensation', label: 'Compensation' },
    { value: 'management', label: 'Management' },
    { value: 'work_life_balance', label: 'Work-Life Balance' },
    { value: 'relocation', label: 'Relocation' },
    { value: 'personal', label: 'Personal' },
    { value: 'retirement', label: 'Retirement' },
    { value: 'other', label: 'Other' },
];

const REASON_LABELS = Object.fromEntries(REASON_OPTIONS.map((r) => [r.value, r.label]));

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function StarRating({ rating, size = 'sm' }) {
    const starSize = size === 'sm' ? 'h-4 w-4' : 'h-5 w-5';
    return (
        <div className="flex items-center gap-0.5">
            {[1, 2, 3, 4, 5].map((star) => (
                <Star
                    key={star}
                    className={cn(
                        starSize,
                        star <= rating ? 'fill-amber-400 text-amber-400' : 'text-zinc-300'
                    )}
                />
            ))}
        </div>
    );
}

function InteractiveStarRating({ value, onChange }) {
    const [hovered, setHovered] = useState(0);
    return (
        <div className="flex items-center gap-1">
            {[1, 2, 3, 4, 5].map((star) => (
                <button
                    key={star}
                    type="button"
                    onMouseEnter={() => setHovered(star)}
                    onMouseLeave={() => setHovered(0)}
                    onClick={() => onChange(star)}
                    className="focus:outline-none"
                >
                    <Star
                        className={cn(
                            'h-6 w-6 transition-colors',
                            star <= (hovered || value)
                                ? 'fill-amber-400 text-amber-400'
                                : 'text-zinc-300 hover:text-zinc-400'
                        )}
                    />
                </button>
            ))}
        </div>
    );
}

export default function ExitInterviews() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [createDialog, setCreateDialog] = useState(false);
    const [detailDialog, setDetailDialog] = useState({ open: false, id: null });
    const [form, setForm] = useState({
        employee_id: '',
        interview_date: new Date().toISOString().split('T')[0],
        reason_for_leaving: '',
        overall_satisfaction: 3,
        would_recommend: true,
        feedback: '',
    });

    const params = {
        search: search || undefined,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'offboarding', 'exit-interviews', params],
        queryFn: () => fetchExitInterviews(params),
    });

    const { data: analyticsData } = useQuery({
        queryKey: ['hr', 'offboarding', 'exit-interviews', 'analytics'],
        queryFn: () => fetchExitInterviewAnalytics(),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'list'],
        queryFn: () => fetchEmployees({ per_page: 200 }),
    });

    const { data: detailData, isLoading: detailLoading } = useQuery({
        queryKey: ['hr', 'offboarding', 'exit-interview', detailDialog.id],
        queryFn: () => fetchExitInterview(detailDialog.id),
        enabled: !!detailDialog.id,
    });

    const createMutation = useMutation({
        mutationFn: (data) => createExitInterview(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'exit-interviews'] });
            setCreateDialog(false);
            setForm({
                employee_id: '',
                interview_date: new Date().toISOString().split('T')[0],
                reason_for_leaving: '',
                overall_satisfaction: 3,
                would_recommend: true,
                feedback: '',
            });
        },
    });

    const interviews = data?.data || [];
    const analytics = analyticsData?.data || {};
    const employees = employeesData?.data || [];
    const interviewDetail = detailData?.data;

    function handleCreate() {
        createMutation.mutate({
            employee_id: form.employee_id,
            interview_date: form.interview_date,
            reason_for_leaving: form.reason_for_leaving,
            overall_satisfaction: form.overall_satisfaction,
            would_recommend: form.would_recommend,
            feedback: form.feedback,
        });
    }

    const reasonBreakdown = analytics.reason_breakdown || [];
    const avgSatisfaction = analytics.average_satisfaction ?? 0;
    const recommendRate = analytics.recommendation_rate ?? 0;
    const totalInterviews = analytics.total_interviews ?? 0;

    return (
        <div>
            <PageHeader
                title="Exit Interviews"
                description="Conduct and review exit interviews for departing employees."
                action={
                    <Button onClick={() => setCreateDialog(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        New Interview
                    </Button>
                }
            />

            {/* Analytics Summary */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50">
                                <Users className="h-5 w-5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-zinc-500">Total Interviews</p>
                                <p className="text-lg font-bold text-zinc-900">{totalInterviews}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50">
                                <Star className="h-5 w-5 text-amber-600" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-zinc-500">Avg Satisfaction</p>
                                <div className="flex items-center gap-2">
                                    <p className="text-lg font-bold text-zinc-900">
                                        {Number(avgSatisfaction).toFixed(1)}
                                    </p>
                                    <StarRating rating={Math.round(avgSatisfaction)} />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50">
                                <ThumbsUp className="h-5 w-5 text-emerald-600" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-zinc-500">Recommendation Rate</p>
                                <p className="text-lg font-bold text-zinc-900">{Number(recommendRate).toFixed(0)}%</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50">
                                <TrendingUp className="h-5 w-5 text-purple-600" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-zinc-500">Top Reason</p>
                                <p className="text-lg font-bold text-zinc-900">
                                    {reasonBreakdown.length > 0
                                        ? REASON_LABELS[reasonBreakdown[0].reason] || reasonBreakdown[0].reason
                                        : '-'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Reason Breakdown */}
            {reasonBreakdown.length > 0 && (
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <BarChart3 className="h-4 w-4 text-zinc-500" />
                            Reason Breakdown
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {reasonBreakdown.map((item, index) => {
                                const maxCount = reasonBreakdown[0]?.count || 1;
                                const widthPercent = Math.round((item.count / maxCount) * 100);
                                return (
                                    <div key={index} className="flex items-center gap-3">
                                        <div className="w-40 shrink-0 text-sm text-zinc-700">
                                            {REASON_LABELS[item.reason] || item.reason}
                                        </div>
                                        <div className="flex-1">
                                            <div className="h-6 overflow-hidden rounded bg-zinc-100">
                                                <div
                                                    className="flex h-full items-center rounded bg-blue-500 px-2 text-xs font-medium text-white transition-all"
                                                    style={{ width: `${widthPercent}%`, minWidth: '2rem' }}
                                                >
                                                    {item.count}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Search */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <SearchInput
                        value={search}
                        onChange={setSearch}
                        placeholder="Search employee..."
                        className="w-64"
                    />
                </CardContent>
            </Card>

            {/* Table */}
            {isLoading ? (
                <div className="flex justify-center py-16">
                    <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                </div>
            ) : interviews.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <MessageSquare className="mb-3 h-10 w-10 text-zinc-300" />
                        <p className="text-sm font-medium text-zinc-500">No exit interviews found</p>
                        <p className="text-xs text-zinc-400">Create a new exit interview to get started.</p>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Reason</TableHead>
                                    <TableHead>Satisfaction</TableHead>
                                    <TableHead>Recommend</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {interviews.map((interview) => (
                                    <TableRow key={interview.id}>
                                        <TableCell>
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {interview.employee?.full_name || '-'}
                                                </p>
                                                <p className="text-xs text-zinc-500">
                                                    {interview.employee?.employee_id || ''}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {formatDate(interview.interview_date)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {REASON_LABELS[interview.reason_for_leaving] || interview.reason_for_leaving}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <StarRating rating={interview.overall_satisfaction} />
                                        </TableCell>
                                        <TableCell>
                                            {interview.would_recommend ? (
                                                <div className="flex items-center gap-1 text-emerald-600">
                                                    <ThumbsUp className="h-4 w-4" />
                                                    <span className="text-xs font-medium">Yes</span>
                                                </div>
                                            ) : (
                                                <div className="flex items-center gap-1 text-red-500">
                                                    <ThumbsDown className="h-4 w-4" />
                                                    <span className="text-xs font-medium">No</span>
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setDetailDialog({ open: true, id: interview.id })}
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            )}

            {/* Create Interview Dialog */}
            <Dialog open={createDialog} onOpenChange={setCreateDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>New Exit Interview</DialogTitle>
                        <DialogDescription>Record an exit interview for a departing employee.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Employee</label>
                            <select
                                value={form.employee_id}
                                onChange={(e) => setForm((p) => ({ ...p, employee_id: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                <option value="">Select employee...</option>
                                {employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>
                                        {emp.full_name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Interview Date</label>
                            <input
                                type="date"
                                value={form.interview_date}
                                onChange={(e) => setForm((p) => ({ ...p, interview_date: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Reason for Leaving</label>
                            <select
                                value={form.reason_for_leaving}
                                onChange={(e) => setForm((p) => ({ ...p, reason_for_leaving: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                <option value="">Select reason...</option>
                                {REASON_OPTIONS.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Overall Satisfaction</label>
                            <InteractiveStarRating
                                value={form.overall_satisfaction}
                                onChange={(val) => setForm((p) => ({ ...p, overall_satisfaction: val }))}
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="would_recommend"
                                checked={form.would_recommend}
                                onChange={(e) => setForm((p) => ({ ...p, would_recommend: e.target.checked }))}
                                className="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500"
                            />
                            <label htmlFor="would_recommend" className="text-sm font-medium text-zinc-700">
                                Would recommend this company to others
                            </label>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Feedback</label>
                            <textarea
                                value={form.feedback}
                                onChange={(e) => setForm((p) => ({ ...p, feedback: e.target.value }))}
                                rows={4}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="Additional feedback from the employee..."
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCreateDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleCreate}
                            disabled={createMutation.isPending || !form.employee_id || !form.reason_for_leaving}
                        >
                            {createMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Save Interview
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Interview Detail Dialog */}
            <Dialog open={detailDialog.open} onOpenChange={() => setDetailDialog({ open: false, id: null })}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Exit Interview Details</DialogTitle>
                        <DialogDescription>
                            {interviewDetail?.employee?.full_name
                                ? `Interview with ${interviewDetail.employee.full_name}`
                                : 'View exit interview details.'}
                        </DialogDescription>
                    </DialogHeader>

                    {detailLoading ? (
                        <div className="flex justify-center py-12">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : interviewDetail ? (
                        <div className="space-y-4">
                            <dl className="space-y-3">
                                <div className="flex justify-between">
                                    <dt className="text-sm text-zinc-500">Employee</dt>
                                    <dd className="text-sm font-medium text-zinc-900">
                                        {interviewDetail.employee?.full_name || '-'}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-sm text-zinc-500">Interview Date</dt>
                                    <dd className="text-sm font-medium text-zinc-900">
                                        {formatDate(interviewDetail.interview_date)}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-sm text-zinc-500">Reason for Leaving</dt>
                                    <dd>
                                        <Badge variant="outline">
                                            {REASON_LABELS[interviewDetail.reason_for_leaving] || interviewDetail.reason_for_leaving}
                                        </Badge>
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-sm text-zinc-500">Overall Satisfaction</dt>
                                    <dd>
                                        <StarRating rating={interviewDetail.overall_satisfaction} />
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-sm text-zinc-500">Would Recommend</dt>
                                    <dd>
                                        {interviewDetail.would_recommend ? (
                                            <div className="flex items-center gap-1 text-emerald-600">
                                                <ThumbsUp className="h-4 w-4" />
                                                <span className="text-xs font-medium">Yes</span>
                                            </div>
                                        ) : (
                                            <div className="flex items-center gap-1 text-red-500">
                                                <ThumbsDown className="h-4 w-4" />
                                                <span className="text-xs font-medium">No</span>
                                            </div>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                            {interviewDetail.feedback && (
                                <div>
                                    <h4 className="mb-1 text-sm font-medium text-zinc-700">Feedback</h4>
                                    <p className="rounded-lg bg-zinc-50 p-3 text-sm text-zinc-700 whitespace-pre-wrap">
                                        {interviewDetail.feedback}
                                    </p>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="flex justify-center py-8 text-sm text-zinc-400">
                            Interview not found.
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
