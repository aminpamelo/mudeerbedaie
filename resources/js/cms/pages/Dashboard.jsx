import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    FileText,
    Clock,
    CheckCircle,
    Flag,
    Eye,
    Heart,
    MessageCircle,
    Share2,
    ChevronRight,
    ArrowRight,
    Loader2,
} from 'lucide-react';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
} from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import {
    Table,
    TableHeader,
    TableBody,
    TableHead,
    TableRow,
    TableCell,
} from '../components/ui/table';
import { cn } from '../lib/utils';
import { fetchDashboardStats, fetchTopPosts } from '../lib/api';

const STAT_CARDS = [
    {
        key: 'total_contents',
        label: 'Total Contents',
        icon: FileText,
        iconBg: 'bg-indigo-100',
        iconColor: 'text-indigo-600',
    },
    {
        key: 'in_progress',
        label: 'In Progress',
        icon: Clock,
        iconBg: 'bg-amber-100',
        iconColor: 'text-amber-600',
    },
    {
        key: 'posted_this_month',
        label: 'Posted This Month',
        icon: CheckCircle,
        iconBg: 'bg-emerald-100',
        iconColor: 'text-emerald-600',
    },
    {
        key: 'flagged_for_ads',
        label: 'Flagged for Ads',
        icon: Flag,
        iconBg: 'bg-rose-100',
        iconColor: 'text-rose-600',
    },
];

const STAGES = [
    { key: 'idea', label: 'Idea', color: 'bg-blue-500', lightBg: 'bg-blue-50', textColor: 'text-blue-700', borderColor: 'border-blue-200' },
    { key: 'shooting', label: 'Shooting', color: 'bg-purple-500', lightBg: 'bg-purple-50', textColor: 'text-purple-700', borderColor: 'border-purple-200' },
    { key: 'editing', label: 'Editing', color: 'bg-amber-500', lightBg: 'bg-amber-50', textColor: 'text-amber-700', borderColor: 'border-amber-200' },
    { key: 'posting', label: 'Posting', color: 'bg-emerald-500', lightBg: 'bg-emerald-50', textColor: 'text-emerald-700', borderColor: 'border-emerald-200' },
    { key: 'posted', label: 'Posted', color: 'bg-green-500', lightBg: 'bg-green-50', textColor: 'text-green-700', borderColor: 'border-green-200' },
];

function StatCardSkeleton() {
    return (
        <Card>
            <CardContent className="p-6">
                <div className="flex items-center gap-4">
                    <div className="h-12 w-12 animate-pulse rounded-full bg-slate-200" />
                    <div className="space-y-2">
                        <div className="h-7 w-16 animate-pulse rounded bg-slate-200" />
                        <div className="h-4 w-24 animate-pulse rounded bg-slate-200" />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function PipelineSkeleton() {
    return (
        <Card>
            <CardHeader>
                <div className="h-6 w-40 animate-pulse rounded bg-slate-200" />
            </CardHeader>
            <CardContent>
                <div className="flex items-center gap-4">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <div key={i} className="h-20 flex-1 animate-pulse rounded-lg bg-slate-200" />
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function TableSkeleton() {
    return (
        <Card>
            <CardHeader>
                <div className="h-6 w-48 animate-pulse rounded bg-slate-200" />
            </CardHeader>
            <CardContent>
                <div className="space-y-3">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <div key={i} className="h-10 animate-pulse rounded bg-slate-200" />
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

export default function Dashboard() {
    const navigate = useNavigate();

    const { data: stats, isLoading: statsLoading } = useQuery({
        queryKey: ['cms', 'dashboard', 'stats'],
        queryFn: fetchDashboardStats,
    });

    const { data: topPostsData, isLoading: postsLoading } = useQuery({
        queryKey: ['cms', 'dashboard', 'top-posts'],
        queryFn: fetchTopPosts,
    });

    const topPosts = topPostsData?.data ?? topPostsData ?? [];

    return (
        <div className="space-y-6">
            {/* Page Header */}
            <div>
                <h1 className="text-2xl font-bold text-slate-900">Dashboard</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Overview of your content pipeline and performance
                </p>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                {statsLoading
                    ? Array.from({ length: 4 }).map((_, i) => (
                          <StatCardSkeleton key={i} />
                      ))
                    : STAT_CARDS.map((card) => {
                          const Icon = card.icon;
                          return (
                              <Card key={card.key}>
                                  <CardContent className="p-6">
                                      <div className="flex items-center gap-4">
                                          <div
                                              className={cn(
                                                  'flex h-12 w-12 items-center justify-center rounded-full',
                                                  card.iconBg
                                              )}
                                          >
                                              <Icon className={cn('h-6 w-6', card.iconColor)} />
                                          </div>
                                          <div>
                                              <p className="text-2xl font-bold text-slate-900">
                                                  {stats?.[card.key] ?? 0}
                                              </p>
                                              <p className="text-sm text-slate-500">{card.label}</p>
                                          </div>
                                      </div>
                                  </CardContent>
                              </Card>
                          );
                      })}
            </div>

            {/* Pipeline Overview */}
            {statsLoading ? (
                <PipelineSkeleton />
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle>Pipeline Overview</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            {STAGES.map((stage, index) => {
                                const count = stats?.by_stage?.[stage.key] ?? 0;
                                return (
                                    <div key={stage.key} className="flex flex-1 items-center gap-3">
                                        <div
                                            className={cn(
                                                'flex flex-1 flex-col items-center rounded-lg border p-4',
                                                stage.lightBg,
                                                stage.borderColor
                                            )}
                                        >
                                            <div
                                                className={cn(
                                                    'mb-2 h-2.5 w-2.5 rounded-full',
                                                    stage.color
                                                )}
                                            />
                                            <p className={cn('text-2xl font-bold', stage.textColor)}>
                                                {count}
                                            </p>
                                            <p className={cn('text-xs font-medium', stage.textColor)}>
                                                {stage.label}
                                            </p>
                                        </div>
                                        {index < STAGES.length - 1 && (
                                            <ChevronRight className="hidden h-5 w-5 shrink-0 text-slate-300 sm:block" />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Top Performing Posts */}
            {postsLoading ? (
                <TableSkeleton />
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle>Top Performing Posts</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {topPosts.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <FileText className="mb-3 h-10 w-10 text-slate-300" />
                                <p className="text-sm font-medium text-slate-500">No posts yet</p>
                                <p className="mt-1 text-xs text-slate-400">
                                    Published content will appear here once available.
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Creator</TableHead>
                                        <TableHead className="text-right">
                                            <span className="inline-flex items-center gap-1">
                                                <Eye className="h-3.5 w-3.5" /> Views
                                            </span>
                                        </TableHead>
                                        <TableHead className="text-right">
                                            <span className="inline-flex items-center gap-1">
                                                <Heart className="h-3.5 w-3.5" /> Likes
                                            </span>
                                        </TableHead>
                                        <TableHead className="text-right">
                                            <span className="inline-flex items-center gap-1">
                                                <MessageCircle className="h-3.5 w-3.5" /> Comments
                                            </span>
                                        </TableHead>
                                        <TableHead className="text-right">
                                            <span className="inline-flex items-center gap-1">
                                                <Share2 className="h-3.5 w-3.5" /> Shares
                                            </span>
                                        </TableHead>
                                        <TableHead>Posted</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {topPosts.map((post) => (
                                        <TableRow
                                            key={post.id}
                                            className="cursor-pointer"
                                            onClick={() => navigate(`/contents/${post.id}`)}
                                        >
                                            <TableCell className="max-w-[200px] truncate font-medium">
                                                {post.title}
                                            </TableCell>
                                            <TableCell className="text-slate-500">
                                                {post.creator ?? '-'}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {(post.views ?? 0).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {(post.likes ?? 0).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {(post.comments ?? 0).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {(post.shares ?? 0).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-sm text-slate-500">
                                                {post.posted_at
                                                    ? new Date(post.posted_at).toLocaleDateString('en-MY', {
                                                          day: 'numeric',
                                                          month: 'short',
                                                          year: 'numeric',
                                                      })
                                                    : '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
