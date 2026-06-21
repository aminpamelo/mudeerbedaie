import { Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { DragDropContext, Draggable, Droppable } from '@hello-pangea/dnd';
import { GraduationCap, GripVertical, Inbox, Plus, UserPlus, X } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';
import MentorAssignmentModal from '@/livehost/components/mentoring/MentorAssignmentModal';

/**
 * The kanban "mentee board": stage columns with drag-to-move, plus graduated /
 * dropped lists. It filters by status client-side from an all-status mentee
 * list, so the same board renders both on its own page and embedded in the
 * program editor as a tab. Pass `reloadOnly` (e.g. ['board']) when embedded so
 * writes only refresh that prop instead of the whole editor payload.
 */
export default function MenteeBoard({
  program,
  stages,
  mentees,
  counts,
  assignableMentors,
  enrollableHosts,
  initialStatus = 'active',
  reloadOnly = null,
}) {
  const [statusTab, setStatusTab] = useState(initialStatus);
  const [stageOverrides, setStageOverrides] = useState({});
  const [openMenteeId, setOpenMenteeId] = useState(null);
  const [enrolling, setEnrolling] = useState(false);

  const visitOpts = useMemo(
    () => (reloadOnly ? { preserveScroll: true, preserveState: true, only: reloadOnly } : { preserveScroll: true, preserveState: true }),
    [reloadOnly],
  );

  const all = mentees ?? [];
  const c = counts ?? { active: 0, graduated: 0, dropped: 0 };

  const statusMentees = useMemo(() => all.filter((m) => m.status === statusTab), [all, statusTab]);

  const effectiveMentees = useMemo(
    () => statusMentees.map((m) => (stageOverrides[m.id] !== undefined ? { ...m, current_stage_id: stageOverrides[m.id] } : m)),
    [statusMentees, stageOverrides],
  );

  const openMentee = useMemo(() => all.find((m) => m.id === openMenteeId) ?? null, [all, openMenteeId]);

  const menteesByStage = useMemo(() => {
    const map = new Map();
    (stages ?? []).forEach((s) => map.set(s.id, []));
    effectiveMentees.forEach((m) => {
      if (m.current_stage_id && map.has(m.current_stage_id)) {
        map.get(m.current_stage_id).push(m);
      }
    });
    return map;
  }, [stages, effectiveMentees]);

  const ungrouped = useMemo(
    () => effectiveMentees.filter((m) => !m.current_stage_id || !(stages ?? []).some((s) => s.id === m.current_stage_id)),
    [effectiveMentees, stages],
  );

  const onDragEnd = (result) => {
    const { source, destination, draggableId } = result;
    if (!destination) return;
    if (source.droppableId === destination.droppableId && source.index === destination.index) return;
    const destStageId = destination.droppableId === '0' ? null : Number(destination.droppableId);
    if (destStageId === null) return;
    const menteeId = Number(draggableId);

    setStageOverrides((prev) => ({ ...prev, [menteeId]: destStageId }));
    router.patch(
      `/livehost/mentoring/mentees/${menteeId}/stage`,
      { to_stage_id: destStageId },
      {
        ...visitOpts,
        onSuccess: () => setStageOverrides((prev) => { const n = { ...prev }; delete n[menteeId]; return n; }),
        onError: () => setStageOverrides((prev) => { const n = { ...prev }; delete n[menteeId]; return n; }),
      },
    );
  };

  const statusTabs = [
    { id: 'active', label: 'Active', count: c.active },
    { id: 'graduated', label: 'Graduated', count: c.graduated },
    { id: 'dropped', label: 'Dropped', count: c.dropped },
  ];

  const hasHosts = (enrollableHosts ?? []).length > 0;

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div className="inline-flex rounded-lg bg-[#F5F5F5] p-1">
          {statusTabs.map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => setStatusTab(tab.id)}
              className={['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-[12.5px] font-medium transition-all', statusTab === tab.id ? 'bg-white text-[#0A0A0A] shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#737373] hover:text-[#404040]'].join(' ')}
            >
              {tab.label}
              <span className={['inline-flex min-w-[18px] justify-center rounded px-1 text-[10.5px] font-semibold tabular-nums', statusTab === tab.id ? 'bg-[#F5F5F5] text-[#0A0A0A]' : 'bg-white text-[#737373]'].join(' ')}>{tab.count}</span>
            </button>
          ))}
        </div>
        {program && (
          <Button size="sm" onClick={() => setEnrolling(true)} className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
            <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
            Enrol mentee
          </Button>
        )}
      </div>

      {statusTab === 'active' ? (
        statusMentees.length === 0 ? (
          <HeroEmpty onEnrol={() => setEnrolling(true)} hasHosts={hasHosts} />
        ) : (
          <DragDropContext onDragEnd={onDragEnd}>
            <div className="flex gap-4 overflow-x-auto pb-2">
              {(stages ?? []).map((stage) => (
                <StageColumn key={stage.id} stage={stage} mentees={menteesByStage.get(stage.id) ?? []} onOpen={(m) => setOpenMenteeId(m.id)} />
              ))}
              {ungrouped.length > 0 && (
                <StageColumn stage={{ id: 0, name: 'Unassigned', is_final: false }} mentees={ungrouped} isDropDisabled onOpen={(m) => setOpenMenteeId(m.id)} />
              )}
            </div>
          </DragDropContext>
        )
      ) : (
        <MenteeList mentees={statusMentees} title={statusTab === 'graduated' ? 'Graduated' : 'Dropped'} />
      )}

      {openMentee && (
        <MentorAssignmentModal
          mentee={openMentee}
          stages={stages}
          assignableMentors={assignableMentors ?? []}
          programLeader={program?.leader ?? null}
          program={program ?? null}
          reloadOnly={reloadOnly}
          onClose={() => setOpenMenteeId(null)}
        />
      )}
      {enrolling && program && (
        <EnrollModal
          program={program}
          enrollableHosts={enrollableHosts ?? []}
          assignableMentors={assignableMentors ?? []}
          reloadOnly={reloadOnly}
          onClose={() => setEnrolling(false)}
        />
      )}
    </div>
  );
}

function LevelBadge({ level }) {
  if (!level) return null;
  return (
    <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide" style={{ backgroundColor: `${level.color}22`, color: level.color }}>
      {level.name}
    </span>
  );
}

function MenteeCard({ mentee, index, isDragDisabled = false, onOpen }) {
  return (
    <Draggable draggableId={String(mentee.id)} index={index} isDragDisabled={isDragDisabled}>
      {(provided, snapshot) => (
        <div
          ref={provided.innerRef}
          {...provided.draggableProps}
          className={[
            'group rounded-lg border bg-white p-3 transition-shadow',
            snapshot.isDragging ? 'border-[#0A0A0A] shadow-[0_8px_24px_-4px_rgba(0,0,0,0.18)]' : 'border-[#EAEAEA] shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[#D4D4D4] hover:shadow-[0_2px_6px_rgba(0,0,0,0.06)]',
          ].join(' ')}
          style={{ ...provided.draggableProps.style, opacity: snapshot.isDragging ? 0.95 : 1 }}
        >
          <div className="flex items-start gap-2">
            <button type="button" {...provided.dragHandleProps} aria-label="Drag to move stage" className="mt-0.5 -ml-1 cursor-grab rounded p-0.5 text-[#A3A3A3] opacity-0 transition-opacity hover:bg-[#F5F5F5] hover:text-[#525252] group-hover:opacity-100 active:cursor-grabbing">
              <GripVertical className="h-3.5 w-3.5" strokeWidth={2} />
            </button>
            <button
              type="button"
              onClick={(e) => {
                if (snapshot.isDragging) {
                  e.preventDefault();
                  return;
                }
                if (typeof onOpen === 'function') onOpen(mentee);
              }}
              className="min-w-0 flex-1 cursor-pointer text-left"
            >
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                  <div className="truncate text-[13px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">{mentee.full_name}</div>
                  <div className="mt-0.5 truncate font-mono text-[10.5px] text-[#737373]">{mentee.mentee_number}</div>
                </div>
                <LevelBadge level={mentee.level} />
              </div>
              {mentee.enrolled_at_human && <div className="mt-2 text-[11px] text-[#A3A3A3]">Enrolled {mentee.enrolled_at_human}</div>}
              {mentee.assignment && (mentee.assignment.assignee || mentee.assignment.due_at) && (
                <div className="mt-2 flex items-center gap-1.5">
                  {mentee.assignment.assignee && (
                    <span title={`Mentor: ${mentee.assignment.assignee.name}`} className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-[#E5E7EB] text-[9px] font-semibold text-[#374151]">
                      {mentee.assignment.assignee.initials}
                    </span>
                  )}
                  {mentee.assignment.due_at && (
                    <span className={['inline-flex items-center rounded-md px-1.5 py-0.5 text-[10.5px] font-medium ring-1 ring-inset', mentee.assignment.is_overdue ? 'bg-[#FEE2E2] text-[#B91C1C] ring-[#FECACA]' : 'bg-[#F5F5F5] text-[#525252] ring-[#E5E5E5]'].join(' ')}>
                      Due {new Date(mentee.assignment.due_at).toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' })}
                    </span>
                  )}
                </div>
              )}
            </button>
          </div>
        </div>
      )}
    </Draggable>
  );
}

function StageColumn({ stage, mentees, isDropDisabled = false, dragDisabled = false, onOpen }) {
  return (
    <div className="flex w-[280px] shrink-0 flex-col rounded-[12px] bg-[#F5F5F5]">
      <div className="flex items-center justify-between border-b border-[#EAEAEA] px-3 py-2.5">
        <div className="flex items-center gap-2">
          <span className="text-[12px] font-semibold uppercase tracking-[0.08em] text-[#525252]">{stage.name}</span>
          {stage.is_final && (
            <span className="inline-flex items-center rounded-full bg-[#ECFDF5] px-1.5 py-0.5 text-[9.5px] font-medium uppercase tracking-wide text-[#047857]">Final</span>
          )}
        </div>
        <span className="inline-flex min-w-[24px] justify-center rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold tabular-nums text-[#525252]">{mentees.length}</span>
      </div>
      <Droppable droppableId={String(stage.id)} isDropDisabled={isDropDisabled}>
        {(provided, snapshot) => (
          <div ref={provided.innerRef} {...provided.droppableProps} className={['flex min-h-[120px] flex-1 flex-col gap-2 p-2 transition-colors', snapshot.isDraggingOver && !isDropDisabled ? 'bg-[#ECFDF5]/60' : ''].join(' ')}>
            {mentees.length === 0 ? (
              <div className={['flex h-full min-h-[100px] flex-col items-center justify-center rounded-md border border-dashed text-center text-[11px] transition-colors', snapshot.isDraggingOver && !isDropDisabled ? 'border-[#10B981] text-[#047857]' : 'border-[#E5E5E5] text-[#A3A3A3]'].join(' ')}>
                {snapshot.isDraggingOver && !isDropDisabled ? 'Drop to move here' : 'Nothing here yet'}
              </div>
            ) : (
              mentees.map((m, i) => <MenteeCard key={m.id} mentee={m} index={i} isDragDisabled={dragDisabled} onOpen={onOpen} />)
            )}
            {provided.placeholder}
          </div>
        )}
      </Droppable>
    </div>
  );
}

function MenteeList({ mentees, title }) {
  if (mentees.length === 0) {
    return (
      <div className="grid place-items-center rounded-[16px] border border-dashed border-[#EAEAEA] bg-white px-8 py-16 text-center">
        <Inbox className="mb-3 h-10 w-10 text-[#D4D4D4]" strokeWidth={1.5} />
        <div className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">No {title.toLowerCase()} mentees</div>
      </div>
    );
  }
  return (
    <div className="overflow-hidden rounded-[12px] border border-[#EAEAEA] bg-white">
      <div className="border-b border-[#F0F0F0] px-5 py-3 text-[12px] font-semibold uppercase tracking-[0.08em] text-[#525252]">{title} · {mentees.length}</div>
      <ul className="divide-y divide-[#F0F0F0]">
        {mentees.map((m) => (
          <li key={m.id}>
            <Link href={`/livehost/mentoring/mentees/${m.id}`} className="flex items-center justify-between gap-4 px-5 py-3 transition-colors hover:bg-[#FAFAFA]">
              <div className="min-w-0">
                <div className="truncate text-[13.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">{m.full_name}</div>
                <div className="mt-0.5 flex items-center gap-2 text-[11.5px] text-[#737373]">
                  <span className="font-mono">{m.mentee_number}</span>
                  <span>·</span>
                  <span>{m.email}</span>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <LevelBadge level={m.level} />
                {m.enrolled_at_human && <div className="shrink-0 text-[11px] text-[#A3A3A3]">{m.enrolled_at_human}</div>}
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}

function HeroEmpty({ onEnrol, hasHosts }) {
  return (
    <div className="grid place-items-center rounded-[16px] border border-dashed border-[#EAEAEA] bg-white px-8 py-16 text-center">
      <div className="mb-4 grid h-14 w-14 place-items-center rounded-full bg-[#F5F5F5]">
        <GraduationCap className="h-6 w-6 text-[#737373]" strokeWidth={1.8} />
      </div>
      <h2 className="max-w-md text-[22px] font-semibold leading-[1.2] tracking-[-0.025em] text-[#0A0A0A]">No mentees yet.</h2>
      <p className="mt-2 max-w-md text-[13.5px] text-[#737373]">Enrol an existing live host to start their journey toward becoming a top host.</p>
      <Button onClick={onEnrol} disabled={!hasHosts} className="mt-5 gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50">
        <UserPlus className="h-3.5 w-3.5" strokeWidth={2} />
        {hasHosts ? 'Enrol your first mentee' : 'No hosts available'}
      </Button>
    </div>
  );
}

function EnrollModal({ program, enrollableHosts, assignableMentors, reloadOnly, onClose }) {
  const [menteeId, setMenteeId] = useState('');
  const [mentorId, setMentorId] = useState('');
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  const submit = () => {
    setBusy(true);
    setErrors({});
    router.post(
      `/livehost/mentoring/programs/${program.id}/mentees`,
      { mentee_user_id: menteeId ? Number(menteeId) : null, mentor_user_id: mentorId ? Number(mentorId) : null },
      {
        preserveScroll: true,
        ...(reloadOnly ? { preserveState: true, only: reloadOnly } : {}),
        onSuccess: () => onClose(),
        onError: (e) => setErrors(e ?? {}),
        onFinish: () => setBusy(false),
      },
    );
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="w-full max-w-md rounded-[16px] bg-white p-6 shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div className="flex items-center gap-2 text-[18px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
            <UserPlus className="h-4 w-4 text-[#10B981]" strokeWidth={2.25} />
            Enrol a mentee
          </div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]" aria-label="Close">
            <X className="h-4 w-4" strokeWidth={2} />
          </button>
        </div>
        <p className="mb-4 text-[13px] text-[#737373]">
          Pick an existing live host to enrol into <span className="font-medium text-[#0A0A0A]">{program.title}</span>. Hosts already
          being mentored elsewhere are hidden.
        </p>

        <div className="space-y-4">
          <div>
            <label className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">Live host</label>
            {(enrollableHosts ?? []).length === 0 ? (
              <div className="rounded-lg border border-dashed border-[#EAEAEA] bg-[#FAFAFA] px-3 py-4 text-center text-[12.5px] text-[#A3A3A3]">
                No available hosts — everyone is already in a program.
              </div>
            ) : (
              <select value={menteeId} onChange={(e) => setMenteeId(e.target.value)} className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20">
                <option value="">— Select a host —</option>
                {enrollableHosts.map((u) => (
                  <option key={u.id} value={u.id}>{u.name} · {u.email}</option>
                ))}
              </select>
            )}
            {errors.mentee_user_id && <p className="mt-1 text-xs text-[#F43F5E]">{errors.mentee_user_id}</p>}
          </div>

          <div>
            <label className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">Mentor (optional)</label>
            <select value={mentorId} onChange={(e) => setMentorId(e.target.value)} className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20">
              <option value="">— Use program leader —</option>
              {(assignableMentors ?? []).map((u) => (
                <option key={u.id} value={u.id}>{u.name}{u.is_top_host_eligible ? ' ★' : ''}</option>
              ))}
            </select>
            {errors.mentor_user_id && <p className="mt-1 text-xs text-[#F43F5E]">{errors.mentor_user_id}</p>}
          </div>
        </div>

        <div className="mt-5 flex justify-end gap-2 border-t border-[#F0F0F0] pt-4">
          <Button type="button" variant="ghost" disabled={busy} onClick={onClose}>Cancel</Button>
          <Button type="button" disabled={busy || !menteeId} onClick={submit} className="bg-[#10B981] text-white hover:bg-[#059669] disabled:opacity-50">
            {busy ? 'Enrolling…' : 'Enrol mentee'}
          </Button>
        </div>
      </div>
    </div>
  );
}
