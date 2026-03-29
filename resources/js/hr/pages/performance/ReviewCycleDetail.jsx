import { useQuery } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import {
    ChevronLeft,
    Star,
    AlertTriangle,
    BarChart2,
} from 'lucide-react';
import { fetchReviewCycle } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';

const STATUS_BADGE = {
    draft: 'bg-zinc-100 text-zinc-600',
    active: 'bg-emerald-100 text-emerald-700',
    completed: 'bg-blue-100 text-blue-700',
    cancelled: 'bg-red-100 text-red-700',
};

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

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-36 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-28 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-28 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1" />
                    <div className="h-6 w-20 animate-pulse rounded-full bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function ReviewCycleDetail() {
    const { id } = useParams();
    const navigate = useNavigate();

    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'performance', 'cycles', id],
        queryFn: () => fetchReviewCycle(id),
        enabled: !!id,
    });

    const cycle = data?.data || data || null;
    const reviews = cycle?.reviews || [];

    if (isError) {
        return (
            <div>
                <PageHeader title="Review Cycle Detail" />
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <AlertTriangle className="mb-3 h-10 w-10 text-red-300" />
                        <p className="text-sm font-medium text-zinc-600">Failed to load cycle details.</p>
                        <Button variant="outline" className="mt-4" onClick={() => navigate('/performance/cycles')}>
                            Back to Cycles
                        </Button>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <div>
            <PageHeader
                title={isLoading ? 'Loading...' : (cycle?.name || 'Review Cycle Detail')}
                description={isLoading ? '' : `${cycle?.type?.replace('_', ' ')} cycle · ${formatDate(cycle?.period_start)} — ${formatDate(cycle?.period_end)}`}
                action={
                    <Button variant="outline" onClick={() => navigate('/performance/cycles')}>
                        <ChevronLeft className="mr-1 h-4 w-4" />
                        Back
                    </Button>
                }
            />

            {/* Cycle Info Cards */}
            {!isLoading && cycle && (
                <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-zinc-500">Status</p>
                            <span className={cn('mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', STATUS_BADGE[cycle.status] || 'bg-zinc-100 text-zinc-600')}>
                                {cycle.status || '-'}
                            </span>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-zinc-500">Total Reviews</p>
                            <p className="mt-1 text-2xl font-bold text-zinc-900">{cycle.reviews_count ?? reviews.length}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-zinc-500">Deadline</p>
                            <p className="mt-1 text-sm font-medium text-zinc-900">{formatDate(cycle.review_deadline)}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-zinc-500">Type</p>
                            <p className="mt-1 text-sm font-medium capitalize text-zinc-900">{cycle.type?.replace('_', ' ') || '-'}</p>
                        </CardContent>
                    </Card>
                </div>
            )}

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-semibold text-zinc-900">Reviews in This Cycle</h3>

                    {isLoading ? (
                        <SkeletonTable />
                    ) : reviews.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <BarChart2 className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No reviews in this cycle yet.</p>
                            <p className="mt-1 text-xs text-zinc-400">Reviews will appear here once the cycle is activated.</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Reviewer</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Rating</TableHead>
                                    <TableHead>Acknowledged</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {reviews.map((review) => (
                                    <TableRow key={review.id}>
                                        <TableCell className="font-medium text-zinc-900">
                                            {review.employee?.full_name || '-'}
                                        </TableCell>
                                        <TableCell className="text-zinc-600">
                                            {review.employee?.department?.name || '-'}
                                        </TableCell>
                                        <TableCell className="text-zinc-600">
                                            {review.reviewer?.full_name || '-'}
                                        </TableCell>
                                        <TableCell>
                                            <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', REVIEW_STATUS_BADGE[review.status] || 'bg-zinc-100 text-zinc-600')}>
                                                {review.status?.replace('_', ' ') || '-'}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {review.overall_rating ? (
                                                <div className="flex items-center gap-1">
                                                    <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                                    <span className="text-sm font-medium text-zinc-700">
                                                        {review.overall_rating}
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-zinc-400">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {review.acknowledged_at ? (
                                                <span className="text-xs text-emerald-600">Yes</span>
                                            ) : (
                                                <span className="text-xs text-zinc-400">No</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => navigate(`/performance/reviews/${review.id}`)}
                                                >
                                                    View
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
