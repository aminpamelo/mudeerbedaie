import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    ZoomIn,
    ZoomOut,
    Maximize2,
    Minimize2,
    Users,
    Building2,
    Loader2,
    Search,
    X,
    AlertCircle,
    Link2,
    ExternalLink,
    Check,
    Crown,
    Network,
} from 'lucide-react';
import { fetchOrgChart, assignEmployeeManager, fetchDeptOrgChart, assignDepartmentParent } from '../lib/api';
import PageHeader from '../components/PageHeader';
import { Avatar, AvatarImage, AvatarFallback } from '../components/ui/avatar';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../components/ui/select';
import { Label } from '../components/ui/label';
import { cn } from '../lib/utils';

// ========== Shared Helpers ==========

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

// Depth-based colors for department cards
const DEPT_DEPTH_COLORS = {
    0: { bg: 'bg-violet-50', border: 'border-violet-300', accent: 'bg-violet-600', text: 'text-violet-700', badge: 'bg-violet-100 text-violet-700' },
    1: { bg: 'bg-amber-50', border: 'border-amber-300', accent: 'bg-amber-600', text: 'text-amber-700', badge: 'bg-amber-100 text-amber-700' },
    2: { bg: 'bg-emerald-50', border: 'border-emerald-300', accent: 'bg-emerald-600', text: 'text-emerald-700', badge: 'bg-emerald-100 text-emerald-700' },
    3: { bg: 'bg-blue-50', border: 'border-blue-300', accent: 'bg-blue-600', text: 'text-blue-700', badge: 'bg-blue-100 text-blue-700' },
    4: { bg: 'bg-rose-50', border: 'border-rose-300', accent: 'bg-rose-600', text: 'text-rose-700', badge: 'bg-rose-100 text-rose-700' },
};

function getColorForDepth(depth) {
    return DEPT_DEPTH_COLORS[depth] || DEPT_DEPTH_COLORS[4];
}

// ========== Zoom Controls ==========

function ZoomControls({ scale, onZoomIn, onZoomOut, onReset, isFullscreen, onToggleFullscreen }) {
    return (
        <div className="flex items-center gap-1 rounded-lg border border-zinc-200 bg-white p-1">
            <Button variant="ghost" size="sm" onClick={onZoomOut} title="Zoom Out">
                <ZoomOut className="h-4 w-4" />
            </Button>
            <span className="px-2 text-xs font-medium text-zinc-500 tabular-nums">
                {Math.round(scale * 100)}%
            </span>
            <Button variant="ghost" size="sm" onClick={onZoomIn} title="Zoom In">
                <ZoomIn className="h-4 w-4" />
            </Button>
            <div className="mx-1 h-4 w-px bg-zinc-200" />
            <Button variant="ghost" size="sm" onClick={onToggleFullscreen} title={isFullscreen ? "Exit Fullscreen" : "Fullscreen"}>
                {isFullscreen ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
            </Button>
        </div>
    );
}

// ========== Employee Tree Components ==========

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

                {(person.positions?.length > 0 ? person.positions : person.position ? [person.position] : []).map((pos, i) => (
                    <span key={pos.id ?? i} className={cn(
                        'inline-block rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider mb-1',
                        colors.badge,
                    )}>
                        {pos.title}
                    </span>
                ))}

                <p className="text-sm font-bold text-zinc-900 leading-tight">
                    {person.full_name}
                </p>

                {person.department && (
                    <p className="text-[11px] text-zinc-500 mt-0.5">
                        {person.department.name}
                    </p>
                )}
            </div>
        </div>
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

function EmployeeTreeBranch({ person, searchTerm, onPersonClick }) {
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
                    <div className="w-px h-5 bg-zinc-300" />
                    <div className="relative">
                        {children.length > 1 && (
                            <div className="absolute top-0 left-0 right-0 flex">
                                <div className="flex-1" />
                                {children.map((_, i) => (
                                    <div key={i} className="flex-1" />
                                ))}
                            </div>
                        )}
                        <div className="flex gap-2 sm:gap-4 relative">
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
                                    <div className="w-px h-5 bg-zinc-300" />
                                    <EmployeeTreeBranch
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

function EmployeeTree({ tree, searchTerm, onPersonClick }) {
    if (tree.length === 0) return null;

    return (
        <div className="flex flex-col items-center gap-0">
            {tree.map((root, idx) => (
                <div key={root.id} className={cn(idx > 0 && 'mt-10')}>
                    <EmployeeTreeBranch
                        person={root}
                        searchTerm={searchTerm}
                        onPersonClick={onPersonClick}
                    />
                </div>
            ))}
        </div>
    );
}

// Flatten employee tree for select dropdown
function flattenTree(tree) {
    const result = [];
    function traverse(nodes) {
        for (const node of nodes) {
            result.push(node);
            if (node.children?.length) {
                traverse(node.children);
            }
        }
    }
    traverse(tree);
    return result.sort((a, b) => a.full_name.localeCompare(b.full_name));
}

// ========== Assign Manager Modal (Employee) ==========

function AssignManagerModal({ person, allEmployees, open, onOpenChange, onAssign, isAssigning, onViewProfile }) {
    const [selectedManager, setSelectedManager] = useState(
        person?.reports_to ? String(person.reports_to) : 'none'
    );

    const personId = person?.id;
    const personReportsTo = person?.reports_to;
    useEffect(() => {
        setSelectedManager(personReportsTo ? String(personReportsTo) : 'none');
    }, [personId, personReportsTo]);

    if (!person) return null;

    const level = person.position?.level || 5;
    const colors = getColorForLevel(level);
    const eligibleManagers = allEmployees.filter((e) => e.id !== person.id);
    const currentManager = allEmployees.find((e) => e.id === person.reports_to);

    const handleSave = () => {
        onAssign(person.id, selectedManager === 'none' ? null : parseInt(selectedManager));
    };

    const hasChanged = (selectedManager === 'none' ? null : parseInt(selectedManager)) !== (person.reports_to || null);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Assign Manager</DialogTitle>
                    <DialogDescription>
                        Set who this employee reports to in the organization hierarchy.
                    </DialogDescription>
                </DialogHeader>

                <div className={cn('rounded-lg border-2 p-4', colors.bg, colors.border)}>
                    <div className="flex items-center gap-3">
                        <Avatar className="h-12 w-12 ring-2 ring-white shadow-md">
                            {person.profile_photo_url ? (
                                <AvatarImage src={person.profile_photo_url} alt={person.full_name} />
                            ) : null}
                            <AvatarFallback className={cn(colors.accent, 'text-white font-bold text-sm')}>
                                {getInitials(person.full_name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <p className="font-bold text-zinc-900">{person.full_name}</p>
                            {(person.positions?.length > 0 ? person.positions : person.position ? [person.position] : []).map((pos, i) => (
                                <span key={pos.id ?? i} className={cn(
                                    'inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider mr-1',
                                    colors.badge,
                                )}>
                                    {pos.title}
                                </span>
                            ))}
                            {person.department && (
                                <p className="text-xs text-zinc-500 mt-0.5">{person.department.name}</p>
                            )}
                        </div>
                    </div>
                </div>

                {currentManager && (
                    <div className="rounded-lg bg-zinc-50 border border-zinc-200 px-3 py-2 text-sm">
                        <span className="text-zinc-500">Currently reports to: </span>
                        <span className="font-medium text-zinc-900">{currentManager.full_name}</span>
                        {currentManager.position?.title && (
                            <span className="text-zinc-400"> — {currentManager.position.title}</span>
                        )}
                    </div>
                )}

                <div className="space-y-2">
                    <Label htmlFor="manager-select">Reports To</Label>
                    <Select value={selectedManager} onValueChange={setSelectedManager}>
                        <SelectTrigger id="manager-select">
                            <SelectValue placeholder="Select a manager..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                <span className="text-zinc-500">No manager (top level)</span>
                            </SelectItem>
                            {eligibleManagers.map((emp) => {
                                const posLabels = (emp.positions?.length > 0 ? emp.positions : emp.position ? [emp.position] : []).map(p => p.title).join(', ');
                                return (
                                    <SelectItem key={emp.id} value={String(emp.id)}>
                                        <div className="flex items-center gap-2">
                                            <span>{emp.full_name}</span>
                                            {posLabels && (
                                                <span className="text-zinc-400 text-xs">— {posLabels}</span>
                                            )}
                                        </div>
                                    </SelectItem>
                                );
                            })}
                        </SelectContent>
                    </Select>
                </div>

                <DialogFooter className="flex-row justify-between sm:justify-between">
                    <Button variant="ghost" size="sm" onClick={() => onViewProfile?.(person.id)} className="text-zinc-500">
                        <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
                        View Profile
                    </Button>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                        <Button onClick={handleSave} disabled={!hasChanged || isAssigning}>
                            {isAssigning ? (
                                <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Saving...</>
                            ) : (
                                <><Check className="mr-2 h-4 w-4" />Save</>
                            )}
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// ========== Employee View Tab ==========

function EmployeeView() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const containerRef = useRef(null);
    const fullscreenRef = useRef(null);
    const [search, setSearch] = useState('');
    const [scale, setScale] = useState(1);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [selectedPerson, setSelectedPerson] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);

    useEffect(() => {
        const handleChange = () => setIsFullscreen(!!document.fullscreenElement);
        document.addEventListener('fullscreenchange', handleChange);
        return () => document.removeEventListener('fullscreenchange', handleChange);
    }, []);

    const toggleFullscreen = useCallback(() => {
        if (!fullscreenRef.current) return;
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            fullscreenRef.current.requestFullscreen();
        }
    }, []);

    const { data, isLoading, error } = useQuery({
        queryKey: ['hr', 'org-chart'],
        queryFn: fetchOrgChart,
    });

    const tree = data?.data || [];
    const meta = data?.meta || {};
    const allEmployees = useMemo(() => flattenTree(tree), [tree]);

    const assignMutation = useMutation({
        mutationFn: ({ employeeId, reportsTo }) =>
            assignEmployeeManager(employeeId, { reports_to: reportsTo }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'org-chart'] });
            setModalOpen(false);
            setSelectedPerson(null);
        },
    });

    const handlePersonClick = useCallback((id) => {
        const person = flattenTree(tree).find((p) => p.id === id);
        if (person) {
            setSelectedPerson(person);
            setModalOpen(true);
        }
    }, [tree]);

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
                <Button variant="outline" size="sm" onClick={() => window.location.reload()}>Try Again</Button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Stats */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <StatCard icon={Users} iconBg="bg-blue-100" iconColor="text-blue-600" value={meta.total_employees ?? 0} label="Total Employees" />
                <StatCard icon={Link2} iconBg="bg-emerald-100" iconColor="text-emerald-600" value={meta.linked_employees ?? 0} label="Linked in Chart" />
                <StatCard icon={AlertCircle} iconBg="bg-amber-100" iconColor="text-amber-600" value={meta.unlinked_employees ?? 0} label="Not Linked" />
            </div>

            {meta.unlinked_employees > 0 && (
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 flex items-start gap-2">
                    <AlertCircle className="h-4 w-4 mt-0.5 shrink-0" />
                    <div>
                        <strong>{meta.unlinked_employees} employee(s)</strong> don't have a "Reports To" assigned.
                        Click on any employee card to assign their manager directly.
                    </div>
                </div>
            )}

            <div ref={fullscreenRef} className={cn(isFullscreen && 'bg-white p-6 overflow-auto')}>
                {/* Toolbar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <SearchBar value={search} onChange={setSearch} placeholder="Search employees, positions, departments..." />
                    <ZoomControls
                        scale={scale}
                        onZoomIn={() => setScale((s) => Math.min(s + 0.1, 1.5))}
                        onZoomOut={() => setScale((s) => Math.max(s - 0.1, 0.3))}
                        onReset={() => setScale(1)}
                        isFullscreen={isFullscreen}
                        onToggleFullscreen={toggleFullscreen}
                    />
                </div>

                {/* Legend */}
                <div className="mt-4 flex flex-wrap gap-3 text-xs">
                    {Object.entries(LEVEL_COLORS).map(([level, colors]) => (
                        <div key={level} className="flex items-center gap-1.5">
                            <div className={cn('h-3 w-3 rounded-sm', colors.accent)} />
                            <span className="text-zinc-500">Level {level}</span>
                        </div>
                    ))}
                </div>

                {/* Tree */}
                <div ref={containerRef} className={cn('mt-4 overflow-auto rounded-xl border border-zinc-200 bg-gradient-to-b from-white to-zinc-50/50 p-8', isFullscreen && 'flex-1')} style={{ minHeight: isFullscreen ? 'calc(100vh - 120px)' : '500px' }}>
                    <div className="w-fit min-w-full" style={{ transform: `scale(${scale})`, transformOrigin: 'top left', transition: 'transform 0.2s ease' }}>
                        {tree.length === 0 ? (
                            <EmptyState icon={Building2} title="No employees found" subtitle="Add employees and set their 'Reports To' to build the org chart" />
                        ) : (
                            <EmployeeTree tree={tree} searchTerm={search} onPersonClick={handlePersonClick} />
                        )}
                    </div>
                </div>
            </div>

            <AssignManagerModal
                person={selectedPerson}
                allEmployees={allEmployees}
                open={modalOpen}
                onOpenChange={(open) => { setModalOpen(open); if (!open) setSelectedPerson(null); }}
                onAssign={(employeeId, reportsTo) => assignMutation.mutate({ employeeId, reportsTo })}
                isAssigning={assignMutation.isPending}
                onViewProfile={(id) => navigate(`/employees/${id}`)}
            />
        </div>
    );
}

// ========== Department Tree Components ==========

function DepartmentCard({ dept, depth, isHighlighted, onClick }) {
    const colors = getColorForDepth(depth);

    return (
        <div
            onClick={() => onClick?.(dept)}
            className={cn(
                'group relative cursor-pointer transition-all duration-200',
                'hover:scale-[1.02]'
            )}
        >
            <div
                className={cn(
                    'relative rounded-xl border-2 shadow-sm transition-all min-w-[220px] max-w-[280px]',
                    colors.bg,
                    colors.border,
                    isHighlighted && 'ring-2 ring-blue-500 ring-offset-2',
                    'hover:shadow-lg'
                )}
            >
                {/* Department Header */}
                <div className="px-4 py-3 border-b border-inherit">
                    <div className="flex items-center justify-between gap-2">
                        <div className="min-w-0">
                            <p className="text-sm font-bold text-zinc-900 leading-tight truncate">
                                {dept.name}
                            </p>
                        </div>
                        <span className={cn(
                            'shrink-0 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                            colors.badge,
                        )}>
                            {dept.code}
                        </span>
                    </div>
                </div>

                {/* Employee List */}
                <div className="px-3 py-2">
                    {dept.employees && dept.employees.length > 0 ? (
                        <div className="space-y-1.5">
                            {dept.employees.map((emp) => (
                                <div key={emp.id} className="flex items-center gap-2">
                                    <Avatar className="h-6 w-6 shrink-0">
                                        {emp.profile_photo_url ? (
                                            <AvatarImage src={emp.profile_photo_url} alt={emp.full_name} />
                                        ) : null}
                                        <AvatarFallback className={cn(colors.accent, 'text-white text-[9px] font-bold')}>
                                            {getInitials(emp.full_name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-xs font-medium text-zinc-800 truncate leading-tight flex items-center gap-1">
                                            {emp.is_head && <Crown className="h-3 w-3 text-amber-500 shrink-0" />}
                                            {emp.full_name}
                                        </p>
                                        {(emp.positions?.length > 0 ? emp.positions : emp.position ? [emp.position] : []).length > 0 && (
                                            <p className="text-[10px] text-zinc-500 leading-tight">
                                                {(emp.positions?.length > 0 ? emp.positions : [emp.position]).map(p => p.title).join(' · ')}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-[11px] text-zinc-400 italic py-1">No employees</p>
                    )}
                </div>
            </div>
        </div>
    );
}

function matchesDept(dept, term) {
    const t = term.toLowerCase();
    return (
        dept.name?.toLowerCase().includes(t) ||
        dept.code?.toLowerCase().includes(t) ||
        (dept.employees || []).some(
            (e) => e.full_name?.toLowerCase().includes(t) || e.position?.title?.toLowerCase().includes(t)
        )
    );
}

function hasDeptTreeMatch(dept, term) {
    if (matchesDept(dept, term)) return true;
    return (dept.children || []).some((c) => hasDeptTreeMatch(c, term));
}

function DeptTreeBranch({ dept, searchTerm, onDeptClick, depth = 0 }) {
    const children = (dept.children || []).filter((child) => {
        if (!searchTerm) return true;
        return hasDeptTreeMatch(child, searchTerm);
    });

    const hasChildren = children.length > 0;
    const isMatch = searchTerm ? matchesDept(dept, searchTerm) : false;

    if (searchTerm && !isMatch && !hasDeptTreeMatch(dept, searchTerm)) {
        return null;
    }

    return (
        <div className="flex flex-col items-center">
            <DepartmentCard
                dept={dept}
                depth={depth}
                isHighlighted={isMatch && !!searchTerm}
                onClick={onDeptClick}
            />

            {hasChildren && (
                <>
                    <div className="w-px h-5 bg-zinc-300" />
                    <div className="relative">
                        <div className="flex gap-3 sm:gap-5 relative">
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
                                    <div className="w-px h-5 bg-zinc-300" />
                                    <DeptTreeBranch
                                        dept={child}
                                        searchTerm={searchTerm}
                                        onDeptClick={onDeptClick}
                                        depth={depth + 1}
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

function DepartmentTree({ tree, searchTerm, onDeptClick }) {
    if (tree.length === 0) return null;

    return (
        <div className="flex flex-col items-center gap-0">
            {tree.map((root, idx) => (
                <div key={root.id} className={cn(idx > 0 && 'mt-10')}>
                    <DeptTreeBranch
                        dept={root}
                        searchTerm={searchTerm}
                        onDeptClick={onDeptClick}
                        depth={0}
                    />
                </div>
            ))}
        </div>
    );
}

// Flatten department tree for select dropdown
function flattenDeptTree(tree) {
    const result = [];
    function traverse(nodes) {
        for (const node of nodes) {
            result.push(node);
            if (node.children?.length) {
                traverse(node.children);
            }
        }
    }
    traverse(tree);
    return result.sort((a, b) => a.name.localeCompare(b.name));
}

// Get all descendant IDs of a department (to prevent cycles)
function getDescendantIds(dept) {
    const ids = [];
    function traverse(nodes) {
        for (const node of nodes) {
            ids.push(node.id);
            if (node.children?.length) {
                traverse(node.children);
            }
        }
    }
    traverse(dept.children || []);
    return ids;
}

// ========== Assign Parent Modal (Department) ==========

function AssignParentModal({ dept, allDepartments, open, onOpenChange, onAssign, isAssigning }) {
    const [selectedParent, setSelectedParent] = useState(
        dept?.parent_id ? String(dept.parent_id) : 'none'
    );

    const deptId = dept?.id;
    const deptParentId = dept?.parent_id;
    useEffect(() => {
        setSelectedParent(deptParentId ? String(deptParentId) : 'none');
    }, [deptId, deptParentId]);

    if (!dept) return null;

    // Exclude self and all descendants
    const descendantIds = getDescendantIds(dept);
    const eligibleParents = allDepartments.filter(
        (d) => d.id !== dept.id && !descendantIds.includes(d.id)
    );

    const currentParent = allDepartments.find((d) => d.id === dept.parent_id);

    const handleSave = () => {
        onAssign(dept.id, selectedParent === 'none' ? null : parseInt(selectedParent));
    };

    const hasChanged = (selectedParent === 'none' ? null : parseInt(selectedParent)) !== (dept.parent_id || null);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Assign Parent Department</DialogTitle>
                    <DialogDescription>
                        Set which department this belongs under in the hierarchy.
                    </DialogDescription>
                </DialogHeader>

                {/* Department Info */}
                <div className="rounded-lg border-2 border-violet-300 bg-violet-50 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="font-bold text-zinc-900">{dept.name}</p>
                            <p className="text-xs text-zinc-500 mt-0.5">{dept.employee_count} employee(s)</p>
                        </div>
                        <span className="rounded-full bg-violet-100 text-violet-700 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                            {dept.code}
                        </span>
                    </div>
                </div>

                {currentParent && (
                    <div className="rounded-lg bg-zinc-50 border border-zinc-200 px-3 py-2 text-sm">
                        <span className="text-zinc-500">Currently under: </span>
                        <span className="font-medium text-zinc-900">{currentParent.name}</span>
                        <span className="text-zinc-400"> ({currentParent.code})</span>
                    </div>
                )}

                <div className="space-y-2">
                    <Label htmlFor="parent-select">Parent Department</Label>
                    <Select value={selectedParent} onValueChange={setSelectedParent}>
                        <SelectTrigger id="parent-select">
                            <SelectValue placeholder="Select parent department..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                <span className="text-zinc-500">No parent (root level)</span>
                            </SelectItem>
                            {eligibleParents.map((d) => (
                                <SelectItem key={d.id} value={String(d.id)}>
                                    <div className="flex items-center gap-2">
                                        <span>{d.name}</span>
                                        <span className="text-zinc-400 text-xs">({d.code})</span>
                                    </div>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                    <Button onClick={handleSave} disabled={!hasChanged || isAssigning}>
                        {isAssigning ? (
                            <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Saving...</>
                        ) : (
                            <><Check className="mr-2 h-4 w-4" />Save</>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// ========== Department View Tab ==========

function DepartmentView() {
    const queryClient = useQueryClient();
    const containerRef = useRef(null);
    const fullscreenRef = useRef(null);
    const [search, setSearch] = useState('');
    const [scale, setScale] = useState(1);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [selectedDept, setSelectedDept] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);

    useEffect(() => {
        const handleChange = () => setIsFullscreen(!!document.fullscreenElement);
        document.addEventListener('fullscreenchange', handleChange);
        return () => document.removeEventListener('fullscreenchange', handleChange);
    }, []);

    const toggleFullscreen = useCallback(() => {
        if (!fullscreenRef.current) return;
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            fullscreenRef.current.requestFullscreen();
        }
    }, []);

    const { data, isLoading, error } = useQuery({
        queryKey: ['hr', 'org-chart', 'departments'],
        queryFn: fetchDeptOrgChart,
    });

    const tree = data?.data || [];
    const meta = data?.meta || {};
    const allDepartments = useMemo(() => flattenDeptTree(tree), [tree]);

    const assignMutation = useMutation({
        mutationFn: ({ departmentId, parentId }) =>
            assignDepartmentParent(departmentId, { parent_id: parentId }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'org-chart', 'departments'] });
            setModalOpen(false);
            setSelectedDept(null);
        },
    });

    const handleDeptClick = useCallback((dept) => {
        setSelectedDept(dept);
        setModalOpen(true);
    }, []);

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
                <p className="text-sm text-zinc-500">Failed to load department chart</p>
                <Button variant="outline" size="sm" onClick={() => window.location.reload()}>Try Again</Button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Stats */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <StatCard icon={Building2} iconBg="bg-violet-100" iconColor="text-violet-600" value={meta.total_departments ?? 0} label="Total Departments" />
                <StatCard icon={Link2} iconBg="bg-emerald-100" iconColor="text-emerald-600" value={meta.in_hierarchy ?? 0} label="In Hierarchy" />
                <StatCard icon={Network} iconBg="bg-amber-100" iconColor="text-amber-600" value={meta.root_level ?? 0} label="Root Level" />
            </div>

            {meta.root_level > 1 && (
                <div className="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 flex items-start gap-2">
                    <AlertCircle className="h-4 w-4 mt-0.5 shrink-0" />
                    <div>
                        <strong>{meta.root_level} department(s)</strong> are at root level.
                        Click on any department card to assign a parent department and build the hierarchy.
                    </div>
                </div>
            )}

            <div ref={fullscreenRef} className={cn(isFullscreen && 'bg-white p-6 overflow-auto')}>
                {/* Toolbar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <SearchBar value={search} onChange={setSearch} placeholder="Search departments, employees..." />
                    <ZoomControls
                        scale={scale}
                        onZoomIn={() => setScale((s) => Math.min(s + 0.1, 1.5))}
                        onZoomOut={() => setScale((s) => Math.max(s - 0.1, 0.3))}
                        onReset={() => setScale(1)}
                        isFullscreen={isFullscreen}
                        onToggleFullscreen={toggleFullscreen}
                    />
                </div>

                {/* Legend */}
                <div className="mt-4 flex flex-wrap gap-3 text-xs">
                    {Object.entries(DEPT_DEPTH_COLORS).map(([depth, colors]) => (
                        <div key={depth} className="flex items-center gap-1.5">
                            <div className={cn('h-3 w-3 rounded-sm', colors.accent)} />
                            <span className="text-zinc-500">Depth {depth}</span>
                        </div>
                    ))}
                </div>

                {/* Tree */}
                <div ref={containerRef} className={cn('mt-4 overflow-auto rounded-xl border border-zinc-200 bg-gradient-to-b from-white to-zinc-50/50 p-8', isFullscreen && 'flex-1')} style={{ minHeight: isFullscreen ? 'calc(100vh - 120px)' : '500px' }}>
                    <div className="w-fit min-w-full" style={{ transform: `scale(${scale})`, transformOrigin: 'top left', transition: 'transform 0.2s ease' }}>
                        {tree.length === 0 ? (
                            <EmptyState icon={Building2} title="No departments found" subtitle="Create departments and assign parent departments to build the hierarchy" />
                        ) : (
                            <DepartmentTree tree={tree} searchTerm={search} onDeptClick={handleDeptClick} />
                        )}
                    </div>
                </div>
            </div>

            <AssignParentModal
                dept={selectedDept}
                allDepartments={allDepartments}
                open={modalOpen}
                onOpenChange={(open) => { setModalOpen(open); if (!open) setSelectedDept(null); }}
                onAssign={(departmentId, parentId) => assignMutation.mutate({ departmentId, parentId })}
                isAssigning={assignMutation.isPending}
            />
        </div>
    );
}

// ========== Shared UI Components ==========

function StatCard({ icon: Icon, iconBg, iconColor, value, label }) {
    return (
        <div className="rounded-lg border border-zinc-200 bg-white p-4">
            <div className="flex items-center gap-3">
                <div className={cn('flex h-10 w-10 items-center justify-center rounded-lg', iconBg)}>
                    <Icon className={cn('h-5 w-5', iconColor)} />
                </div>
                <div>
                    <p className="text-2xl font-bold text-zinc-900">{value}</p>
                    <p className="text-xs text-zinc-500">{label}</p>
                </div>
            </div>
        </div>
    );
}

function SearchBar({ value, onChange, placeholder }) {
    return (
        <div className="relative max-w-sm flex-1">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
            <Input
                placeholder={placeholder}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="pl-9 pr-8"
            />
            {value && (
                <button
                    onClick={() => onChange('')}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}

function EmptyState({ icon: Icon, title, subtitle }) {
    return (
        <div className="flex h-64 flex-col items-center justify-center gap-2 text-zinc-400">
            <Icon className="h-12 w-12" />
            <p className="text-sm font-medium">{title}</p>
            <p className="text-xs">{subtitle}</p>
        </div>
    );
}

// ========== Main OrgChart Page ==========

export default function OrgChart() {
    const [activeTab, setActiveTab] = useState('employee');

    return (
        <div className="space-y-6">
            <PageHeader
                title="Organization Chart"
                description="Visual hierarchy of your company — click any card to configure the hierarchy"
            />

            {/* Tab Switcher */}
            <div className="flex gap-1 rounded-lg bg-zinc-100 p-1 w-fit">
                <button
                    onClick={() => setActiveTab('employee')}
                    className={cn(
                        'flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-all',
                        activeTab === 'employee'
                            ? 'bg-white text-zinc-900 shadow-sm'
                            : 'text-zinc-500 hover:text-zinc-700'
                    )}
                >
                    <Users className="h-4 w-4" />
                    By Employee
                </button>
                <button
                    onClick={() => setActiveTab('department')}
                    className={cn(
                        'flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-all',
                        activeTab === 'department'
                            ? 'bg-white text-zinc-900 shadow-sm'
                            : 'text-zinc-500 hover:text-zinc-700'
                    )}
                >
                    <Building2 className="h-4 w-4" />
                    By Department
                </button>
            </div>

            {/* Tab Content */}
            {activeTab === 'employee' ? <EmployeeView /> : <DepartmentView />}
        </div>
    );
}
