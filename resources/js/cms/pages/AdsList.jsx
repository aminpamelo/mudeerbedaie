import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    ChevronLeft,
    ChevronRight,
    Megaphone,
} from 'lucide-react';
import { fetchAdCampaigns } from '../lib/api';
import { cn } from '../lib/utils';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';
import { Card, CardContent } from '../components/ui/card';
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

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'running', label: 'Running' },
    { value: 'paused', label: 'Paused' },
    { value: 'completed', label: 'Completed' },
];

const PLATFORM_OPTIONS = [
    { value: 'all', label: 'All Platforms' },
    { value: 'facebook', label: 'Facebook' },
    { value: 'tiktok', label: 'TikTok' },
];

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

function formatCurrency(amount) {
    if (amount === null || amount === undefined) return '-';
    return `RM ${Number(amount).toLocaleString('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
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

function PlatformBadge({ platform }) {
    const colorClass = PLATFORM_COLORS[platform] || 'bg-zinc-100 text-zinc-700';
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                colorClass
            )}
        >
            {capitalize(platform)}
        </span>
    );
}

function SkeletonTable() {
    return (
        <div className="space-y-3 p-4">
            {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 py-3">
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-48 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-5 w-16 animate-pulse rounded-full bg-zinc-200" />
                    <div className="h-5 w-16 animate-pulse rounded-full bg-zinc-200" />
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <Megaphone className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">No ad campaigns yet</h3>
            <p className="mt-1 text-sm text-zinc-500">
                Create your first ad campaign from the Marked Posts page.
            </p>
        </div>
    );
}

function Pagination({ currentPage, lastPage, total, perPage, onPageChange }) {
    if (lastPage <= 1) return null;

    const from = (currentPage - 1) * perPage + 1;
    const to = Math.min(currentPage * perPage, total);

    function getPageNumbers() {
        const pages = [];
        const maxVisible = 5;

        let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let end = Math.min(lastPage, start + maxVisible - 1);

        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }

        if (start > 1) {
            pages.push(1);
            if (start > 2) pages.push('...');
        }

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }

        if (end < lastPage) {
            if (end < lastPage - 1) pages.push('...');
            pages.push(lastPage);
        }

        return pages;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-3 px-4 py-3 sm:flex-row">
            <p className="text-sm text-zinc-500">
                Showing <span className="font-medium text-zinc-900">{from}</span> to{' '}
                <span className="font-medium text-zinc-900">{to}</span> of{' '}
                <span className="font-medium text-zinc-900">{total}</span> results
            </p>
            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage <= 1}
                >
                    <ChevronLeft className="h-4 w-4" />
                </Button>
                {getPageNumbers().map((pageNum, index) =>
                    pageNum === '...' ? (
                        <span key={`ellipsis-${index}`} className="px-2 text-sm text-zinc-400">
                            ...
                        </span>
                    ) : (
                        <Button
                            key={pageNum}
                            variant={pageNum === currentPage ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => onPageChange(pageNum)}
                            className="min-w-[2.25rem]"
                        >
                            {pageNum}
                        </Button>
                    )
                )}
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage >= lastPage}
                >
                    <ChevronRight className="h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────

export default function AdsList() {
    const navigate = useNavigate();
    const [statusFilter, setStatusFilter] = useState('all');
    const [platformFilter, setPlatformFilter] = useState('all');
    const [page, setPage] = useState(1);

    const { data, isLoading } = useQuery({
        queryKey: ['cms', 'ad-campaigns', { page, status: statusFilter, platform: platformFilter }],
        queryFn: () =>
            fetchAdCampaigns({
                page,
                status: statusFilter !== 'all' ? statusFilter : undefined,
                platform: platformFilter !== 'all' ? platformFilter : undefined,
                per_page: 15,
            }),
    });

    const campaigns = data?.data || [];
    const pagination = data?.meta || data || {};
    const currentPage = pagination.current_page || 1;
    const lastPage = pagination.last_page || 1;
    const total = pagination.total || 0;
    const perPage = pagination.per_page || 15;

    function handleStatusChange(value) {
        setStatusFilter(value);
        setPage(1);
    }

    function handlePlatformChange(value) {
        setPlatformFilter(value);
        setPage(1);
    }

    return (
        <div>
            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-zinc-900">Ad Campaigns</h1>
                <p className="mt-1 text-sm text-zinc-500">
                    Manage and track all advertising campaigns for your content.
                </p>
            </div>

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <Select value={statusFilter} onValueChange={handleStatusChange}>
                            <SelectTrigger className="w-full lg:w-44">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={platformFilter} onValueChange={handlePlatformChange}>
                            <SelectTrigger className="w-full lg:w-44">
                                <SelectValue placeholder="Platform" />
                            </SelectTrigger>
                            <SelectContent>
                                {PLATFORM_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {(statusFilter !== 'all' || platformFilter !== 'all') && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setStatusFilter('all');
                                    setPlatformFilter('all');
                                    setPage(1);
                                }}
                                className="lg:ml-auto"
                            >
                                Clear Filters
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            <Card>
                {isLoading ? (
                    <SkeletonTable />
                ) : campaigns.length === 0 ? (
                    <EmptyState />
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Content</TableHead>
                                    <TableHead>Platform</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Budget</TableHead>
                                    <TableHead>Start Date</TableHead>
                                    <TableHead>End Date</TableHead>
                                    <TableHead>Assigned By</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {campaigns.map((campaign) => (
                                    <TableRow
                                        key={campaign.id}
                                        className="cursor-pointer"
                                        onClick={() => navigate(`/ads/${campaign.id}`)}
                                    >
                                        <TableCell>
                                            <p className="font-medium text-zinc-900 truncate max-w-[200px]">
                                                {campaign.content?.title || `Content #${campaign.content_id}`}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <PlatformBadge platform={campaign.platform} />
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={campaign.status} />
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-sm">
                                            {formatCurrency(campaign.budget)}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-sm">
                                            {formatDate(campaign.start_date)}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-sm">
                                            {formatDate(campaign.end_date)}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {campaign.assigned_by?.name || campaign.assigned_by_name || '-'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <Pagination
                            currentPage={currentPage}
                            lastPage={lastPage}
                            total={total}
                            perPage={perPage}
                            onPageChange={setPage}
                        />
                    </>
                )}
            </Card>
        </div>
    );
}
