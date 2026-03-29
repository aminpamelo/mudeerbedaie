import { useQuery } from '@tanstack/react-query';
import {
    BarChart2,
    CheckCircle,
    RefreshCw,
    Star,
    TrendingUp,
    AlertTriangle,
    Loader2,
} from 'lucide-react';
import { fetchPerformanceDashboard } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Card, CardContent } from '../../components/ui/card';

const STAT_CARDS = [
    { key: 'active_cycles', label: 'Active Cycles', icon: RefreshCw, color: 'text-blue-600', bg: 'bg-blue-50' },
    { key: 'total_reviews', label: 'Total Reviews', icon: BarChart2, color: 'text-purple-600', bg: 'bg-purple-50' },
    { key: 'completion_rate', label: 'Completion Rate', icon: CheckCircle, color: 'text-emerald-600', bg: 'bg-emerald-50', isPercent: true },
    { key: 'active_pips', label: 'Active PIPs', icon: AlertTriangle, color: 'text-amber-600', bg: 'bg-amber-50' },
];

const RATING_COLORS = [
    '#ef4444', // 1 - red
    '#f97316', // 2 - orange
    '#f59e0b', // 3 - amber
    '#3b82f6', // 4 - blue
    '#10b981', // 5 - emerald
];

const RATING_LABELS = ['', 'Unsatisfactory', 'Needs Improvement', 'Meets Expectations', 'Exceeds Expectations', 'Outstanding'];

function SkeletonCards() {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, i) => (
                <Card key={i}>
                    <CardContent className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="h-12 w-12 animate-pulse rounded-lg bg-zinc-200" />
                            <div className="flex-1 space-y-2">
                                <div className="h-3 w-24 animate-pulse rounded bg-zinc-200" />
                                <div className="h-6 w-12 animate-pulse rounded bg-zinc-200" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function RatingDistributionChart({ distribution }) {
    if (!distribution || distribution.length === 0) {
        return (
            <div className="flex h-48 items-center justify-center text-sm text-zinc-400">
                No rating data available
            </div>
        );
    }

    const maxCount = Math.max(...distribution.map((d) => d.count || 0), 1);

    return (
        <div className="space-y-3">
            {distribution.map((item, i) => {
                const score = item.score || i + 1;
                const count = item.count || 0;
                const width = Math.round((count / maxCount) * 100);
                const color = RATING_COLORS[(score - 1) % RATING_COLORS.length];

                return (
                    <div key={score} className="flex items-center gap-3">
                        <div className="flex w-6 items-center justify-center">
                            <Star className="h-4 w-4 text-zinc-400" />
                            <span className="ml-0.5 text-xs font-semibold text-zinc-600">{score}</span>
                        </div>
                        <div className="flex-1">
                            <div className="h-6 w-full overflow-hidden rounded-full bg-zinc-100">
                                <div
                                    className="h-full rounded-full transition-all duration-500"
                                    style={{ width: `${width}%`, backgroundColor: color }}
                                />
                            </div>
                        </div>
                        <div className="w-16 text-right">
                            <span className="text-sm font-medium text-zinc-700">{count}</span>
                            <span className="ml-1 text-xs text-zinc-400">reviews</span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export default function PerformanceDashboard() {
    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'performance', 'dashboard'],
        queryFn: fetchPerformanceDashboard,
    });

    const stats = data?.stats || data || {};
    const distribution = data?.rating_distribution || [];
    const recentReviews = data?.recent_reviews || [];

    if (isError) {
        return (
            <div>
                <PageHeader
                    title="Performance Dashboard"
                    description="Overview of performance reviews, cycles, and improvement plans."
                />
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <AlertTriangle className="mb-3 h-10 w-10 text-red-300" />
                        <p className="text-sm font-medium text-zinc-600">Failed to load performance data.</p>
                        <p className="mt-1 text-xs text-zinc-400">Please try refreshing the page.</p>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <div>
            <PageHeader
                title="Performance Dashboard"
                description="Overview of performance reviews, cycles, and improvement plans."
            />

            {isLoading ? (
                <SkeletonCards />
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {STAT_CARDS.map((card) => {
                        const Icon = card.icon;
                        const rawValue = stats?.[card.key] ?? 0;
                        const displayValue = card.isPercent ? `${rawValue}%` : rawValue;

                        return (
                            <Card key={card.key}>
                                <CardContent className="p-6">
                                    <div className="flex items-center gap-4">
                                        <div className={cn('flex h-12 w-12 items-center justify-center rounded-lg', card.bg)}>
                                            <Icon className={cn('h-6 w-6', card.color)} />
                                        </div>
                                        <div>
                                            <p className="text-sm text-zinc-500">{card.label}</p>
                                            <p className="text-2xl font-bold text-zinc-900">{displayValue}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardContent className="p-6">
                        <h3 className="mb-4 text-lg font-semibold text-zinc-900">Rating Distribution</h3>
                        {isLoading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <div key={i} className="flex items-center gap-3">
                                        <div className="h-4 w-6 animate-pulse rounded bg-zinc-200" />
                                        <div className="h-6 flex-1 animate-pulse rounded-full bg-zinc-200" />
                                        <div className="h-4 w-16 animate-pulse rounded bg-zinc-200" />
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <RatingDistributionChart distribution={distribution} />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-6">
                        <h3 className="mb-4 text-lg font-semibold text-zinc-900">Recent Reviews</h3>
                        {isLoading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <div key={i} className="flex items-center gap-3">
                                        <div className="h-9 w-9 animate-pulse rounded-full bg-zinc-200" />
                                        <div className="flex-1 space-y-1">
                                            <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                                            <div className="h-3 w-20 animate-pulse rounded bg-zinc-200" />
                                        </div>
                                        <div className="h-5 w-16 animate-pulse rounded-full bg-zinc-200" />
                                    </div>
                                ))}
                            </div>
                        ) : recentReviews.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-10 text-center">
                                <TrendingUp className="mb-3 h-10 w-10 text-zinc-300" />
                                <p className="text-sm font-medium text-zinc-600">No recent reviews</p>
                                <p className="mt-1 text-xs text-zinc-400">Reviews will appear here once cycles are active.</p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-zinc-100">
                                {recentReviews.map((review) => (
                                    <li key={review.id} className="flex items-center gap-3 py-3">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold text-zinc-600">
                                            {review.employee?.full_name?.charAt(0) || '?'}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-zinc-900">
                                                {review.employee?.full_name || '-'}
                                            </p>
                                            <p className="text-xs text-zinc-500">{review.cycle?.name || '-'}</p>
                                        </div>
                                        {review.overall_rating && (
                                            <div className="flex items-center gap-1">
                                                <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                                <span className="text-sm font-medium text-zinc-700">
                                                    {review.overall_rating}
                                                </span>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
