import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Flag, Megaphone, Calendar } from 'lucide-react';
import { fetchKanban } from '../lib/api';
import { cn } from '../lib/utils';
import { Button } from '../components/ui/button';

// ─── Constants ──────────────────────────────────────────────────────────────

const STAGES = [
    { key: 'idea', label: 'Idea', headerBg: 'bg-blue-500', cardBg: 'bg-blue-50/50', badge: 'bg-blue-100 text-blue-700' },
    { key: 'shooting', label: 'Shooting', headerBg: 'bg-purple-500', cardBg: 'bg-purple-50/50', badge: 'bg-purple-100 text-purple-700' },
    { key: 'editing', label: 'Editing', headerBg: 'bg-amber-500', cardBg: 'bg-amber-50/50', badge: 'bg-amber-100 text-amber-700' },
    { key: 'posting', label: 'Posting', headerBg: 'bg-emerald-500', cardBg: 'bg-emerald-50/50', badge: 'bg-emerald-100 text-emerald-700' },
    { key: 'posted', label: 'Posted', headerBg: 'bg-green-500', cardBg: 'bg-green-50/50', badge: 'bg-green-100 text-green-700' },
];

const PRIORITY_COLORS = {
    urgent: 'bg-red-100 text-red-700',
    high: 'bg-orange-100 text-orange-700',
    medium: 'bg-yellow-100 text-yellow-700',
    low: 'bg-green-100 text-green-700',
};

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

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function isOverdue(dateString) {
    if (!dateString) return false;
    return new Date(dateString) < new Date();
}

function formatDate(dateString) {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('en-MY', {
        month: 'short',
        day: 'numeric',
    });
}

// ─── Sub-components ─────────────────────────────────────────────────────────

function AssigneeAvatarStack({ assignees }) {
    if (!assignees || assignees.length === 0) return null;

    const visible = assignees.slice(0, 3);
    const remaining = assignees.length - visible.length;

    return (
        <div className="flex -space-x-1.5">
            {visible.map((assignee, index) => (
                <div
                    key={assignee.id || index}
                    title={assignee.full_name || assignee.name}
                >
                    {assignee.profile_photo_url ? (
                        <img
                            src={assignee.profile_photo_url}
                            alt={assignee.full_name || assignee.name}
                            className="h-6 w-6 rounded-full border-2 border-white object-cover"
                        />
                    ) : (
                        <div className="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-zinc-200 text-[10px] font-semibold text-zinc-600">
                            {getInitials(assignee.full_name || assignee.name)}
                        </div>
                    )}
                </div>
            ))}
            {remaining > 0 && (
                <div className="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-zinc-100 text-[10px] font-medium text-zinc-600">
                    +{remaining}
                </div>
            )}
        </div>
    );
}

function KanbanCard({ content }) {
    const navigate = useNavigate();
    const overdue = isOverdue(content.due_date);
    const priorityColor = PRIORITY_COLORS[content.priority] || 'bg-zinc-100 text-zinc-700';
    const assignees = content.assignees || content.stage_assignees || [];

    return (
        <div
            onClick={() => navigate(`/contents/${content.id}`)}
            className="cursor-pointer rounded-lg border border-zinc-200 bg-white p-3 shadow-sm transition-shadow hover:shadow-md"
        >
            {/* Title */}
            <p className="truncate text-sm font-semibold text-zinc-900">
                {content.title}
            </p>

            {/* Priority + Indicators */}
            <div className="mt-2 flex items-center gap-1.5">
                <span
                    className={cn(
                        'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold',
                        priorityColor
                    )}
                >
                    {capitalize(content.priority)}
                </span>
                {content.is_flagged && (
                    <Flag className="h-3.5 w-3.5 text-red-500" />
                )}
                {content.marked_for_ads && (
                    <Megaphone className="h-3.5 w-3.5 text-indigo-500" />
                )}
            </div>

            {/* Footer: due date + assignees */}
            <div className="mt-3 flex items-center justify-between">
                {content.due_date ? (
                    <div className={cn('flex items-center gap-1 text-xs', overdue ? 'text-red-600 font-medium' : 'text-zinc-500')}>
                        <Calendar className="h-3 w-3" />
                        {formatDate(content.due_date)}
                    </div>
                ) : (
                    <span />
                )}
                <AssigneeAvatarStack assignees={assignees} />
            </div>
        </div>
    );
}

function SkeletonColumn() {
    return (
        <div className="flex min-w-[280px] flex-col rounded-xl border border-zinc-200 bg-zinc-50">
            <div className="flex items-center justify-between rounded-t-xl px-4 py-3">
                <div className="h-5 w-24 animate-pulse rounded bg-zinc-200" />
                <div className="h-5 w-8 animate-pulse rounded-full bg-zinc-200" />
            </div>
            <div className="flex-1 space-y-3 p-3">
                {Array.from({ length: 3 }).map((_, i) => (
                    <div key={i} className="rounded-lg border border-zinc-200 bg-white p-3">
                        <div className="h-4 w-3/4 animate-pulse rounded bg-zinc-200" />
                        <div className="mt-2 h-4 w-16 animate-pulse rounded-full bg-zinc-200" />
                        <div className="mt-3 flex items-center justify-between">
                            <div className="h-3 w-16 animate-pulse rounded bg-zinc-200" />
                            <div className="flex -space-x-1.5">
                                <div className="h-6 w-6 animate-pulse rounded-full bg-zinc-200" />
                                <div className="h-6 w-6 animate-pulse rounded-full bg-zinc-200" />
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────

export default function KanbanBoard() {
    const { data, isLoading } = useQuery({
        queryKey: ['cms', 'kanban'],
        queryFn: fetchKanban,
    });

    // data is expected to be an object keyed by stage
    const stages = data || {};

    return (
        <div>
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-zinc-900">Kanban Board</h1>
                    <p className="mt-1 text-sm text-zinc-500">
                        Visualise your content pipeline across all stages.
                    </p>
                </div>
                <Button asChild>
                    <Link to="/contents/create">
                        <Plus className="mr-1.5 h-4 w-4" />
                        Create Content
                    </Link>
                </Button>
            </div>

            {/* Board */}
            <div className="flex gap-4 overflow-x-auto pb-4">
                {isLoading
                    ? Array.from({ length: 5 }).map((_, i) => <SkeletonColumn key={i} />)
                    : STAGES.map((stage) => {
                          const items = stages[stage.key] || [];
                          return (
                              <div
                                  key={stage.key}
                                  className={cn(
                                      'flex min-w-[280px] flex-col rounded-xl border border-zinc-200',
                                      stage.cardBg
                                  )}
                              >
                                  {/* Column Header */}
                                  <div
                                      className={cn(
                                          'flex items-center justify-between rounded-t-xl px-4 py-3',
                                          stage.headerBg
                                      )}
                                  >
                                      <span className="text-sm font-semibold text-white">
                                          {stage.label}
                                      </span>
                                      <span className="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-white/25 px-1.5 text-xs font-semibold text-white">
                                          {items.length}
                                      </span>
                                  </div>

                                  {/* Column Body */}
                                  <div className="flex max-h-[calc(100vh-240px)] flex-1 flex-col gap-3 overflow-y-auto p-3">
                                      {items.length === 0 ? (
                                          <p className="py-8 text-center text-xs text-zinc-400">
                                              No items
                                          </p>
                                      ) : (
                                          items.map((content) => (
                                              <KanbanCard key={content.id} content={content} />
                                          ))
                                      )}
                                  </div>
                              </div>
                          );
                      })}
            </div>
        </div>
    );
}
