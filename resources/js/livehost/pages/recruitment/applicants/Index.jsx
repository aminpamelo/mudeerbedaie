import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { DragDropContext, Draggable, Droppable } from '@hello-pangea/dnd';
import {
  ArrowUpRight,
  CalendarDays,
  Check,
  Copy,
  GripVertical,
  Inbox,
  Link as LinkIcon,
  Mail,
  MapPin,
  MessageCircle,
  Phone,
  Radio,
  Rows3,
  Sparkles,
  Square,
  Star,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StageAssignmentModal from '@/livehost/components/recruitment/StageAssignmentModal';

function platformTone(slug) {
  const map = {
    tiktok: 'bg-[#0A0A0A] text-white',
    shopee: 'bg-[#FEE2E2] text-[#B91C1C]',
    facebook: 'bg-[#E0E7FF] text-[#1E40AF]',
  };
  return map[slug] ?? 'bg-[#F5F5F5] text-[#525252]';
}

function StatusBadge({ status }) {
  const tone = {
    open: 'bg-[#ECFDF5] text-[#047857] ring-[#A7F3D0]',
    paused: 'bg-[#FEF3C7] text-[#B45309] ring-[#FDE68A]',
    closed: 'bg-[#FFE4E6] text-[#9F1239] ring-[#FECDD3]',
    draft: 'bg-[#F5F5F5] text-[#525252] ring-[#E5E5E5]',
  }[status] ?? 'bg-[#F5F5F5] text-[#525252] ring-[#E5E5E5]';

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium capitalize ring-1 ring-inset ${tone}`}
    >
      <span
        className={`inline-block h-1.5 w-1.5 rounded-full ${
          status === 'open' ? 'bg-[#10B981]' : status === 'paused' ? 'bg-[#F59E0B]' : status === 'closed' ? 'bg-[#F43F5E]' : 'bg-[#A3A3A3]'
        }`}
      />
      {status}
    </span>
  );
}

function RatingStars({ value }) {
  if (!value || value <= 0) {
    return null;
  }
  const stars = Math.max(0, Math.min(5, Number(value)));
  return (
    <div className="flex items-center gap-0.5">
      {Array.from({ length: stars }).map((_, i) => (
        <Star key={i} className="h-3 w-3 fill-[#F59E0B] text-[#F59E0B]" strokeWidth={1.5} />
      ))}
    </div>
  );
}

function PreviewRow({ icon: Icon, label, value }) {
  if (!value) {
    return null;
  }
  return (
    <div className="flex items-start gap-2">
      <Icon className="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#A3A3A3]" strokeWidth={2} />
      <div className="min-w-0">
        <div className="text-[9.5px] font-medium uppercase tracking-[0.06em] text-[#A3A3A3]">{label}</div>
        <div className="truncate text-[12px] font-medium text-[#262626]">{value}</div>
      </div>
    </div>
  );
}

function ApplicantPreview({ applicant, anchor }) {
  const WIDTH = 268;
  const MARGIN = 12;
  const viewportW = typeof window !== 'undefined' ? window.innerWidth : 1280;
  const viewportH = typeof window !== 'undefined' ? window.innerHeight : 800;

  // Prefer placing to the right of the card; flip left if it would overflow.
  let left = anchor.right + MARGIN;
  if (left + WIDTH > viewportW - 8) {
    left = anchor.left - WIDTH - MARGIN;
  }
  if (left < 8) {
    left = 8;
  }
  let top = anchor.top;
  const EST_HEIGHT = 220;
  if (top + EST_HEIGHT > viewportH - 8) {
    top = Math.max(8, viewportH - EST_HEIGHT - 8);
  }

  return createPortal(
    <div
      className="pointer-events-none fixed z-[70] w-[268px] rounded-xl border border-[#EAEAEA] bg-white p-3.5 shadow-[0_12px_32px_-8px_rgba(0,0,0,0.22)]"
      style={{ top, left }}
    >
      <div className="mb-2.5 border-b border-[#F0F0F0] pb-2.5">
        <div className="truncate text-[13px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
          {applicant.full_name}
        </div>
        <div className="mt-0.5 truncate font-mono text-[10.5px] text-[#737373]">
          {applicant.applicant_number}
        </div>
      </div>
      <div className="flex flex-col gap-2">
        <PreviewRow icon={CalendarDays} label="Applied" value={applicant.applied_at_display} />
        <PreviewRow icon={Mail} label="Email" value={applicant.email} />
        <PreviewRow icon={Phone} label="Phone" value={applicant.phone} />
        <PreviewRow icon={MapPin} label="Domisili" value={applicant.domicile} />
        <PreviewRow icon={Radio} label="Sumber" value={applicant.source} />
      </div>
    </div>,
    document.body,
  );
}

function ApplicantCard({ applicant, index, isDragDisabled = false, onOpen, compact = false }) {
  const [anchor, setAnchor] = useState(null);
  const hoverTimer = useRef(null);

  const openPreview = (el) => {
    if (!el) {
      return;
    }
    const rect = el.getBoundingClientRect();
    clearTimeout(hoverTimer.current);
    hoverTimer.current = setTimeout(() => {
      setAnchor({ top: rect.top, left: rect.left, right: rect.right, bottom: rect.bottom });
    }, 220);
  };

  const closePreview = () => {
    clearTimeout(hoverTimer.current);
    setAnchor(null);
  };

  useEffect(() => () => clearTimeout(hoverTimer.current), []);

  return (
    <Draggable draggableId={String(applicant.id)} index={index} isDragDisabled={isDragDisabled}>
      {(provided, snapshot) => (
        <div
          ref={provided.innerRef}
          {...provided.draggableProps}
          onMouseEnter={(e) => {
            if (!snapshot.isDragging) {
              openPreview(e.currentTarget);
            }
          }}
          onMouseLeave={closePreview}
          className={[
            'group rounded-lg border bg-white transition-shadow',
            compact ? 'p-2.5' : 'p-3',
            snapshot.isDragging
              ? 'border-[#0A0A0A] shadow-[0_8px_24px_-4px_rgba(0,0,0,0.18)]'
              : 'border-[#EAEAEA] shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[#D4D4D4] hover:shadow-[0_2px_6px_rgba(0,0,0,0.06)]',
          ].join(' ')}
          style={{
            ...provided.draggableProps.style,
            opacity: snapshot.isDragging ? 0.95 : 1,
          }}
        >
          <div className="flex items-start gap-2">
            <button
              type="button"
              {...provided.dragHandleProps}
              aria-label="Drag to move stage"
              className={[
                '-ml-1 cursor-grab rounded p-0.5 text-[#A3A3A3] opacity-0 transition-opacity hover:bg-[#F5F5F5] hover:text-[#525252] group-hover:opacity-100 active:cursor-grabbing',
                compact ? 'mt-px' : 'mt-0.5',
              ].join(' ')}
            >
              <GripVertical className="h-3.5 w-3.5" strokeWidth={2} />
            </button>
            <button
              type="button"
              onClick={(e) => {
                if (snapshot.isDragging) {
                  e.preventDefault();
                  return;
                }
                if (typeof onOpen === 'function') {
                  onOpen(applicant);
                }
              }}
              className="min-w-0 flex-1 cursor-pointer text-left"
            >
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                  <div className="truncate text-[13px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                    {applicant.full_name}
                  </div>
                  <div className="mt-0.5 truncate font-mono text-[10.5px] text-[#737373]">
                    {applicant.applicant_number}
                  </div>
                </div>
                <div className="flex shrink-0 items-center gap-1.5">
                  <RatingStars value={applicant.rating} />
                  {compact && applicant.assignment?.assignee && (
                    <span
                      title={applicant.assignment.assignee.name}
                      className="inline-flex h-4 w-4 items-center justify-center rounded-full bg-[#E5E7EB] text-[8px] font-semibold text-[#374151]"
                    >
                      {applicant.assignment.assignee.initials}
                    </span>
                  )}
                </div>
              </div>

              {applicant.applied_at_display && (
                <div
                  className={[
                    'flex items-center gap-1 text-[11px] tabular-nums text-[#737373]',
                    compact ? 'mt-1.5' : 'mt-2',
                  ].join(' ')}
                >
                  <CalendarDays className="h-3 w-3 shrink-0 text-[#A3A3A3]" strokeWidth={2} />
                  {applicant.applied_at_display}
                </div>
              )}

              {!compact && applicant.platforms?.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1">
                  {applicant.platforms.map((p) => (
                    <span
                      key={p}
                      className={`inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide ${platformTone(p)}`}
                    >
                      {p}
                    </span>
                  ))}
                </div>
              )}

              {!compact && applicant.assignment && (applicant.assignment.assignee || applicant.assignment.due_at) && (
                <div className="mt-2 flex items-center gap-1.5">
                  {applicant.assignment.assignee && (
                    <span
                      title={applicant.assignment.assignee.name}
                      className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-[#E5E7EB] text-[9px] font-semibold text-[#374151]"
                    >
                      {applicant.assignment.assignee.initials}
                    </span>
                  )}
                  {applicant.assignment.due_at && (
                    <span
                      className={[
                        'inline-flex items-center rounded-md px-1.5 py-0.5 text-[10.5px] font-medium ring-1 ring-inset',
                        applicant.assignment.is_overdue
                          ? 'bg-[#FEE2E2] text-[#B91C1C] ring-[#FECACA]'
                          : 'bg-[#F5F5F5] text-[#525252] ring-[#E5E5E5]',
                      ].join(' ')}
                    >
                      Due {new Date(applicant.assignment.due_at).toLocaleDateString(undefined, {
                        weekday: 'short', day: 'numeric', month: 'short',
                      })}
                    </span>
                  )}
                </div>
              )}
            </button>
          </div>
          {anchor && !snapshot.isDragging && <ApplicantPreview applicant={applicant} anchor={anchor} />}
        </div>
      )}
    </Draggable>
  );
}

function StageColumn({ stage, applicants, isDropDisabled = false, dragDisabled = false, onOpen, compact = false }) {
  return (
    <div className="flex w-[280px] shrink-0 flex-col rounded-[12px] bg-[#F5F5F5]">
      <div className="flex items-center justify-between border-b border-[#EAEAEA] px-3 py-2.5">
        <div className="flex items-center gap-2">
          <span className="text-[12px] font-semibold uppercase tracking-[0.08em] text-[#525252]">
            {stage.name}
          </span>
          {stage.is_final && (
            <span className="inline-flex items-center rounded-full bg-[#ECFDF5] px-1.5 py-0.5 text-[9.5px] font-medium uppercase tracking-wide text-[#047857]">
              Final
            </span>
          )}
        </div>
        <span className="inline-flex min-w-[24px] justify-center rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold tabular-nums text-[#525252]">
          {applicants.length}
        </span>
      </div>
      <Droppable droppableId={String(stage.id)} isDropDisabled={isDropDisabled}>
        {(provided, snapshot) => (
          <div
            ref={provided.innerRef}
            {...provided.droppableProps}
            className={[
              'flex min-h-[120px] flex-1 flex-col gap-2 p-2 transition-colors',
              snapshot.isDraggingOver && !isDropDisabled
                ? 'bg-[#ECFDF5]/60'
                : '',
            ].join(' ')}
          >
            {applicants.length === 0 ? (
              <div
                className={[
                  'flex h-full min-h-[100px] flex-col items-center justify-center rounded-md border border-dashed text-center text-[11px] transition-colors',
                  snapshot.isDraggingOver && !isDropDisabled
                    ? 'border-[#10B981] text-[#047857]'
                    : 'border-[#E5E5E5] text-[#A3A3A3]',
                ].join(' ')}
              >
                {snapshot.isDraggingOver && !isDropDisabled ? 'Drop to move here' : 'Nothing here yet'}
              </div>
            ) : (
              applicants.map((a, index) => (
                <ApplicantCard key={a.id} applicant={a} index={index} isDragDisabled={dragDisabled} onOpen={onOpen} compact={compact} />
              ))
            )}
            {provided.placeholder}
          </div>
        )}
      </Droppable>
    </div>
  );
}

function ApplicantList({ applicants, title }) {
  if (applicants.length === 0) {
    return (
      <div className="grid place-items-center rounded-[16px] border border-dashed border-[#EAEAEA] bg-white px-8 py-16 text-center">
        <Inbox className="mb-3 h-10 w-10 text-[#D4D4D4]" strokeWidth={1.5} />
        <div className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
          No {title.toLowerCase()} applicants
        </div>
      </div>
    );
  }
  return (
    <div className="overflow-hidden rounded-[12px] border border-[#EAEAEA] bg-white">
      <div className="border-b border-[#F0F0F0] px-5 py-3 text-[12px] font-semibold uppercase tracking-[0.08em] text-[#525252]">
        {title} · {applicants.length}
      </div>
      <ul className="divide-y divide-[#F0F0F0]">
        {applicants.map((a) => (
          <li key={a.id}>
            <Link
              href={`/livehost/recruitment/applicants/${a.id}`}
              className="flex items-center justify-between gap-4 px-5 py-3 transition-colors hover:bg-[#FAFAFA]"
            >
              <div className="min-w-0">
                <div className="truncate text-[13.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                  {a.full_name}
                </div>
                <div className="mt-0.5 flex items-center gap-2 text-[11.5px] text-[#737373]">
                  <span className="font-mono">{a.applicant_number}</span>
                  <span>·</span>
                  <span>{a.email}</span>
                </div>
              </div>
              {a.applied_at_display && (
                <div className="shrink-0 text-[11px] tabular-nums text-[#A3A3A3]">{a.applied_at_display}</div>
              )}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}

function CopyField({ url }) {
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      setTimeout(() => setCopied(false), 1600);
    } catch {
      // clipboard not available; fall back to select
      const el = document.getElementById('recruitment-public-url');
      if (el) {
        el.select();
      }
    }
  };

  return (
    <button
      type="button"
      onClick={copy}
      className="group relative flex w-full items-center gap-3 overflow-hidden rounded-[10px] border border-[#EAEAEA] bg-white px-3.5 py-3 text-left transition-all hover:border-[#D4D4D4] hover:shadow-[0_2px_8px_rgba(0,0,0,0.04)]"
    >
      <LinkIcon className="h-4 w-4 shrink-0 text-[#737373]" strokeWidth={2} />
      <span
        id="recruitment-public-url"
        className="flex-1 truncate font-mono text-[12.5px] text-[#0A0A0A]"
      >
        {url}
      </span>
      <span
        className={`inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium transition-all ${
          copied
            ? 'bg-[#ECFDF5] text-[#047857]'
            : 'bg-[#F5F5F5] text-[#525252] group-hover:bg-[#EAEAEA]'
        }`}
      >
        {copied ? (
          <>
            <Check className="h-3 w-3" strokeWidth={2.5} /> Copied
          </>
        ) : (
          <>
            <Copy className="h-3 w-3" strokeWidth={2} /> Copy
          </>
        )}
      </span>
    </button>
  );
}

function ShareButton({ href, icon: Icon, label, tone = 'neutral' }) {
  const toneClass =
    tone === 'whatsapp'
      ? 'hover:bg-[#25D366]/10 hover:border-[#25D366]/30 hover:text-[#128C7E]'
      : 'hover:bg-[#F5F5F5] hover:border-[#D4D4D4]';

  return (
    <a
      href={href}
      target="_blank"
      rel="noreferrer"
      className={`inline-flex items-center gap-2 rounded-[8px] border border-[#EAEAEA] bg-white px-3 py-2 text-[12.5px] font-medium text-[#404040] transition-all ${toneClass}`}
    >
      <Icon className="h-3.5 w-3.5" strokeWidth={2} />
      {label}
    </a>
  );
}

function HeroEmpty({ campaign }) {
  if (!campaign.public_url) {
    // Campaign is draft/paused/closed — give a clear next-step nudge instead
    return (
      <div className="overflow-hidden rounded-[20px] border border-[#EAEAEA] bg-white">
        <div className="flex flex-col items-center px-8 py-16 text-center">
          <div className="mb-4 grid h-14 w-14 place-items-center rounded-full bg-[#F5F5F5]">
            <Sparkles className="h-6 w-6 text-[#737373]" strokeWidth={1.8} />
          </div>
          <h2 className="max-w-md text-[22px] font-semibold leading-[1.2] tracking-[-0.025em] text-[#0A0A0A]">
            Publish this campaign to start receiving applications.
          </h2>
          <p className="mt-2 max-w-md text-[13.5px] text-[#737373]">
            Once the campaign is open, you'll get a public link candidates can use to apply.
          </p>
          <Link
            href={`/livehost/recruitment/campaigns/${campaign.id}/edit`}
            className="mt-5 inline-flex items-center gap-1.5 rounded-lg bg-[#0A0A0A] px-4 py-2 text-[13px] font-medium text-white hover:bg-[#262626]"
          >
            Open campaign settings
            <ArrowUpRight className="h-3.5 w-3.5" strokeWidth={2} />
          </Link>
        </div>
      </div>
    );
  }

  const shareText = encodeURIComponent(
    `We're hiring! ${campaign.title} — apply here: ${campaign.public_url}`,
  );
  const waHref = `https://wa.me/?text=${shareText}`;
  const mailtoHref = `mailto:?subject=${encodeURIComponent(`Apply now: ${campaign.title}`)}&body=${shareText}`;

  return (
    <div className="overflow-hidden rounded-[20px] border border-[#EAEAEA] bg-white">
      {/* Top half: hero share card with subtle atmosphere */}
      <div className="relative isolate px-8 pb-8 pt-12">
        {/* atmosphere — soft emerald glow behind */}
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 opacity-60"
          style={{
            background:
              'radial-gradient(circle at 50% -20%, rgba(16,185,129,0.10) 0%, rgba(16,185,129,0) 55%)',
          }}
        />
        {/* subtle grid texture */}
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 opacity-[0.35]"
          style={{
            backgroundImage:
              'linear-gradient(to right, rgba(10,10,10,0.03) 1px, transparent 1px), linear-gradient(to bottom, rgba(10,10,10,0.03) 1px, transparent 1px)',
            backgroundSize: '24px 24px',
            maskImage: 'radial-gradient(ellipse at top, black 0%, transparent 75%)',
          }}
        />

        <div className="mx-auto max-w-2xl text-center">
          <div className="mb-5 inline-flex items-center gap-1.5 rounded-full border border-[#A7F3D0] bg-[#ECFDF5] px-3 py-1 text-[11px] font-medium text-[#047857]">
            <span className="relative flex h-1.5 w-1.5">
              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#10B981] opacity-60" />
              <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-[#10B981]" />
            </span>
            Campaign is live
          </div>

          <h2 className="text-[32px] font-semibold leading-[1.05] tracking-[-0.035em] text-[#0A0A0A] sm:text-[36px]">
            Share this link to start
            <br />
            receiving applications.
          </h2>

          <p className="mx-auto mt-3 max-w-md text-[14px] leading-relaxed text-[#737373]">
            Candidates who submit the form will appear here, organised by the stages you set for{' '}
            <span className="font-medium text-[#404040]">{campaign.title}</span>.
          </p>

          <div className="mx-auto mt-7 max-w-xl">
            <CopyField url={campaign.public_url} />
          </div>

          <div className="mt-4 flex flex-wrap items-center justify-center gap-2">
            <ShareButton
              href={campaign.public_url}
              icon={ArrowUpRight}
              label="Open preview"
            />
            <ShareButton
              href={waHref}
              icon={MessageCircle}
              label="Share on WhatsApp"
              tone="whatsapp"
            />
            <ShareButton
              href={mailtoHref}
              icon={Mail}
              label="Email"
            />
          </div>
        </div>
      </div>

      {/* Bottom half: what candidates see */}
      {campaign.description && (
        <div className="border-t border-dashed border-[#EAEAEA] bg-[#FAFAFA] px-8 py-8">
          <div className="mx-auto max-w-2xl">
            <div className="mb-3 flex items-center gap-2 text-[11px] font-medium uppercase tracking-[0.1em] text-[#A3A3A3]">
              <span className="h-px flex-1 bg-[#EAEAEA]" />
              What candidates see
              <span className="h-px flex-1 bg-[#EAEAEA]" />
            </div>
            <div
              className="whitespace-pre-wrap text-[13.5px] leading-relaxed text-[#404040]"
              style={{ wordBreak: 'break-word' }}
            >
              {campaign.description}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default function ApplicantsIndex() {
  const { campaign, campaigns, stages, applicants, counts, filters } = usePage().props;
  const { assignableUsers } = usePage().props;

  // Optimistic stage overrides keyed by applicant id while a moveStage request is in flight.
  const [stageOverrides, setStageOverrides] = useState({});
  const [isMoving, setIsMoving] = useState(false);
  const [openApplicantId, setOpenApplicantId] = useState(null);

  // Card density + sort — persisted so the PIC's choice sticks across visits.
  const [viewMode, setViewMode] = useState(() =>
    typeof window !== 'undefined' ? window.localStorage.getItem('lh_applicants_view') || 'compact' : 'compact',
  );
  const [sortBy, setSortBy] = useState(() =>
    typeof window !== 'undefined' ? window.localStorage.getItem('lh_applicants_sort') || 'newest' : 'newest',
  );
  useEffect(() => {
    window.localStorage.setItem('lh_applicants_view', viewMode);
  }, [viewMode]);
  useEffect(() => {
    window.localStorage.setItem('lh_applicants_sort', sortBy);
  }, [sortBy]);
  const compact = viewMode === 'compact';

  const effectiveApplicants = useMemo(
    () =>
      (applicants ?? []).map((a) =>
        stageOverrides[a.id] !== undefined
          ? { ...a, current_stage_id: stageOverrides[a.id] }
          : a,
      ),
    [applicants, stageOverrides],
  );

  const openApplicant = useMemo(
    () => effectiveApplicants.find((a) => a.id === openApplicantId) ?? null,
    [effectiveApplicants, openApplicantId],
  );

  const sortedApplicants = useMemo(() => {
    const list = [...effectiveApplicants];
    list.sort((a, b) => {
      if (sortBy === 'name') {
        return (a.full_name || '').localeCompare(b.full_name || '');
      }
      const ta = a.applied_at ? Date.parse(a.applied_at) : 0;
      const tb = b.applied_at ? Date.parse(b.applied_at) : 0;
      return sortBy === 'oldest' ? ta - tb : tb - ta;
    });
    return list;
  }, [effectiveApplicants, sortBy]);

  const applicantsByStage = useMemo(() => {
    const map = new Map();
    (stages ?? []).forEach((s) => map.set(s.id, []));
    sortedApplicants.forEach((a) => {
      if (a.current_stage_id && map.has(a.current_stage_id)) {
        map.get(a.current_stage_id).push(a);
      }
    });
    return map;
  }, [stages, sortedApplicants]);

  const ungrouped = useMemo(
    () =>
      sortedApplicants.filter(
        (a) => !a.current_stage_id || !(stages ?? []).some((s) => s.id === a.current_stage_id),
      ),
    [sortedApplicants, stages],
  );

  const onDragEnd = (result) => {
    const { source, destination, draggableId } = result;
    if (!destination) return;
    if (source.droppableId === destination.droppableId && source.index === destination.index) {
      return;
    }
    const destStageId = destination.droppableId === '0' ? null : Number(destination.droppableId);
    if (destStageId === null) {
      return; // Cannot drop into the Unassigned pseudo-stage
    }
    const applicantId = Number(draggableId);

    // Optimistic update
    setStageOverrides((prev) => ({ ...prev, [applicantId]: destStageId }));
    setIsMoving(true);

    router.patch(
      `/livehost/recruitment/applicants/${applicantId}/stage`,
      { to_stage_id: destStageId },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setStageOverrides((prev) => {
            const next = { ...prev };
            delete next[applicantId];
            return next;
          });
        },
        onError: () => {
          // Roll back on failure
          setStageOverrides((prev) => {
            const next = { ...prev };
            delete next[applicantId];
            return next;
          });
        },
        onFinish: () => setIsMoving(false),
      },
    );
  };

  const statusTab = filters?.status ?? 'active';
  const campaignCounts = counts ?? { active: 0, rejected: 0, hired: 0 };

  const setCampaign = (id) => {
    router.get(
      '/livehost/recruitment/applicants',
      { campaign: id || undefined, status: statusTab },
      { preserveScroll: true, preserveState: true, replace: true },
    );
  };

  const setStatusTab = (status) => {
    router.get(
      '/livehost/recruitment/applicants',
      { campaign: filters?.campaign ?? undefined, status },
      { preserveScroll: true, preserveState: true, replace: true },
    );
  };

  const statusTabs = [
    { id: 'active', label: 'Active', count: campaignCounts.active },
    { id: 'rejected', label: 'Rejected', count: campaignCounts.rejected },
    { id: 'hired', label: 'Hired', count: campaignCounts.hired },
  ];

  return (
    <>
      <Head title="Applicants" />
      <TopBar breadcrumb={['Live Host Desk', 'Recruitment', 'Applicants']} />

      <div className="px-4 pb-12 pt-8 sm:px-8">
        {!campaign ? (
          <EmptyNoCampaigns />
        ) : (
          <>
            {/* Page header */}
            <header className="mb-7">
              <div className="flex flex-wrap items-start justify-between gap-6">
                <div className="min-w-0">
                  <div className="mb-2 flex items-center gap-2.5">
                    <StatusBadge status={campaign.status} />
                    {campaign.closes_at && (
                      <span className="text-[11.5px] text-[#A3A3A3]">
                        Closes {new Date(campaign.closes_at).toLocaleDateString()}
                      </span>
                    )}
                  </div>
                  <h1 className="max-w-[780px] text-[32px] font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
                    {campaign.title}
                  </h1>
                  <p className="mt-1.5 text-[13.5px] text-[#737373]">
                    <span className="font-medium text-[#404040]">{campaignCounts.active}</span> active
                    <span className="mx-1.5 text-[#D4D4D4]">·</span>
                    <span className="font-medium text-[#404040]">{campaignCounts.hired}</span> hired
                    <span className="mx-1.5 text-[#D4D4D4]">·</span>
                    <span className="font-medium text-[#404040]">{campaignCounts.rejected}</span> rejected
                  </p>
                </div>

                {campaign.public_url && (
                  <div className="flex items-center gap-2">
                    <a
                      href={campaign.public_url}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-3.5 py-2 text-[13px] font-medium text-[#404040] hover:border-[#D4D4D4] hover:bg-[#FAFAFA]"
                    >
                      Preview
                      <ArrowUpRight className="h-3.5 w-3.5" strokeWidth={2} />
                    </a>
                    <CopyLinkButton url={campaign.public_url} />
                  </div>
                )}
              </div>
            </header>

            {/* Toolbar: campaign + status */}
            <div className="mb-6 flex flex-col gap-3 border-b border-[#EAEAEA] pb-4 sm:flex-row sm:flex-wrap sm:items-center">
              {(campaigns ?? []).length > 1 && (
                <div className="relative w-full sm:w-auto">
                  <select
                    value={filters?.campaign ?? ''}
                    onChange={(e) => setCampaign(e.target.value)}
                    className="h-9 w-full appearance-none rounded-lg border border-[#EAEAEA] bg-white pl-3 pr-8 text-[13px] font-medium text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto"
                  >
                    {(campaigns ?? []).map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.title}
                      </option>
                    ))}
                  </select>
                  <svg
                    className="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#737373]"
                    viewBox="0 0 20 20"
                    fill="none"
                  >
                    <path d="M6 8l4 4 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                  </svg>
                </div>
              )}

              <div className="inline-flex rounded-lg bg-[#F5F5F5] p-1">
                {statusTabs.map((tab) => (
                  <button
                    key={tab.id}
                    type="button"
                    onClick={() => setStatusTab(tab.id)}
                    className={[
                      'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-[12.5px] font-medium transition-all',
                      statusTab === tab.id
                        ? 'bg-white text-[#0A0A0A] shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                        : 'text-[#737373] hover:text-[#404040]',
                    ].join(' ')}
                  >
                    {tab.label}
                    <span
                      className={[
                        'inline-flex min-w-[18px] justify-center rounded px-1 text-[10.5px] font-semibold tabular-nums',
                        statusTab === tab.id ? 'bg-[#F5F5F5] text-[#0A0A0A]' : 'bg-white text-[#737373]',
                      ].join(' ')}
                    >
                      {tab.count}
                    </span>
                  </button>
                ))}
              </div>

              {statusTab === 'active' && applicants.length > 0 && (
                <div className="flex items-center gap-2 sm:ml-auto">
                  <div className="relative">
                    <select
                      value={sortBy}
                      onChange={(e) => setSortBy(e.target.value)}
                      aria-label="Susun pemohon"
                      className="h-9 appearance-none rounded-lg border border-[#EAEAEA] bg-white pl-3 pr-8 text-[12.5px] font-medium text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                    >
                      <option value="newest">Terbaru dahulu</option>
                      <option value="oldest">Terlama dahulu</option>
                      <option value="name">Nama (A–Z)</option>
                    </select>
                    <svg
                      className="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#737373]"
                      viewBox="0 0 20 20"
                      fill="none"
                    >
                      <path d="M6 8l4 4 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                    </svg>
                  </div>

                  <div className="inline-flex rounded-lg bg-[#F5F5F5] p-1">
                    {[
                      { id: 'compact', label: 'Compact', icon: Rows3 },
                      { id: 'comfortable', label: 'Selesa', icon: Square },
                    ].map((opt) => {
                      const Icon = opt.icon;
                      return (
                        <button
                          key={opt.id}
                          type="button"
                          onClick={() => setViewMode(opt.id)}
                          className={[
                            'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[12.5px] font-medium transition-all',
                            viewMode === opt.id
                              ? 'bg-white text-[#0A0A0A] shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                              : 'text-[#737373] hover:text-[#404040]',
                          ].join(' ')}
                        >
                          <Icon className="h-3.5 w-3.5" strokeWidth={2} />
                          {opt.label}
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>

            {/* Content */}
            {statusTab === 'active' ? (
              applicants.length === 0 ? (
                <HeroEmpty campaign={campaign} />
              ) : (
                <DragDropContext onDragEnd={onDragEnd}>
                  <div className="flex gap-4 overflow-x-auto pb-2">
                    {(stages ?? []).map((stage) => (
                      <StageColumn
                        key={stage.id}
                        stage={stage}
                        applicants={applicantsByStage.get(stage.id) ?? []}
                        onOpen={(a) => setOpenApplicantId(a.id)}
                        compact={compact}
                      />
                    ))}
                    {ungrouped.length > 0 && (
                      <StageColumn
                        stage={{ id: 0, name: 'Unassigned', is_final: false }}
                        applicants={ungrouped}
                        isDropDisabled={true}
                        onOpen={(a) => setOpenApplicantId(a.id)}
                        compact={compact}
                      />
                    )}
                  </div>
                </DragDropContext>
              )
            ) : (
              <ApplicantList
                applicants={applicants}
                title={statusTab === 'rejected' ? 'Rejected' : 'Hired'}
              />
            )}
          </>
        )}
      </div>
      {openApplicant && (
        <StageAssignmentModal
          applicant={openApplicant}
          stages={stages}
          assignableUsers={assignableUsers ?? []}
          onClose={() => setOpenApplicantId(null)}
        />
      )}
    </>
  );
}

function CopyLinkButton({ url }) {
  const [copied, setCopied] = useState(false);
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      setTimeout(() => setCopied(false), 1600);
    } catch {
      /* noop */
    }
  };
  return (
    <button
      type="button"
      onClick={copy}
      className="inline-flex items-center gap-1.5 rounded-lg bg-[#0A0A0A] px-3.5 py-2 text-[13px] font-medium text-white transition-all hover:bg-[#262626]"
    >
      {copied ? (
        <>
          <Check className="h-3.5 w-3.5" strokeWidth={2.5} /> Copied
        </>
      ) : (
        <>
          <Copy className="h-3.5 w-3.5" strokeWidth={2} /> Share public link
        </>
      )}
    </button>
  );
}

function EmptyNoCampaigns() {
  return (
    <div className="grid place-items-center rounded-[16px] border border-dashed border-[#EAEAEA] bg-white px-8 py-20 text-center">
      <Inbox className="mb-3 h-10 w-10 text-[#D4D4D4]" strokeWidth={1.5} />
      <div className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">No campaigns yet</div>
      <p className="mt-1 max-w-md text-sm text-[#737373]">
        Create a campaign before reviewing applicants.
      </p>
      <Link
        href="/livehost/recruitment/campaigns"
        className="mt-4 inline-flex items-center rounded-md bg-[#0A0A0A] px-4 py-2 text-sm font-medium text-white hover:bg-[#262626]"
      >
        Go to campaigns
      </Link>
    </div>
  );
}

ApplicantsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
