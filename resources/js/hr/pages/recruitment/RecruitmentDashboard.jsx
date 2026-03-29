import { useQuery } from '@tanstack/react-query';
import {
    Briefcase,
    Users,
    UserCheck,
    TrendingUp,
    Loader2,
} from 'lucide-react';
import { fetchRecruitmentDashboard } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Card, CardContent } from '../../components/ui/card';

const STAT_CARDS = [
    { key: 'open_positions', label: 'Open Positions', icon: Briefcase, color: 'text-blue-600', bg: 'bg-blue-50' },
    { key: 'total_applicants', label: 'Total Applicants', icon: Users, color: 'text-purple-600', bg: 'bg-purple-50' },
    { key: 'active_applicants', label: 'Active Applicants', icon: TrendingUp, color: 'text-amber-600', bg: 'bg-amber-50' },
    { key: 'hired_this_month', label: 'Hired This Month', icon: UserCheck, color: 'text-emerald-600', bg: 'bg-emerald-50' },
];

const PIPELINE_STAGES = [
    { key: 'applied', label: 'Applied', color: 'bg-zinc-400' },
    { key: 'screening', label: 'Screening', color: 'bg-blue-400' },
    { key: 'interview', label: 'Interview', color: 'bg-amber-400' },
    { key: 'assessment', label: 'Assessment', color: 'bg-purple-400' },
    { key: 'offer', label: 'Offer', color: 'bg-emerald-400' },
];

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

function SkeletonPipeline() {
    return (
        <div className="space-y-4">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4">
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="h-6 flex-1 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-8 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function RecruitmentDashboard() {
    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'recruitment', 'dashboard'],
        queryFn: fetchRecruitmentDashboard,
    });

    const stats = data?.stats || {};
    const pipeline = data?.pipeline || {};
    const maxPipelineValue = Math.max(1, ...PIPELINE_STAGES.map((s) => pipeline[s.key] || 0));

    if (isError) {
        return (
            <div>
                <PageHeader
                    title="Recruitment Dashboard"
                    description="Overview of job postings, applicants, and hiring pipeline."
                />
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <p className="text-sm font-medium text-red-600">Failed to load dashboard data.</p>
                        <p className="mt-1 text-xs text-zinc-400">Please try refreshing the page.</p>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <div>
            <PageHeader
                title="Recruitment Dashboard"
                description="Overview of job postings, applicants, and hiring pipeline."
            />

            {isLoading ? (
                <SkeletonCards />
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {STAT_CARDS.map((card) => {
                        const Icon = card.icon;
                        return (
                            <Card key={card.key}>
                                <CardContent className="p-6">
                                    <div className="flex items-center gap-4">
                                        <div className={cn('flex h-12 w-12 items-center justify-center rounded-lg', card.bg)}>
                                            <Icon className={cn('h-6 w-6', card.color)} />
                                        </div>
                                        <div>
                                            <p className="text-sm text-zinc-500">{card.label}</p>
                                            <p className="text-2xl font-bold text-zinc-900">{stats[card.key] ?? 0}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Pipeline Funnel */}
                <Card>
                    <CardContent className="p-6">
                        <h3 className="mb-6 text-lg font-semibold text-zinc-900">Hiring Pipeline</h3>
                        {isLoading ? (
                            <SkeletonPipeline />
                        ) : (
                            <div className="space-y-3">
                                {PIPELINE_STAGES.map((stage) => {
                                    const count = pipeline[stage.key] || 0;
                                    const widthPct = Math.round((count / maxPipelineValue) * 100);
                                    return (
                                        <div key={stage.key} className="flex items-center gap-3">
                                            <span className="w-20 shrink-0 text-right text-sm text-zinc-500">
                                                {stage.label}
                                            </span>
                                            <div className="flex-1 overflow-hidden rounded-full bg-zinc-100">
                                                <div
                                                    className={cn('h-6 rounded-full transition-all duration-500', stage.color)}
                                                    style={{ width: `${widthPct}%`, minWidth: count > 0 ? '2rem' : '0' }}
                                                />
                                            </div>
                                            <span className="w-8 shrink-0 text-sm font-semibold text-zinc-700">
                                                {count}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Recent Activity */}
                <Card>
                    <CardContent className="p-6">
                        <h3 className="mb-4 text-lg font-semibold text-zinc-900">Stage Conversion</h3>
                        {isLoading ? (
                            <SkeletonPipeline />
                        ) : (
                            <div className="space-y-4">
                                {PIPELINE_STAGES.slice(0, -1).map((stage, i) => {
                                    const fromCount = pipeline[stage.key] || 0;
                                    const toStage = PIPELINE_STAGES[i + 1];
                                    const toCount = pipeline[toStage.key] || 0;
                                    const rate = fromCount > 0 ? Math.round((toCount / fromCount) * 100) : 0;
                                    return (
                                        <div key={stage.key} className="flex items-center justify-between">
                                            <span className="text-sm text-zinc-600">
                                                {stage.label} → {toStage.label}
                                            </span>
                                            <div className="flex items-center gap-2">
                                                <div className="h-2 w-24 overflow-hidden rounded-full bg-zinc-100">
                                                    <div
                                                        className="h-full rounded-full bg-emerald-400 transition-all duration-500"
                                                        style={{ width: `${rate}%` }}
                                                    />
                                                </div>
                                                <span className="w-10 text-right text-sm font-semibold text-zinc-700">
                                                    {rate}%
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
