import { useState, useRef, useCallback, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    ZoomIn,
    ZoomOut,
    Maximize2,
    Users,
    Building2,
    Loader2,
    Search,
    X,
    AlertCircle,
    Link2,
} from 'lucide-react';
import { fetchOrgChart } from '../lib/api';
import PageHeader from '../components/PageHeader';
import { Avatar, AvatarImage, AvatarFallback } from '../components/ui/avatar';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { cn } from '../lib/utils';

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0][0].toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

const LEVEL_COLORS = {
    1: { bg: 'bg-amber-50', border: 'border-amber-300', accent: 'bg-amber-600', text: 'text-amber-700', badge: 'bg-amber-100 text-amber-700' },
    2: { bg: 'bg-blue-50', border: 'border-blue-300', accent: 'bg-blue-600', text: 'text-blue-700', badge: 'bg-blue-100 text-blue-700' },
    3: { bg: 'bg-violet-50', border: 'border-violet-300', accent: 'bg-violet-600', text: 'text-violet-700', badge: 'bg-violet-100 text-violet-700' },
    4: { bg: 'bg-emerald-50', border: 'border-emerald-300', accent: 'bg-emerald-600', text: 'text-emerald-700', badge: 'bg-emerald-100 text-emerald-700' },
    5: { bg: 'bg-rose-50', border: 'border-rose-300', accent: 'bg-rose-600', text: 'text-rose-700', badge: 'bg-rose-100 text-rose-700' },
};

function getColorForLevel(level) {
    return LEVEL_COLORS[level] || LEVEL_COLORS[5];
}

// Single employee card in the tree
function PersonCard({ person, isHighlighted, onClick }) {
    const level = person.position?.level || 5;
    const colors = getColorForLevel(level);

    return (
        <div
            onClick={() => onClick?.(person.id)}
            className={cn(
                'group relative flex flex-col items-center cursor-pointer transition-all duration-200',
                'hover:scale-105'
            )}
        >
            <div
                className={cn(
                    'relative rounded-xl border-2 px-5 py-4 text-center shadow-sm transition-all min-w-[180px] max-w-[220px]',
                    colors.bg,
                    colors.border,
                    isHighlighted && 'ring-2 ring-blue-500 ring-offset-2',
                    'hover:shadow-lg'
                )}
            >
                {/* Avatar */}
                <div className="flex justify-center mb-2">
                    <Avatar className="h-14 w-14 ring-2 ring-white shadow-md">
                        {person.profile_photo_url ? (
                            <AvatarImage src={person.profile_photo_url} alt={person.full_name} />
                        ) : null}
                        <AvatarFallback className={cn(colors.accent, 'text-white font-bold text-sm')}>
                            {getInitials(person.full_name)}
                        </AvatarFallback>
                    </Avatar>
                </div>

                {/* Position label */}
                {person.position?.title && (
                    <span className={cn(
                        'inline-block rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider mb-1.5',
                        colors.badge,
                    )}>
                        {person.position.title}
                    </span>
                )}

                {/* Name */}
                <p className="text-sm font-bold text-zinc-900 leading-tight">
                    {person.full_name}
                </p>

                {/* Department */}
                {person.department && (
                    <p className="text-[11px] text-zinc-500 mt-0.5">
                        {person.department.name}
                    </p>
                )}
            </div>
        </div>
    );
}

// Recursive tree node that renders a person and their children with connector lines
function TreeNode({ person, searchTerm, onPersonClick, isRoot = false }) {
    const children = person.children || [];
    const hasChildren = children.length > 0;

    // Check if this node or any descendant matches the search
    const isMatch = searchTerm
        ? matchesPerson(person, searchTerm)
        : false;
    const hasDescendantMatch = searchTerm
        ? children.some((c) => hasTreeMatch(c, searchTerm))
        : false;

    // If searching and no match in this subtree, hide
    if (searchTerm && !isMatch && !hasDescendantMatch) {
        return null;
    }

    return (
        <div className="flex flex-col items-center">
            {/* This person */}
            <PersonCard
                person={person}
                isHighlighted={isMatch && !!searchTerm}
                onClick={onPersonClick}
            />

            {/* Connector line down from this person */}
            {hasChildren && (
                <div className="w-px h-6 bg-zinc-300" />
            )}

            {/* Children row */}
            {hasChildren && (
                <div className="relative flex items-start">
                    {/* Horizontal connector bar */}
                    {children.length > 1 && (
                        <div
                            className="absolute top-0 h-px bg-zinc-300"
                            style={{
                                left: '50%',
                                right: '50%',
                                // Will be recalculated by the container
                            }}
                        />
                    )}

                    <div className="flex gap-6 relative">
                        {/* Horizontal bar across all children */}
                        {children.length > 1 && (
                            <HorizontalBar />
                        )}

                        {children.map((child) => (
                            <div key={child.id} className="flex flex-col items-center">
                                {/* Vertical connector from horizontal bar to child */}
                                <div className="w-px h-6 bg-zinc-300" />
                                <TreeNode
                                    person={child}
                                    searchTerm={searchTerm}
                                    onPersonClick={onPersonClick}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

// Horizontal connector bar that spans across all sibling nodes
function HorizontalBar() {
    return (
        <div
            className="absolute top-0 bg-zinc-300"
            style={{
                height: '1px',
                left: 'calc(50% / var(--child-count, 1))',
                right: 'calc(50% / var(--child-count, 1))',
                // Simplified: just connect from first child center to last child center
                left: 0,
                right: 0,
                // Adjust to center on first and last child
                marginLeft: 'calc(50% / var(--child-count))',
                marginRight: 'calc(50% / var(--child-count))',
            }}
        />
    );
}

function matchesPerson(person, term) {
    const t = term.toLowerCase();
    return (
        person.full_name?.toLowerCase().includes(t) ||
        person.position?.title?.toLowerCase().includes(t) ||
        person.department?.name?.toLowerCase().includes(t) ||
        person.employee_id?.toLowerCase().includes(t)
    );
}

function hasTreeMatch(person, term) {
    if (matchesPerson(person, term)) return true;
    return (person.children || []).some((c) => hasTreeMatch(c, term));
}

// Better tree rendering with SVG connectors
function OrgTreeSVG({ tree, searchTerm, onPersonClick }) {
    if (tree.length === 0) return null;

    return (
        <div className="flex flex-col items-center gap-0">
            {tree.map((root, idx) => (
                <div key={root.id} className={cn(idx > 0 && 'mt-10')}>
                    <TreeBranch
                        person={root}
                        searchTerm={searchTerm}
                        onPersonClick={onPersonClick}
                    />
                </div>
            ))}
        </div>
    );
}

function TreeBranch({ person, searchTerm, onPersonClick }) {
    const children = (person.children || []).filter((child) => {
        if (!searchTerm) return true;
        return hasTreeMatch(child, searchTerm);
    });

    const hasChildren = children.length > 0;
    const isMatch = searchTerm ? matchesPerson(person, searchTerm) : false;

    if (searchTerm && !isMatch && !hasTreeMatch(person, searchTerm)) {
        return null;
    }

    return (
        <div className="flex flex-col items-center">
            <PersonCard
                person={person}
                isHighlighted={isMatch && !!searchTerm}
                onClick={onPersonClick}
            />

            {hasChildren && (
                <>
                    {/* Vertical line down */}
                    <div className="w-px h-5 bg-zinc-300" />

                    {/* Children wrapper with horizontal connector */}
                    <div className="relative">
                        {/* Horizontal bar */}
                        {children.length > 1 && (
                            <div className="absolute top-0 left-0 right-0 flex">
                                <div className="flex-1" />
                                {children.map((_, i) => (
                                    <div key={i} className="flex-1" />
                                ))}
                            </div>
                        )}

                        <div className="flex gap-2 sm:gap-4 relative">
                            {/* Horizontal connector line */}
                            {children.length > 1 && (
                                <div
                                    className="absolute top-0 h-px bg-zinc-300"
                                    style={{
                                        left: `${100 / (children.length * 2)}%`,
                                        right: `${100 / (children.length * 2)}%`,
                                    }}
                                />
                            )}

                            {children.map((child) => (
                                <div key={child.id} className="flex flex-col items-center">
                                    {/* Vertical stub down to child */}
                                    <div className="w-px h-5 bg-zinc-300" />
                                    <TreeBranch
                                        person={child}
                                        searchTerm={searchTerm}
                                        onPersonClick={onPersonClick}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}

export default function OrgChart() {
    const navigate = useNavigate();
    const containerRef = useRef(null);
    const [search, setSearch] = useState('');
    const [scale, setScale] = useState(1);

    const { data, isLoading, error } = useQuery({
        queryKey: ['hr', 'org-chart'],
        queryFn: fetchOrgChart,
    });

    const tree = data?.data || [];
    const meta = data?.meta || {};

    const handleZoomIn = useCallback(() => {
        setScale((s) => Math.min(s + 0.1, 1.5));
    }, []);

    const handleZoomOut = useCallback(() => {
        setScale((s) => Math.max(s - 0.1, 0.3));
    }, []);

    const handleReset = useCallback(() => {
        setScale(1);
    }, []);

    const handlePersonClick = useCallback((id) => {
        navigate(`/employees/${id}`);
    }, [navigate]);

    if (isLoading) {
        return (
            <div className="flex h-96 items-center justify-center">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex h-96 flex-col items-center justify-center gap-2">
                <p className="text-sm text-zinc-500">Failed to load organization chart</p>
                <Button variant="outline" size="sm" onClick={() => window.location.reload()}>
                    Try Again
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Organization Chart"
                description="Visual hierarchy of your company — click any person to view their profile"
            />

            {/* Stats bar */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div className="rounded-lg border border-zinc-200 bg-white p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100">
                            <Users className="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-zinc-900">{meta.total_employees ?? 0}</p>
                            <p className="text-xs text-zinc-500">Total Employees</p>
                        </div>
                    </div>
                </div>
                <div className="rounded-lg border border-zinc-200 bg-white p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100">
                            <Link2 className="h-5 w-5 text-emerald-600" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-zinc-900">{meta.linked_employees ?? 0}</p>
                            <p className="text-xs text-zinc-500">Linked in Chart</p>
                        </div>
                    </div>
                </div>
                <div className="rounded-lg border border-zinc-200 bg-white p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100">
                            <AlertCircle className="h-5 w-5 text-amber-600" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-zinc-900">{meta.unlinked_employees ?? 0}</p>
                            <p className="text-xs text-zinc-500">Not Linked</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Info banner for unlinked */}
            {meta.unlinked_employees > 0 && (
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 flex items-start gap-2">
                    <AlertCircle className="h-4 w-4 mt-0.5 shrink-0" />
                    <div>
                        <strong>{meta.unlinked_employees} employee(s)</strong> don't have a "Reports To" assigned.
                        Edit their profile to set their manager and they will appear in the hierarchy tree.
                        Unlinked employees appear as separate root nodes at the top level.
                    </div>
                </div>
            )}

            {/* Toolbar */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <Input
                        placeholder="Search employees, positions, departments..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="pl-9 pr-8"
                    />
                    {search && (
                        <button
                            onClick={() => setSearch('')}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </div>
                <div className="flex items-center gap-1 rounded-lg border border-zinc-200 bg-white p-1">
                    <Button variant="ghost" size="sm" onClick={handleZoomOut} title="Zoom Out">
                        <ZoomOut className="h-4 w-4" />
                    </Button>
                    <span className="px-2 text-xs font-medium text-zinc-500 tabular-nums">
                        {Math.round(scale * 100)}%
                    </span>
                    <Button variant="ghost" size="sm" onClick={handleZoomIn} title="Zoom In">
                        <ZoomIn className="h-4 w-4" />
                    </Button>
                    <div className="mx-1 h-4 w-px bg-zinc-200" />
                    <Button variant="ghost" size="sm" onClick={handleReset} title="Reset Zoom">
                        <Maximize2 className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            {/* Legend */}
            <div className="flex flex-wrap gap-3 text-xs">
                {Object.entries(LEVEL_COLORS).map(([level, colors]) => (
                    <div key={level} className="flex items-center gap-1.5">
                        <div className={cn('h-3 w-3 rounded-sm', colors.accent)} />
                        <span className="text-zinc-500">Level {level}</span>
                    </div>
                ))}
            </div>

            {/* Org Chart Tree */}
            <div
                ref={containerRef}
                className="overflow-auto rounded-xl border border-zinc-200 bg-gradient-to-b from-white to-zinc-50/50 p-8"
                style={{ minHeight: '500px' }}
            >
                <div
                    style={{
                        transform: `scale(${scale})`,
                        transformOrigin: 'top center',
                        transition: 'transform 0.2s ease',
                    }}
                >
                    {tree.length === 0 ? (
                        <div className="flex h-64 flex-col items-center justify-center gap-2 text-zinc-400">
                            <Building2 className="h-12 w-12" />
                            <p className="text-sm font-medium">No employees found</p>
                            <p className="text-xs">Add employees and set their "Reports To" to build the org chart</p>
                        </div>
                    ) : (
                        <OrgTreeSVG
                            tree={tree}
                            searchTerm={search}
                            onPersonClick={handlePersonClick}
                        />
                    )}
                </div>
            </div>
        </div>
    );
}
