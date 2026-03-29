import { useState, useCallback, useMemo } from 'react';
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
    MessageSquare,
    LayoutList,
    Columns3,
    CalendarDays,
    GripVertical,
    User,
} from 'lucide-react';
import { fetchMeetingTasks, updateTaskStatus, addTaskComment } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
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
    { key: 'pending', label: 'Pending', color: 'bg-zinc-400', bgColor: 'bg-zinc-50' },
    { key: 'in_progress', label: 'In Progress', color: 'bg-amber-400', bgColor: 'bg-amber-50' },
    { key: 'completed', label: 'Completed', color: 'bg-emerald-400', bgColor: 'bg-emerald-50' },
    { key: 'cancelled', label: 'Cancelled', color: 'bg-red-400', bgColor: 'bg-red-50' },
];

const PRIORITY_COLORS = {
    urgent: 'bg-red-500',
    high: 'bg-orange-500',
    medium: 'bg-blue-500',
    low: 'bg-zinc-400',
};

const PRIORITY_DOT_COLORS = {
    urgent: 'bg-red-100 text-red-700 border-red-200',
    high: 'bg-orange-100 text-orange-700 border-orange-200',
    medium: 'bg-blue-100 text-blue-700 border-blue-200',
    low: 'bg-zinc-100 text-zinc-600 border-zinc-200',
};

const VIEW_MODES = [
    { key: 'table', label: 'Table', icon: LayoutList },
    { key: 'kanban', label: 'Kanban', icon: Columns3 },
    { key: 'calendar', label: 'Calendar', icon: CalendarDays },
];

function TaskPriorityBadge({ priority }) {
    const config = PRIORITY_BADGE[priority] || { label: priority, variant: 'secondary' };
    return <Badge variant={config.variant} className={config.className}>{config.label}</Badge>;
}

function TaskStatusBadge({ status }) {
    const config = STATUS_BADGE[status] || { label: status, variant: 'secondary' };
    return <Badge variant={config.variant} className={config.className}>{config.label}</Badge>;
}

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' });
}

function isOverdue(task) {
    return task.deadline && new Date(task.deadline) < new Date() && task.status !== 'completed' && task.status !== 'cancelled';
}

// ==================== KANBAN VIEW ====================

function KanbanCard({ task, index }) {
    const overdue = isOverdue(task);
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
                            className="mt-0.5 cursor-grab text-zinc-300 hover:text-zinc-500"
                        >
                            <GripVertical className="h-4 w-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <Link
                                to={`/meetings/${task.taskable_id || task.meeting?.id}`}
                                className="text-sm font-medium text-zinc-900 hover:text-blue-600 line-clamp-2"
                            >
                                {task.title}
                            </Link>

                            {task.meeting && (
                                <p className="mt-0.5 truncate text-xs text-zinc-400">
                                    {task.meeting.title}
                                </p>
                            )}

                            <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                <TaskPriorityBadge priority={task.priority} />
                                {task.deadline && (
                                    <span className={cn(
                                        'text-xs',
                                        overdue ? 'font-medium text-red-600' : 'text-zinc-500'
                                    )}>
                                        {formatDate(task.deadline)}
                                    </span>
                                )}
                            </div>

                            {task.assignee && (
                                <div className="mt-2 flex items-center gap-1.5">
                                    <div className="flex h-5 w-5 items-center justify-center rounded-full bg-zinc-200">
                                        <User className="h-3 w-3 text-zinc-500" />
                                    </div>
                                    <span className="truncate text-xs text-zinc-500">{task.assignee.full_name}</span>
                                </div>
                            )}

                            {(task.subtasks_count > 0 || task.comments_count > 0 || task.subtasks?.length > 0 || task.comments?.length > 0) && (
                                <div className="mt-2 flex items-center gap-3 text-xs text-zinc-400">
                                    {(task.subtasks_count > 0 || task.subtasks?.length > 0) && (
                                        <span className="flex items-center gap-0.5">
                                            <CheckCircle2 className="h-3 w-3" />
                                            {task.subtasks_count || task.subtasks?.length}
                                        </span>
                                    )}
                                    {(task.comments_count > 0 || task.comments?.length > 0) && (
                                        <span className="flex items-center gap-0.5">
                                            <MessageSquare className="h-3 w-3" />
                                            {task.comments_count || task.comments?.length}
                                        </span>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </Draggable>
    );
}

function KanbanColumn({ column, tasks }) {
    return (
        <div className="flex w-72 shrink-0 flex-col lg:w-full lg:shrink">
            <div className={cn('mb-3 flex items-center gap-2 rounded-lg px-3 py-2', column.bgColor)}>
                <div className={cn('h-2.5 w-2.5 rounded-full', column.color)} />
                <h3 className="text-sm font-semibold text-zinc-700">{column.label}</h3>
                <span className="ml-auto rounded-full bg-white/80 px-2 py-0.5 text-xs font-medium text-zinc-500">
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
                            <KanbanCard key={task.id} task={task} index={index} />
                        ))}
                        {provided.placeholder}
                        {tasks.length === 0 && !snapshot.isDraggingOver && (
                            <div className="flex items-center justify-center py-8 text-xs text-zinc-400">
                                Drop tasks here
                            </div>
                        )}
                    </div>
                )}
            </Droppable>
        </div>
    );
}

function KanbanView({ tasks, onStatusChange }) {
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
                    <KanbanColumn key={col.key} column={col} tasks={grouped[col.key]} />
                ))}
            </div>
        </DragDropContext>
    );
}

// ==================== CALENDAR VIEW ====================

function CalendarView({ tasks }) {
    const [currentDate, setCurrentDate] = useState(new Date());
    const [selectedDate, setSelectedDate] = useState(null);

    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const monthName = currentDate.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' });

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startPad = firstDay.getDay(); // 0=Sun
    const totalDays = lastDay.getDate();

    const tasksByDate = useMemo(() => {
        const map = {};
        tasks.forEach((task) => {
            if (!task.deadline) return;
            const key = task.deadline.split('T')[0]; // YYYY-MM-DD
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
                        {/* Month Navigation */}
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-zinc-900">{monthName}</h3>
                            <div className="flex gap-1">
                                <Button variant="outline" size="sm" onClick={goToday}>
                                    Today
                                </Button>
                                <Button variant="outline" size="icon" className="h-8 w-8" onClick={prevMonth}>
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <Button variant="outline" size="icon" className="h-8 w-8" onClick={nextMonth}>
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        {/* Weekday Headers */}
                        <div className="grid grid-cols-7 gap-px">
                            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => (
                                <div key={d} className="py-2 text-center text-xs font-medium uppercase text-zinc-400">
                                    {d}
                                </div>
                            ))}
                        </div>

                        {/* Calendar Grid */}
                        <div className="grid grid-cols-7 gap-px rounded-lg border border-zinc-200 bg-zinc-200">
                            {calendarDays.map(({ day, dateStr, key }) => {
                                if (day === null) {
                                    return <div key={key} className="bg-zinc-50 p-2" style={{ minHeight: 80 }} />;
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
                                            isToday ? 'bg-blue-600 font-bold text-white' : 'text-zinc-700'
                                        )}>
                                            {day}
                                        </span>
                                        <div className="mt-1 space-y-0.5">
                                            {dayTasks.slice(0, 3).map((task) => (
                                                <div
                                                    key={task.id}
                                                    className={cn(
                                                        'truncate rounded px-1 py-0.5 text-xs font-medium',
                                                        PRIORITY_DOT_COLORS[task.priority] || 'bg-zinc-100 text-zinc-600'
                                                    )}
                                                    title={task.title}
                                                >
                                                    {task.title}
                                                </div>
                                            ))}
                                            {dayTasks.length > 3 && (
                                                <div className="px-1 text-xs text-zinc-400">
                                                    +{dayTasks.length - 3} more
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Legend */}
                        <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-zinc-500">
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

            {/* Side Panel — Tasks for Selected Date */}
            <div className="lg:col-span-1">
                <Card className="sticky top-4">
                    <CardContent className="p-4">
                        <h4 className="mb-3 text-sm font-semibold text-zinc-900">
                            {selectedDate
                                ? `Tasks for ${new Date(selectedDate + 'T00:00:00').toLocaleDateString('en-MY', { weekday: 'short', day: 'numeric', month: 'short' })}`
                                : 'Select a date'
                            }
                        </h4>
                        {!selectedDate ? (
                            <p className="text-sm text-zinc-400">Click a date on the calendar to view tasks.</p>
                        ) : selectedTasks.length === 0 ? (
                            <div className="flex flex-col items-center py-8 text-center">
                                <CalendarDays className="mb-2 h-8 w-8 text-zinc-300" />
                                <p className="text-sm text-zinc-400">No tasks due on this date.</p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {selectedTasks.map((task) => (
                                    <Link
                                        key={task.id}
                                        to={`/meetings/${task.taskable_id || task.meeting?.id}`}
                                        className="block rounded-lg border p-3 transition-colors hover:bg-zinc-50"
                                    >
                                        <p className="text-sm font-medium text-zinc-900">{task.title}</p>
                                        {task.meeting && (
                                            <p className="mt-0.5 text-xs text-zinc-400">{task.meeting.title}</p>
                                        )}
                                        <div className="mt-2 flex items-center gap-2">
                                            <TaskPriorityBadge priority={task.priority} />
                                            <TaskStatusBadge status={task.status} />
                                        </div>
                                        {task.assignee && (
                                            <div className="mt-2 flex items-center gap-1.5 text-xs text-zinc-500">
                                                <User className="h-3 w-3" />
                                                {task.assignee.full_name}
                                            </div>
                                        )}
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

// ==================== TABLE VIEW ====================

function TableView({ tasks, isLoading, expandedTask, setExpandedTask, commentText, setCommentText, statusMut, commentMut, page, setPage, lastPage }) {
    return (
        <Card>
            <CardContent className="p-0">
                {isLoading ? (
                    <div className="flex items-center justify-center py-20">
                        <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
                    </div>
                ) : tasks.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <ListTodo className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">No tasks found</h3>
                        <p className="mt-1 text-sm text-zinc-500">Tasks created in meetings will appear here.</p>
                    </div>
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-8" />
                                    <TableHead>Task</TableHead>
                                    <TableHead>Meeting</TableHead>
                                    <TableHead>Assignee</TableHead>
                                    <TableHead>Priority</TableHead>
                                    <TableHead>Deadline</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tasks.map((task) => (
                                    <>
                                        <TableRow key={task.id}>
                                            <TableCell className="w-8">
                                                <button onClick={() => setExpandedTask(expandedTask === task.id ? null : task.id)}>
                                                    <ChevronDown
                                                        className={cn(
                                                            'h-4 w-4 text-zinc-400 transition-transform',
                                                            expandedTask === task.id && 'rotate-180'
                                                        )}
                                                    />
                                                </button>
                                            </TableCell>
                                            <TableCell>
                                                <p className="font-medium text-zinc-900">{task.title}</p>
                                            </TableCell>
                                            <TableCell>
                                                {task.meeting ? (
                                                    <Link
                                                        to={`/meetings/${task.meeting_id || task.meeting?.id}`}
                                                        className="text-sm text-blue-600 hover:underline"
                                                    >
                                                        {task.meeting.title}
                                                    </Link>
                                                ) : '-'}
                                            </TableCell>
                                            <TableCell>{task.assignee?.full_name || '-'}</TableCell>
                                            <TableCell><TaskPriorityBadge priority={task.priority} /></TableCell>
                                            <TableCell>
                                                <span className={cn(isOverdue(task) && 'font-medium text-red-600')}>
                                                    {formatDate(task.deadline)}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <Select
                                                    value={task.status}
                                                    onValueChange={(v) => statusMut.mutate({ id: task.id, status: v })}
                                                >
                                                    <SelectTrigger className="h-8 w-[130px]">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="pending">Pending</SelectItem>
                                                        <SelectItem value="in_progress">In Progress</SelectItem>
                                                        <SelectItem value="completed">Completed</SelectItem>
                                                        <SelectItem value="cancelled">Cancelled</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </TableCell>
                                        </TableRow>
                                        {expandedTask === task.id && (
                                            <TableRow key={`${task.id}-expanded`}>
                                                <TableCell colSpan={7}>
                                                    <div className="space-y-3 px-4 py-2">
                                                        {task.description && (
                                                            <p className="text-sm text-zinc-600">{task.description}</p>
                                                        )}
                                                        {task.subtasks?.length > 0 && (
                                                            <div>
                                                                <p className="text-xs font-medium uppercase text-zinc-500">Subtasks</p>
                                                                <ul className="mt-1 space-y-1">
                                                                    {task.subtasks.map((st) => (
                                                                        <li key={st.id} className="flex items-center gap-2 text-sm">
                                                                            <span className={cn(
                                                                                'h-2 w-2 rounded-full',
                                                                                st.status === 'completed' ? 'bg-emerald-500' : 'bg-zinc-300'
                                                                            )} />
                                                                            {st.title}
                                                                        </li>
                                                                    ))}
                                                                </ul>
                                                            </div>
                                                        )}
                                                        {task.comments?.length > 0 && (
                                                            <div>
                                                                <p className="text-xs font-medium uppercase text-zinc-500">Comments</p>
                                                                <div className="mt-1 space-y-2">
                                                                    {task.comments.map((c) => (
                                                                        <div key={c.id} className="rounded bg-zinc-50 px-3 py-2">
                                                                            <p className="text-xs font-medium text-zinc-700">
                                                                                {c.user?.name || c.author?.full_name || 'Unknown'}
                                                                            </p>
                                                                            <p className="text-sm text-zinc-600">{c.body || c.content}</p>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        )}
                                                        <div className="flex gap-2">
                                                            <Input
                                                                value={expandedTask === task.id ? commentText : ''}
                                                                onChange={(e) => setCommentText(e.target.value)}
                                                                placeholder="Add a comment..."
                                                                className="flex-1"
                                                            />
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => commentMut.mutate({ taskId: task.id, body: commentText })}
                                                                disabled={!commentText.trim() || commentMut.isPending}
                                                            >
                                                                <MessageSquare className="mr-1 h-3.5 w-3.5" />
                                                                Comment
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </>
                                ))}
                            </TableBody>
                        </Table>

                        {lastPage > 1 && (
                            <div className="flex items-center justify-between border-t border-zinc-200 px-4 py-3">
                                <p className="text-sm text-zinc-500">Page {page} of {lastPage}</p>
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

// ==================== MAIN COMPONENT ====================

export default function TaskDashboard() {
    const navigate = useNavigate();
    const location = useLocation();
    const queryClient = useQueryClient();

    const validViews = ['table', 'kanban', 'calendar'];
    const hashView = location.hash.replace('#', '');
    const [viewMode, setViewMode] = useState(validViews.includes(hashView) ? hashView : 'table');

    function handleViewChange(mode) {
        setViewMode(mode);
        navigate(`#${mode}`, { replace: true });
    }
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [priorityFilter, setPriorityFilter] = useState('all');
    const [page, setPage] = useState(1);
    const [expandedTask, setExpandedTask] = useState(null);
    const [commentText, setCommentText] = useState('');

    const params = {
        search: search || undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
        priority: priorityFilter !== 'all' ? priorityFilter : undefined,
        page: viewMode === 'table' ? page : undefined,
        per_page: viewMode !== 'table' ? 200 : undefined,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'tasks', params],
        queryFn: () => fetchMeetingTasks(params),
    });

    const statusMut = useMutation({
        mutationFn: ({ id, status }) => updateTaskStatus(id, { status }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['hr', 'tasks'] }),
    });

    const commentMut = useMutation({
        mutationFn: ({ taskId, body }) => addTaskComment(taskId, { body }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'tasks'] });
            setCommentText('');
        },
    });

    const tasks = data?.data || [];
    const pagination = data?.meta || data || {};
    const lastPage = pagination.last_page || 1;

    const resetPage = useCallback(() => setPage(1), []);

    // Stats
    const totalTasks = pagination.total || tasks.length;
    const pendingCount = tasks.filter((t) => t.status === 'pending').length;
    const inProgressCount = tasks.filter((t) => t.status === 'in_progress').length;
    const completedCount = tasks.filter((t) => t.status === 'completed').length;
    const overdueCount = tasks.filter((t) => isOverdue(t)).length;

    function handleKanbanStatusChange(taskId, newStatus) {
        statusMut.mutate({ id: taskId, status: newStatus });
    }

    return (
        <div>
            <PageHeader
                title="Task Dashboard"
                description="Track action items across all meetings."
                action={
                    <Button variant="outline" onClick={() => navigate('/meetings')}>
                        <ArrowLeft className="mr-1.5 h-4 w-4" />
                        Back to Meetings
                    </Button>
                }
            />

            {/* Stats */}
            <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100">
                                <ListTodo className="h-5 w-5 text-zinc-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">{totalTasks}</p>
                                <p className="text-xs text-zinc-500">Total</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100">
                                <Clock className="h-5 w-5 text-zinc-500" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">{pendingCount}</p>
                                <p className="text-xs text-zinc-500">Pending</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50">
                                <Clock className="h-5 w-5 text-amber-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">{inProgressCount}</p>
                                <p className="text-xs text-zinc-500">In Progress</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50">
                                <CheckCircle2 className="h-5 w-5 text-emerald-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">{completedCount}</p>
                                <p className="text-xs text-zinc-500">Completed</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50">
                                <AlertTriangle className="h-5 w-5 text-red-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">{overdueCount}</p>
                                <p className="text-xs text-zinc-500">Overdue</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Filters + View Toggle */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <SearchInput
                            value={search}
                            onChange={(v) => { setSearch(v); resetPage(); }}
                            placeholder="Search tasks..."
                            className="lg:w-80"
                        />
                        <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-[160px]">
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="in_progress">In Progress</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={priorityFilter} onValueChange={(v) => { setPriorityFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-[160px]">
                                <SelectValue placeholder="All Priority" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Priority</SelectItem>
                                <SelectItem value="low">Low</SelectItem>
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                                <SelectItem value="urgent">Urgent</SelectItem>
                            </SelectContent>
                        </Select>

                        {/* View Mode Toggle */}
                        <div className="ml-auto flex rounded-lg border border-zinc-200 bg-zinc-50 p-0.5">
                            {VIEW_MODES.map(({ key, label, icon: Icon }) => (
                                <button
                                    key={key}
                                    onClick={() => handleViewChange(key)}
                                    className={cn(
                                        'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                        viewMode === key
                                            ? 'bg-white text-zinc-900 shadow-sm'
                                            : 'text-zinc-500 hover:text-zinc-700'
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
                            <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
                        </div>
                    </CardContent>
                </Card>
            ) : viewMode === 'kanban' ? (
                <KanbanView tasks={tasks} onStatusChange={handleKanbanStatusChange} />
            ) : viewMode === 'calendar' ? (
                <CalendarView tasks={tasks} />
            ) : (
                <TableView
                    tasks={tasks}
                    isLoading={false}
                    expandedTask={expandedTask}
                    setExpandedTask={setExpandedTask}
                    commentText={commentText}
                    setCommentText={setCommentText}
                    statusMut={statusMut}
                    commentMut={commentMut}
                    page={page}
                    setPage={setPage}
                    lastPage={lastPage}
                />
            )}
        </div>
    );
}
