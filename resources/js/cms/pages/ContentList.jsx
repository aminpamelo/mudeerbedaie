import { useState, useCallback, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import {
    Plus,
    Search,
    X,
    Eye,
    Pencil,
    ChevronLeft,
    ChevronRight,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    FileText,
    Check,
    Flag,
    Megaphone,
} from 'lucide-react';
import { fetchContents } from '../lib/api';
import { cn } from '../lib/utils';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { Input } from '../components/ui/input';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
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

const STAGE_OPTIONS = [
    { value: 'all', label: 'All Stages' },
    { value: 'idea', label: 'Idea' },
    { value: 'shooting', label: 'Shooting' },
    { value: 'editing', label: 'Editing' },
    { value: 'posting', label: 'Posting' },
    { value: 'posted', label: 'Posted' },
];

const PRIORITY_OPTIONS = [
    { value: 'all', label: 'All Priorities' },
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
    { value: 'urgent', label: 'Urgent' },
];

const STAGE_COLORS = {
    idea: 'bg-blue-100 text-blue-700',
    shooting: 'bg-purple-100 text-purple-700',
    editing: 'bg-amber-100 text-amber-700',
    posting: 'bg-emerald-100 text-emerald-700',
    posted: 'bg-green-100 text-green-700',
};

const PRIORITY_COLORS = {
    urgent: 'bg-red-100 text-red-700',
    high: 'bg-orange-100 text-orange-700',
    medium: 'bg-yellow-100 text-yellow-700',
    low: 'bg-green-100 text-green-700',
};

const SORTABLE_COLUMNS = ['title', 'stage', 'priority', 'due_date', 'created_at'];

// ─── Helpers ────────────────────────────────────────────────────────────────

function getInitials(name) {
    if (!name) return '?';
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// ─── Sub-components ─────────────────────────────────────────────────────────

const PIPELINE_STAGES = [
    { key: 'idea', full: 'Idea', color: 'bg-blue-500', text: 'text-blue-700', light: 'bg-blue-50', border: 'border-blue-300' },
    { key: 'shooting', full: 'Shooting', color: 'bg-purple-500', text: 'text-purple-700', light: 'bg-purple-50', border: 'border-purple-300' },
    { key: 'editing', full: 'Editing', color: 'bg-amber-500', text: 'text-amber-700', light: 'bg-amber-50', border: 'border-amber-300' },
    { key: 'posting', full: 'Posting', color: 'bg-emerald-500', text: 'text-emerald-700', light: 'bg-emerald-50', border: 'border-emerald-300' },
    { key: 'posted', full: 'Posted', color: 'bg-green-500', text: 'text-green-700', light: 'bg-green-50', border: 'border-green-300' },
];

function isOverdue(dateString) {
    if (!dateString) return false;
    return new Date(dateString) < new Date();
}

function StageHoverCard({ stageData, stageDef, isCompleted, isCurrent, children }) {
    const [open, setOpen] = useState(false);
    const timeoutRef = useRef(null);

    const hasData = stageData && (stageData.due_date || (stageData.assignees && stageData.assignees.length > 0));

    function handleEnter() {
        if (!hasData) return;
        clearTimeout(timeoutRef.current);
        timeoutRef.current = setTimeout(() => setOpen(true), 200);
    }

    function handleLeave() {
        clearTimeout(timeoutRef.current);
        timeoutRef.current = setTimeout(() => setOpen(false), 150);
    }

    return (
        <div
            className="relative"
            onMouseEnter={handleEnter}
            onMouseLeave={handleLeave}
        >
            {children}
            {open && hasData && (
                <div
                    className="absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 rounded-lg border border-zinc-200 bg-white p-3 shadow-lg"
                    style={{ minWidth: '180px' }}
                    onClick={(e) => e.stopPropagation()}
                >
                    {/* Arrow */}
                    <div className="absolute -bottom-1.5 left-1/2 -translate-x-1/2 h-3 w-3 rotate-45 border-b border-r border-zinc-200 bg-white" />

                    {/* Stage name */}
                    <div className="mb-2 flex items-center gap-2">
                        <div className={cn('h-2 w-2 rounded-full', stageDef.color)} />
                        <span className="text-xs font-semibold text-zinc-900">{stageDef.full}</span>
                        {isCompleted && (
                            <span className="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">Done</span>
                        )}
                        {isCurrent && (
                            <span className="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">Current</span>
                        )}
                    </div>

                    {/* Due date */}
                    {stageData.due_date && (
                        <div className="mb-2">
                            <p className="text-[10px] font-medium uppercase tracking-wide text-zinc-400">Due Date</p>
                            <p className={cn(
                                'text-xs font-medium',
                                isOverdue(stageData.due_date) && !isCompleted ? 'text-red-600' : 'text-zinc-700'
                            )}>
                                {formatDate(stageData.due_date)}
                                {isOverdue(stageData.due_date) && !isCompleted && (
                                    <span className="ml-1 text-[10px] text-red-500">(Overdue)</span>
                                )}
                            </p>
                        </div>
                    )}

                    {/* PIC / Assignees */}
                    {stageData.assignees && stageData.assignees.length > 0 && (
                        <div>
                            <p className="mb-1 text-[10px] font-medium uppercase tracking-wide text-zinc-400">PIC</p>
                            <div className="space-y-1.5">
                                {stageData.assignees.map((assignee, aIdx) => {
                                    const emp = assignee.employee || assignee;
                                    const name = emp.full_name || emp.name || 'Unknown';
                                    return (
                                        <div key={emp.id || aIdx} className="flex items-center gap-2">
                                            {emp.profile_photo_url ? (
                                                <img
                                                    src={emp.profile_photo_url}
                                                    alt={name}
                                                    className="h-5 w-5 rounded-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-5 w-5 items-center justify-center rounded-full bg-zinc-200 text-[9px] font-semibold text-zinc-600">
                                                    {getInitials(name)}
                                                </div>
                                            )}
                                            <span className="text-xs text-zinc-700">{name}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function StagePipeline({ stage, stages = [] }) {
    const currentIndex = PIPELINE_STAGES.findIndex((s) => s.key === stage);

    function getStageData(stageKey) {
        return stages.find((s) => s.stage === stageKey) || null;
    }

    return (
        <div className="flex items-center">
            {PIPELINE_STAGES.map((s, idx) => {
                const isCompleted = idx < currentIndex;
                const isCurrent = idx === currentIndex;
                const stageData = getStageData(s.key);
                const overdue = stageData?.due_date && isOverdue(stageData.due_date) && !isCompleted;

                return (
                    <div key={s.key} className="flex items-center">
                        {/* Stage step */}
                        <StageHoverCard
                            stageData={stageData}
                            stageDef={s}
                            isCompleted={isCompleted}
                            isCurrent={isCurrent}
                        >
                            <div
                                className={cn(
                                    'flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold transition-all border cursor-default',
                                    isCompleted && `${s.color} text-white border-transparent`,
                                    isCurrent && !overdue && `${s.light} ${s.text} ${s.border}`,
                                    isCurrent && overdue && 'bg-red-50 text-red-700 border-red-300',
                                    !isCompleted && !isCurrent && !overdue && 'bg-zinc-100 text-zinc-400 border-transparent',
                                    !isCompleted && !isCurrent && overdue && 'bg-red-50 text-red-400 border-red-200',
                                )}
                            >
                                {isCompleted && <Check className="h-3 w-3" />}
                                <span>{s.full}</span>
                            </div>
                        </StageHoverCard>
                        {/* Connector */}
                        {idx < PIPELINE_STAGES.length - 1 && (
                            <div
                                className={cn(
                                    'h-0.5 w-1.5 shrink-0',
                                    idx < currentIndex ? s.color : 'bg-zinc-200'
                                )}
                            />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

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

function PriorityBadge({ priority }) {
    const colorClass = PRIORITY_COLORS[priority] || 'bg-zinc-100 text-zinc-700';
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                colorClass
            )}
        >
            {capitalize(priority)}
        </span>
    );
}

function MarkBadges({ content }) {
    const flagged = content.is_flagged_for_ads && !content.is_marked_for_ads;
    const marked = content.is_marked_for_ads;

    if (!flagged && !marked) {
        return <span className="text-sm text-zinc-300">-</span>;
    }

    return (
        <div className="flex items-center gap-1">
            {marked && (
                <span
                    className="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-800"
                    title="Marked for ads"
                >
                    <Megaphone className="h-3 w-3" />
                    Marked
                </span>
            )}
            {flagged && (
                <span
                    className="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold text-orange-800"
                    title="Flagged for ads"
                >
                    <Flag className="h-3 w-3" />
                    Flagged
                </span>
            )}
        </div>
    );
}

function CreatorAvatar({ creator }) {
    if (!creator) return null;

    if (creator.profile_photo_url) {
        return (
            <img
                src={creator.profile_photo_url}
                alt={creator.full_name || creator.name}
                className="h-7 w-7 rounded-full object-cover"
            />
        );
    }

    return (
        <div className="flex h-7 w-7 items-center justify-center rounded-full bg-zinc-200 text-xs font-semibold text-zinc-600">
            {getInitials(creator.full_name || creator.name)}
        </div>
    );
}

function getUniqueAssigneesFromStages(content) {
    if (!content.stages || content.stages.length === 0) return [];
    const seen = new Set();
    const result = [];
    for (const stage of content.stages) {
        for (const assignee of stage.assignees || []) {
            const emp = assignee.employee;
            if (emp && !seen.has(emp.id)) {
                seen.add(emp.id);
                result.push(emp);
            }
        }
    }
    return result;
}

function AssigneeStack({ assignees }) {
    if (!assignees || assignees.length === 0) {
        return <span className="text-sm text-zinc-400">-</span>;
    }

    const visible = assignees.slice(0, 3);
    const remaining = assignees.length - visible.length;

    return (
        <div className="flex -space-x-2">
            {visible.map((assignee, index) => (
                <div
                    key={assignee.id || index}
                    className="relative"
                    title={assignee.full_name || assignee.name}
                >
                    {assignee.profile_photo_url ? (
                        <img
                            src={assignee.profile_photo_url}
                            alt={assignee.full_name || assignee.name}
                            className="h-7 w-7 rounded-full border-2 border-white object-cover"
                        />
                    ) : (
                        <div className="flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-zinc-200 text-xs font-semibold text-zinc-600">
                            {getInitials(assignee.full_name || assignee.name)}
                        </div>
                    )}
                </div>
            ))}
            {remaining > 0 && (
                <div className="flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-zinc-100 text-xs font-medium text-zinc-600">
                    +{remaining}
                </div>
            )}
        </div>
    );
}

function SortIcon({ column, currentSort, currentDirection }) {
    if (currentSort !== column) {
        return <ArrowUpDown className="ml-1 h-3 w-3 opacity-40" />;
    }
    return currentDirection === 'asc' ? (
        <ArrowUp className="ml-1 h-3 w-3" />
    ) : (
        <ArrowDown className="ml-1 h-3 w-3" />
    );
}

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-7 w-7 animate-pulse rounded-full bg-zinc-200" />
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-48 animate-pulse rounded bg-zinc-200" />
                        <div className="h-3 w-32 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-5 w-16 animate-pulse rounded-full bg-zinc-200" />
                    <div className="h-5 w-16 animate-pulse rounded-full bg-zinc-200" />
                    <div className="h-5 w-14 animate-pulse rounded-full bg-zinc-200" />
                    <div className="flex -space-x-2">
                        <div className="h-7 w-7 animate-pulse rounded-full bg-zinc-200" />
                        <div className="h-7 w-7 animate-pulse rounded-full bg-zinc-200" />
                    </div>
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="flex gap-2">
                        <div className="h-8 w-8 animate-pulse rounded bg-zinc-200" />
                        <div className="h-8 w-8 animate-pulse rounded bg-zinc-200" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function EmptyState({ hasFilters, onClearFilters }) {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <FileText className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">
                {hasFilters ? 'No content items found' : 'No content yet'}
            </h3>
            <p className="mt-1 text-sm text-zinc-500">
                {hasFilters
                    ? 'Try adjusting your search or filters to find what you are looking for.'
                    : 'Get started by creating your first content item.'}
            </p>
            <div className="mt-4 flex gap-2">
                {hasFilters && (
                    <Button variant="outline" onClick={onClearFilters}>
                        Clear Filters
                    </Button>
                )}
                <Button asChild>
                    <Link to="/contents/create">
                        <Plus className="mr-1.5 h-4 w-4" />
                        Create Content
                    </Link>
                </Button>
            </div>
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
            if (start > 2) {
                pages.push('...');
            }
        }

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }

        if (end < lastPage) {
            if (end < lastPage - 1) {
                pages.push('...');
            }
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
                        <span
                            key={`ellipsis-${index}`}
                            className="px-2 text-sm text-zinc-400"
                        >
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

// ─── Search Input (with debounce) ───────────────────────────────────────────

function SearchInput({ value, onChange, placeholder = 'Search...', className }) {
    const [localValue, setLocalValue] = useState(value);
    const timerRef = { current: null };

    function handleChange(e) {
        const newValue = e.target.value;
        setLocalValue(newValue);

        if (timerRef.current) {
            clearTimeout(timerRef.current);
        }

        timerRef.current = setTimeout(() => {
            onChange?.(newValue);
        }, 300);
    }

    function handleClear() {
        setLocalValue('');
        onChange?.('');
    }

    return (
        <div className={cn('relative', className)}>
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
            <Input
                value={localValue}
                onChange={handleChange}
                placeholder={placeholder}
                className="pl-9 pr-9"
            />
            {localValue && (
                <button
                    type="button"
                    onClick={handleClear}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────

export default function ContentList() {
    const navigate = useNavigate();
    const [search, setSearch] = useState('');
    const [stageFilter, setStageFilter] = useState('all');
    const [priorityFilter, setPriorityFilter] = useState('all');
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState('created_at');
    const [direction, setDirection] = useState('desc');

    const hasFilters =
        search !== '' || stageFilter !== 'all' || priorityFilter !== 'all';

    const { data, isLoading } = useQuery({
        queryKey: [
            'cms',
            'contents',
            { page, search, stage: stageFilter, priority: priorityFilter, sort, direction },
        ],
        queryFn: () =>
            fetchContents({
                page,
                search: search || undefined,
                stage: stageFilter !== 'all' ? stageFilter : undefined,
                priority: priorityFilter !== 'all' ? priorityFilter : undefined,
                sort,
                direction,
                per_page: 15,
            }),
    });

    const contents = data?.data || [];
    const pagination = data?.meta || data || {};
    const currentPage = pagination.current_page || 1;
    const lastPage = pagination.last_page || 1;
    const total = pagination.total || 0;
    const perPage = pagination.per_page || 15;

    const resetPage = useCallback(() => setPage(1), []);

    function handleSearchChange(value) {
        setSearch(value);
        resetPage();
    }

    function handleStageChange(value) {
        setStageFilter(value);
        resetPage();
    }

    function handlePriorityChange(value) {
        setPriorityFilter(value);
        resetPage();
    }

    function handleSort(column) {
        if (!SORTABLE_COLUMNS.includes(column)) return;

        if (sort === column) {
            setDirection((prev) => (prev === 'asc' ? 'desc' : 'asc'));
        } else {
            setSort(column);
            setDirection('asc');
        }
        resetPage();
    }

    function clearFilters() {
        setSearch('');
        setStageFilter('all');
        setPriorityFilter('all');
        resetPage();
    }

    return (
        <div>
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-zinc-900">All Contents</h1>
                    <p className="mt-1 text-sm text-zinc-500">
                        Manage and track all your content items across stages.
                    </p>
                </div>
                <Button asChild>
                    <Link to="/contents/create">
                        <Plus className="mr-1.5 h-4 w-4" />
                        Create Content
                    </Link>
                </Button>
            </div>

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <SearchInput
                            value={search}
                            onChange={handleSearchChange}
                            placeholder="Search title, description..."
                            className="w-full lg:w-64"
                        />

                        <Select value={stageFilter} onValueChange={handleStageChange}>
                            <SelectTrigger className="w-full lg:w-40">
                                <SelectValue placeholder="Stage" />
                            </SelectTrigger>
                            <SelectContent>
                                {STAGE_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={priorityFilter}
                            onValueChange={handlePriorityChange}
                        >
                            <SelectTrigger className="w-full lg:w-40">
                                <SelectValue placeholder="Priority" />
                            </SelectTrigger>
                            <SelectContent>
                                {PRIORITY_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearFilters}
                                className="lg:ml-auto"
                            >
                                <X className="mr-1 h-4 w-4" />
                                Clear
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            <Card>
                {isLoading ? (
                    <SkeletonTable />
                ) : contents.length === 0 ? (
                    <EmptyState hasFilters={hasFilters} onClearFilters={clearFilters} />
                ) : (
                    <>
                        <Table className="table-fixed w-full">
                            <TableHeader>
                                <TableRow>
                                    <TableHead
                                        className="cursor-pointer select-none w-[35%]"
                                        onClick={() => handleSort('title')}
                                    >
                                        <div className="flex items-center">
                                            Title
                                            <SortIcon
                                                column="title"
                                                currentSort={sort}
                                                currentDirection={direction}
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead
                                        className="cursor-pointer select-none"
                                        onClick={() => handleSort('stage')}
                                    >
                                        <div className="flex items-center">
                                            Pipeline
                                            <SortIcon
                                                column="stage"
                                                currentSort={sort}
                                                currentDirection={direction}
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead
                                        className="cursor-pointer select-none w-[8%]"
                                        onClick={() => handleSort('priority')}
                                    >
                                        <div className="flex items-center">
                                            Priority
                                            <SortIcon
                                                column="priority"
                                                currentSort={sort}
                                                currentDirection={direction}
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead className="w-[9%]">Mark</TableHead>
                                    <TableHead className="w-[12%]">Assignees</TableHead>
                                    <TableHead
                                        className="cursor-pointer select-none w-[10%]"
                                        onClick={() => handleSort('due_date')}
                                    >
                                        <div className="flex items-center">
                                            Due Date
                                            <SortIcon
                                                column="due_date"
                                                currentSort={sort}
                                                currentDirection={direction}
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead className="w-[7%] text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {contents.map((content) => (
                                    <TableRow
                                        key={content.id}
                                        className="cursor-pointer"
                                        onClick={() => navigate(`/contents/${content.id}`)}
                                    >
                                        <TableCell>
                                            <div className="flex items-center gap-3 min-w-0">
                                                <CreatorAvatar creator={content.creator} />
                                                <p className="truncate text-sm font-medium text-zinc-900">
                                                    {content.title}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <StagePipeline stage={content.stage} stages={content.stages || []} />
                                        </TableCell>
                                        <TableCell>
                                            <PriorityBadge priority={content.priority} />
                                        </TableCell>
                                        <TableCell>
                                            <MarkBadges content={content} />
                                        </TableCell>
                                        <TableCell>
                                            <AssigneeStack
                                                assignees={getUniqueAssigneesFromStages(content)}
                                            />
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-sm">
                                            {formatDate(content.due_date)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div
                                                className="flex items-center justify-end gap-1"
                                                onClick={(e) => e.stopPropagation()}
                                            >
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
                                                    asChild
                                                    className="h-8 w-8"
                                                >
                                                    <Link to={`/contents/${content.id}/edit`}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            </div>
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
