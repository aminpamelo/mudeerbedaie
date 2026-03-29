import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import {
    ChevronLeft,
    Star,
    Plus,
    CheckCircle,
    AlertTriangle,
    Loader2,
    User,
    BarChart2,
} from 'lucide-react';
import {
    fetchPerformanceReview,
    submitManagerReview,
    completeReview,
    addReviewKpi,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '../../components/ui/dialog';

const REVIEW_STATUS_BADGE = {
    pending: 'bg-zinc-100 text-zinc-600',
    self_review: 'bg-amber-100 text-amber-700',
    manager_review: 'bg-blue-100 text-blue-700',
    completed: 'bg-emerald-100 text-emerald-700',
    acknowledged: 'bg-purple-100 text-purple-700',
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function StarRating({ value, onChange, readOnly }) {
    const [hovered, setHovered] = useState(0);

    return (
        <div className="flex gap-1">
            {[1, 2, 3, 4, 5].map((star) => (
                <button
                    key={star}
                    type="button"
                    disabled={readOnly}
                    onClick={() => !readOnly && onChange && onChange(star)}
                    onMouseEnter={() => !readOnly && setHovered(star)}
                    onMouseLeave={() => !readOnly && setHovered(0)}
                    className={cn('h-6 w-6 transition-colors', readOnly ? 'cursor-default' : 'cursor-pointer')}
                >
                    <Star
                        className={cn(
                            'h-5 w-5',
                            (hovered || value) >= star
                                ? 'fill-amber-400 text-amber-400'
                                : 'text-zinc-300'
                        )}
                    />
                </button>
            ))}
        </div>
    );
}

function SkeletonDetail() {
    return (
        <div className="space-y-6">
            <div className="h-32 animate-pulse rounded-lg bg-zinc-200" />
            <div className="h-64 animate-pulse rounded-lg bg-zinc-200" />
        </div>
    );
}

export default function ReviewDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [managerDialog, setManagerDialog] = useState(false);
    const [managerForm, setManagerForm] = useState({ overall_rating: 0, manager_comments: '' });
    const [addKpiDialog, setAddKpiDialog] = useState(false);
    const [kpiForm, setKpiForm] = useState({ title: '', target: '', weight: '', category: 'productivity' });
    const [formError, setFormError] = useState('');

    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'performance', 'reviews', id],
        queryFn: () => fetchPerformanceReview(id),
        enabled: !!id,
    });

    const submitManagerMutation = useMutation({
        mutationFn: (formData) => submitManagerReview(id, formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'reviews', id] });
            setManagerDialog(false);
        },
        onError: (err) => setFormError(err?.response?.data?.message || 'Failed to submit manager review.'),
    });

    const completeMutation = useMutation({
        mutationFn: () => completeReview(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'reviews', id] });
        },
    });

    const addKpiMutation = useMutation({
        mutationFn: (kpiData) => addReviewKpi(id, kpiData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'reviews', id] });
            setAddKpiDialog(false);
            setKpiForm({ title: '', target: '', weight: '', category: 'productivity' });
            setFormError('');
        },
        onError: (err) => setFormError(err?.response?.data?.message || 'Failed to add KPI.'),
    });

    const review = data?.data || data || null;
    const kpis = review?.kpis || [];

    function handleOpenManagerReview() {
        setManagerForm({
            overall_rating: review?.overall_rating || 0,
            manager_comments: review?.manager_comments || '',
        });
        setFormError('');
        setManagerDialog(true);
    }

    function handleSubmitManagerReview(e) {
        e.preventDefault();
        if (!managerForm.overall_rating) {
            setFormError('Please provide an overall rating.');
            return;
        }
        submitManagerMutation.mutate(managerForm);
    }

    function handleAddKpi(e) {
        e.preventDefault();
        if (!kpiForm.title.trim()) {
            setFormError('KPI title is required.');
            return;
        }
        addKpiMutation.mutate({
            ...kpiForm,
            weight: kpiForm.weight ? parseFloat(kpiForm.weight) : null,
        });
    }

    if (isError) {
        return (
            <div>
                <PageHeader title="Review Detail" />
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <AlertTriangle className="mb-3 h-10 w-10 text-red-300" />
                        <p className="text-sm font-medium text-zinc-600">Failed to load review details.</p>
                        <Button variant="outline" className="mt-4" onClick={() => navigate(-1)}>
                            Go Back
                        </Button>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <div>
            <PageHeader
                title="Review Detail"
                description={review ? `${review.employee?.full_name} — ${review.cycle?.name}` : ''}
                action={
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => navigate(-1)}>
                            <ChevronLeft className="mr-1 h-4 w-4" />
                            Back
                        </Button>
                        {review?.status === 'manager_review' && (
                            <Button onClick={handleOpenManagerReview}>
                                <Star className="mr-1.5 h-4 w-4" />
                                Submit Manager Review
                            </Button>
                        )}
                        {review?.status === 'manager_review' && (
                            <Button
                                variant="outline"
                                onClick={() => completeMutation.mutate()}
                                disabled={completeMutation.isPending}
                            >
                                {completeMutation.isPending ? (
                                    <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                ) : (
                                    <CheckCircle className="mr-1.5 h-4 w-4" />
                                )}
                                Complete Review
                            </Button>
                        )}
                    </div>
                }
            />

            {isLoading ? (
                <SkeletonDetail />
            ) : (
                <div className="space-y-6">
                    {/* Employee Info */}
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex flex-col gap-6 sm:flex-row">
                                <div className="flex items-center gap-4">
                                    <div className="flex h-14 w-14 items-center justify-center rounded-full bg-zinc-100 text-lg font-bold text-zinc-600">
                                        {review?.employee?.full_name?.charAt(0) || '?'}
                                    </div>
                                    <div>
                                        <p className="font-semibold text-zinc-900">{review?.employee?.full_name || '-'}</p>
                                        <p className="text-sm text-zinc-500">{review?.employee?.department?.name || '-'} · {review?.employee?.position?.name || '-'}</p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-6 sm:ml-auto">
                                    <div>
                                        <p className="text-xs text-zinc-500">Status</p>
                                        <span className={cn('mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', REVIEW_STATUS_BADGE[review?.status] || 'bg-zinc-100 text-zinc-600')}>
                                            {review?.status?.replace('_', ' ') || '-'}
                                        </span>
                                    </div>
                                    <div>
                                        <p className="text-xs text-zinc-500">Reviewer</p>
                                        <p className="mt-1 text-sm font-medium text-zinc-900">{review?.reviewer?.full_name || '-'}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-zinc-500">Cycle</p>
                                        <p className="mt-1 text-sm font-medium text-zinc-900">{review?.cycle?.name || '-'}</p>
                                    </div>
                                    {review?.overall_rating && (
                                        <div>
                                            <p className="text-xs text-zinc-500">Overall Rating</p>
                                            <div className="mt-1 flex items-center gap-1">
                                                <Star className="h-4 w-4 fill-amber-400 text-amber-400" />
                                                <span className="text-sm font-bold text-zinc-900">{review.overall_rating}</span>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* KPI Scores */}
                    <Card>
                        <CardContent className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-semibold text-zinc-900">KPI Scores</h3>
                                <Button variant="outline" size="sm" onClick={() => { setFormError(''); setAddKpiDialog(true); }}>
                                    <Plus className="mr-1.5 h-4 w-4" />
                                    Add KPI
                                </Button>
                            </div>

                            {kpis.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-10 text-center">
                                    <BarChart2 className="mb-3 h-10 w-10 text-zinc-300" />
                                    <p className="text-sm text-zinc-500">No KPIs added yet.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-zinc-200">
                                                <th className="pb-2 text-left font-medium text-zinc-500">KPI</th>
                                                <th className="pb-2 text-left font-medium text-zinc-500">Target</th>
                                                <th className="pb-2 text-left font-medium text-zinc-500">Weight</th>
                                                <th className="pb-2 text-center font-medium text-zinc-500">Self Score</th>
                                                <th className="pb-2 text-center font-medium text-zinc-500">Manager Score</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-zinc-100">
                                            {kpis.map((kpi) => (
                                                <tr key={kpi.id}>
                                                    <td className="py-3 font-medium text-zinc-900">{kpi.title}</td>
                                                    <td className="py-3 text-zinc-600">{kpi.target || '-'}</td>
                                                    <td className="py-3 text-zinc-600">{kpi.weight != null ? `${kpi.weight}%` : '-'}</td>
                                                    <td className="py-3 text-center">
                                                        {kpi.self_score != null ? (
                                                            <span className="inline-flex items-center justify-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                                                {kpi.self_score}
                                                            </span>
                                                        ) : <span className="text-zinc-300">—</span>}
                                                    </td>
                                                    <td className="py-3 text-center">
                                                        {kpi.manager_score != null ? (
                                                            <span className="inline-flex items-center justify-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                                {kpi.manager_score}
                                                            </span>
                                                        ) : <span className="text-zinc-300">—</span>}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Comments */}
                    {(review?.self_comments || review?.manager_comments) && (
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            {review?.self_comments && (
                                <Card>
                                    <CardContent className="p-6">
                                        <h3 className="mb-3 font-semibold text-zinc-900">Self Assessment Comments</h3>
                                        <p className="text-sm text-zinc-600">{review.self_comments}</p>
                                    </CardContent>
                                </Card>
                            )}
                            {review?.manager_comments && (
                                <Card>
                                    <CardContent className="p-6">
                                        <h3 className="mb-3 font-semibold text-zinc-900">Manager Comments</h3>
                                        <p className="text-sm text-zinc-600">{review.manager_comments}</p>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    )}
                </div>
            )}

            {/* Manager Review Dialog */}
            <Dialog open={managerDialog} onOpenChange={setManagerDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Submit Manager Review</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmitManagerReview} className="space-y-4">
                        {formError && (
                            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{formError}</p>
                        )}
                        <div>
                            <label className="mb-2 block text-sm font-medium text-zinc-700">Overall Rating *</label>
                            <StarRating
                                value={managerForm.overall_rating}
                                onChange={(v) => setManagerForm((f) => ({ ...f, overall_rating: v }))}
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Manager Comments</label>
                            <textarea
                                value={managerForm.manager_comments}
                                onChange={(e) => setManagerForm((f) => ({ ...f, manager_comments: e.target.value }))}
                                placeholder="Provide feedback and comments..."
                                rows={4}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setManagerDialog(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={submitManagerMutation.isPending}>
                                {submitManagerMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Submit Review
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Add KPI Dialog */}
            <Dialog open={addKpiDialog} onOpenChange={setAddKpiDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Add KPI to Review</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleAddKpi} className="space-y-4">
                        {formError && (
                            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{formError}</p>
                        )}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Title *</label>
                            <input
                                type="text"
                                value={kpiForm.title}
                                onChange={(e) => setKpiForm((f) => ({ ...f, title: e.target.value }))}
                                placeholder="e.g. Monthly Sales Target"
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Target</label>
                            <input
                                type="text"
                                value={kpiForm.target}
                                onChange={(e) => setKpiForm((f) => ({ ...f, target: e.target.value }))}
                                placeholder="e.g. RM 50,000"
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Weight (%)</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={kpiForm.weight}
                                    onChange={(e) => setKpiForm((f) => ({ ...f, weight: e.target.value }))}
                                    placeholder="e.g. 20"
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Category</label>
                                <select
                                    value={kpiForm.category}
                                    onChange={(e) => setKpiForm((f) => ({ ...f, category: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                >
                                    {['productivity', 'quality', 'attendance', 'leadership', 'teamwork', 'communication', 'other'].map((c) => (
                                        <option key={c} value={c} className="capitalize">
                                            {c.charAt(0).toUpperCase() + c.slice(1)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setAddKpiDialog(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={addKpiMutation.isPending}>
                                {addKpiMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Add KPI
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
