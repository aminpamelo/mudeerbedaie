import { useEffect, useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    Search,
    Pencil,
    ExternalLink,
    Inbox,
    User as UserIcon,
    ChevronDown,
    ChevronRight,
    Plus,
    Calendar as CalendarIcon,
} from 'lucide-react';
import {
    fetchPlatforms,
    fetchPlatformPosts,
    updatePlatformPost,
} from '../lib/api';
import { cn } from '../lib/utils';
import { toastSuccess, toastError } from '../lib/toast';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';
import { Card } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Avatar, AvatarFallback, AvatarImage } from '../components/ui/avatar';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../components/ui/dialog';

// ─── Constants ──────────────────────────────────────────────────────────────

const STATUS_COLORS = {
    pending: 'bg-amber-100 text-amber-800',
    posted: 'bg-green-100 text-green-800',
    skipped: 'bg-zinc-100 text-zinc-700',
};

const STATUS_OPTIONS = [
    { value: 'pending', label: 'Pending' },
    { value: 'posted', label: 'Posted' },
    { value: 'skipped', label: 'Skipped' },
];

const CONTENT_FILTERS = [
    { value: 'all', label: 'All Content' },
    { value: 'has_pending', label: 'Has Pending' },
    { value: 'fully_posted', label: 'Fully Posted' },
    { value: 'has_skipped', label: 'Has Skipped' },
];

const ALL_FILTER = 'all';

// ─── Helpers ────────────────────────────────────────────────────────────────

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

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

function formatDate(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function toDatetimeLocalValue(dateString) {
    if (!dateString) return '';
    const d = new Date(dateString);
    if (Number.isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return (
        `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` +
        `T${pad(d.getHours())}:${pad(d.getMinutes())}`
    );
}

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function groupByContent(posts) {
    const map = new Map();
    for (const post of posts) {
        const key = post.content?.id ?? `orphan-${post.id}`;
        if (!map.has(key)) {
            map.set(key, { content: post.content, posts: [] });
        }
        map.get(key).posts.push(post);
    }
    return Array.from(map.values());
}

function progressOf(posts) {
    const total = posts.length;
    const posted = posts.filter((p) => p.status === 'posted').length;
    const skipped = posts.filter((p) => p.status === 'skipped').length;
    const pending = total - posted - skipped;
    const finished = posted + skipped;
    return { total, posted, skipped, pending, finished };
}

function latestActivityDate(posts) {
    let latest = null;
    for (const p of posts) {
        const d = p.updated_at || p.posted_at;
        if (!d) continue;
        const t = new Date(d).getTime();
        if (latest == null || t > latest) latest = t;
    }
    return latest ? new Date(latest).toISOString() : null;
}

// ─── Sub-components ─────────────────────────────────────────────────────────

function StatusBadge({ status }) {
    const colorClass = STATUS_COLORS[status] || 'bg-zinc-100 text-zinc-700';
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                colorClass
            )}
        >
            {capitalize(status)}
        </span>
    );
}

function AssigneeCell({ assignee }) {
    if (!assignee) {
        return <span className="text-xs text-zinc-400">Unassigned</span>;
    }
    return (
        <div className="flex items-center gap-2">
            <Avatar className="h-6 w-6">
                {assignee.profile_photo ? (
                    <AvatarImage src={assignee.profile_photo} alt={assignee.full_name} />
                ) : null}
                <AvatarFallback className="text-[10px]">
                    {getInitials(assignee.full_name)}
                </AvatarFallback>
            </Avatar>
            <span className="text-xs text-zinc-700 truncate max-w-[120px]">
                {assignee.full_name}
            </span>
        </div>
    );
}

function ProgressBar({ posted, skipped, total }) {
    if (total === 0) return null;
    const postedPct = (posted / total) * 100;
    const skippedPct = (skipped / total) * 100;
    return (
        <div className="flex h-1.5 w-24 overflow-hidden rounded-full bg-zinc-100">
            <div
                className="bg-green-500 transition-all"
                style={{ width: `${postedPct}%` }}
            />
            <div
                className="bg-zinc-400 transition-all"
                style={{ width: `${skippedPct}%` }}
            />
        </div>
    );
}

function PlatformRow({ post, onEdit, onInlineStatusChange, isUpdating }) {
    return (
        <div className="grid grid-cols-12 items-center gap-3 px-5 py-3 transition-colors hover:bg-zinc-50/60">
            {/* Platform - col 1-3 */}
            <div className="col-span-3 flex items-center gap-2">
                <div className="flex h-7 w-7 items-center justify-center rounded-md bg-zinc-100 text-[10px] font-bold uppercase text-zinc-600">
                    {(post.platform?.name || '?').charAt(0)}
                </div>
                <span className="text-sm font-medium text-zinc-800">
                    {post.platform?.name || '—'}
                </span>
            </div>

            {/* Status - col 4-5 */}
            <div className="col-span-2">
                <Select
                    value={post.status}
                    onValueChange={(v) => onInlineStatusChange(v)}
                    disabled={isUpdating}
                >
                    <SelectTrigger
                        className={cn(
                            'h-8 text-xs',
                            STATUS_COLORS[post.status],
                            'border-0'
                        )}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {STATUS_OPTIONS.map((s) => (
                            <SelectItem key={s.value} value={s.value}>
                                {s.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {/* URL - col 6-8 */}
            <div className="col-span-3 min-w-0">
                {post.post_url ? (
                    <a
                        href={post.post_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex max-w-full items-center gap-1 text-xs text-indigo-600 hover:underline"
                    >
                        <ExternalLink className="h-3 w-3 flex-shrink-0" />
                        <span className="truncate">{post.post_url}</span>
                    </a>
                ) : (
                    <button
                        type="button"
                        onClick={onEdit}
                        className="inline-flex items-center gap-1 text-xs text-zinc-500 hover:text-indigo-600"
                    >
                        <Plus className="h-3 w-3" />
                        Add URL
                    </button>
                )}
            </div>

            {/* Posted at - col 9-10 */}
            <div className="col-span-2 text-xs text-zinc-500 truncate">
                {post.posted_at ? formatDateTime(post.posted_at) : '—'}
            </div>

            {/* Assignee - col 11 */}
            <div className="col-span-1">
                <AssigneeCell assignee={post.assignee} />
            </div>

            {/* Edit - col 12 */}
            <div className="col-span-1 flex justify-end">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={onEdit}
                    className="h-7 w-7 p-0"
                    title="Edit"
                >
                    <Pencil className="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>
    );
}

function ContentGroupCard({
    group,
    expanded,
    onToggle,
    platformFilter,
    onEdit,
    onInlineStatusChange,
    pendingMutationId,
}) {
    const { content } = group;
    const sortedPosts = useMemo(
        () =>
            [...group.posts].sort(
                (a, b) =>
                    (a.platform?.sort_order ?? 0) - (b.platform?.sort_order ?? 0)
            ),
        [group.posts]
    );

    const visiblePosts = useMemo(() => {
        if (platformFilter === ALL_FILTER) return sortedPosts;
        return sortedPosts.filter(
            (p) => String(p.platform?.id) === String(platformFilter)
        );
    }, [sortedPosts, platformFilter]);

    const { total, posted, skipped, pending } = progressOf(sortedPosts);
    const allDone = pending === 0;

    if (visiblePosts.length === 0) return null;

    return (
        <Card className="overflow-hidden">
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-start gap-3 border-b border-zinc-100 bg-white px-5 py-4 text-left hover:bg-zinc-50/60 transition-colors"
            >
                <div className="mt-0.5 text-zinc-400">
                    {expanded ? (
                        <ChevronDown className="h-5 w-5" />
                    ) : (
                        <ChevronRight className="h-5 w-5" />
                    )}
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-3">
                        {content ? (
                            <Link
                                to={`/contents/${content.id}`}
                                onClick={(e) => e.stopPropagation()}
                                className="truncate font-semibold text-zinc-900 hover:text-indigo-600"
                            >
                                {content.title}
                            </Link>
                        ) : (
                            <span className="text-sm italic text-zinc-400">
                                Orphan post (content deleted)
                            </span>
                        )}
                        <Badge
                            className={cn(
                                'text-xs',
                                allDone
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-amber-100 text-amber-700'
                            )}
                        >
                            {posted}/{total} posted
                            {skipped > 0 && ` · ${skipped} skipped`}
                        </Badge>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-3 text-xs text-zinc-500">
                        {content?.tiktok_url && (
                            <a
                                href={content.tiktok_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                onClick={(e) => e.stopPropagation()}
                                className="inline-flex items-center gap-1 hover:text-indigo-600"
                            >
                                <ExternalLink className="h-3 w-3" />
                                TikTok original
                            </a>
                        )}
                        {latestActivityDate(sortedPosts) && (
                            <span className="inline-flex items-center gap-1">
                                <CalendarIcon className="h-3 w-3" />
                                Updated {formatDate(latestActivityDate(sortedPosts))}
                            </span>
                        )}
                    </div>
                </div>

                <div className="flex flex-shrink-0 items-center gap-3">
                    <ProgressBar posted={posted} skipped={skipped} total={total} />
                </div>
            </button>

            {expanded && (
                <div className="divide-y divide-zinc-100">
                    {visiblePosts.map((post) => (
                        <PlatformRow
                            key={post.id}
                            post={post}
                            onEdit={() => onEdit(post)}
                            onInlineStatusChange={(newStatus) =>
                                onInlineStatusChange(post, newStatus)
                            }
                            isUpdating={pendingMutationId === post.id}
                        />
                    ))}
                </div>
            )}
        </Card>
    );
}

function SkeletonGroups() {
    return (
        <div className="space-y-4">
            {Array.from({ length: 3 }).map((_, i) => (
                <Card key={i} className="overflow-hidden">
                    <div className="px-5 py-4">
                        <div className="h-4 w-64 animate-pulse rounded bg-zinc-200" />
                        <div className="mt-2 h-3 w-40 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="space-y-2 border-t border-zinc-100 px-5 py-3">
                        {Array.from({ length: 3 }).map((_, j) => (
                            <div
                                key={j}
                                className="h-8 animate-pulse rounded bg-zinc-100"
                            />
                        ))}
                    </div>
                </Card>
            ))}
        </div>
    );
}

function EmptyState() {
    return (
        <Card>
            <div className="flex flex-col items-center justify-center py-16 text-center">
                <Inbox className="mb-4 h-12 w-12 text-zinc-300" />
                <h3 className="text-lg font-semibold text-zinc-900">
                    No content to cross-post yet
                </h3>
                <p className="mt-1 text-sm text-zinc-500">
                    Mark a content to populate this queue.
                </p>
            </div>
        </Card>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────

export default function PlatformQueue() {
    const queryClient = useQueryClient();

    // Filters
    const [searchInput, setSearchInput] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [platformFilter, setPlatformFilter] = useState(ALL_FILTER);
    const [contentFilter, setContentFilter] = useState(ALL_FILTER);

    // Group expand/collapse — keyed by content id
    const [collapsedIds, setCollapsedIds] = useState(() => new Set());
    const [pendingMutationId, setPendingMutationId] = useState(null);

    // Edit modal
    const [editOpen, setEditOpen] = useState(false);
    const [editingPost, setEditingPost] = useState(null);
    const [editForm, setEditForm] = useState({
        status: 'pending',
        post_url: '',
        posted_at: '',
    });

    // Debounce search
    useEffect(() => {
        const handle = setTimeout(() => {
            setDebouncedSearch(searchInput);
        }, 300);
        return () => clearTimeout(handle);
    }, [searchInput]);

    // Build query params (only search hits the API; grouping/filters are client-side)
    const queryParams = useMemo(() => {
        const params = { per_page: 200 };
        if (debouncedSearch.trim()) params.search = debouncedSearch.trim();
        return params;
    }, [debouncedSearch]);

    const { data: platformsData } = useQuery({
        queryKey: ['cms', 'platforms'],
        queryFn: () => fetchPlatforms(),
    });
    const platforms = platformsData?.data || [];

    const { data: postsData, isLoading } = useQuery({
        queryKey: ['cms', 'platform-posts', queryParams],
        queryFn: () => fetchPlatformPosts(queryParams),
    });
    const posts = postsData?.data || [];

    // Group + content-level filter
    const groups = useMemo(() => {
        const grouped = groupByContent(posts);
        const filtered = grouped.filter((g) => {
            const { posted, skipped, pending, total } = progressOf(g.posts);
            if (contentFilter === 'has_pending') return pending > 0;
            if (contentFilter === 'fully_posted')
                return total > 0 && posted + skipped === total && posted > 0;
            if (contentFilter === 'has_skipped') return skipped > 0;
            return true;
        });
        // Sort: cards with pending first, then by latest activity desc
        return filtered.sort((a, b) => {
            const aProg = progressOf(a.posts);
            const bProg = progressOf(b.posts);
            if (aProg.pending > 0 && bProg.pending === 0) return -1;
            if (aProg.pending === 0 && bProg.pending > 0) return 1;
            const aDate = latestActivityDate(a.posts);
            const bDate = latestActivityDate(b.posts);
            return new Date(bDate || 0) - new Date(aDate || 0);
        });
    }, [posts, contentFilter]);

    // Mutations — share invalidation logic
    const sharedMutationConfig = {
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: ['cms', 'platform-posts'],
            });
        },
        onSettled: () => setPendingMutationId(null),
    };

    const inlineStatusMutation = useMutation({
        mutationFn: ({ id, status }) =>
            updatePlatformPost(id, { status }),
        ...sharedMutationConfig,
        onSuccess: (...args) => {
            sharedMutationConfig.onSuccess(...args);
            toastSuccess('Status updated');
        },
        onError: (error) => toastError(error, 'Failed to update status'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, payload }) => updatePlatformPost(id, payload),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: ['cms', 'platform-posts'],
            });
            setEditOpen(false);
            setEditingPost(null);
            toastSuccess('Platform post updated');
        },
        onError: (error) => toastError(error, 'Failed to update platform post'),
    });

    function toggleGroup(contentId) {
        setCollapsedIds((prev) => {
            const next = new Set(prev);
            if (next.has(contentId)) next.delete(contentId);
            else next.add(contentId);
            return next;
        });
    }

    function setAllExpanded(expand) {
        if (expand) {
            setCollapsedIds(new Set());
        } else {
            setCollapsedIds(
                new Set(
                    groups
                        .map((g) => g.content?.id)
                        .filter((id) => id != null)
                )
            );
        }
    }

    function handleInlineStatusChange(post, newStatus) {
        if (post.status === newStatus) return;
        setPendingMutationId(post.id);
        inlineStatusMutation.mutate({ id: post.id, status: newStatus });
    }

    function openEdit(post) {
        setEditingPost(post);
        setEditForm({
            status: post.status || 'pending',
            post_url: post.post_url || '',
            posted_at: toDatetimeLocalValue(post.posted_at),
        });
        setEditOpen(true);
    }

    function handleSave() {
        if (!editingPost) return;
        const payload = {
            status: editForm.status,
            post_url: editForm.post_url || null,
            posted_at: editForm.posted_at
                ? new Date(editForm.posted_at).toISOString()
                : null,
        };
        updateMutation.mutate({ id: editingPost.id, payload });
    }

    return (
        <div>
            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-zinc-900">Cross-Post Queue</h1>
                <p className="mt-1 text-sm text-zinc-500">
                    Each marked content shows its progress across every platform. Update
                    statuses inline, or click Edit for full details.
                </p>
            </div>

            {/* Filter row */}
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <div className="relative min-w-[260px] flex-1">
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
                    <SelectTrigger className="w-[180px]">
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
                <Select value={contentFilter} onValueChange={setContentFilter}>
                    <SelectTrigger className="w-[180px]">
                        <SelectValue placeholder="All Content" />
                    </SelectTrigger>
                    <SelectContent>
                        {CONTENT_FILTERS.map((f) => (
                            <SelectItem key={f.value} value={f.value}>
                                {f.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <div className="ml-auto flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setAllExpanded(true)}
                        className="text-xs"
                    >
                        Expand all
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setAllExpanded(false)}
                        className="text-xs"
                    >
                        Collapse all
                    </Button>
                </div>
            </div>

            {/* Summary strip */}
            {!isLoading && groups.length > 0 && (
                <div className="mb-4 flex flex-wrap gap-4 text-xs text-zinc-500">
                    <span>
                        <span className="font-semibold text-zinc-700">
                            {groups.length}
                        </span>{' '}
                        content{groups.length === 1 ? '' : 's'}
                    </span>
                    <span>
                        <span className="font-semibold text-zinc-700">
                            {groups.reduce(
                                (sum, g) => sum + progressOf(g.posts).pending,
                                0
                            )}
                        </span>{' '}
                        pending
                    </span>
                    <span>
                        <span className="font-semibold text-zinc-700">
                            {groups.reduce(
                                (sum, g) => sum + progressOf(g.posts).posted,
                                0
                            )}
                        </span>{' '}
                        posted
                    </span>
                    <span>
                        <span className="font-semibold text-zinc-700">
                            {groups.reduce(
                                (sum, g) => sum + progressOf(g.posts).skipped,
                                0
                            )}
                        </span>{' '}
                        skipped
                    </span>
                </div>
            )}

            {/* Groups */}
            {isLoading ? (
                <SkeletonGroups />
            ) : groups.length === 0 ? (
                <EmptyState />
            ) : (
                <div className="space-y-4">
                    {groups.map((group) => {
                        const cid = group.content?.id;
                        const expanded = cid != null ? !collapsedIds.has(cid) : true;
                        return (
                            <ContentGroupCard
                                key={cid ?? `orphan-${group.posts[0]?.id}`}
                                group={group}
                                expanded={expanded}
                                onToggle={() => cid != null && toggleGroup(cid)}
                                platformFilter={platformFilter}
                                onEdit={openEdit}
                                onInlineStatusChange={handleInlineStatusChange}
                                pendingMutationId={pendingMutationId}
                            />
                        );
                    })}
                </div>
            )}

            {/* Edit Modal */}
            <Dialog
                open={editOpen}
                onOpenChange={(open) => {
                    setEditOpen(open);
                    if (!open) setEditingPost(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Platform Post</DialogTitle>
                        <DialogDescription>
                            {editingPost?.content?.title
                                ? `Update ${editingPost.platform?.name || 'platform'} details for "${editingPost.content.title}".`
                                : 'Update the status and post URL for this platform post.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div>
                            <Label htmlFor="edit-status">Status</Label>
                            <Select
                                value={editForm.status}
                                onValueChange={(v) =>
                                    setEditForm((f) => ({ ...f, status: v }))
                                }
                            >
                                <SelectTrigger id="edit-status" className="mt-1.5">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {STATUS_OPTIONS.map((s) => (
                                        <SelectItem key={s.value} value={s.value}>
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="edit-post-url">Post URL</Label>
                            <Input
                                id="edit-post-url"
                                type="url"
                                className="mt-1.5"
                                placeholder="https://..."
                                value={editForm.post_url}
                                onChange={(e) =>
                                    setEditForm((f) => ({ ...f, post_url: e.target.value }))
                                }
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-posted-at">Posted At</Label>
                            <Input
                                id="edit-posted-at"
                                type="datetime-local"
                                className="mt-1.5"
                                value={editForm.posted_at}
                                onChange={(e) =>
                                    setEditForm((f) => ({ ...f, posted_at: e.target.value }))
                                }
                            />
                        </div>
                        {editingPost?.assignee && (
                            <div className="flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-600">
                                <UserIcon className="h-3.5 w-3.5" />
                                <span>
                                    Currently assigned to{' '}
                                    <span className="font-medium text-zinc-800">
                                        {editingPost.assignee.full_name}
                                    </span>
                                </span>
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSave}
                            disabled={updateMutation.isPending}
                        >
                            {updateMutation.isPending ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
