import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    Download,
    TrendingUp,
    Eye,
    Heart,
    MessageCircle,
    Megaphone,
    Flag,
    CheckCircle2,
    ArrowRight,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
} from 'lucide-react';
import {
    fetchContentReport,
    contentReportExportUrl,
} from '../lib/api';
import { cn } from '../lib/utils';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Card } from '../components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../components/ui/table';

// ─── Constants ──────────────────────────────────────────────────────────────

const RANGE_PRESETS = [
    { value: '7d', label: 'Last 7 days' },
    { value: '30d', label: 'Last 30 days' },
    { value: '90d', label: 'Last 90 days' },
    { value: 'this_month', label: 'This month' },
    { value: 'last_month', label: 'Last month' },
    { value: 'all_time', label: 'All time' },
];

const STAGE_BADGE = {
    idea: 'bg-blue-100 text-blue-700',
    shooting: 'bg-purple-100 text-purple-700',
    editing: 'bg-amber-100 text-amber-700',
    posting: 'bg-orange-100 text-orange-700',
    posted: 'bg-green-100 text-green-700',
};

// ─── Helpers ────────────────────────────────────────────────────────────────

function todayStr(offsetDays = 0) {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    return d.toISOString().slice(0, 10);
}

function startOfMonth(offsetMonths = 0) {
    const d = new Date();
    d.setMonth(d.getMonth() + offsetMonths, 1);
    return d.toISOString().slice(0, 10);
}

function endOfMonth(offsetMonths = 0) {
    const d = new Date();
    d.setMonth(d.getMonth() + offsetMonths + 1, 0);
    return d.toISOString().slice(0, 10);
}

function rangeFromPreset(preset) {
    switch (preset) {
        case '7d':
            return { start_date: todayStr(-7), end_date: todayStr(0) };
        case '90d':
            return { start_date: todayStr(-90), end_date: todayStr(0) };
        case 'this_month':
            return { start_date: startOfMonth(0), end_date: endOfMonth(0) };
        case 'last_month':
            return { start_date: startOfMonth(-1), end_date: endOfMonth(-1) };
        case 'all_time':
            return { start_date: '2020-01-01', end_date: todayStr(0) };
        case '30d':
        default:
            return { start_date: todayStr(-30), end_date: todayStr(0) };
    }
}

function formatNumber(n) {
    if (n == null) return '0';
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000) return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return n.toLocaleString();
}

function formatDate(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

// ─── Sub-components ─────────────────────────────────────────────────────────

function KpiCard({ label, value, sub, icon: Icon, accent = 'indigo' }) {
    const accentClasses = {
        indigo: 'bg-indigo-50 text-indigo-600',
        green: 'bg-green-50 text-green-600',
        amber: 'bg-amber-50 text-amber-600',
        blue: 'bg-blue-50 text-blue-600',
        rose: 'bg-rose-50 text-rose-600',
    };
    return (
        <Card className="p-5">
            <div className="flex items-start justify-between">
                <div>
                    <div className="text-xs font-medium uppercase tracking-wide text-zinc-500">
                        {label}
                    </div>
                    <div className="mt-2 text-2xl font-bold text-zinc-900">
                        {value}
                    </div>
                    {sub && (
                        <div className="mt-1 text-xs text-zinc-500">{sub}</div>
                    )}
                </div>
                {Icon && (
                    <div
                        className={cn(
                            'rounded-md p-2',
                            accentClasses[accent] || accentClasses.indigo
                        )}
                    >
                        <Icon className="h-4 w-4" />
                    </div>
                )}
            </div>
        </Card>
    );
}

function FunnelChart({ funnel }) {
    const max = Math.max(...funnel.map((s) => s.count), 1);
    return (
        <Card className="p-5">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-zinc-900">
                    Lifecycle funnel
                </h3>
                <span className="text-xs text-zinc-500">All-time pipeline</span>
            </div>
            <div className="space-y-2">
                {funnel.map((step, i) => {
                    const widthPct = (step.count / max) * 100;
                    const prev = i > 0 ? funnel[i - 1] : null;
                    const conv =
                        prev && prev.count > 0
                            ? Math.round((step.count / prev.count) * 100)
                            : null;
                    return (
                        <div
                            key={step.key}
                            className="flex items-center gap-3"
                        >
                            <div className="w-28 text-xs font-medium text-zinc-700">
                                {step.label}
                            </div>
                            <div className="flex-1">
                                <div className="h-7 w-full overflow-hidden rounded bg-zinc-100">
                                    <div
                                        className={cn(
                                            'flex h-full items-center justify-end px-2 text-xs font-semibold text-white transition-all',
                                            i < 5
                                                ? 'bg-indigo-500'
                                                : i === 5
                                                  ? 'bg-amber-500'
                                                  : i === 6
                                                    ? 'bg-emerald-500'
                                                    : 'bg-rose-500'
                                        )}
                                        style={{
                                            width: `${Math.max(widthPct, 6)}%`,
                                        }}
                                    >
                                        {step.count}
                                    </div>
                                </div>
                            </div>
                            <div className="w-12 text-right text-xs text-zinc-500">
                                {conv != null ? `${conv}%` : ''}
                            </div>
                        </div>
                    );
                })}
            </div>
        </Card>
    );
}

function PlatformBars({ rows }) {
    const max = Math.max(...rows.map((r) => r.views), 1);
    return (
        <Card className="p-5">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-zinc-900">
                    Reach by platform
                </h3>
                <span className="text-xs text-zinc-500">All-time views</span>
            </div>
            {rows.length === 0 ? (
                <p className="text-sm text-zinc-400">No data yet.</p>
            ) : (
                <div className="space-y-3">
                    {rows.map((row) => {
                        const pct = (row.views / max) * 100;
                        return (
                            <div key={row.key}>
                                <div className="mb-1 flex items-center justify-between text-xs">
                                    <span className="font-medium text-zinc-700">
                                        {row.name}
                                    </span>
                                    <span className="text-zinc-500">
                                        {formatNumber(row.views)} views ·{' '}
                                        {formatNumber(row.likes)} likes ·{' '}
                                        {formatNumber(row.comments)} comments
                                    </span>
                                </div>
                                <div className="h-2 w-full overflow-hidden rounded-full bg-zinc-100">
                                    <div
                                        className={cn(
                                            'h-full rounded-full transition-all',
                                            row.key === 'tiktok'
                                                ? 'bg-zinc-900'
                                                : 'bg-indigo-500'
                                        )}
                                        style={{
                                            width: `${Math.max(pct, 2)}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </Card>
    );
}

function TopList({ title, items, suffix }) {
    return (
        <Card className="p-5">
            <h3 className="mb-3 text-sm font-semibold text-zinc-900">{title}</h3>
            {items.length === 0 ? (
                <p className="text-sm text-zinc-400">No data yet.</p>
            ) : (
                <ol className="space-y-2">
                    {items.map((item, i) => (
                        <li
                            key={item.id}
                            className="flex items-center gap-3 text-sm"
                        >
                            <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-bold text-zinc-600">
                                {i + 1}
                            </span>
                            <Link
                                to={`/contents/${item.id}`}
                                className="flex-1 truncate text-zinc-700 hover:text-indigo-600"
                            >
                                {item.title}
                            </Link>
                            <span className="text-xs font-semibold text-zinc-900">
                                {suffix === '%'
                                    ? `${item.metric}%`
                                    : formatNumber(item.metric)}
                            </span>
                        </li>
                    ))}
                </ol>
            )}
        </Card>
    );
}

function SortHeader({ label, sortKey, sortBy, sortDir, onSort, align }) {
    const active = sortBy === sortKey;
    return (
        <TableHead className={cn(align === 'right' && 'text-right')}>
            <button
                type="button"
                onClick={() => onSort(sortKey)}
                className={cn(
                    'inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wide transition-colors hover:text-zinc-900',
                    active ? 'text-zinc-900' : 'text-zinc-500'
                )}
            >
                {label}
                {active ? (
                    sortDir === 'asc' ? (
                        <ArrowUp className="h-3 w-3" />
                    ) : (
                        <ArrowDown className="h-3 w-3" />
                    )
                ) : (
                    <ArrowUpDown className="h-3 w-3 opacity-50" />
                )}
            </button>
        </TableHead>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────

export default function ContentReport() {
    const [preset, setPreset] = useState('30d');
    const [sortBy, setSortBy] = useState('total_views');
    const [sortDir, setSortDir] = useState('desc');

    const range = useMemo(() => rangeFromPreset(preset), [preset]);

    const { data, isLoading } = useQuery({
        queryKey: ['cms', 'content-report', range],
        queryFn: () => fetchContentReport(range),
    });

    const report = data?.data;
    const contents = report?.contents ?? [];
    const kpis = report?.kpis ?? {};
    const funnel = report?.funnel ?? [];
    const byPlatform = report?.by_platform ?? [];
    const topPerformers = report?.top_performers ?? {};

    const sortedContents = useMemo(() => {
        const rows = [...contents];
        const getValue = (row) => {
            switch (sortBy) {
                case 'title':
                    return (row.title || '').toLowerCase();
                case 'stage':
                    return row.stage || '';
                case 'created_at':
                    return row.created_at || '';
                case 'posted_at':
                    return row.posted_at || '';
                case 'tiktok_views':
                    return row.tiktok.views;
                case 'cross_views':
                    return row.cross_post.views;
                case 'cross_progress':
                    return row.cross_post.posted;
                case 'total_views':
                    return row.totals.views;
                case 'engagement_rate':
                    return row.totals.engagement_rate;
                default:
                    return 0;
            }
        };
        rows.sort((a, b) => {
            const av = getValue(a);
            const bv = getValue(b);
            if (av < bv) return sortDir === 'asc' ? -1 : 1;
            if (av > bv) return sortDir === 'asc' ? 1 : -1;
            return 0;
        });
        return rows;
    }, [contents, sortBy, sortDir]);

    function handleSort(key) {
        if (sortBy === key) {
            setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortBy(key);
            setSortDir(key === 'title' || key === 'stage' ? 'asc' : 'desc');
        }
    }

    const exportUrl = contentReportExportUrl(range);

    return (
        <div>
            {/* Header */}
            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-zinc-900">
                        Content Report
                    </h1>
                    <p className="mt-1 text-sm text-zinc-500">
                        TikTok performance, cross-platform reach, and ad
                        conversion in one view.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Select value={preset} onValueChange={setPreset}>
                        <SelectTrigger className="w-[180px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {RANGE_PRESETS.map((p) => (
                                <SelectItem key={p.value} value={p.value}>
                                    {p.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button asChild variant="outline" size="sm">
                        <a href={exportUrl} target="_blank" rel="noopener noreferrer">
                            <Download className="mr-1 h-4 w-4" />
                            Export CSV
                        </a>
                    </Button>
                </div>
            </div>

            {/* Period note */}
            {report && (
                <p className="mb-4 text-xs text-zinc-500">
                    Period: <strong>{formatDate(report.period.start_date)}</strong>{' '}
                    → <strong>{formatDate(report.period.end_date)}</strong>
                </p>
            )}

            {isLoading ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <Card key={i} className="p-5">
                            <div className="h-3 w-20 animate-pulse rounded bg-zinc-200" />
                            <div className="mt-3 h-7 w-24 animate-pulse rounded bg-zinc-200" />
                        </Card>
                    ))}
                </div>
            ) : (
                <>
                    {/* KPI cards */}
                    <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        <KpiCard
                            label="Content in period"
                            value={formatNumber(kpis.total_content)}
                            icon={TrendingUp}
                            accent="indigo"
                        />
                        <KpiCard
                            label="Posted"
                            value={formatNumber(kpis.posted_in_period)}
                            sub="this period"
                            icon={CheckCircle2}
                            accent="green"
                        />
                        <KpiCard
                            label="Marked rate"
                            value={`${kpis.marked_rate ?? 0}%`}
                            sub={`${kpis.marked_in_period ?? 0} marked`}
                            icon={Flag}
                            accent="amber"
                        />
                        <KpiCard
                            label="Total views"
                            value={formatNumber(kpis.total_views)}
                            sub="TikTok + cross-platform"
                            icon={Eye}
                            accent="blue"
                        />
                        <KpiCard
                            label="Total engagement"
                            value={formatNumber(kpis.total_engagement)}
                            sub="likes + comments + shares"
                            icon={Heart}
                            accent="rose"
                        />
                    </div>

                    {/* Funnel + Platform bars */}
                    <div className="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <FunnelChart funnel={funnel} />
                        <PlatformBars rows={byPlatform} />
                    </div>

                    {/* Top performers */}
                    <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <TopList
                            title="Top by total views"
                            items={topPerformers.by_total_views ?? []}
                        />
                        <TopList
                            title="Top by cross-platform views"
                            items={topPerformers.by_cross_platform ?? []}
                        />
                        <TopList
                            title="Top engagement rate"
                            items={topPerformers.by_engagement_rate ?? []}
                            suffix="%"
                        />
                    </div>

                    {/* Content table */}
                    <Card>
                        <div className="border-b border-zinc-100 px-5 py-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-sm font-semibold text-zinc-900">
                                        Content performance
                                    </h3>
                                    <p className="mt-0.5 text-xs text-zinc-500">
                                        {sortedContents.length} content
                                        {sortedContents.length === 1 ? '' : 's'} in
                                        period · click column headers to sort
                                    </p>
                                </div>
                            </div>
                        </div>
                        {sortedContents.length === 0 ? (
                            <div className="py-12 text-center text-sm text-zinc-500">
                                No content in this period yet.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <SortHeader
                                                label="Title"
                                                sortKey="title"
                                                sortBy={sortBy}
                                                sortDir={sortDir}
                                                onSort={handleSort}
                                            />
                                            <SortHeader
                                                label="Stage"
                                                sortKey="stage"
                                                sortBy={sortBy}
                                                sortDir={sortDir}
                                                onSort={handleSort}
                                            />
                                            <SortHeader
                                                label="Posted"
                                                sortKey="posted_at"
                                                sortBy={sortBy}
                                                sortDir={sortDir}
                                                onSort={handleSort}
                                            />
                                            <SortHeader
                                                label="TikTok views"
                                                sortKey="tiktok_views"
                                                sortBy={sortBy}
                                                sortDir={sortDir}
                                                onSort={handleSort}
                                            />
                                            <SortHeader
                                                label="Cross-posts"
                                                sortKey="cross_progress"
                                                sortBy={sortBy}
                                                sortDir={sortDir}
                                                onSort={handleSort}
                                            />
                                            <SortHeader
                                                label="Cross views"
                                                sortKey="cross_views"
                                                sortBy={sortBy}
                                                sortDir={sortDir}
                                                onSort={handleSort}
                                            />
                                            <SortHeader
                                                label="Total views"
                                                sortKey="total_views"
                                                sortBy={sortBy}
                                                sortDir={sortDir}
                                                onSort={handleSort}
                                            />
                                            <SortHeader
                                                label="Eng. rate"
                                                sortKey="engagement_rate"
                                                sortBy={sortBy}
                                                sortDir={sortDir}
                                                onSort={handleSort}
                                            />
                                            <TableHead>Flags</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {sortedContents.map((row) => (
                                            <TableRow key={row.id}>
                                                <TableCell>
                                                    <Link
                                                        to={`/contents/${row.id}`}
                                                        className="font-medium text-indigo-600 hover:underline"
                                                    >
                                                        {row.title}
                                                    </Link>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        className={cn(
                                                            'text-[10px]',
                                                            STAGE_BADGE[row.stage] ||
                                                                'bg-zinc-100 text-zinc-700'
                                                        )}
                                                    >
                                                        {row.stage}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-600">
                                                    {formatDate(row.posted_at)}
                                                </TableCell>
                                                <TableCell className="text-sm font-medium text-zinc-900">
                                                    {formatNumber(row.tiktok.views)}
                                                </TableCell>
                                                <TableCell>
                                                    <span className="text-xs text-zinc-600">
                                                        {row.cross_post.posted}/
                                                        {row.cross_post.total}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-700">
                                                    {formatNumber(
                                                        row.cross_post.views
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-sm font-semibold text-zinc-900">
                                                    {formatNumber(row.totals.views)}
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-600">
                                                    {row.totals.engagement_rate}%
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        {row.is_marked && (
                                                            <Flag
                                                                className="h-3.5 w-3.5 text-amber-500"
                                                                title="Marked"
                                                            />
                                                        )}
                                                        {row.has_ad_campaign && (
                                                            <Megaphone
                                                                className="h-3.5 w-3.5 text-rose-500"
                                                                title="Has ad campaign"
                                                            />
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </Card>
                </>
            )}
        </div>
    );
}
