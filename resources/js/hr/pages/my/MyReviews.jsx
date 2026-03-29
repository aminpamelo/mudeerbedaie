import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    ClipboardList,
    ChevronRight,
    Loader2,
    Star,
} from 'lucide-react';
import { fetchMyReviews } from '../../lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';

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

function StarRating({ rating, max = 5 }) {
    if (!rating) return null;
    const value = parseFloat(rating);
    return (
        <div className="flex items-center gap-1">
            {Array.from({ length: max }).map((_, i) => (
                <Star
                    key={i}
                    className={`h-3.5 w-3.5 ${i < Math.round(value) ? 'fill-amber-400 text-amber-400' : 'text-zinc-200'}`}
                />
            ))}
            <span className="ml-1 text-xs font-medium text-zinc-600">{value.toFixed(1)}</span>
        </div>
    );
}

export default function MyReviews() {
    const { data, isLoading } = useQuery({
        queryKey: ['my-reviews'],
        queryFn: fetchMyReviews,
    });

    const reviews = data?.data ?? [];

    return (
        <div className="space-y-4">
            {/* Header */}
            <div>
                <h1 className="text-xl font-bold text-zinc-900">My Performance Reviews</h1>
                <p className="text-sm text-zinc-500 mt-0.5">Track your review cycles and self-assessment progress</p>
            </div>

            {/* Reviews List */}
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-sm">Review History</CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex justify-center py-10">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : reviews.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-14 text-center">
                            <ClipboardList className="h-10 w-10 text-zinc-300 mb-3" />
                            <p className="text-sm font-medium text-zinc-600">No reviews yet</p>
                            <p className="mt-1 text-xs text-zinc-400">Your performance reviews will appear here.</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {reviews.map((review) => {
                                const statusCfg = STATUS_CONFIG[review.status] || { label: review.status, className: 'bg-zinc-100 text-zinc-600' };
                                return (
                                    <Link
                                        key={review.id}
                                        to={`/my/reviews/${review.id}`}
                                        className="flex items-center justify-between rounded-lg border border-zinc-100 p-3 hover:bg-zinc-50 transition-colors"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {review.cycle?.name || 'Performance Review'}
                                                </p>
                                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${statusCfg.className}`}>
                                                    {statusCfg.label}
                                                </span>
                                                {review.cycle?.review_type && (
                                                    <span className="rounded-full bg-purple-50 px-2 py-0.5 text-[10px] font-medium text-purple-600 capitalize">
                                                        {review.cycle.review_type.replace('_', ' ')}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-xs text-zinc-500 mt-0.5">
                                                {formatDate(review.cycle?.period_start)} – {formatDate(review.cycle?.period_end)}
                                            </p>
                                            <div className="mt-1 flex items-center gap-3">
                                                {review.reviewer && (
                                                    <p className="text-xs text-zinc-400">
                                                        Reviewer: <span className="text-zinc-600">{review.reviewer?.name}</span>
                                                    </p>
                                                )}
                                                {review.status === 'completed' && review.overall_rating && (
                                                    <StarRating rating={review.overall_rating} />
                                                )}
                                            </div>
                                        </div>
                                        <ChevronRight className="h-4 w-4 text-zinc-300 shrink-0 ml-2" />
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
