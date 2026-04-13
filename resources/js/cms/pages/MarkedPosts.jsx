import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import {
    Flag,
    Megaphone,
    Eye,
    ToggleLeft,
    ToggleRight,
    Plus,
    ExternalLink,
    FileText,
} from 'lucide-react';
import { fetchContents, markContentForAds, createAdCampaign } from '../lib/api';
import { cn } from '../lib/utils';
import { toastSuccess, toastError } from '../lib/toast';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';
import { Card, CardContent } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Textarea } from '../components/ui/textarea';
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

const STAGE_COLORS = {
    idea: 'bg-blue-100 text-blue-700',
    shooting: 'bg-purple-100 text-purple-700',
    editing: 'bg-amber-100 text-amber-700',
    posting: 'bg-emerald-100 text-emerald-700',
    posted: 'bg-green-100 text-green-700',
};

const TAB_OPTIONS = [
    { value: 'all', label: 'All' },
    { value: 'flagged', label: 'Flagged' },
    { value: 'marked', label: 'Marked' },
];

// ─── Helpers ────────────────────────────────────────────────────────────────

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatNumber(num) {
    if (!num) return '0';
    return Number(num).toLocaleString();
}

// ─── Sub-components ─────────────────────────────────────────────────────────

function StageBadge({ stage }) {
    const colorClass = STAGE_COLORS[stage] || 'bg-zinc-100 text-zinc-700';
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                colorClass
            )}
        >
            {capitalize(stage)}
        </span>
    );
}

function StatusBadges({ content }) {
    return (
        <div className="flex items-center gap-1.5">
            {content.is_flagged_for_ads && (
                <Badge className="bg-orange-100 text-orange-800 text-[10px]">
                    <Flag className="mr-0.5 h-3 w-3" />
                    Flagged
                </Badge>
            )}
            {content.is_marked_for_ads && (
                <Badge className="bg-indigo-100 text-indigo-800 text-[10px]">
                    <Megaphone className="mr-0.5 h-3 w-3" />
                    Marked
                </Badge>
            )}
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
                        <div className="h-8 w-8 animate-pulse rounded bg-zinc-200" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function EmptyState({ filter }) {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <FileText className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">
                No {filter !== 'all' ? filter : ''} posts found
            </h3>
            <p className="mt-1 text-sm text-zinc-500">
                Content items that are flagged or marked for ads will appear here.
            </p>
        </div>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────

export default function MarkedPosts() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [filter, setFilter] = useState('all');
    const [campaignOpen, setCampaignOpen] = useState(false);
    const [selectedContentId, setSelectedContentId] = useState(null);
    const [campaignForm, setCampaignForm] = useState({
        platform: 'tiktok',
        budget: '',
        start_date: '',
        end_date: '',
        notes: '',
    });

    const { data, isLoading } = useQuery({
        queryKey: ['cms', 'marked-posts'],
        queryFn: () => fetchContents({ per_page: 100 }),
    });

    const markMutation = useMutation({
        mutationFn: (id) => markContentForAds(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'marked-posts'] });
            queryClient.invalidateQueries({ queryKey: ['cms', 'contents'] });
            toastSuccess('Mark status updated');
        },
        onError: (error) => toastError(error, 'Failed to update mark status'),
    });

    const createCampaignMutation = useMutation({
        mutationFn: (formData) => createAdCampaign(formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'ad-campaigns'] });
            setCampaignOpen(false);
            resetCampaignForm();
            toastSuccess('Ad campaign created');
            navigate('/ads');
        },
        onError: (error) => toastError(error, 'Failed to create ad campaign'),
    });

    function resetCampaignForm() {
        setCampaignForm({
            platform: 'tiktok',
            budget: '',
            start_date: '',
            end_date: '',
            notes: '',
        });
        setSelectedContentId(null);
    }

    function openCreateCampaign(contentId) {
        setSelectedContentId(contentId);
        setCampaignOpen(true);
    }

    function handleCreateCampaign() {
        createCampaignMutation.mutate({
            content_id: selectedContentId,
            platform: campaignForm.platform,
            budget: parseFloat(campaignForm.budget) || 0,
            start_date: campaignForm.start_date || undefined,
            end_date: campaignForm.end_date || undefined,
            notes: campaignForm.notes || undefined,
        });
    }

    // Filter contents client-side
    const allContents = data?.data || [];
    const markedContents = allContents.filter((c) => {
        if (filter === 'flagged') return c.is_flagged_for_ads && !c.is_marked_for_ads;
        if (filter === 'marked') return c.is_marked_for_ads;
        return c.is_flagged_for_ads || c.is_marked_for_ads;
    });

    function getLatestStats(content) {
        const stats = content.stats || content.tiktok_stats || [];
        if (stats.length === 0) return null;
        return stats[stats.length - 1];
    }

    return (
        <div>
            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-zinc-900">Marked Posts</h1>
                <p className="mt-1 text-sm text-zinc-500">
                    Content items flagged by the system or manually marked for ad campaigns.
                </p>
            </div>

            {/* Tab Filters */}
            <div className="mb-6 flex items-center gap-2">
                {TAB_OPTIONS.map((tab) => (
                    <Button
                        key={tab.value}
                        variant={filter === tab.value ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter(tab.value)}
                    >
                        {tab.label}
                        {filter === tab.value && (
                            <span className="ml-1.5 rounded-full bg-white/20 px-1.5 py-0.5 text-[10px] font-bold">
                                {allContents.filter((c) => {
                                    if (tab.value === 'flagged') return c.is_flagged_for_ads && !c.is_marked_for_ads;
                                    if (tab.value === 'marked') return c.is_marked_for_ads;
                                    return c.is_flagged_for_ads || c.is_marked_for_ads;
                                }).length}
                            </span>
                        )}
                    </Button>
                ))}
            </div>

            {/* Table */}
            <Card>
                {isLoading ? (
                    <SkeletonTable />
                ) : markedContents.length === 0 ? (
                    <EmptyState filter={filter} />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Title</TableHead>
                                <TableHead>Stage</TableHead>
                                <TableHead>TikTok URL</TableHead>
                                <TableHead>Stats</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {markedContents.map((content) => {
                                const latestStats = getLatestStats(content);
                                return (
                                    <TableRow key={content.id}>
                                        <TableCell>
                                            <p className="font-medium text-zinc-900 truncate max-w-[200px]">
                                                {content.title}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <StageBadge stage={content.stage || content.current_stage} />
                                        </TableCell>
                                        <TableCell>
                                            {content.tiktok_url ? (
                                                <a
                                                    href={content.tiktok_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1 text-sm text-indigo-600 hover:underline"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <ExternalLink className="h-3 w-3" />
                                                    View
                                                </a>
                                            ) : (
                                                <span className="text-sm text-zinc-400">-</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {latestStats ? (
                                                <div className="text-xs text-zinc-600">
                                                    <span>{formatNumber(latestStats.views)} views</span>
                                                    <span className="mx-1 text-zinc-300">|</span>
                                                    <span>{formatNumber(latestStats.likes)} likes</span>
                                                </div>
                                            ) : (
                                                <span className="text-sm text-zinc-400">-</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadges content={content} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {content.is_marked_for_ads && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => openCreateCampaign(content.id)}
                                                        className="text-xs"
                                                    >
                                                        <Plus className="mr-1 h-3 w-3" />
                                                        Campaign
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                    className="h-8 w-8"
                                                >
                                                    <Link to={`/contents/${content.id}`}>
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8"
                                                    onClick={() => markMutation.mutate(content.id)}
                                                    disabled={markMutation.isPending}
                                                    title={content.is_marked_for_ads ? 'Unmark for ads' : 'Mark for ads'}
                                                >
                                                    {content.is_marked_for_ads ? (
                                                        <ToggleRight className="h-4 w-4 text-indigo-600" />
                                                    ) : (
                                                        <ToggleLeft className="h-4 w-4" />
                                                    )}
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}
            </Card>

            {/* Create Campaign Dialog */}
            <Dialog open={campaignOpen} onOpenChange={(open) => {
                setCampaignOpen(open);
                if (!open) resetCampaignForm();
            }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Ad Campaign</DialogTitle>
                        <DialogDescription>
                            Set up a new ad campaign for the selected content.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div>
                            <Label htmlFor="campaign-platform">Platform</Label>
                            <Select
                                value={campaignForm.platform}
                                onValueChange={(v) =>
                                    setCampaignForm((f) => ({ ...f, platform: v }))
                                }
                            >
                                <SelectTrigger className="mt-1.5">
                                    <SelectValue placeholder="Select platform" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="facebook">Facebook</SelectItem>
                                    <SelectItem value="tiktok">TikTok</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="campaign-budget">Budget (RM)</Label>
                            <Input
                                id="campaign-budget"
                                type="number"
                                min="0"
                                step="0.01"
                                className="mt-1.5"
                                value={campaignForm.budget}
                                onChange={(e) =>
                                    setCampaignForm((f) => ({ ...f, budget: e.target.value }))
                                }
                                placeholder="0.00"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="campaign-start">Start Date</Label>
                                <Input
                                    id="campaign-start"
                                    type="date"
                                    className="mt-1.5"
                                    value={campaignForm.start_date}
                                    onChange={(e) =>
                                        setCampaignForm((f) => ({ ...f, start_date: e.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <Label htmlFor="campaign-end">End Date</Label>
                                <Input
                                    id="campaign-end"
                                    type="date"
                                    className="mt-1.5"
                                    value={campaignForm.end_date}
                                    onChange={(e) =>
                                        setCampaignForm((f) => ({ ...f, end_date: e.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div>
                            <Label htmlFor="campaign-notes">Notes</Label>
                            <Textarea
                                id="campaign-notes"
                                className="mt-1.5"
                                rows={3}
                                value={campaignForm.notes}
                                onChange={(e) =>
                                    setCampaignForm((f) => ({ ...f, notes: e.target.value }))
                                }
                                placeholder="Campaign notes..."
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCampaignOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleCreateCampaign}
                            disabled={createCampaignMutation.isPending || !campaignForm.budget}
                        >
                            {createCampaignMutation.isPending ? 'Creating...' : 'Create Campaign'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
