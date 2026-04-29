import { useEffect, useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    Search,
    Pencil,
    ExternalLink,
    Inbox,
    User as UserIcon,
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
                    <div className="h-5 w-20 animate-pulse rounded-full bg-zinc-200" />
                    <div className="flex gap-2">
                        <div className="h-8 w-8 animate-pulse rounded bg-zinc-200" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <Inbox className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">
                No platform posts yet
            </h3>
            <p className="mt-1 text-sm text-zinc-500">
                Mark a content to populate this queue.
            </p>
        </div>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────

export default function PlatformQueue() {
    const queryClient = useQueryClient();

    // Filter state
    const [searchInput, setSearchInput] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [platformFilter, setPlatformFilter] = useState(ALL_FILTER);
    const [statusFilter, setStatusFilter] = useState(ALL_FILTER);

    // Edit modal state
    const [editOpen, setEditOpen] = useState(false);
    const [editingPost, setEditingPost] = useState(null);
    const [editForm, setEditForm] = useState({
        status: 'pending',
        post_url: '',
        posted_at: '',
    });

    // Debounce search input (300ms)
    useEffect(() => {
        const handle = setTimeout(() => {
            setDebouncedSearch(searchInput);
        }, 300);
        return () => clearTimeout(handle);
    }, [searchInput]);

    // Build query params (omit `all` sentinel)
    const queryParams = useMemo(() => {
        const params = {};
        if (debouncedSearch.trim()) params.search = debouncedSearch.trim();
        if (platformFilter !== ALL_FILTER) params.platform_id = platformFilter;
        if (statusFilter !== ALL_FILTER) params.status = statusFilter;
        return params;
    }, [debouncedSearch, platformFilter, statusFilter]);

    // Platforms (for filter select)
    const { data: platformsData } = useQuery({
        queryKey: ['cms', 'platforms'],
        queryFn: () => fetchPlatforms(),
    });
    const platforms = platformsData?.data || [];

    // Platform posts
    const { data: postsData, isLoading } = useQuery({
        queryKey: ['cms', 'platform-posts', queryParams],
        queryFn: () => fetchPlatformPosts(queryParams),
    });
    const posts = postsData?.data || [];

    // Update mutation
    const updateMutation = useMutation({
        mutationFn: ({ id, payload }) => updatePlatformPost(id, payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'platform-posts'] });
            setEditOpen(false);
            setEditingPost(null);
            toastSuccess('Platform post updated');
        },
        onError: (error) => toastError(error, 'Failed to update platform post'),
    });

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
                    Track every platform post generated from marked content. Filter by
                    platform or status, and update post URLs as they go live.
                </p>
            </div>

            {/* Filter row */}
            <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
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
                <Select value={statusFilter} onValueChange={setStatusFilter}>
                    <SelectTrigger>
                        <SelectValue placeholder="All Statuses" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL_FILTER}>All Statuses</SelectItem>
                        {STATUS_OPTIONS.map((s) => (
                            <SelectItem key={s.value} value={s.value}>
                                {s.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {/* Table */}
            <Card>
                {isLoading ? (
                    <SkeletonTable />
                ) : posts.length === 0 ? (
                    <EmptyState />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Content</TableHead>
                                <TableHead>Platform</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Assignee</TableHead>
                                <TableHead>Posted At</TableHead>
                                <TableHead>Post URL</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {posts.map((post) => (
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
                                        <StatusBadge status={post.status} />
                                    </TableCell>
                                    <TableCell>
                                        <AssigneeCell assignee={post.assignee} />
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
                                    <TableCell className="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => openEdit(post)}
                                            className="text-xs"
                                        >
                                            <Pencil className="mr-1 h-3 w-3" />
                                            Edit
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

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
                                ? `Update status and post URL for "${editingPost.content.title}".`
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
                        {/* Assignee field intentionally omitted in v1 — use bulk-assign UI elsewhere. */}
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
