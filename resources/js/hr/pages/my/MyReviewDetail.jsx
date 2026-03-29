import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import {
    ChevronLeft,
    Loader2,
    Star,
    CheckCircle2,
    ClipboardList,
    Send,
} from 'lucide-react';
import { fetchMyReview, submitMySelfAssessment } from '../../lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

const STATUS_CONFIG = {
    draft: { label: 'Draft', className: 'bg-zinc-100 text-zinc-600' },
    self_assessment: { label: 'Self Assessment', className: 'bg-blue-100 text-blue-700' },
    manager_review: { label: 'Manager Review', className: 'bg-amber-100 text-amber-700' },
    completed: { label: 'Completed', className: 'bg-emerald-100 text-emerald-700' },
    cancelled: { label: 'Cancelled', className: 'bg-red-100 text-red-700' },
};

function StarSelector({ value, onChange, disabled }) {
    const [hovered, setHovered] = useState(null);
    const display = hovered ?? value ?? 0;

    return (
        <div className="flex items-center gap-1">
            {[1, 2, 3, 4, 5].map((star) => (
                <button
                    key={star}
                    type="button"
                    disabled={disabled}
                    className="focus:outline-none disabled:cursor-not-allowed"
                    onMouseEnter={() => !disabled && setHovered(star)}
                    onMouseLeave={() => !disabled && setHovered(null)}
                    onClick={() => !disabled && onChange(star)}
                >
                    <Star
                        className={`h-6 w-6 transition-colors ${
                            star <= display
                                ? 'fill-amber-400 text-amber-400'
                                : 'text-zinc-200 hover:text-amber-300'
                        }`}
                    />
                </button>
            ))}
            {value > 0 && (
                <span className="ml-1 text-sm font-medium text-zinc-600">{value}/5</span>
            )}
        </div>
    );
}

function RatingDisplay({ rating, max = 5 }) {
    if (!rating) return <span className="text-sm text-zinc-400">Not rated</span>;
    const value = parseFloat(rating);
    return (
        <div className="flex items-center gap-1">
            {Array.from({ length: max }).map((_, i) => (
                <Star
                    key={i}
                    className={`h-4 w-4 ${i < Math.round(value) ? 'fill-amber-400 text-amber-400' : 'text-zinc-200'}`}
                />
            ))}
            <span className="ml-1 text-sm font-medium text-zinc-700">{value.toFixed(1)}</span>
        </div>
    );
}

export default function MyReviewDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: ['my-review', id],
        queryFn: () => fetchMyReview(id),
    });

    const review = data?.data ?? null;

    // Self-assessment form state — keyed by kpi_score id or kpi_id
    const [scores, setScores] = useState({});
    const [comments, setComments] = useState({});
    const [overallComment, setOverallComment] = useState('');
    const [submitted, setSubmitted] = useState(false);

    const mutation = useMutation({
        mutationFn: (payload) => submitMySelfAssessment(id, payload),
        onSuccess: () => {
            setSubmitted(true);
            queryClient.invalidateQueries({ queryKey: ['my-review', id] });
            queryClient.invalidateQueries({ queryKey: ['my-reviews'] });
        },
    });

    function handleSubmit(e) {
        e.preventDefault();
        const kpiScores = Object.entries(scores).map(([kpiId, score]) => ({
            kpi_id: kpiId,
            self_score: score,
            self_comment: comments[kpiId] || '',
        }));
        mutation.mutate({
            kpi_scores: kpiScores,
            self_comment: overallComment,
        });
    }

    if (isLoading) {
        return (
            <div className="flex justify-center py-16">
                <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (!review) {
        return (
            <div className="flex flex-col items-center justify-center py-16 text-center">
                <ClipboardList className="h-10 w-10 text-zinc-300 mb-3" />
                <p className="text-sm text-zinc-600">Review not found.</p>
            </div>
        );
    }

    const statusCfg = STATUS_CONFIG[review.status] || { label: review.status, className: 'bg-zinc-100 text-zinc-600' };
    const isEditable = review.status === 'self_assessment' || review.status === 'draft';
    const isCompleted = review.status === 'completed';
    const kpiScores = review.kpi_scores ?? [];

    return (
        <div className="space-y-4">
            {/* Back + Header */}
            <div className="flex items-center gap-3">
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 w-8 p-0 shrink-0"
                    onClick={() => navigate(-1)}
                >
                    <ChevronLeft className="h-4 w-4" />
                </Button>
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <h1 className="text-lg font-bold text-zinc-900">
                            {review.cycle?.name || 'Performance Review'}
                        </h1>
                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${statusCfg.className}`}>
                            {statusCfg.label}
                        </span>
                    </div>
                    <p className="text-xs text-zinc-500 mt-0.5">
                        {formatDate(review.cycle?.period_start)} – {formatDate(review.cycle?.period_end)}
                    </p>
                </div>
            </div>

            {/* Review Meta */}
            <Card>
                <CardContent className="py-3.5 px-4 grid grid-cols-2 gap-3">
                    <div>
                        <p className="text-[10px] uppercase tracking-wide text-zinc-400 font-medium">Reviewer</p>
                        <p className="text-sm text-zinc-900 mt-0.5">{review.reviewer?.name || '-'}</p>
                    </div>
                    <div>
                        <p className="text-[10px] uppercase tracking-wide text-zinc-400 font-medium">Review Type</p>
                        <p className="text-sm text-zinc-900 mt-0.5 capitalize">
                            {review.cycle?.review_type?.replace('_', ' ') || '-'}
                        </p>
                    </div>
                    {isCompleted && review.overall_rating && (
                        <div className="col-span-2">
                            <p className="text-[10px] uppercase tracking-wide text-zinc-400 font-medium mb-1">Overall Rating</p>
                            <RatingDisplay rating={review.overall_rating} />
                        </div>
                    )}
                    {isCompleted && review.manager_comment && (
                        <div className="col-span-2">
                            <p className="text-[10px] uppercase tracking-wide text-zinc-400 font-medium mb-1">Manager Comments</p>
                            <p className="text-sm text-zinc-700 bg-zinc-50 rounded-lg p-3">{review.manager_comment}</p>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Submitted success state */}
            {submitted && (
                <div className="flex items-center gap-3 rounded-lg bg-emerald-50 border border-emerald-200 p-4">
                    <CheckCircle2 className="h-5 w-5 text-emerald-600 shrink-0" />
                    <p className="text-sm text-emerald-800 font-medium">
                        Self-assessment submitted successfully.
                    </p>
                </div>
            )}

            {/* KPI Scores */}
            {kpiScores.length > 0 && (
                <form onSubmit={handleSubmit} className="space-y-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">KPI Scores</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {kpiScores.map((ks) => {
                                const kpiId = String(ks.kpi_id ?? ks.id);
                                return (
                                    <div key={kpiId} className="border-b border-zinc-100 pb-4 last:border-0 last:pb-0">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {ks.kpi?.name || ks.name || 'KPI'}
                                                </p>
                                                {ks.kpi?.description && (
                                                    <p className="text-xs text-zinc-400 mt-0.5">{ks.kpi.description}</p>
                                                )}
                                                {ks.kpi?.weight && (
                                                    <p className="text-[10px] text-zinc-400 mt-0.5">
                                                        Weight: {ks.kpi.weight}%
                                                    </p>
                                                )}
                                            </div>
                                            {isCompleted && (
                                                <div className="shrink-0">
                                                    <RatingDisplay rating={ks.manager_score ?? ks.score} />
                                                </div>
                                            )}
                                        </div>

                                        {/* Self-assessment fields */}
                                        {isEditable && !submitted && (
                                            <div className="mt-3 space-y-2">
                                                <div>
                                                    <p className="text-xs text-zinc-500 mb-1">Your Score</p>
                                                    <StarSelector
                                                        value={scores[kpiId] ?? ks.self_score ?? 0}
                                                        onChange={(val) => setScores((s) => ({ ...s, [kpiId]: val }))}
                                                    />
                                                </div>
                                                <div>
                                                    <p className="text-xs text-zinc-500 mb-1">Your Comments</p>
                                                    <textarea
                                                        rows={2}
                                                        value={comments[kpiId] ?? ks.self_comment ?? ''}
                                                        onChange={(e) =>
                                                            setComments((c) => ({ ...c, [kpiId]: e.target.value }))
                                                        }
                                                        placeholder="Add comments for this KPI..."
                                                        className="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                                    />
                                                </div>
                                            </div>
                                        )}

                                        {/* Read-only self-assessment data */}
                                        {(isCompleted || submitted) && (
                                            <div className="mt-3 grid grid-cols-2 gap-3">
                                                <div>
                                                    <p className="text-[10px] uppercase text-zinc-400 mb-1">Self Score</p>
                                                    <RatingDisplay rating={ks.self_score} />
                                                </div>
                                                {ks.self_comment && (
                                                    <div className="col-span-2">
                                                        <p className="text-[10px] uppercase text-zinc-400 mb-1">Self Comment</p>
                                                        <p className="text-xs text-zinc-600">{ks.self_comment}</p>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    {/* Overall comment + submit button */}
                    {isEditable && !submitted && (
                        <>
                            <Card>
                                <CardContent className="py-3.5 px-4">
                                    <label className="block text-sm font-medium text-zinc-700 mb-2">
                                        Overall Comments
                                    </label>
                                    <textarea
                                        rows={3}
                                        value={overallComment}
                                        onChange={(e) => setOverallComment(e.target.value)}
                                        placeholder="Share any overall thoughts or comments about your performance..."
                                        className="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    />
                                </CardContent>
                            </Card>
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={mutation.isPending}
                            >
                                {mutation.isPending ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <Send className="mr-2 h-4 w-4" />
                                )}
                                Submit Self-Assessment
                            </Button>
                            {mutation.isError && (
                                <p className="text-xs text-red-600 text-center">
                                    Failed to submit. Please try again.
                                </p>
                            )}
                        </>
                    )}
                </form>
            )}

            {/* No KPIs state */}
            {kpiScores.length === 0 && (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-10 text-center">
                        <ClipboardList className="h-8 w-8 text-zinc-300 mb-2" />
                        <p className="text-sm text-zinc-500">No KPIs assigned for this review.</p>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
