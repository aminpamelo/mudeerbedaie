import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    Search,
    ExternalLink,
    Inbox,
    Eye,
    Heart,
    MessageCircle,
    Plus,
} from 'lucide-react';
import {
    fetchEmployees,
    fetchPlatforms,
    fetchPlatformPosts,
    updatePlatformPost,
    updatePlatformPostStats,
} from '../lib/api';
import { cn } from '../lib/utils';
import { toastSuccess, toastError } from '../lib/toast';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Card } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
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
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../components/ui/dialog';

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

function formatNumber(n) {
    if (n == null) return '0';
    if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n);
}

function hasStats(stats) {
    if (!stats || typeof stats !== 'object') return false;
    return ['views', 'likes', 'comments'].some((k) => stats[k] != null);
}

// ─── Sub-components ─────────────────────────────────────────────────────────

function AssigneePicker({ assignee, employees, isUpdating, onAssign }) {
    const value = assignee?.id ? String(assignee.id) : 'unassigned';
    return (
        <Select
            value={value}
            onValueChange={(v) =>
                onAssign(v === 'unassigned' ? null : Number(v))
            }
            disabled={isUpdating}
        >
            <SelectTrigger
                className={cn(
                    'h-8 border-0 bg-transparent px-2 text-xs hover:bg-zinc-100',
                    !assignee && 'text-zinc-400'
                )}
            >
                <SelectValue placeholder="Unassigned" />
            </SelectTrigger>
            <SelectContent className="max-h-[320px]">
                <SelectItem value="unassigned">
                    <span className="text-zinc-500">— Unassigned</span>
                </SelectItem>
                {employees.map((e) => (
                    <SelectItem key={e.id} value={String(e.id)}>
                        {e.full_name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function StatsCell({ stats, onClick }) {
    if (!hasStats(stats)) {
        return (
            <button
                type="button"
                onClick={onClick}
                className="inline-flex items-center gap-1 text-xs text-zinc-500 hover:text-indigo-600"
            >
                <Plus className="h-3 w-3" />
                Add stats
            </button>
        );
    }
    return (
        <button
            type="button"
            onClick={onClick}
            className="group inline-flex items-center gap-3 rounded-md px-2 py-1 text-xs text-zinc-700 hover:bg-zinc-100"
            title="Click to edit"
        >
            <span className="inline-flex items-center gap-1">
                <Eye className="h-3 w-3 text-zinc-400" />
                {formatNumber(stats.views ?? 0)}
            </span>
            <span className="inline-flex items-center gap-1">
                <Heart className="h-3 w-3 text-zinc-400" />
                {formatNumber(stats.likes ?? 0)}
            </span>
            <span className="inline-flex items-center gap-1">
                <MessageCircle className="h-3 w-3 text-zinc-400" />
                {formatNumber(stats.comments ?? 0)}
            </span>
        </button>
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
    const queryClient = useQueryClient();

    const [searchInput, setSearchInput] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [platformFilter, setPlatformFilter] = useState(ALL_FILTER);

    const [pendingMutationId, setPendingMutationId] = useState(null);

    // Stats edit dialog
    const [statsOpen, setStatsOpen] = useState(false);
    const [editingStatsPost, setEditingStatsPost] = useState(null);
    const [statsForm, setStatsForm] = useState({
        views: '',
        likes: '',
        comments: '',
    });

    useEffect(() => {
        const handle = setTimeout(() => {
            setDebouncedSearch(searchInput);
        }, 300);
        return () => clearTimeout(handle);
    }, [searchInput]);

    const queryParams = useMemo(() => {
        const params = { status: 'posted' };
        if (debouncedSearch.trim()) params.search = debouncedSearch.trim();
        if (platformFilter !== ALL_FILTER) params.platform_id = platformFilter;
        return params;
    }, [debouncedSearch, platformFilter]);

    const { data: platformsData } = useQuery({
        queryKey: ['cms', 'platforms'],
        queryFn: () => fetchPlatforms(),
    });
    const platforms = platformsData?.data || [];

    const { data: employeesData } = useQuery({
        queryKey: ['cms', 'employees', 'active'],
        queryFn: () =>
            fetchEmployees({ per_page: 200, status: 'active', sort_by: 'full_name' }),
        staleTime: 1000 * 60 * 10,
    });
    const employees = employeesData?.data || [];

    const { data: postsData, isLoading } = useQuery({
        queryKey: [
            'cms',
            'platform-history',
            { platform_id: queryParams.platform_id, search: queryParams.search },
        ],
        queryFn: () => fetchPlatformPosts(queryParams),
    });

    const rows = useMemo(() => {
        return (postsData?.data ?? []).slice().sort((a, b) => {
            return new Date(b.posted_at ?? 0) - new Date(a.posted_at ?? 0);
        });
    }, [postsData]);

    function invalidateQueries() {
        queryClient.invalidateQueries({ queryKey: ['cms', 'platform-history'] });
        // Also invalidate the queue cache so changes show up there too.
        queryClient.invalidateQueries({ queryKey: ['cms', 'platform-posts'] });
    }

    const assigneeMutation = useMutation({
        mutationFn: ({ id, assigneeId }) =>
            updatePlatformPost(id, { assignee_id: assigneeId }),
        onSuccess: (_data, variables) => {
            invalidateQueries();
            toastSuccess(
                variables.assigneeId == null ? 'Assignee cleared' : 'Assignee updated'
            );
        },
        onError: (error) => toastError(error, 'Failed to update assignee'),
        onSettled: () => setPendingMutationId(null),
    });

    const statsMutation = useMutation({
        mutationFn: ({ id, payload }) => updatePlatformPostStats(id, payload),
        onSuccess: () => {
            invalidateQueries();
            setStatsOpen(false);
            setEditingStatsPost(null);
            toastSuccess('Stats updated');
        },
        onError: (error) => toastError(error, 'Failed to update stats'),
    });

    function handleAssigneeChange(post, assigneeId) {
        const current = post.assignee?.id ?? null;
        if (current === assigneeId) return;
        setPendingMutationId(post.id);
        assigneeMutation.mutate({ id: post.id, assigneeId });
    }

    function openStatsEdit(post) {
        setEditingStatsPost(post);
        setStatsForm({
            views: post.stats?.views ?? '',
            likes: post.stats?.likes ?? '',
            comments: post.stats?.comments ?? '',
        });
        setStatsOpen(true);
    }

    function handleStatsSave() {
        if (!editingStatsPost) return;
        const payload = {};
        // Only include fields the user actually typed; backend merges.
        if (statsForm.views !== '' && statsForm.views !== null) {
            payload.views = parseInt(statsForm.views, 10) || 0;
        }
        if (statsForm.likes !== '' && statsForm.likes !== null) {
            payload.likes = parseInt(statsForm.likes, 10) || 0;
        }
        if (statsForm.comments !== '' && statsForm.comments !== null) {
            payload.comments = parseInt(statsForm.comments, 10) || 0;
        }
        statsMutation.mutate({ id: editingStatsPost.id, payload });
    }

    return (
        <div>
            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-zinc-900">Posted History</h1>
                <p className="mt-1 text-sm text-zinc-500">
                    Click stats or assignee to edit inline.
                </p>
            </div>

            {/* Filters */}
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
                                        <StatsCell
                                            stats={post.stats}
                                            onClick={() => openStatsEdit(post)}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <AssigneePicker
                                            assignee={post.assignee}
                                            employees={employees}
                                            isUpdating={pendingMutationId === post.id}
                                            onAssign={(assigneeId) =>
                                                handleAssigneeChange(post, assigneeId)
                                            }
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            {/* Stats Edit Dialog */}
            <Dialog
                open={statsOpen}
                onOpenChange={(open) => {
                    setStatsOpen(open);
                    if (!open) setEditingStatsPost(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Stats</DialogTitle>
                        <DialogDescription>
                            {editingStatsPost?.content?.title
                                ? `${editingStatsPost.platform?.name || 'Platform'} stats for "${editingStatsPost.content.title}"`
                                : 'Update views, likes, and comments for this post.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid grid-cols-1 gap-4 py-4 sm:grid-cols-3">
                        <div>
                            <Label htmlFor="stats-views">
                                <span className="inline-flex items-center gap-1">
                                    <Eye className="h-3.5 w-3.5" /> Views
                                </span>
                            </Label>
                            <Input
                                id="stats-views"
                                type="number"
                                min="0"
                                inputMode="numeric"
                                className="mt-1.5"
                                placeholder="0"
                                value={statsForm.views}
                                onChange={(e) =>
                                    setStatsForm((f) => ({ ...f, views: e.target.value }))
                                }
                            />
                        </div>
                        <div>
                            <Label htmlFor="stats-likes">
                                <span className="inline-flex items-center gap-1">
                                    <Heart className="h-3.5 w-3.5" /> Likes
                                </span>
                            </Label>
                            <Input
                                id="stats-likes"
                                type="number"
                                min="0"
                                inputMode="numeric"
                                className="mt-1.5"
                                placeholder="0"
                                value={statsForm.likes}
                                onChange={(e) =>
                                    setStatsForm((f) => ({ ...f, likes: e.target.value }))
                                }
                            />
                        </div>
                        <div>
                            <Label htmlFor="stats-comments">
                                <span className="inline-flex items-center gap-1">
                                    <MessageCircle className="h-3.5 w-3.5" /> Comments
                                </span>
                            </Label>
                            <Input
                                id="stats-comments"
                                type="number"
                                min="0"
                                inputMode="numeric"
                                className="mt-1.5"
                                placeholder="0"
                                value={statsForm.comments}
                                onChange={(e) =>
                                    setStatsForm((f) => ({
                                        ...f,
                                        comments: e.target.value,
                                    }))
                                }
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setStatsOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleStatsSave}
                            disabled={statsMutation.isPending}
                        >
                            {statsMutation.isPending ? 'Saving...' : 'Save Stats'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
