import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ArrowLeft,
    Pencil,
    Plus,
    ExternalLink,
    BarChart3,
    Eye,
    Heart,
    MousePointerClick,
    DollarSign,
    Target,
    TrendingUp,
} from 'lucide-react';
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
} from 'recharts';
import { fetchAdCampaign, updateAdCampaign, addAdStats } from '../lib/api';
import { cn } from '../lib/utils';
import { toastSuccess, toastError } from '../lib/toast';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
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
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../components/ui/dialog';

// ─── Constants ──────────────────────────────────────────────────────────────

const STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    running: 'bg-green-100 text-green-800',
    paused: 'bg-zinc-100 text-zinc-700',
    completed: 'bg-blue-100 text-blue-800',
};

const PLATFORM_COLORS = {
    facebook: 'bg-blue-100 text-blue-800',
    tiktok: 'bg-zinc-900 text-white',
};

const STAGE_COLORS = {
    idea: 'bg-blue-100 text-blue-700',
    shooting: 'bg-purple-100 text-purple-700',
    editing: 'bg-amber-100 text-amber-700',
    posting: 'bg-emerald-100 text-emerald-700',
    posted: 'bg-green-100 text-green-700',
};

const LINE_COLORS = {
    impressions: '#6366f1',
    clicks: '#f59e0b',
    spend: '#10b981',
    conversions: '#a855f7',
};

// ─── Helpers ────────────────────────────────────────────────────────────────

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatShortDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
    });
}

function formatCurrency(amount) {
    if (amount === null || amount === undefined) return '-';
    return `RM ${Number(amount).toLocaleString('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function formatNumber(num) {
    if (!num) return '0';
    return Number(num).toLocaleString();
}

function calculateCTR(clicks, impressions) {
    if (!impressions || impressions === 0) return '0.00';
    return ((clicks / impressions) * 100).toFixed(2);
}

// ─── Main Component ─────────────────────────────────────────────────────────

export default function AdCampaignDetail() {
    const { id } = useParams();
    const queryClient = useQueryClient();

    const [editOpen, setEditOpen] = useState(false);
    const [addStatsOpen, setAddStatsOpen] = useState(false);

    const [editForm, setEditForm] = useState({
        status: '',
        budget: '',
        start_date: '',
        end_date: '',
        notes: '',
        ad_id: '',
    });

    const [statsForm, setStatsForm] = useState({
        impressions: '',
        clicks: '',
        spend: '',
        conversions: '',
    });

    const { data: campaign, isLoading } = useQuery({
        queryKey: ['cms', 'ad-campaign', id],
        queryFn: () => fetchAdCampaign(id),
    });

    const updateMutation = useMutation({
        mutationFn: (formData) => updateAdCampaign(id, formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'ad-campaign', id] });
            queryClient.invalidateQueries({ queryKey: ['cms', 'ad-campaigns'] });
            setEditOpen(false);
            toastSuccess('Campaign updated');
        },
        onError: (error) => toastError(error, 'Failed to update campaign'),
    });

    const addStatsMutation = useMutation({
        mutationFn: (formData) => addAdStats(id, formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'ad-campaign', id] });
            setAddStatsOpen(false);
            setStatsForm({ impressions: '', clicks: '', spend: '', conversions: '' });
            toastSuccess('Stats added');
        },
        onError: (error) => toastError(error, 'Failed to add stats'),
    });

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-24">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent" />
            </div>
        );
    }

    if (!campaign) {
        return (
            <div className="py-24 text-center">
                <p className="text-slate-500">Campaign not found.</p>
                <Link to="/ads" className="mt-4 inline-block text-sm text-indigo-600 hover:underline">
                    Back to Campaigns
                </Link>
            </div>
        );
    }

    const data = campaign.data || campaign;
    const content = data.content || {};
    const adStats = data.stats || data.ad_stats || [];
    const latestAdStats = adStats.length > 0 ? adStats[adStats.length - 1] : null;
    const contentStats = content.stats || content.tiktok_stats || [];
    const latestContentStats = contentStats.length > 0 ? contentStats[contentStats.length - 1] : null;

    function openEditDialog() {
        setEditForm({
            status: data.status || 'pending',
            budget: data.budget || '',
            start_date: data.start_date ? data.start_date.split('T')[0] : '',
            end_date: data.end_date ? data.end_date.split('T')[0] : '',
            notes: data.notes || '',
            ad_id: data.ad_id || '',
        });
        setEditOpen(true);
    }

    function handleUpdate() {
        updateMutation.mutate({
            status: editForm.status,
            budget: parseFloat(editForm.budget) || 0,
            start_date: editForm.start_date || undefined,
            end_date: editForm.end_date || undefined,
            notes: editForm.notes || undefined,
            ad_id: editForm.ad_id || undefined,
        });
    }

    function handleAddStats() {
        addStatsMutation.mutate({
            impressions: parseInt(statsForm.impressions, 10) || 0,
            clicks: parseInt(statsForm.clicks, 10) || 0,
            spend: parseFloat(statsForm.spend) || 0,
            conversions: parseInt(statsForm.conversions, 10) || 0,
        });
    }

    const chartData = adStats.map((s) => ({
        date: formatShortDate(s.recorded_at || s.created_at),
        impressions: s.impressions || 0,
        clicks: s.clicks || 0,
        spend: s.spend || 0,
        conversions: s.conversions || 0,
    }));

    return (
        <div className="space-y-6">
            {/* Header */}
            <div>
                <Link
                    to="/ads"
                    className="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Campaigns
                </Link>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-bold text-slate-800">
                            {content.title || `Campaign #${id}`} - {capitalize(data.platform)}
                        </h1>
                        <div className="flex items-center gap-2">
                            <span
                                className={cn(
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    PLATFORM_COLORS[data.platform] || 'bg-zinc-100 text-zinc-700'
                                )}
                            >
                                {capitalize(data.platform)}
                            </span>
                            <span
                                className={cn(
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    STATUS_COLORS[data.status] || 'bg-zinc-100 text-zinc-700'
                                )}
                            >
                                {capitalize(data.status)}
                            </span>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        <Button variant="outline" size="sm" onClick={openEditDialog}>
                            <Pencil className="mr-1.5 h-3.5 w-3.5" />
                            Edit Campaign
                        </Button>
                        <Button size="sm" onClick={() => setAddStatsOpen(true)}>
                            <Plus className="mr-1.5 h-3.5 w-3.5" />
                            Add Stats
                        </Button>
                    </div>
                </div>
            </div>

            {/* Info Card */}
            <Card>
                <CardHeader>
                    <CardTitle>Campaign Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p className="text-xs font-medium text-slate-500">Budget</p>
                            <p className="mt-1 text-lg font-semibold text-slate-800">
                                {formatCurrency(data.budget)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-medium text-slate-500">Date Range</p>
                            <p className="mt-1 text-sm text-slate-800">
                                {formatDate(data.start_date)} - {formatDate(data.end_date)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-medium text-slate-500">Assigned By</p>
                            <p className="mt-1 text-sm text-slate-800">
                                {data.assigned_by?.name || data.assigned_by_name || '-'}
                            </p>
                        </div>
                        {data.ad_id && (
                            <div>
                                <p className="text-xs font-medium text-slate-500">Ad ID</p>
                                <p className="mt-1 text-sm font-mono text-slate-800">
                                    {data.ad_id}
                                </p>
                            </div>
                        )}
                    </div>
                    {data.notes && (
                        <div className="mt-4 rounded-lg bg-slate-50 p-3">
                            <p className="text-xs font-medium text-slate-500 mb-1">Notes</p>
                            <p className="text-sm text-slate-700 whitespace-pre-wrap">
                                {data.notes}
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Linked Content Card */}
            <Card>
                <CardHeader>
                    <CardTitle>Linked Content</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="space-y-2">
                            <Link
                                to={`/contents/${content.id || data.content_id}`}
                                className="text-base font-semibold text-indigo-600 hover:underline"
                            >
                                {content.title || `Content #${data.content_id}`}
                            </Link>
                            <div className="flex items-center gap-2">
                                {(content.stage || content.current_stage) && (
                                    <span
                                        className={cn(
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                            STAGE_COLORS[content.stage || content.current_stage] || 'bg-zinc-100 text-zinc-700'
                                        )}
                                    >
                                        {capitalize(content.stage || content.current_stage)}
                                    </span>
                                )}
                                {content.tiktok_url && (
                                    <a
                                        href={content.tiktok_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline"
                                    >
                                        <ExternalLink className="h-3 w-3" />
                                        TikTok
                                    </a>
                                )}
                            </div>
                        </div>
                        {latestContentStats && (
                            <div className="flex items-center gap-4 text-sm text-slate-600">
                                <span className="flex items-center gap-1">
                                    <Eye className="h-3.5 w-3.5" />
                                    {formatNumber(latestContentStats.views)} views
                                </span>
                                <span className="flex items-center gap-1">
                                    <Heart className="h-3.5 w-3.5" />
                                    {formatNumber(latestContentStats.likes)} likes
                                </span>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Ad Performance Section */}
            <div>
                <h2 className="mb-4 text-lg font-semibold text-slate-800">Ad Performance</h2>

                {adStats.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <BarChart3 className="mb-3 h-10 w-10 text-slate-300" />
                            <p className="text-sm text-slate-500">No performance data yet</p>
                            <Button
                                variant="outline"
                                size="sm"
                                className="mt-4"
                                onClick={() => setAddStatsOpen(true)}
                            >
                                <Plus className="mr-1.5 h-3.5 w-3.5" />
                                Add Stats
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {/* Metric Cards */}
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                            <div className="flex items-center gap-3 rounded-lg border border-slate-200/80 bg-white p-3">
                                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50">
                                    <Eye className="h-4 w-4 text-indigo-600" />
                                </div>
                                <div>
                                    <p className="text-lg font-semibold text-slate-800">
                                        {formatNumber(latestAdStats?.impressions)}
                                    </p>
                                    <p className="text-xs text-slate-500">Impressions</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-slate-200/80 bg-white p-3">
                                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50">
                                    <MousePointerClick className="h-4 w-4 text-amber-600" />
                                </div>
                                <div>
                                    <p className="text-lg font-semibold text-slate-800">
                                        {formatNumber(latestAdStats?.clicks)}
                                    </p>
                                    <p className="text-xs text-slate-500">Clicks</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-slate-200/80 bg-white p-3">
                                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50">
                                    <TrendingUp className="h-4 w-4 text-emerald-600" />
                                </div>
                                <div>
                                    <p className="text-lg font-semibold text-slate-800">
                                        {calculateCTR(latestAdStats?.clicks, latestAdStats?.impressions)}%
                                    </p>
                                    <p className="text-xs text-slate-500">CTR</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-slate-200/80 bg-white p-3">
                                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50">
                                    <DollarSign className="h-4 w-4 text-rose-600" />
                                </div>
                                <div>
                                    <p className="text-lg font-semibold text-slate-800">
                                        {formatCurrency(latestAdStats?.spend)}
                                    </p>
                                    <p className="text-xs text-slate-500">Spend</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-slate-200/80 bg-white p-3">
                                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-purple-50">
                                    <Target className="h-4 w-4 text-purple-600" />
                                </div>
                                <div>
                                    <p className="text-lg font-semibold text-slate-800">
                                        {formatNumber(latestAdStats?.conversions)}
                                    </p>
                                    <p className="text-xs text-slate-500">Conversions</p>
                                </div>
                            </div>
                        </div>

                        {/* Trend Chart */}
                        {adStats.length > 1 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm">Performance Trends</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="h-64">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <LineChart data={chartData}>
                                                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                                                <XAxis
                                                    dataKey="date"
                                                    tick={{ fontSize: 11, fill: '#94a3b8' }}
                                                    tickLine={false}
                                                    axisLine={{ stroke: '#e2e8f0' }}
                                                />
                                                <YAxis
                                                    tick={{ fontSize: 11, fill: '#94a3b8' }}
                                                    tickLine={false}
                                                    axisLine={{ stroke: '#e2e8f0' }}
                                                    tickFormatter={(v) =>
                                                        v >= 1000 ? `${(v / 1000).toFixed(1)}k` : v
                                                    }
                                                />
                                                <Tooltip
                                                    contentStyle={{
                                                        borderRadius: '8px',
                                                        border: '1px solid #e2e8f0',
                                                        boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)',
                                                    }}
                                                    formatter={(value, name) => [
                                                        name === 'Spend'
                                                            ? formatCurrency(value)
                                                            : formatNumber(value),
                                                        name,
                                                    ]}
                                                />
                                                <Legend />
                                                <Line
                                                    type="monotone"
                                                    dataKey="impressions"
                                                    name="Impressions"
                                                    stroke={LINE_COLORS.impressions}
                                                    strokeWidth={2}
                                                    dot={{ r: 3 }}
                                                    activeDot={{ r: 5 }}
                                                />
                                                <Line
                                                    type="monotone"
                                                    dataKey="clicks"
                                                    name="Clicks"
                                                    stroke={LINE_COLORS.clicks}
                                                    strokeWidth={2}
                                                    dot={{ r: 3 }}
                                                    activeDot={{ r: 5 }}
                                                />
                                            </LineChart>
                                        </ResponsiveContainer>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </div>

            {/* Edit Campaign Dialog */}
            <Dialog open={editOpen} onOpenChange={setEditOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Campaign</DialogTitle>
                        <DialogDescription>
                            Update the campaign details below.
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
                                <SelectTrigger className="mt-1.5">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pending">Pending</SelectItem>
                                    <SelectItem value="running">Running</SelectItem>
                                    <SelectItem value="paused">Paused</SelectItem>
                                    <SelectItem value="completed">Completed</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="edit-budget">Budget (RM)</Label>
                            <Input
                                id="edit-budget"
                                type="number"
                                min="0"
                                step="0.01"
                                className="mt-1.5"
                                value={editForm.budget}
                                onChange={(e) =>
                                    setEditForm((f) => ({ ...f, budget: e.target.value }))
                                }
                                placeholder="0.00"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="edit-start">Start Date</Label>
                                <Input
                                    id="edit-start"
                                    type="date"
                                    className="mt-1.5"
                                    value={editForm.start_date}
                                    onChange={(e) =>
                                        setEditForm((f) => ({ ...f, start_date: e.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <Label htmlFor="edit-end">End Date</Label>
                                <Input
                                    id="edit-end"
                                    type="date"
                                    className="mt-1.5"
                                    value={editForm.end_date}
                                    onChange={(e) =>
                                        setEditForm((f) => ({ ...f, end_date: e.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div>
                            <Label htmlFor="edit-ad-id">Ad ID (Platform)</Label>
                            <Input
                                id="edit-ad-id"
                                type="text"
                                className="mt-1.5"
                                value={editForm.ad_id}
                                onChange={(e) =>
                                    setEditForm((f) => ({ ...f, ad_id: e.target.value }))
                                }
                                placeholder="Platform ad identifier"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-notes">Notes</Label>
                            <Textarea
                                id="edit-notes"
                                className="mt-1.5"
                                rows={3}
                                value={editForm.notes}
                                onChange={(e) =>
                                    setEditForm((f) => ({ ...f, notes: e.target.value }))
                                }
                                placeholder="Campaign notes..."
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleUpdate}
                            disabled={updateMutation.isPending}
                        >
                            {updateMutation.isPending ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Add Stats Dialog */}
            <Dialog open={addStatsOpen} onOpenChange={setAddStatsOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Ad Performance Stats</DialogTitle>
                        <DialogDescription>
                            Enter the latest ad performance metrics.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid grid-cols-2 gap-4 py-4">
                        <div>
                            <Label htmlFor="ad-impressions">Impressions</Label>
                            <Input
                                id="ad-impressions"
                                type="number"
                                min="0"
                                className="mt-1.5"
                                value={statsForm.impressions}
                                onChange={(e) =>
                                    setStatsForm((f) => ({ ...f, impressions: e.target.value }))
                                }
                                placeholder="0"
                            />
                        </div>
                        <div>
                            <Label htmlFor="ad-clicks">Clicks</Label>
                            <Input
                                id="ad-clicks"
                                type="number"
                                min="0"
                                className="mt-1.5"
                                value={statsForm.clicks}
                                onChange={(e) =>
                                    setStatsForm((f) => ({ ...f, clicks: e.target.value }))
                                }
                                placeholder="0"
                            />
                        </div>
                        <div>
                            <Label htmlFor="ad-spend">Spend (RM)</Label>
                            <Input
                                id="ad-spend"
                                type="number"
                                min="0"
                                step="0.01"
                                className="mt-1.5"
                                value={statsForm.spend}
                                onChange={(e) =>
                                    setStatsForm((f) => ({ ...f, spend: e.target.value }))
                                }
                                placeholder="0.00"
                            />
                        </div>
                        <div>
                            <Label htmlFor="ad-conversions">Conversions</Label>
                            <Input
                                id="ad-conversions"
                                type="number"
                                min="0"
                                className="mt-1.5"
                                value={statsForm.conversions}
                                onChange={(e) =>
                                    setStatsForm((f) => ({ ...f, conversions: e.target.value }))
                                }
                                placeholder="0"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setAddStatsOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleAddStats}
                            disabled={addStatsMutation.isPending}
                        >
                            {addStatsMutation.isPending ? 'Saving...' : 'Save Stats'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
