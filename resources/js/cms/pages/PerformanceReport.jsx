import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    ChevronLeft,
    ChevronRight,
    CheckCircle,
    AlertTriangle,
    Clock,
    Users,
    TrendingUp,
    Timer,
    FileText,
    Eye,
    Heart,
    MessageCircle,
    Share2,
    Megaphone,
    DollarSign,
    MousePointerClick,
    BarChart3,
} from 'lucide-react';
import { fetchPerformanceReport } from '../lib/api';
import { cn } from '../lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';
import { Avatar, AvatarFallback } from '../components/ui/avatar';
import {
    Table,
    TableHeader,
    TableBody,
    TableHead,
    TableRow,
    TableCell,
} from '../components/ui/table';

// ─── Constants ──────────────────────────────────────────────────────────────

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const STAGE_COLORS = {
    idea: 'bg-blue-500',
    shooting: 'bg-purple-500',
    editing: 'bg-amber-500',
    posting: 'bg-emerald-500',
    posted: 'bg-green-500',
};

const STAGE_LABELS = {
    idea: 'Idea',
    shooting: 'Shooting',
    editing: 'Editing',
    posting: 'Posting',
    posted: 'Posted',
};

const PRIORITY_COLORS = {
    urgent: 'bg-rose-500',
    high: 'bg-orange-500',
    medium: 'bg-blue-500',
    low: 'bg-slate-400',
};

const STATUS_COLORS = {
    pending: 'bg-slate-100 text-slate-700',
    running: 'bg-emerald-100 text-emerald-700',
    paused: 'bg-amber-100 text-amber-700',
    completed: 'bg-green-100 text-green-700',
};

const PLATFORM_COLORS = {
    tiktok: 'bg-zinc-900 text-white',
    facebook: 'bg-blue-600 text-white',
};

// ─── Helpers ────────────────────────────────────────────────────────────────

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);
}

function formatNumber(n) {
    if (n === null || n === undefined) return '0';
    return Number(n).toLocaleString();
}

function formatCurrency(n) {
    return `RM ${Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// ─── Shared Sub-components ──────────────────────────────────────────────────

function StatCard({ icon: Icon, label, value, iconBg, iconColor, subtitle }) {
    return (
        <Card>
            <CardContent className="p-5">
                <div className="flex items-center gap-4">
                    <div className={cn('flex h-11 w-11 shrink-0 items-center justify-center rounded-full', iconBg)}>
                        <Icon className={cn('h-5 w-5', iconColor)} />
                    </div>
                    <div className="min-w-0">
                        <p className="text-2xl font-bold text-slate-900">{value}</p>
                        <p className="text-sm text-slate-500">{label}</p>
                        {subtitle && <p className="text-xs text-slate-400">{subtitle}</p>}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function OnTimeBar({ rate }) {
    if (rate === null || rate === undefined) return <span className="text-xs text-slate-400">N/A</span>;
    const color = rate >= 80 ? 'bg-emerald-500' : rate >= 60 ? 'bg-amber-500' : 'bg-rose-500';
    const textColor = rate >= 80 ? 'text-emerald-700' : rate >= 60 ? 'text-amber-700' : 'text-rose-700';
    return (
        <div className="flex items-center gap-2">
            <div className="h-2 w-16 overflow-hidden rounded-full bg-slate-100">
                <div className={cn('h-full rounded-full transition-all', color)} style={{ width: `${rate}%` }} />
            </div>
            <span className={cn('text-xs font-semibold tabular-nums', textColor)}>{rate}%</span>
        </div>
    );
}

function HorizontalBar({ data, colorMap, labelMap }) {
    const total = Object.values(data).reduce((sum, v) => sum + v, 0);
    if (total === 0) return <p className="text-xs text-slate-400">No data</p>;
    return (
        <div className="space-y-2">
            <div className="flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                {Object.entries(data).map(([key, count]) => {
                    const pct = (count / total) * 100;
                    if (pct === 0) return null;
                    return (
                        <div
                            key={key}
                            className={cn('h-full transition-all', colorMap[key] || 'bg-slate-300')}
                            style={{ width: `${pct}%` }}
                            title={`${labelMap?.[key] || capitalize(key)}: ${count}`}
                        />
                    );
                })}
            </div>
            <div className="flex flex-wrap gap-3">
                {Object.entries(data).map(([key, count]) => (
                    <div key={key} className="flex items-center gap-1.5">
                        <div className={cn('h-2.5 w-2.5 rounded-full', colorMap[key] || 'bg-slate-300')} />
                        <span className="text-xs text-slate-600">
                            {labelMap?.[key] || capitalize(key)}{' '}
                            <span className="font-semibold text-slate-800">{count}</span>
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function SectionSkeleton({ rows = 4 }) {
    return (
        <div className="space-y-3 p-6">
            {Array.from({ length: rows }).map((_, i) => (
                <div key={i} className="h-10 animate-pulse rounded bg-slate-200" />
            ))}
        </div>
    );
}

// ─── Section: Team Performance ──────────────────────────────────────────────

function TeamSection({ team, isLoading }) {
    const summary = team?.summary || {};
    const employees = team?.employees || [];

    return (
        <div className="space-y-4">
            <h2 className="flex items-center gap-2 text-lg font-semibold text-slate-800">
                <Users className="h-5 w-5 text-indigo-500" />
                Team Performance
            </h2>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard icon={CheckCircle} label="Tasks Completed" value={isLoading ? '-' : summary.total_stages_completed ?? 0} iconBg="bg-emerald-100" iconColor="text-emerald-600" subtitle="This month" />
                <StatCard icon={TrendingUp} label="On Time" value={isLoading ? '-' : summary.total_on_time ?? 0} iconBg="bg-blue-100" iconColor="text-blue-600" subtitle="Met deadline" />
                <StatCard icon={AlertTriangle} label="Overdue" value={isLoading ? '-' : summary.total_overdue ?? 0} iconBg="bg-rose-100" iconColor="text-rose-600" subtitle="Missed deadline" />
                <StatCard icon={Clock} label="In Progress" value={isLoading ? '-' : summary.total_in_progress ?? 0} iconBg="bg-amber-100" iconColor="text-amber-600" subtitle="Active now" />
            </div>

            <Card>
                <CardHeader className="pb-2">
                    <div className="flex items-center gap-2">
                        <CardTitle className="text-base">Employee Breakdown</CardTitle>
                        {!isLoading && <Badge variant="secondary" className="text-xs">{employees.length} members</Badge>}
                    </div>
                </CardHeader>
                {isLoading ? (
                    <SectionSkeleton rows={5} />
                ) : employees.length === 0 ? (
                    <CardContent>
                        <p className="py-8 text-center text-sm text-slate-400">No assignee data for this month.</p>
                    </CardContent>
                ) : (
                    <CardContent className="px-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-6">Employee</TableHead>
                                    <TableHead className="text-center">Assigned</TableHead>
                                    <TableHead className="text-center">Completed</TableHead>
                                    <TableHead className="text-center">On Time</TableHead>
                                    <TableHead className="text-center">Late</TableHead>
                                    <TableHead className="text-center">Overdue</TableHead>
                                    <TableHead className="text-center">In Progress</TableHead>
                                    <TableHead>On-Time Rate</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {employees.map((emp) => (
                                    <TableRow key={emp.employee_id}>
                                        <TableCell className="pl-6">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="h-8 w-8">
                                                    {emp.profile_photo_url ? (
                                                        <img src={emp.profile_photo_url} alt={emp.full_name} className="h-8 w-8 rounded-full object-cover" />
                                                    ) : (
                                                        <AvatarFallback className="text-xs bg-indigo-100 text-indigo-700">
                                                            {getInitials(emp.full_name)}
                                                        </AvatarFallback>
                                                    )}
                                                </Avatar>
                                                <span className="text-sm font-medium text-slate-800">{emp.full_name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">{emp.total_assigned}</TableCell>
                                        <TableCell className="text-center font-semibold text-emerald-700 tabular-nums">{emp.completed_this_month}</TableCell>
                                        <TableCell className="text-center text-blue-700 tabular-nums">{emp.on_time}</TableCell>
                                        <TableCell className="text-center">
                                            {emp.completed_late > 0 ? (
                                                <span className="inline-flex items-center gap-1 font-medium text-amber-600 tabular-nums">
                                                    <Timer className="h-3 w-3" />{emp.completed_late}
                                                </span>
                                            ) : <span className="text-slate-400 tabular-nums">0</span>}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {emp.overdue > 0 ? (
                                                <span className="inline-flex items-center gap-1 font-medium text-rose-600 tabular-nums">
                                                    <AlertTriangle className="h-3 w-3" />{emp.overdue}
                                                </span>
                                            ) : <span className="text-slate-400 tabular-nums">0</span>}
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">{emp.in_progress}</TableCell>
                                        <TableCell><OnTimeBar rate={emp.on_time_rate} /></TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                )}
            </Card>
        </div>
    );
}

// ─── Section: Content Pipeline ──────────────────────────────────────────────

function ContentSection({ content, isLoading, navigate }) {
    const summary = content?.summary || {};
    const byStage = content?.by_stage || {};
    const byPriority = content?.by_priority || {};
    const topContent = content?.top_content || [];

    return (
        <div className="space-y-4">
            <h2 className="flex items-center gap-2 text-lg font-semibold text-slate-800">
                <FileText className="h-5 w-5 text-blue-500" />
                Content Pipeline
            </h2>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard icon={FileText} label="Created" value={isLoading ? '-' : summary.created_this_month ?? 0} iconBg="bg-indigo-100" iconColor="text-indigo-600" subtitle="This month" />
                <StatCard icon={CheckCircle} label="Posted" value={isLoading ? '-' : summary.posted_this_month ?? 0} iconBg="bg-green-100" iconColor="text-green-600" subtitle="This month" />
                <StatCard icon={Eye} label="Total Views" value={isLoading ? '-' : formatNumber(summary.total_views)} iconBg="bg-purple-100" iconColor="text-purple-600" subtitle="All posted" />
                <StatCard icon={Heart} label="Total Likes" value={isLoading ? '-' : formatNumber(summary.total_likes)} iconBg="bg-rose-100" iconColor="text-rose-600" subtitle="All posted" />
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                {/* Stage Distribution */}
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">Stage Distribution</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? <SectionSkeleton rows={2} /> : (
                            <HorizontalBar data={byStage} colorMap={STAGE_COLORS} labelMap={STAGE_LABELS} />
                        )}
                    </CardContent>
                </Card>

                {/* Priority Distribution */}
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">Priority Distribution</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? <SectionSkeleton rows={2} /> : (
                            <HorizontalBar data={byPriority} colorMap={PRIORITY_COLORS} />
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Top Content */}
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-base">Top Performing Content</CardTitle>
                </CardHeader>
                {isLoading ? (
                    <SectionSkeleton rows={5} />
                ) : topContent.length === 0 ? (
                    <CardContent>
                        <p className="py-8 text-center text-sm text-slate-400">No posted content yet.</p>
                    </CardContent>
                ) : (
                    <CardContent className="px-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-6">Title</TableHead>
                                    <TableHead className="text-right">
                                        <span className="inline-flex items-center gap-1"><Eye className="h-3.5 w-3.5" /> Views</span>
                                    </TableHead>
                                    <TableHead className="text-right">
                                        <span className="inline-flex items-center gap-1"><Heart className="h-3.5 w-3.5" /> Likes</span>
                                    </TableHead>
                                    <TableHead className="text-right">
                                        <span className="inline-flex items-center gap-1"><MessageCircle className="h-3.5 w-3.5" /> Comments</span>
                                    </TableHead>
                                    <TableHead className="text-right">
                                        <span className="inline-flex items-center gap-1"><Share2 className="h-3.5 w-3.5" /> Shares</span>
                                    </TableHead>
                                    <TableHead className="text-right">Engagement</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {topContent.map((item) => (
                                    <TableRow
                                        key={item.id}
                                        className="cursor-pointer"
                                        onClick={() => navigate(`/contents/${item.id}`)}
                                    >
                                        <TableCell className="pl-6 max-w-[220px] truncate font-medium">{item.title}</TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">{formatNumber(item.views)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{formatNumber(item.likes)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{formatNumber(item.comments)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{formatNumber(item.shares)}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            <Badge variant="secondary" className="text-[10px]">{item.engagement_rate}%</Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                )}
            </Card>
        </div>
    );
}

// ─── Section: Ads Performance ───────────────────────────────────────────────

function AdsSection({ ads, isLoading, navigate }) {
    const summary = ads?.summary || {};
    const byStatus = ads?.by_status || {};
    const byPlatform = ads?.by_platform || {};
    const topCampaigns = ads?.top_campaigns || [];

    return (
        <div className="space-y-4">
            <h2 className="flex items-center gap-2 text-lg font-semibold text-slate-800">
                <Megaphone className="h-5 w-5 text-orange-500" />
                Ads Performance
            </h2>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard icon={Megaphone} label="Total Campaigns" value={isLoading ? '-' : summary.total_campaigns ?? 0} iconBg="bg-orange-100" iconColor="text-orange-600" subtitle={`${summary.campaigns_created ?? 0} new this month`} />
                <StatCard icon={DollarSign} label="Total Budget" value={isLoading ? '-' : formatCurrency(summary.total_budget)} iconBg="bg-emerald-100" iconColor="text-emerald-600" subtitle={`${formatCurrency(summary.total_spend)} spent`} />
                <StatCard icon={Eye} label="Impressions" value={isLoading ? '-' : formatNumber(summary.total_impressions)} iconBg="bg-purple-100" iconColor="text-purple-600" subtitle={`CTR: ${summary.avg_ctr ?? 0}%`} />
                <StatCard icon={MousePointerClick} label="Clicks" value={isLoading ? '-' : formatNumber(summary.total_clicks)} iconBg="bg-blue-100" iconColor="text-blue-600" subtitle={`${formatNumber(summary.total_conversions)} conversions`} />
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                {/* Campaign Status */}
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">Campaign Status</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? <SectionSkeleton rows={2} /> : (
                            <HorizontalBar data={byStatus} colorMap={{
                                pending: 'bg-slate-400',
                                running: 'bg-emerald-500',
                                paused: 'bg-amber-500',
                                completed: 'bg-green-500',
                            }} />
                        )}
                    </CardContent>
                </Card>

                {/* Platform Split */}
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">Platform Split</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? <SectionSkeleton rows={2} /> : (
                            <HorizontalBar data={byPlatform} colorMap={{
                                tiktok: 'bg-zinc-800',
                                facebook: 'bg-blue-600',
                            }} labelMap={{ tiktok: 'TikTok', facebook: 'Facebook' }} />
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Top Campaigns */}
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-base">Top Campaigns</CardTitle>
                </CardHeader>
                {isLoading ? (
                    <SectionSkeleton rows={5} />
                ) : topCampaigns.length === 0 ? (
                    <CardContent>
                        <p className="py-8 text-center text-sm text-slate-400">No ad campaigns yet.</p>
                    </CardContent>
                ) : (
                    <CardContent className="px-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-6">Content</TableHead>
                                    <TableHead>Platform</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Budget</TableHead>
                                    <TableHead className="text-right">Impressions</TableHead>
                                    <TableHead className="text-right">Clicks</TableHead>
                                    <TableHead className="text-right">CTR</TableHead>
                                    <TableHead className="text-right">Spend</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {topCampaigns.map((camp) => (
                                    <TableRow
                                        key={camp.id}
                                        className="cursor-pointer"
                                        onClick={() => navigate(`/ads/${camp.id}`)}
                                    >
                                        <TableCell className="pl-6 max-w-[180px] truncate font-medium">{camp.content_title}</TableCell>
                                        <TableCell>
                                            <Badge className={cn('text-[10px]', PLATFORM_COLORS[camp.platform] || '')}>
                                                {camp.platform === 'tiktok' ? 'TikTok' : 'Facebook'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge className={cn('text-[10px]', STATUS_COLORS[camp.status] || '')}>
                                                {capitalize(camp.status)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{formatCurrency(camp.budget)}</TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">{formatNumber(camp.impressions)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{formatNumber(camp.clicks)}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            <Badge variant="secondary" className="text-[10px]">{camp.ctr}%</Badge>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{formatCurrency(camp.spend)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                )}
            </Card>
        </div>
    );
}

// ─── Tab Navigation ─────────────────────────────────────────────────────────

const TABS = [
    { key: 'team', label: 'Team', icon: Users },
    { key: 'content', label: 'Content', icon: FileText },
    { key: 'ads', label: 'Ads', icon: Megaphone },
];

// ─── Main Component ─────────────────────────────────────────────────────────

export default function PerformanceReport() {
    const navigate = useNavigate();
    const now = new Date();
    const [month, setMonth] = useState(now.getMonth() + 1);
    const [year, setYear] = useState(now.getFullYear());
    const [activeTab, setActiveTab] = useState('team');

    const { data: reportData, isLoading } = useQuery({
        queryKey: ['cms', 'performance-report', month, year],
        queryFn: () => fetchPerformanceReport({ month, year }),
    });

    const report = reportData?.data || reportData || {};

    function goToPrevMonth() {
        if (month === 1) { setMonth(12); setYear(year - 1); }
        else { setMonth(month - 1); }
    }

    function goToNextMonth() {
        if (month === 12) { setMonth(1); setYear(year + 1); }
        else { setMonth(month + 1); }
    }

    const isCurrentMonth = month === now.getMonth() + 1 && year === now.getFullYear();

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Performance Report</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Unified monthly report for team, content pipeline, and ad campaigns.
                    </p>
                </div>

                {/* Month Picker */}
                <div className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1.5">
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={goToPrevMonth}>
                        <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <span className="min-w-[140px] text-center text-sm font-semibold text-slate-800">
                        {MONTHS[month - 1]} {year}
                    </span>
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={goToNextMonth} disabled={isCurrentMonth}>
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            {/* Tab Navigation */}
            <div className="flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1">
                {TABS.map((tab) => {
                    const Icon = tab.icon;
                    const isActive = activeTab === tab.key;
                    return (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key)}
                            className={cn(
                                'flex flex-1 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                isActive
                                    ? 'bg-slate-900 text-white shadow-sm'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            )}
                        >
                            <Icon className="h-4 w-4" />
                            {tab.label}
                        </button>
                    );
                })}
            </div>

            {/* Tab Content */}
            {activeTab === 'team' && <TeamSection team={report.team} isLoading={isLoading} />}
            {activeTab === 'content' && <ContentSection content={report.content} isLoading={isLoading} navigate={navigate} />}
            {activeTab === 'ads' && <AdsSection ads={report.ads} isLoading={isLoading} navigate={navigate} />}
        </div>
    );
}
