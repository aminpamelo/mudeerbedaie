import { useState, useCallback, useMemo, useEffect, useRef, Fragment } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { DragDropContext, Droppable, Draggable } from '@hello-pangea/dnd';
import {
    ArrowLeft,
    ListTodo,
    CheckCircle2,
    Clock,
    AlertTriangle,
    Loader2,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    ChevronRight as ChevronRightIcon,
    MessageSquare,
    LayoutList,
    Columns3,
    CalendarDays,
    GripVertical,
    User,
    Users,
    Plus,
    Tags,
    Tag,
    Pencil,
    Trash2,
    Check,
    X,
    Search,
    FolderTree,
} from 'lucide-react';
import {
    fetchMeetingTasks,
    updateTaskStatus,
    addTaskComment,
    createTask,
    updateMeetingTaskItem,
    fetchEmployees,
    fetchTaskCategories,
    createTaskCategory,
    updateTaskCategory,
    deleteTaskCategory,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import { taskAssignees, assigneeSummary, assigneeNames } from '../../lib/taskAssignees';
import { useToast } from '../../components/Toast';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Badge } from '../../components/ui/badge';
import { StatCard } from '../../components/ui/stat-card';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/textarea';
import { Card, CardContent } from '../../components/ui/card';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';

const PRIORITY_BADGE = {
    low: { label: 'Low', variant: 'secondary' },
    medium: { label: 'Medium', className: 'bg-blue-100 text-blue-800 border-transparent' },
    high: { label: 'High', variant: 'warning' },
    urgent: { label: 'Urgent', variant: 'destructive' },
};

const STATUS_BADGE = {
    pending: { label: 'Pending', variant: 'secondary' },
    in_progress: { label: 'In Progress', variant: 'warning' },
    completed: { label: 'Completed', variant: 'success' },
    cancelled: { label: 'Cancelled', variant: 'destructive' },
};

const STATUS_COLUMNS = [
    { key: 'pending', label: 'Pending', color: 'bg-slate-400', bgColor: 'bg-slate-50', hex: '#94a3b8' },
    { key: 'in_progress', label: 'In Progress', color: 'bg-amber-400', bgColor: 'bg-amber-50', hex: '#fbbf24' },
    { key: 'completed', label: 'Completed', color: 'bg-emerald-400', bgColor: 'bg-emerald-50', hex: '#34d399' },
    { key: 'cancelled', label: 'Cancelled', color: 'bg-red-400', bgColor: 'bg-red-50', hex: '#f87171' },
];

const PRIORITY_COLORS = {
    urgent: 'bg-red-500',
    high: 'bg-orange-500',
    medium: 'bg-blue-500',
    low: 'bg-slate-400',
};

const PRIORITY_HEX = {
    urgent: '#ef4444',
    high: '#f97316',
    medium: '#3b82f6',
    low: '#94a3b8',
};

const PRIORITY_DOT_COLORS = {
    urgent: 'bg-red-100 text-red-700 border-red-200',
    high: 'bg-orange-100 text-orange-700 border-orange-200',
    medium: 'bg-blue-100 text-blue-700 border-blue-200',
    low: 'bg-slate-100 text-slate-600 border-slate-200',
};

const VIEW_MODES = [
    { key: 'table', label: 'Table', icon: LayoutList },
    { key: 'group', label: 'Group', icon: FolderTree },
    { key: 'kanban', label: 'Kanban', icon: Columns3 },
    { key: 'calendar', label: 'Calendar', icon: CalendarDays },
];

const GROUP_BY_OPTIONS = [
    { key: 'category', label: 'Category' },
    { key: 'assignee', label: 'Assignee' },
    { key: 'priority', label: 'Priority' },
    { key: 'status', label: 'Status' },
];

const COLOR_SWATCHES = [
    '#6366f1', '#0ea5e9', '#14b8a6', '#10b981', '#84cc16',
    '#f59e0b', '#f97316', '#ef4444', '#ec4899', '#8b5cf6', '#64748b',
];

const EMPTY_TASK_FORM = { title: '', description: '', assignee_ids: [], category_id: 'none', priority: 'medium', deadline: '' };
const EMPTY_CATEGORY_FORM = { name: '', color: '#6366f1', description: '', is_active: true };

function TaskPriorityBadge({ priority }) {
    const config = PRIORITY_BADGE[priority] || { label: priority, variant: 'secondary' };
    return <Badge variant={config.variant} className={config.className}>{config.label}</Badge>;
}

function TaskStatusBadge({ status }) {
    const config = STATUS_BADGE[status] || { label: status, variant: 'secondary' };
    return <Badge variant={config.variant} className={config.className}>{config.label}</Badge>;
}

function CategoryBadge({ category, className }) {
    if (!category) {
        return (
            <span className={cn('inline-flex items-center gap-1 text-xs text-slate-400', className)}>
                <span className="h-2 w-2 rounded-full bg-slate-300" />
                Uncategorized
            </span>
        );
    }
    return (
        <span
            className={cn('inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium', className)}
            style={{
                color: category.color,
                borderColor: `${category.color}55`,
                backgroundColor: `${category.color}14`,
            }}
        >
            <span className="h-2 w-2 rounded-full" style={{ backgroundColor: category.color }} />
            {category.name}
        </span>
    );
}

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' });
}

function isOverdue(task) {
    return task.deadline && new Date(task.deadline) < new Date() && task.status !== 'completed' && task.status !== 'cancelled';
}

function getMeeting(task) {
    const taskable = task.taskable || task.meeting;
    if (!taskable) return null;
    if (task.taskable_type && !task.taskable_type.endsWith('Meeting')) return null;
    return taskable;
}

function StatusSelect({ task, statusMut, className }) {
    return (
        <Select value={task.status} onValueChange={(v) => statusMut.mutate({ id: task.id, status: v })}>
            <SelectTrigger className={cn('h-8 w-[130px]', className)}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="in_progress">In Progress</SelectItem>
                <SelectItem value="completed">Completed</SelectItem>
                <SelectItem value="cancelled">Cancelled</SelectItem>
            </SelectContent>
        </Select>
    );
}

// ==================== KANBAN VIEW ====================

function KanbanCard({ task, index, onEdit }) {
    const overdue = isOverdue(task);
    const meeting = getMeeting(task);
    return (
        <Draggable draggableId={String(task.id)} index={index}>
            {(provided, snapshot) => (
                <div
                    ref={provided.innerRef}
                    {...provided.draggableProps}
                    className={cn(
                        'mb-2 rounded-lg border bg-white p-3 shadow-sm transition-shadow',
                        snapshot.isDragging && 'shadow-lg ring-2 ring-blue-200',
                        overdue && 'border-red-300 bg-red-50/50'
                    )}
                >
                    <div className="flex items-start gap-2">
                        <div
                            {...provided.dragHandleProps}
                            className="mt-0.5 cursor-grab text-slate-300 hover:text-slate-500"
                        >
                            <GripVertical className="h-4 w-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                            {meeting ? (
                                <Link
                                    to={`/meetings/${meeting.id || task.taskable_id}`}
                                    className="text-sm font-medium text-slate-900 hover:text-blue-600 line-clamp-2"
                                >
                                    {task.title}
                                </Link>
                            ) : (
                                <button
                                    onClick={() => onEdit(task)}
                                    className="block text-left text-sm font-medium text-slate-900 hover:text-blue-600 line-clamp-2"
                                >
                                    {task.title}
                                </button>
                            )}

                            {meeting && (
                                <p className="mt-0.5 truncate text-xs text-slate-400">{meeting.title}</p>
                            )}

                            <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                <TaskPriorityBadge priority={task.priority} />
                                {task.category && <CategoryBadge category={task.category} />}
                                {task.deadline && (
                                    <span className={cn('text-xs', overdue ? 'font-medium text-red-600' : 'text-slate-500')}>
                                        {formatDate(task.deadline)}
                                    </span>
                                )}
                            </div>

                            {taskAssignees(task).length > 0 && (
                                <div className="mt-2 flex items-center gap-1.5" title={assigneeNames(task)}>
                                    <div className="flex h-5 w-5 items-center justify-center rounded-full bg-slate-200">
                                        {taskAssignees(task).length > 1
                                            ? <Users className="h-3 w-3 text-slate-500" />
                                            : <User className="h-3 w-3 text-slate-500" />}
                                    </div>
                                    <span className="truncate text-xs text-slate-500">{assigneeSummary(task)}</span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </Draggable>
    );
}

function KanbanColumn({ column, tasks, onEdit }) {
    return (
        <div className="flex w-72 shrink-0 flex-col lg:w-full lg:shrink">
            <div className={cn('mb-3 flex items-center gap-2 rounded-lg px-3 py-2', column.bgColor)}>
                <div className={cn('h-2.5 w-2.5 rounded-full', column.color)} />
                <h3 className="text-sm font-semibold text-slate-700">{column.label}</h3>
                <span className="ml-auto rounded-full bg-white/80 px-2 py-0.5 text-xs font-medium text-slate-500">
                    {tasks.length}
                </span>
            </div>
            <Droppable droppableId={column.key}>
                {(provided, snapshot) => (
                    <div
                        ref={provided.innerRef}
                        {...provided.droppableProps}
                        className={cn(
                            'min-h-[200px] flex-1 rounded-lg border-2 border-dashed p-2 transition-colors',
                            snapshot.isDraggingOver ? 'border-blue-300 bg-blue-50/50' : 'border-transparent'
                        )}
                    >
                        {tasks.map((task, index) => (
                            <KanbanCard key={task.id} task={task} index={index} onEdit={onEdit} />
                        ))}
                        {provided.placeholder}
                        {tasks.length === 0 && !snapshot.isDraggingOver && (
                            <div className="flex items-center justify-center py-8 text-xs text-slate-400">
                                Drop tasks here
                            </div>
                        )}
                    </div>
                )}
            </Droppable>
        </div>
    );
}

function KanbanView({ tasks, onStatusChange, onEdit }) {
    const grouped = useMemo(() => {
        const groups = {};
        STATUS_COLUMNS.forEach((col) => { groups[col.key] = []; });
        tasks.forEach((task) => {
            if (groups[task.status]) {
                groups[task.status].push(task);
            }
        });
        return groups;
    }, [tasks]);

    function handleDragEnd(result) {
        if (!result.destination) return;
        const { draggableId, destination } = result;
        const newStatus = destination.droppableId;
        const taskId = parseInt(draggableId, 10);
        const task = tasks.find((t) => t.id === taskId);
        if (task && task.status !== newStatus) {
            onStatusChange(taskId, newStatus);
        }
    }

    return (
        <DragDropContext onDragEnd={handleDragEnd}>
            <div className="flex gap-4 overflow-x-auto pb-4 lg:grid lg:grid-cols-4 lg:overflow-visible">
                {STATUS_COLUMNS.map((col) => (
                    <KanbanColumn key={col.key} column={col} tasks={grouped[col.key]} onEdit={onEdit} />
                ))}
            </div>
        </DragDropContext>
    );
}

// ==================== GROUP VIEW ====================

function TaskRow({ task, statusMut, onEdit }) {
    const overdue = isOverdue(task);
    const meeting = getMeeting(task);
    return (
        <div className="flex items-center gap-3 px-3 py-2.5 hover:bg-slate-50">
            <div className="min-w-0 flex-1">
                {meeting ? (
                    <Link to={`/meetings/${meeting.id || task.taskable_id}`} className="text-sm font-medium text-slate-900 hover:text-blue-600">
                        {task.title}
                    </Link>
                ) : (
                    <button onClick={() => onEdit(task)} className="text-left text-sm font-medium text-slate-900 hover:text-blue-600">
                        {task.title}
                    </button>
                )}
                <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-400">
                    {meeting && <span className="truncate">{meeting.title}</span>}
                    {task.category && <CategoryBadge category={task.category} />}
                    {taskAssignees(task).length > 0 && (
                        <span className="flex items-center gap-1" title={assigneeNames(task)}>
                            {taskAssignees(task).length > 1 ? <Users className="h-3 w-3" /> : <User className="h-3 w-3" />}
                            {assigneeSummary(task)}
                        </span>
                    )}
                </div>
            </div>
            <TaskPriorityBadge priority={task.priority} />
            <span className={cn('hidden w-24 text-right text-xs sm:inline', overdue ? 'font-medium text-red-600' : 'text-slate-500')}>
                {formatDate(task.deadline)}
            </span>
            <StatusSelect task={task} statusMut={statusMut} className="w-[120px]" />
            <Button variant="ghost" size="icon" className="h-8 w-8 text-slate-400" onClick={() => onEdit(task)}>
                <Pencil className="h-4 w-4" />
            </Button>
        </div>
    );
}

function buildGroups(tasks, groupBy, categories) {
    if (groupBy === 'category') {
        const groups = categories.map((c) => ({ key: String(c.id), label: c.name, hex: c.color, tasks: [] }));
        const none = { key: 'none', label: 'Uncategorized', hex: '#cbd5e1', tasks: [] };
        const byId = new Map(groups.map((g) => [g.key, g]));
        tasks.forEach((t) => {
            const g = t.category_id ? byId.get(String(t.category_id)) : null;
            (g || none).tasks.push(t);
        });
        return [...groups, none].filter((g) => g.tasks.length > 0);
    }

    if (groupBy === 'priority') {
        return ['urgent', 'high', 'medium', 'low']
            .map((p) => ({
                key: p,
                label: PRIORITY_BADGE[p]?.label || p,
                hex: PRIORITY_HEX[p],
                tasks: tasks.filter((t) => t.priority === p),
            }))
            .filter((g) => g.tasks.length > 0);
    }

    if (groupBy === 'status') {
        return STATUS_COLUMNS
            .map((s) => ({ key: s.key, label: s.label, hex: s.hex, tasks: tasks.filter((t) => t.status === s.key) }))
            .filter((g) => g.tasks.length > 0);
    }

    // assignee — key by id, resolve the label from any task that carries the assignee
    // so a soft-deleted/missing assignee on the first row doesn't mislabel the group.
    const map = new Map();
    tasks.forEach((t) => {
        const key = t.assigned_to ? String(t.assigned_to) : 'none';
        if (!map.has(key)) {
            map.set(key, { key, label: null, hex: '#6366f1', tasks: [] });
        }
        const group = map.get(key);
        group.tasks.push(t);
        if (!group.label && t.assignee?.full_name) {
            group.label = t.assignee.full_name;
        }
    });
    return [...map.values()]
        .map((group) => ({ ...group, label: group.label || 'Unassigned' }))
        .sort((a, b) => a.label.localeCompare(b.label));
}

function GroupView({ tasks, groupBy, categories, statusMut, onEdit }) {
    const groups = useMemo(() => buildGroups(tasks, groupBy, categories), [tasks, groupBy, categories]);
    const [collapsed, setCollapsed] = useState({});

    function toggle(key) {
        setCollapsed((c) => ({ ...c, [key]: !c[key] }));
    }

    if (tasks.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                    <ListTodo className="mb-4 h-12 w-12 text-slate-300" />
                    <h3 className="text-lg font-semibold text-slate-900">No tasks found</h3>
                    <p className="mt-1 text-sm text-slate-500">Create a task or adjust your filters.</p>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-3">
            {groups.map((group) => {
                const isCollapsed = collapsed[group.key];
                const completed = group.tasks.filter((t) => t.status === 'completed').length;
                return (
                    <Card key={group.key} className="overflow-hidden">
                        <button
                            onClick={() => toggle(group.key)}
                            className="flex w-full items-center gap-2.5 border-b border-slate-100 px-4 py-3 text-left transition-colors hover:bg-slate-50"
                        >
                            {isCollapsed
                                ? <ChevronRightIcon className="h-4 w-4 text-slate-400" />
                                : <ChevronDown className="h-4 w-4 text-slate-400" />}
                            <span className="h-3 w-3 rounded-full" style={{ backgroundColor: group.hex }} />
                            <span className="font-semibold text-slate-800">{group.label}</span>
                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                                {group.tasks.length}
                            </span>
                            <span className="ml-auto text-xs text-slate-400">{completed}/{group.tasks.length} done</span>
                        </button>
                        {!isCollapsed && (
                            <div className="divide-y divide-slate-100">
                                {group.tasks.map((task) => (
                                    <TaskRow key={task.id} task={task} statusMut={statusMut} onEdit={onEdit} />
                                ))}
                            </div>
                        )}
                    </Card>
                );
            })}
        </div>
    );
}

// ==================== CALENDAR VIEW ====================

function CalendarView({ tasks, onEdit }) {
    const [currentDate, setCurrentDate] = useState(new Date());
    const [selectedDate, setSelectedDate] = useState(null);

    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const monthName = currentDate.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' });

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startPad = firstDay.getDay();
    const totalDays = lastDay.getDate();

    const tasksByDate = useMemo(() => {
        const map = {};
        tasks.forEach((task) => {
            if (!task.deadline) return;
            const key = task.deadline.split('T')[0];
            if (!map[key]) map[key] = [];
            map[key].push(task);
        });
        return map;
    }, [tasks]);

    const today = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

    const calendarDays = [];
    for (let i = 0; i < startPad; i++) {
        calendarDays.push({ day: null, key: `pad-${i}` });
    }
    for (let d = 1; d <= totalDays; d++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        calendarDays.push({ day: d, dateStr, key: dateStr });
    }

    function prevMonth() {
        setCurrentDate(new Date(year, month - 1, 1));
        setSelectedDate(null);
    }
    function nextMonth() {
        setCurrentDate(new Date(year, month + 1, 1));
        setSelectedDate(null);
    }
    function goToday() {
        setCurrentDate(new Date());
        setSelectedDate(todayStr);
    }

    const selectedTasks = selectedDate ? (tasksByDate[selectedDate] || []) : [];

    return (
        <div className="grid gap-6 lg:grid-cols-3">
            <div className="lg:col-span-2">
                <Card>
                    <CardContent className="p-4">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-slate-900">{monthName}</h3>
                            <div className="flex gap-1">
                                <Button variant="outline" size="sm" onClick={goToday}>Today</Button>
                                <Button variant="outline" size="icon" className="h-8 w-8" onClick={prevMonth}>
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <Button variant="outline" size="icon" className="h-8 w-8" onClick={nextMonth}>
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <div className="grid grid-cols-7 gap-px">
                            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => (
                                <div key={d} className="py-2 text-center text-xs font-medium uppercase text-slate-400">{d}</div>
                            ))}
                        </div>

                        <div className="grid grid-cols-7 gap-px rounded-lg border border-slate-200 bg-slate-200">
                            {calendarDays.map(({ day, dateStr, key }) => {
                                if (day === null) {
                                    return <div key={key} className="bg-slate-50 p-2" style={{ minHeight: 80 }} />;
                                }
                                const dayTasks = tasksByDate[dateStr] || [];
                                const isToday = dateStr === todayStr;
                                const isSelected = dateStr === selectedDate;

                                return (
                                    <div
                                        key={key}
                                        onClick={() => setSelectedDate(dateStr === selectedDate ? null : dateStr)}
                                        className={cn(
                                            'cursor-pointer bg-white p-2 transition-colors hover:bg-blue-50',
                                            isSelected && 'bg-blue-50 ring-2 ring-inset ring-blue-400',
                                        )}
                                        style={{ minHeight: 80 }}
                                    >
                                        <span className={cn(
                                            'inline-flex h-6 w-6 items-center justify-center rounded-full text-sm',
                                            isToday ? 'bg-blue-600 font-bold text-white' : 'text-slate-700'
                                        )}>
                                            {day}
                                        </span>
                                        <div className="mt-1 space-y-0.5">
                                            {dayTasks.slice(0, 3).map((task) => (
                                                <div
                                                    key={task.id}
                                                    className={cn(
                                                        'truncate rounded px-1 py-0.5 text-xs font-medium',
                                                        PRIORITY_DOT_COLORS[task.priority] || 'bg-slate-100 text-slate-600'
                                                    )}
                                                    title={task.title}
                                                >
                                                    {task.title}
                                                </div>
                                            ))}
                                            {dayTasks.length > 3 && (
                                                <div className="px-1 text-xs text-slate-400">+{dayTasks.length - 3} more</div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                            <span className="font-medium">Priority:</span>
                            {Object.entries(PRIORITY_BADGE).map(([key, { label }]) => (
                                <span key={key} className="flex items-center gap-1">
                                    <span className={cn('h-2 w-2 rounded-full', PRIORITY_COLORS[key])} />
                                    {label}
                                </span>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="lg:col-span-1">
                <Card className="sticky top-4">
                    <CardContent className="p-4">
                        <h4 className="mb-3 text-sm font-semibold text-slate-900">
                            {selectedDate
                                ? `Tasks for ${new Date(selectedDate + 'T00:00:00').toLocaleDateString('en-MY', { weekday: 'short', day: 'numeric', month: 'short' })}`
                                : 'Select a date'}
                        </h4>
                        {!selectedDate ? (
                            <p className="text-sm text-slate-400">Click a date on the calendar to view tasks.</p>
                        ) : selectedTasks.length === 0 ? (
                            <div className="flex flex-col items-center py-8 text-center">
                                <CalendarDays className="mb-2 h-8 w-8 text-slate-300" />
                                <p className="text-sm text-slate-400">No tasks due on this date.</p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {selectedTasks.map((task) => {
                                    const meeting = getMeeting(task);
                                    const inner = (
                                        <>
                                            <p className="text-sm font-medium text-slate-900">{task.title}</p>
                                            {meeting && <p className="mt-0.5 text-xs text-slate-400">{meeting.title}</p>}
                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                <TaskPriorityBadge priority={task.priority} />
                                                <TaskStatusBadge status={task.status} />
                                                {task.category && <CategoryBadge category={task.category} />}
                                            </div>
                                            {taskAssignees(task).length > 0 && (
                                                <div className="mt-2 flex items-center gap-1.5 text-xs text-slate-500" title={assigneeNames(task)}>
                                                    {taskAssignees(task).length > 1 ? <Users className="h-3 w-3" /> : <User className="h-3 w-3" />}
                                                    {assigneeSummary(task)}
                                                </div>
                                            )}
                                        </>
                                    );
                                    return meeting ? (
                                        <Link key={task.id} to={`/meetings/${meeting.id || task.taskable_id}`} className="block rounded-lg border p-3 transition-colors hover:bg-slate-50">
                                            {inner}
                                        </Link>
                                    ) : (
                                        <button key={task.id} onClick={() => onEdit(task)} className="block w-full rounded-lg border p-3 text-left transition-colors hover:bg-slate-50">
                                            {inner}
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

// ==================== TABLE VIEW ====================

function TableView({ tasks, expandedTask, setExpandedTask, commentText, setCommentText, statusMut, commentMut, page, setPage, lastPage, onEdit }) {
    return (
        <Card>
            <CardContent className="p-0">
                {tasks.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <ListTodo className="mb-4 h-12 w-12 text-slate-300" />
                        <h3 className="text-lg font-semibold text-slate-900">No tasks found</h3>
                        <p className="mt-1 text-sm text-slate-500">Create a task with the “New Task” button, or one from a meeting.</p>
                    </div>
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-8" />
                                    <TableHead>Task</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Source</TableHead>
                                    <TableHead>Assignee</TableHead>
                                    <TableHead>Priority</TableHead>
                                    <TableHead>Deadline</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tasks.map((task) => {
                                    const meeting = getMeeting(task);
                                    return (
                                        <Fragment key={task.id}>
                                            <TableRow>
                                                <TableCell className="w-8">
                                                    <button onClick={() => setExpandedTask(expandedTask === task.id ? null : task.id)}>
                                                        <ChevronDown className={cn('h-4 w-4 text-slate-400 transition-transform', expandedTask === task.id && 'rotate-180')} />
                                                    </button>
                                                </TableCell>
                                                <TableCell>
                                                    {meeting ? (
                                                        <p className="font-medium text-slate-900">{task.title}</p>
                                                    ) : (
                                                        <button onClick={() => onEdit(task)} className="text-left font-medium text-slate-900 hover:text-blue-600">
                                                            {task.title}
                                                        </button>
                                                    )}
                                                </TableCell>
                                                <TableCell><CategoryBadge category={task.category} /></TableCell>
                                                <TableCell>
                                                    {meeting ? (
                                                        <Link to={`/meetings/${meeting.id || task.taskable_id}`} className="text-sm text-blue-600 hover:underline">
                                                            {meeting.title}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-xs text-slate-400">Standalone</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <span title={assigneeNames(task)}>
                                                        {assigneeSummary(task) || '-'}
                                                    </span>
                                                </TableCell>
                                                <TableCell><TaskPriorityBadge priority={task.priority} /></TableCell>
                                                <TableCell>
                                                    <span className={cn(isOverdue(task) && 'font-medium text-red-600')}>{formatDate(task.deadline)}</span>
                                                </TableCell>
                                                <TableCell><StatusSelect task={task} statusMut={statusMut} /></TableCell>
                                            </TableRow>
                                            {expandedTask === task.id && (
                                                <TableRow>
                                                    <TableCell colSpan={8}>
                                                        <div className="space-y-3 px-4 py-2">
                                                            {task.description && <p className="text-sm text-slate-600">{task.description}</p>}
                                                            {task.subtasks?.length > 0 && (
                                                                <div>
                                                                    <p className="text-xs font-medium uppercase text-slate-500">Subtasks</p>
                                                                    <ul className="mt-1 space-y-1">
                                                                        {task.subtasks.map((st) => (
                                                                            <li key={st.id} className="flex items-center gap-2 text-sm">
                                                                                <span className={cn('h-2 w-2 rounded-full', st.status === 'completed' ? 'bg-emerald-500' : 'bg-slate-300')} />
                                                                                {st.title}
                                                                            </li>
                                                                        ))}
                                                                    </ul>
                                                                </div>
                                                            )}
                                                            {task.comments?.length > 0 && (
                                                                <div>
                                                                    <p className="text-xs font-medium uppercase text-slate-500">Comments</p>
                                                                    <div className="mt-1 space-y-2">
                                                                        {task.comments.map((c) => (
                                                                            <div key={c.id} className="rounded bg-slate-50 px-3 py-2">
                                                                                <p className="text-xs font-medium text-slate-700">
                                                                                    {c.user?.name || c.author?.full_name || c.employee?.full_name || 'Unknown'}
                                                                                </p>
                                                                                <p className="text-sm text-slate-600">{c.body || c.content}</p>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            )}
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <Input
                                                                    value={expandedTask === task.id ? commentText : ''}
                                                                    onChange={(e) => setCommentText(e.target.value)}
                                                                    placeholder="Add a comment..."
                                                                    className="flex-1"
                                                                />
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => commentMut.mutate({ taskId: task.id, content: commentText })}
                                                                    disabled={!commentText.trim() || commentMut.isPending}
                                                                >
                                                                    <MessageSquare className="mr-1 h-3.5 w-3.5" />
                                                                    Comment
                                                                </Button>
                                                                <Button variant="outline" size="sm" onClick={() => onEdit(task)}>
                                                                    <Pencil className="mr-1 h-3.5 w-3.5" />
                                                                    Edit details
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </Fragment>
                                    );
                                })}
                            </TableBody>
                        </Table>

                        {lastPage > 1 && (
                            <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3">
                                <p className="text-sm text-slate-500">Page {page} of {lastPage}</p>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                        <ChevronLeft className="h-4 w-4" />
                                    </Button>
                                    <Button variant="outline" size="sm" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}

// ==================== TASK FORM DIALOG (create / edit) ====================

/**
 * Searchable multi-select of employees for the task form. A task can be co-owned
 * by several people; the first selected becomes the canonical assignee server-side.
 */
function AssigneeMultiSelect({ employees, value, onChange, error }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const ref = useRef(null);

    useEffect(() => {
        function onDocClick(e) {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onDocClick);
        return () => document.removeEventListener('mousedown', onDocClick);
    }, []);

    const selected = new Set(value);
    const byId = new Map(employees.map((e) => [e.id, e.full_name]));
    const needle = query.trim().toLowerCase();
    const filtered = needle ? employees.filter((e) => e.full_name.toLowerCase().includes(needle)) : employees;

    function toggle(id) {
        onChange(selected.has(id) ? value.filter((x) => x !== id) : [...value, id]);
    }

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className={cn(
                    'flex min-h-[38px] w-full flex-wrap items-center gap-1.5 rounded-md border bg-white px-2.5 py-1.5 text-left text-sm transition focus:outline-none focus:ring-2 focus:ring-blue-500/30',
                    error ? 'border-red-300' : 'border-slate-200'
                )}
            >
                {value.length === 0 ? (
                    <span className="text-slate-400">Select staff...</span>
                ) : (
                    value.map((id) => (
                        <span key={id} className="inline-flex items-center gap-1 rounded-md bg-blue-50 px-1.5 py-0.5 text-xs font-medium text-blue-700">
                            {byId.get(id) ?? id}
                            <span
                                role="button"
                                tabIndex={-1}
                                onClick={(e) => { e.stopPropagation(); toggle(id); }}
                                className="grid h-3.5 w-3.5 cursor-pointer place-items-center rounded hover:bg-blue-100"
                            >
                                <X className="h-3 w-3" />
                            </span>
                        </span>
                    ))
                )}
                <ChevronDown className="ml-auto h-4 w-4 shrink-0 text-slate-400" />
            </button>

            {open && (
                <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg">
                    <div className="relative border-b border-slate-100">
                        <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                        <input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search staff..."
                            className="w-full bg-transparent py-2 pl-8 pr-3 text-sm text-slate-900 outline-none"
                        />
                    </div>
                    <div className="max-h-52 overflow-y-auto py-1">
                        {filtered.length === 0 ? (
                            <div className="px-3 py-2 text-sm text-slate-400">No staff found</div>
                        ) : (
                            filtered.map((e) => {
                                const on = selected.has(e.id);
                                return (
                                    <button
                                        type="button"
                                        key={e.id}
                                        onClick={() => toggle(e.id)}
                                        className="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-slate-700 transition-colors hover:bg-slate-50"
                                    >
                                        <span className={cn('grid h-4 w-4 shrink-0 place-items-center rounded border transition-colors', on ? 'border-transparent bg-blue-600 text-white' : 'border-slate-300')}>
                                            {on && <Check className="h-3 w-3" strokeWidth={3} />}
                                        </span>
                                        {e.full_name}
                                    </button>
                                );
                            })
                        )}
                    </div>
                    {value.length > 0 && (
                        <div className="border-t border-slate-100 px-3 py-1.5 text-xs text-slate-500">
                            {value.length} selected
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function TaskFormDialog({ open, onClose, task, employees, categories, saveMut }) {
    const isEdit = Boolean(task);
    const [form, setForm] = useState(EMPTY_TASK_FORM);
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (!open) return;
        if (task) {
            const assigneeIds = Array.isArray(task.assignees) && task.assignees.length
                ? task.assignees.map((a) => a.id)
                : task.assigned_to ? [task.assigned_to] : [];
            setForm({
                title: task.title || '',
                description: task.description || '',
                assignee_ids: assigneeIds,
                category_id: task.category_id ? String(task.category_id) : 'none',
                priority: task.priority || 'medium',
                deadline: task.deadline ? task.deadline.split('T')[0] : '',
            });
        } else {
            setForm(EMPTY_TASK_FORM);
        }
        setErrors({});
    }, [open, task]);

    function field(key, value) {
        setForm((f) => ({ ...f, [key]: value }));
    }

    function submit() {
        const payload = {
            title: form.title,
            description: form.description || null,
            assignee_ids: form.assignee_ids,
            category_id: form.category_id === 'none' ? null : form.category_id,
            priority: form.priority,
            deadline: form.deadline,
        };
        saveMut.mutate(
            { id: task?.id, payload },
            {
                onSuccess: () => onClose(),
                onError: (err) => setErrors(err.response?.data?.errors || {}),
            }
        );
    }

    const valid = form.title.trim() && form.assignee_ids.length > 0 && form.deadline;

    return (
        <Dialog
            open={open}
            onOpenChange={(o) => { if (!o) onClose(); }}
        >
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Edit Task' : 'New Task'}</DialogTitle>
                    <DialogDescription>
                        {isEdit ? 'Update this task.' : 'Add a task directly — no meeting required.'}
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                    <div>
                        <Label>Title *</Label>
                        <Input value={form.title} onChange={(e) => field('title', e.target.value)} placeholder="What needs to be done?" />
                        {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title[0]}</p>}
                    </div>
                    <div>
                        <Label>Description</Label>
                        <Textarea value={form.description} onChange={(e) => field('description', e.target.value)} placeholder="Additional details..." rows={3} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Assignees *</Label>
                            <AssigneeMultiSelect
                                employees={employees}
                                value={form.assignee_ids}
                                onChange={(ids) => field('assignee_ids', ids)}
                                error={Boolean(errors.assignee_ids || errors.assigned_to)}
                            />
                            {(errors.assignee_ids || errors.assigned_to) && (
                                <p className="mt-1 text-xs text-red-600">{(errors.assignee_ids || errors.assigned_to)[0]}</p>
                            )}
                        </div>
                        <div>
                            <Label>Category</Label>
                            <Select value={form.category_id} onValueChange={(v) => field('category_id', v)}>
                                <SelectTrigger><SelectValue placeholder="Uncategorized" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Uncategorized</SelectItem>
                                    {categories.filter((c) => c.is_active).map((c) => (
                                        <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Priority</Label>
                            <Select value={form.priority} onValueChange={(v) => field('priority', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Low</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="urgent">Urgent</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Deadline *</Label>
                            <Input type="date" value={form.deadline} onChange={(e) => field('deadline', e.target.value)} />
                            {errors.deadline && <p className="mt-1 text-xs text-red-600">{errors.deadline[0]}</p>}
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit} disabled={!valid || saveMut.isPending}>
                        {saveMut.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                        {isEdit ? 'Save Changes' : 'Create Task'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// ==================== MANAGE CATEGORIES DIALOG ====================

function ManageCategoriesDialog({ open, onClose, categories }) {
    const queryClient = useQueryClient();
    const { toast } = useToast();
    const [form, setForm] = useState(EMPTY_CATEGORY_FORM);
    const [editingId, setEditingId] = useState(null);
    const [errors, setErrors] = useState({});

    function invalidate() {
        queryClient.invalidateQueries({ queryKey: ['hr', 'task-categories'] });
        queryClient.invalidateQueries({ queryKey: ['hr', 'tasks'] });
    }

    const saveMut = useMutation({
        mutationFn: ({ id, payload }) => (id ? updateTaskCategory(id, payload) : createTaskCategory(payload)),
        onSuccess: (data, variables) => {
            invalidate();
            resetForm();
            toast.success(variables.id ? 'Category updated' : 'Category created');
        },
        onError: (err) => {
            setErrors(err.response?.data?.errors || {});
            toast.error('Could not save category', 'Please check the form and try again.');
        },
    });

    const deleteMut = useMutation({
        mutationFn: (id) => deleteTaskCategory(id),
        onSuccess: () => {
            invalidate();
            if (editingId) resetForm();
            toast.success('Category deleted');
        },
        onError: () => toast.error('Could not delete category', 'Please try again.'),
    });

    function resetForm() {
        setForm(EMPTY_CATEGORY_FORM);
        setEditingId(null);
        setErrors({});
    }

    function startEdit(category) {
        setEditingId(category.id);
        setForm({
            name: category.name || '',
            color: category.color || '#6366f1',
            description: category.description || '',
            is_active: category.is_active ?? true,
        });
        setErrors({});
    }

    function submit() {
        saveMut.mutate({ id: editingId, payload: form });
    }

    return (
        <Dialog open={open} onOpenChange={(o) => { if (!o) { resetForm(); onClose(); } }}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Manage Task Categories</DialogTitle>
                    <DialogDescription>Customize the categories used to organize and group tasks.</DialogDescription>
                </DialogHeader>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Existing list */}
                    <div className="space-y-1.5">
                        <p className="text-xs font-medium uppercase text-slate-500">Categories</p>
                        <div className="max-h-72 space-y-1 overflow-y-auto pr-1">
                            {categories.length === 0 && (
                                <p className="py-6 text-center text-sm text-slate-400">No categories yet.</p>
                            )}
                            {categories.map((c) => (
                                <div
                                    key={c.id}
                                    className={cn(
                                        'flex items-center gap-2 rounded-lg border px-3 py-2',
                                        editingId === c.id ? 'border-blue-300 bg-blue-50/50' : 'border-slate-200'
                                    )}
                                >
                                    <span className="h-3 w-3 shrink-0 rounded-full" style={{ backgroundColor: c.color }} />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-slate-800">
                                            {c.name}
                                            {!c.is_active && <span className="ml-1.5 text-xs font-normal text-slate-400">(inactive)</span>}
                                        </p>
                                        {typeof c.tasks_count === 'number' && (
                                            <p className="text-xs text-slate-400">{c.tasks_count} task{c.tasks_count === 1 ? '' : 's'}</p>
                                        )}
                                    </div>
                                    <Button variant="ghost" size="icon" className="h-7 w-7 text-slate-400" onClick={() => startEdit(c)}>
                                        <Pencil className="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-7 w-7 text-red-500 hover:text-red-600"
                                        onClick={() => deleteMut.mutate(c.id)}
                                        disabled={deleteMut.isPending}
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Add / edit form */}
                    <div className="space-y-3 rounded-lg border border-slate-200 p-3">
                        <div className="flex items-center justify-between">
                            <p className="text-sm font-semibold text-slate-700">{editingId ? 'Edit Category' : 'New Category'}</p>
                            {editingId && (
                                <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={resetForm}>
                                    <X className="mr-1 h-3 w-3" /> Cancel edit
                                </Button>
                            )}
                        </div>
                        <div>
                            <Label>Name *</Label>
                            <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} placeholder="e.g. Recruitment" />
                            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name[0]}</p>}
                        </div>
                        <div>
                            <Label>Color</Label>
                            <div className="flex flex-wrap items-center gap-1.5">
                                {COLOR_SWATCHES.map((hex) => (
                                    <button
                                        key={hex}
                                        type="button"
                                        onClick={() => setForm((f) => ({ ...f, color: hex }))}
                                        className={cn(
                                            'h-7 w-7 rounded-full border-2 transition-transform hover:scale-110',
                                            form.color.toLowerCase() === hex ? 'border-slate-800' : 'border-transparent'
                                        )}
                                        style={{ backgroundColor: hex }}
                                        aria-label={hex}
                                    />
                                ))}
                                <input
                                    type="color"
                                    value={form.color}
                                    onChange={(e) => setForm((f) => ({ ...f, color: e.target.value }))}
                                    className="h-7 w-9 cursor-pointer rounded border border-slate-300 bg-white p-0.5"
                                    aria-label="Custom color"
                                />
                            </div>
                            {errors.color && <p className="mt-1 text-xs text-red-600">{errors.color[0]}</p>}
                        </div>
                        <div>
                            <Label>Description</Label>
                            <Textarea value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} rows={2} placeholder="Optional" />
                        </div>
                        <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={form.is_active}
                                onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
                                className="rounded border-slate-300"
                            />
                            Active (available when creating tasks)
                        </label>
                        <Button className="w-full" onClick={submit} disabled={!form.name.trim() || saveMut.isPending}>
                            {saveMut.isPending ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Check className="mr-1.5 h-4 w-4" />}
                            {editingId ? 'Save Category' : 'Add Category'}
                        </Button>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => { resetForm(); onClose(); }}>Done</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// ==================== MAIN COMPONENT ====================

export default function TaskDashboard() {
    const navigate = useNavigate();
    const location = useLocation();
    const queryClient = useQueryClient();
    const { toast } = useToast();

    const validViews = VIEW_MODES.map((v) => v.key);
    const hashView = location.hash.replace('#', '');
    const [viewMode, setViewMode] = useState(validViews.includes(hashView) ? hashView : 'group');
    const [groupBy, setGroupBy] = useState('category');

    function handleViewChange(mode) {
        setViewMode(mode);
        navigate(`#${mode}`, { replace: true });
    }

    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [priorityFilter, setPriorityFilter] = useState('all');
    const [categoryFilter, setCategoryFilter] = useState('all');
    const [page, setPage] = useState(1);
    const [expandedTask, setExpandedTask] = useState(null);
    const [commentText, setCommentText] = useState('');

    const [taskDialog, setTaskDialog] = useState({ open: false, task: null });
    const [categoriesDialogOpen, setCategoriesDialogOpen] = useState(false);

    const isTable = viewMode === 'table';

    const params = {
        search: search || undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
        priority: priorityFilter !== 'all' ? priorityFilter : undefined,
        category_id: categoryFilter !== 'all' ? categoryFilter : undefined,
        page: isTable ? page : undefined,
        per_page: isTable ? undefined : 200,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'tasks', params],
        queryFn: () => fetchMeetingTasks(params),
    });

    const { data: categoriesData } = useQuery({
        queryKey: ['hr', 'task-categories'],
        queryFn: () => fetchTaskCategories({ with_counts: true }),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'all'],
        queryFn: () => fetchEmployees({ per_page: 200 }),
    });

    const categories = categoriesData?.data || [];
    const employees = employeesData?.data || [];

    const statusMut = useMutation({
        mutationFn: ({ id, status }) => updateTaskStatus(id, { status }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'tasks'] });
            toast.success('Task status updated');
        },
        onError: () => toast.error('Could not update status', 'Please try again.'),
    });

    const commentMut = useMutation({
        mutationFn: ({ taskId, content }) => addTaskComment(taskId, { content }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'tasks'] });
            setCommentText('');
            toast.success('Comment added');
        },
        onError: () => toast.error('Could not add comment', 'Please try again.'),
    });

    const saveTaskMut = useMutation({
        mutationFn: ({ id, payload }) => (id ? updateMeetingTaskItem(id, payload) : createTask(payload)),
        onSuccess: (data, variables) => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'tasks'] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'task-categories'] });
            toast.success(variables.id ? 'Task updated' : 'Task created');
        },
        onError: () => toast.error('Could not save task', 'Please check the form and try again.'),
    });

    const tasks = data?.data || [];
    const pagination = data?.meta || data || {};
    const lastPage = pagination.last_page || 1;

    const resetPage = useCallback(() => setPage(1), []);

    const totalTasks = pagination.total || tasks.length;
    const pendingCount = tasks.filter((t) => t.status === 'pending').length;
    const inProgressCount = tasks.filter((t) => t.status === 'in_progress').length;
    const completedCount = tasks.filter((t) => t.status === 'completed').length;
    const overdueCount = tasks.filter((t) => isOverdue(t)).length;

    function handleKanbanStatusChange(taskId, newStatus) {
        statusMut.mutate({ id: taskId, status: newStatus });
    }

    function openCreate() {
        setTaskDialog({ open: true, task: null });
    }

    function openEdit(task) {
        setTaskDialog({ open: true, task });
    }

    return (
        <div>
            <PageHeader
                title="Task Dashboard"
                description="Track action items across meetings and standalone tasks."
                action={
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" onClick={() => navigate('/meetings')}>
                            <ArrowLeft className="mr-1.5 h-4 w-4" />
                            Meetings
                        </Button>
                        <Button variant="outline" onClick={() => setCategoriesDialogOpen(true)}>
                            <Tags className="mr-1.5 h-4 w-4" />
                            Categories
                        </Button>
                        <Button onClick={openCreate}>
                            <Plus className="mr-1.5 h-4 w-4" />
                            New Task
                        </Button>
                    </div>
                }
            />

            {/* Stats */}
            <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
                <StatCard label="Total" value={totalTasks} icon={ListTodo} accent="indigo" />
                <StatCard label="Pending" value={pendingCount} icon={Clock} accent="slate" />
                <StatCard label="In Progress" value={inProgressCount} icon={Clock} accent="amber" />
                <StatCard label="Completed" value={completedCount} icon={CheckCircle2} accent="emerald" />
                <StatCard label="Overdue" value={overdueCount} icon={AlertTriangle} accent="rose" />
            </div>

            {/* Filters + View Toggle */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center">
                        <SearchInput
                            value={search}
                            onChange={(v) => { setSearch(v); resetPage(); }}
                            placeholder="Search tasks..."
                            className="lg:w-72"
                        />
                        <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-[150px]"><SelectValue placeholder="All Status" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="in_progress">In Progress</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={priorityFilter} onValueChange={(v) => { setPriorityFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-[150px]"><SelectValue placeholder="All Priority" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Priority</SelectItem>
                                <SelectItem value="low">Low</SelectItem>
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                                <SelectItem value="urgent">Urgent</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={categoryFilter} onValueChange={(v) => { setCategoryFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-[170px]"><SelectValue placeholder="All Categories" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Categories</SelectItem>
                                <SelectItem value="none">Uncategorized</SelectItem>
                                {categories.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {viewMode === 'group' && (
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-slate-500">Group by</span>
                                <Select value={groupBy} onValueChange={setGroupBy}>
                                    <SelectTrigger className="w-[140px]"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {GROUP_BY_OPTIONS.map((o) => (
                                            <SelectItem key={o.key} value={o.key}>{o.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {/* View Mode Toggle */}
                        <div className="flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 lg:ml-auto">
                            {VIEW_MODES.map(({ key, label, icon: Icon }) => (
                                <button
                                    key={key}
                                    onClick={() => handleViewChange(key)}
                                    className={cn(
                                        'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                        viewMode === key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                                    )}
                                    title={label}
                                >
                                    <Icon className="h-4 w-4" />
                                    <span className="hidden sm:inline">{label}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Content */}
            {isLoading ? (
                <Card>
                    <CardContent className="p-0">
                        <div className="flex items-center justify-center py-20">
                            <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
                        </div>
                    </CardContent>
                </Card>
            ) : viewMode === 'group' ? (
                <GroupView tasks={tasks} groupBy={groupBy} categories={categories} statusMut={statusMut} onEdit={openEdit} />
            ) : viewMode === 'kanban' ? (
                <KanbanView tasks={tasks} onStatusChange={handleKanbanStatusChange} onEdit={openEdit} />
            ) : viewMode === 'calendar' ? (
                <CalendarView tasks={tasks} onEdit={openEdit} />
            ) : (
                <TableView
                    tasks={tasks}
                    expandedTask={expandedTask}
                    setExpandedTask={setExpandedTask}
                    commentText={commentText}
                    setCommentText={setCommentText}
                    statusMut={statusMut}
                    commentMut={commentMut}
                    page={page}
                    setPage={setPage}
                    lastPage={lastPage}
                    onEdit={openEdit}
                />
            )}

            <TaskFormDialog
                open={taskDialog.open}
                task={taskDialog.task}
                onClose={() => setTaskDialog({ open: false, task: null })}
                employees={employees}
                categories={categories}
                saveMut={saveTaskMut}
            />

            <ManageCategoriesDialog
                open={categoriesDialogOpen}
                onClose={() => setCategoriesDialogOpen(false)}
                categories={categories}
            />
        </div>
    );
}
