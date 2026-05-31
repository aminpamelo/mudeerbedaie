import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight, GraduationCap, Users } from 'lucide-react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';

function pct(done, total) {
  return total > 0 ? Math.round((done / total) * 100) : 0;
}

function MenteeRow({ mentee }) {
  const progress = pct(mentee.checklist_done, mentee.checklist_total);
  const graduated = mentee.status === 'graduated';

  return (
    <Link
      href={`/live-host/mentees/${mentee.id}`}
      className="flex items-center gap-3 rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[14px] py-[13px] transition active:scale-[0.99]"
    >
      <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-[var(--accent)] to-[var(--hot)] font-display text-[14px] font-bold text-white">
        {(mentee.name ?? '?').slice(0, 1).toUpperCase()}
      </span>
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <span className="truncate font-display text-[15px] font-medium tracking-[-0.01em] text-[var(--fg)]">{mentee.name}</span>
          {mentee.level && (
            <span className="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold text-white" style={{ backgroundColor: mentee.level.color || '#10B981' }}>
              {mentee.level.name}
            </span>
          )}
          {graduated && (
            <span className="shrink-0 rounded-full bg-[var(--app-bg)] px-1.5 py-0.5 font-mono text-[9px] font-bold uppercase tracking-wide text-[var(--fg-3)] ring-1 ring-[var(--hair)]">
              Graduated
            </span>
          )}
        </div>
        <div className="mt-0.5 flex items-center gap-2 text-[11.5px] text-[var(--fg-2)]">
          <span className="truncate">{mentee.current_stage ?? '—'}</span>
          <span className="text-[var(--fg-3)]">·</span>
          <span className="shrink-0 tabular-nums">{mentee.checklist_done}/{mentee.checklist_total} tasks</span>
        </div>
        <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-[var(--app-bg)]">
          <div className="h-full rounded-full bg-[var(--accent)]" style={{ width: `${progress}%` }} />
        </div>
      </div>
      <ChevronRight className="h-[18px] w-[18px] shrink-0 text-[var(--fg-3)]" strokeWidth={2} />
    </Link>
  );
}

export default function Mentees() {
  const { mentees } = usePage().props;
  const active = (mentees ?? []).filter((m) => m.status === 'active');
  const graduated = (mentees ?? []).filter((m) => m.status === 'graduated');

  return (
    <>
      <Head title="My Mentees" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <div className="px-1 pt-3 pb-2">
          <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">Mentoring</div>
          <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">My Mentees</h1>
        </div>

        {(mentees ?? []).length === 0 ? (
          <div className="mt-10 flex flex-col items-center px-6 text-center">
            <div className="grid h-16 w-16 place-items-center rounded-full bg-[var(--app-bg-2)] ring-1 ring-[var(--hair)]">
              <Users className="h-7 w-7 text-[var(--fg-3)]" strokeWidth={1.8} />
            </div>
            <div className="mt-4 font-display text-[18px] font-medium tracking-[-0.02em] text-[var(--fg)]">No mentees assigned</div>
            <p className="mt-1.5 text-[13px] leading-relaxed text-[var(--fg-2)]">
              When your team assigns mentees to you, they'll appear here for you to coach toward becoming top hosts.
            </p>
          </div>
        ) : (
          <div className="space-y-4">
            {active.length > 0 && (
              <div className="space-y-2">
                {active.map((m) => <MenteeRow key={m.id} mentee={m} />)}
              </div>
            )}
            {graduated.length > 0 && (
              <div className="space-y-2">
                <div className="flex items-center gap-1.5 px-1 pt-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
                  <GraduationCap className="h-3.5 w-3.5" strokeWidth={2} /> Graduated
                </div>
                {graduated.map((m) => <MenteeRow key={m.id} mentee={m} />)}
              </div>
            )}
          </div>
        )}
      </div>
    </>
  );
}

Mentees.layout = (page) => <PocketLayout>{page}</PocketLayout>;
