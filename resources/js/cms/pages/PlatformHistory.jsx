import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Search, ExternalLink, Inbox } from 'lucide-react';
import { fetchPlatforms, fetchPlatformPosts } from '../lib/api';
import { Badge } from '../components/ui/badge';
import { Card } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { Avatar, AvatarFallback, AvatarImage } from '../components/ui/avatar';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../components/ui/select';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../components/ui/table';

// ─── Constants ──────────────────────────────────────────────────────────────

const ALL_FILTER = 'all';

// ─── Helpers ────────────────────────────────────────────────────────────────

function formatDateTime(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function formatNumber(n) {
    if (n == null) return '0';
    if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n);
}

function formatStatsSummary(stats) {
    if (!stats || (typeof stats === 'object' && Object.keys(stats).length === 0)) {
        return '—';
    }
    const parts = [];
    if (stats.views != null) parts.push(`${formatNumber(stats.views)} views`);
    if (stats.likes != null) parts.push(`${formatNumber(stats.likes)} likes`);
    if (stats.comments != null) parts.push(`${formatNumber(stats.comments)} comments`);
    return parts.length > 0 ? parts.join(' • ') : '—';
}

// ─── Sub-components ─────────────────────────────────────────────────────────

function AssigneeCell({ assignee }) {
    if (!assignee) {
        return <span className="text-sm text-zinc-400">—</span>;
    }
    return (
        <div className="flex items-center gap-2">
            <Avatar className="h-7 w-7">
                {assignee.profile_photo ? (
                    <AvatarImage src={assignee.profile_photo} alt={assignee.full_name} />
                ) : null}
                <AvatarFallback className="text-[10px]">
                    {getInitials(assignee.full_name)}
                </AvatarFallback>
            </Avatar>
            <span className="text-sm text-zinc-700 truncate max-w-[140px]">
                {assignee.full_name}
            </span>
        </div>
    );
}

function SkeletonTable() {
    return (
        <div className="space-y-3 p-4">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 py-3">
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-48 animate-pulse rounded bg-zinc-200" />
                        <div className="h-3 w-32 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-5 w-16 animate-pulse rounded-full bg-zinc-200" />
                    <div className="h-5 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="h-5 w-20 animate-pulse rounded-full bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <Inbox className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">No posts yet</h3>
            <p className="mt-1 text-sm text-zinc-500">
                Posts appear here once you mark them as Posted in the queue.
            </p>
        </div>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────

export default function PlatformHistory() {
    // Filter state
    const [searchInput, setSearchInput] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [platformFilter, setPlatformFilter] = useState(ALL_FILTER);

    // Debounce search input (300ms)
    useEffect(() => {
        const handle = setTimeout(() => {
            setDebouncedSearch(searchInput);
        }, 300);
        return () => clearTimeout(handle);
    }, [searchInput]);

    // Build query params (always status=posted)
    const queryParams = useMemo(() => {
        const params = { status: 'posted' };
        if (debouncedSearch.trim()) params.search = debouncedSearch.trim();
        if (platformFilter !== ALL_FILTER) params.platform_id = platformFilter;
        return params;
    }, [debouncedSearch, platformFilter]);

    // Platforms (for filter select)
    const { data: platformsData } = useQuery({
        queryKey: ['cms', 'platforms'],
        queryFn: () => fetchPlatforms(),
    });
    const platforms = platformsData?.data || [];

    // Platform posts (status=posted only)
    const { data: postsData, isLoading } = useQuery({
        queryKey: [
            'cms',
            'platform-history',
            { platform_id: queryParams.platform_id, search: queryParams.search },
        ],
        queryFn: () => fetchPlatformPosts(queryParams),
    });

    // Sort by posted_at desc client-side for correctness
    const rows = useMemo(() => {
        return (postsData?.data ?? []).slice().sort((a, b) => {
            return new Date(b.posted_at ?? 0) - new Date(a.posted_at ?? 0);
        });
    }, [postsData]);

    return (
        <div>
            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-zinc-900">Posted History</h1>
                <p className="mt-1 text-sm text-zinc-500">
                    All cross-posted content across platforms.
                </p>
            </div>

            {/* Filter row — platform + search only */}
            <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <Input
                        type="search"
                        placeholder="Search by content title..."
                        value={searchInput}
                        onChange={(e) => setSearchInput(e.target.value)}
                        className="pl-9"
                    />
                </div>
                <Select value={platformFilter} onValueChange={setPlatformFilter}>
                    <SelectTrigger>
                        <SelectValue placeholder="All Platforms" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL_FILTER}>All Platforms</SelectItem>
                        {platforms.map((p) => (
                            <SelectItem key={p.id} value={String(p.id)}>
                                {p.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {/* Table */}
            <Card>
                {isLoading ? (
                    <SkeletonTable />
                ) : rows.length === 0 ? (
                    <EmptyState />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Content</TableHead>
                                <TableHead>Platform</TableHead>
                                <TableHead>Posted At</TableHead>
                                <TableHead>Post URL</TableHead>
                                <TableHead>Stats</TableHead>
                                <TableHead>Assignee</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((post) => (
                                <TableRow key={post.id}>
                                    <TableCell>
                                        {post.content ? (
                                            <Link
                                                to={`/contents/${post.content.id}`}
                                                className="font-medium text-indigo-600 hover:underline truncate max-w-[220px] inline-block"
                                            >
                                                {post.content.title}
                                            </Link>
                                        ) : (
                                            <span className="text-sm text-zinc-400">—</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {post.platform ? (
                                            <Badge className="bg-zinc-100 text-zinc-700">
                                                {post.platform.name}
                                            </Badge>
                                        ) : (
                                            <span className="text-sm text-zinc-400">—</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <span className="text-sm text-zinc-600 whitespace-nowrap">
                                            {formatDateTime(post.posted_at)}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        {post.post_url ? (
                                            <a
                                                href={post.post_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-sm text-indigo-600 hover:underline"
                                            >
                                                <ExternalLink className="h-3.5 w-3.5" />
                                                Open
                                            </a>
                                        ) : (
                                            <span className="text-sm text-zinc-400">—</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <span className="text-sm text-zinc-600 whitespace-nowrap">
                                            {formatStatsSummary(post.stats)}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <AssigneeCell assignee={post.assignee} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>
        </div>
    );
}
