import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import {
  CheckCircle2,
  Flag,
  GraduationCap,
  Pause,
  Pencil,
  Play,
  Plus,
  SlidersHorizontal,
  Trash2,
  Users,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

function StatusBadge({ status }) {
  const map = {
    draft: { label: 'Draft', tone: 'bg-[#F5F5F5] text-[#525252]' },
    active: { label: 'Active', tone: 'bg-[#ECFDF5] text-[#059669]' },
    paused: { label: 'Paused', tone: 'bg-[#FEF3C7] text-[#B45309]' },
    completed: { label: 'Completed', tone: 'bg-[#E0E7FF] text-[#4338CA]' },
  };
  const entry = map[status] ?? map.draft;
  return (
    <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${entry.tone}`}>
      {entry.label}
    </span>
  );
}

function formatDate(iso) {
  if (!iso) {
    return '—';
  }
  try {
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  } catch {
    return iso;
  }
}

const ACTIVITY_COLOR = { green: '#10B981', amber: '#F59E0B', red: '#F43F5E', none: '#D4D4D4' };

function ActivityDot({ activity }) {
  if (!activity) return null;
  const color = ACTIVITY_COLOR[activity.level] ?? '#D4D4D4';
  const title = activity.level === 'none'
    ? 'No leader assigned'
    : `${activity.label} · ${activity.count30} activities in 30 days`;
  return (
    <span className="inline-flex items-center gap-1" title={title}>
      <span className="inline-block h-2 w-2 rounded-full" style={{ backgroundColor: color }} />
      <span className="text-[11px] text-[#737373]">{activity.label}</span>
    </span>
  );
}

export default function ProgramsIndex() {
  const { programs } = usePage().props;
  const rows = useMemo(() => programs?.data ?? [], [programs]);

  const runLifecycle = (verb, program) => {
    router.patch(`/livehost/mentoring/programs/${program.id}/${verb}`, {}, { preserveScroll: true });
  };

  const handleDelete = (program) => {
    if (!window.confirm(`Delete the "${program.title}" program? This can't be undone.`)) {
      return;
    }
    router.delete(`/livehost/mentoring/programs/${program.id}`, { preserveScroll: true });
  };

  const newProgramAction = (
    <div className="flex items-center gap-2">
      <Link href="/livehost/mentoring/levels">
        <Button size="sm" variant="outline" className="h-9 gap-1.5 rounded-lg shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20">
          <SlidersHorizontal className="h-[13px] w-[13px]" strokeWidth={2.25} />
          Levels
        </Button>
      </Link>
      <Link href="/livehost/mentoring/programs/create">
        <Button size="sm" className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
          <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
          New program
        </Button>
      </Link>
    </div>
  );

  return (
    <>
      <Head title="Mentoring Programs" />
      <TopBar breadcrumb={['Live Host Desk', 'Mentoring', 'Programs']} actions={newProgramAction} />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Mentoring Programs
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              {programs?.total ?? 0} total program{(programs?.total ?? 0) === 1 ? '' : 's'} · turn newly-hired hosts into top hosts
            </p>
          </div>
        </div>

        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {rows.length === 0 ? (
            <div className="py-16 text-center">
              <GraduationCap className="mx-auto mb-3 h-10 w-10 text-[#D4D4D4]" strokeWidth={1.5} />
              <div className="text-sm text-[#737373]">No mentoring programs yet.</div>
              <Link
                href="/livehost/mentoring/programs/create"
                className="mt-2 inline-block text-sm font-medium text-[#059669] hover:text-[#047857]"
              >
                Create your first program
              </Link>
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                  <th className="px-5 py-3 text-left">Program</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-left">Leader</th>
                  <th className="px-5 py-3 text-right">Active</th>
                  <th className="px-5 py-3 text-right">Graduated</th>
                  <th className="px-5 py-3 text-left">Window</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((program) => (
                  <tr key={program.id} className="border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]">
                    <td className="px-5 py-3.5">
                      <Link href={`/livehost/mentoring/programs/${program.id}/edit`} className="group block min-w-0">
                        <div className="truncate text-[13.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A] group-hover:text-[#059669]">
                          {program.title}
                        </div>
                        <div className="mt-0.5 truncate text-[11.5px] text-[#737373]">/{program.slug}</div>
                      </Link>
                    </td>
                    <td className="px-5 py-3.5">
                      <StatusBadge status={program.status} />
                    </td>
                    <td className="px-5 py-3.5">
                      {program.leader ? (
                        <div className="flex flex-col gap-1">
                          <span className="inline-flex items-center gap-2">
                            <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#E5E7EB] text-[9px] font-semibold text-[#374151]">
                              {program.leader.initials}
                            </span>
                            <span className="text-[13px] text-[#0A0A0A]">{program.leader.name}</span>
                          </span>
                          <ActivityDot activity={program.activity} />
                        </div>
                      ) : (
                        <span className="text-[12.5px] text-[#A3A3A3]">Unassigned</span>
                      )}
                    </td>
                    <td className="px-5 py-3.5 text-right font-semibold tabular-nums">{program.active_mentees_count}</td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[#525252]">{program.graduated_mentees_count}</td>
                    <td className="px-5 py-3.5 text-[12.5px] text-[#525252]">
                      {formatDate(program.starts_at)} → {formatDate(program.ends_at)}
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <div className="inline-flex flex-wrap items-center justify-end gap-1">
                        <Link
                          href={`/livehost/mentoring/programs/${program.id}/edit`}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="Edit"
                        >
                          <Pencil className="h-[14px] w-[14px]" strokeWidth={2} />
                        </Link>
                        <Link
                          href={`/livehost/mentoring/mentees?program=${program.id}`}
                          className="inline-flex h-8 items-center gap-1 rounded-md px-2 text-[11.5px] font-medium text-[#525252] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="View mentees for this program"
                        >
                          <Users className="h-[12px] w-[12px]" strokeWidth={2.25} /> Mentees
                        </Link>
                        {program.status === 'draft' && (
                          <button
                            type="button"
                            onClick={() => runLifecycle('activate', program)}
                            className="inline-flex h-8 items-center gap-1 rounded-md px-2 text-[11.5px] font-medium text-[#059669] hover:bg-[#ECFDF5]"
                            title="Activate"
                          >
                            <Play className="h-[12px] w-[12px]" strokeWidth={2.25} /> Activate
                          </button>
                        )}
                        {program.status === 'active' && (
                          <button
                            type="button"
                            onClick={() => runLifecycle('pause', program)}
                            className="inline-flex h-8 items-center gap-1 rounded-md px-2 text-[11.5px] font-medium text-[#B45309] hover:bg-[#FEF3C7]"
                            title="Pause"
                          >
                            <Pause className="h-[12px] w-[12px]" strokeWidth={2.25} /> Pause
                          </button>
                        )}
                        {program.status === 'paused' && (
                          <button
                            type="button"
                            onClick={() => runLifecycle('activate', program)}
                            className="inline-flex h-8 items-center gap-1 rounded-md px-2 text-[11.5px] font-medium text-[#059669] hover:bg-[#ECFDF5]"
                            title="Resume"
                          >
                            <Play className="h-[12px] w-[12px]" strokeWidth={2.25} /> Resume
                          </button>
                        )}
                        {(program.status === 'active' || program.status === 'paused') && (
                          <button
                            type="button"
                            onClick={() => {
                              if (window.confirm(`Mark "${program.title}" as completed?`)) {
                                runLifecycle('complete', program);
                              }
                            }}
                            className="inline-flex h-8 items-center gap-1 rounded-md px-2 text-[11.5px] font-medium text-[#4338CA] hover:bg-[#E0E7FF]"
                            title="Complete"
                          >
                            <Flag className="h-[12px] w-[12px]" strokeWidth={2.25} /> Complete
                          </button>
                        )}
                        {program.mentees_count === 0 && (
                          <button
                            type="button"
                            onClick={() => handleDelete(program)}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#FFF1F2] hover:text-[#F43F5E]"
                            title="Delete"
                          >
                            <Trash2 className="h-[14px] w-[14px]" strokeWidth={2} />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {programs?.last_page > 1 && (
          <div className="flex items-center justify-between">
            <div className="text-xs text-[#737373]">
              Showing {programs.from}–{programs.to} of {programs.total}
            </div>
            <div className="flex gap-1">
              {programs.links.map((link, index) => (
                <button
                  key={`${link.label}-${index}`}
                  type="button"
                  disabled={!link.url}
                  onClick={() => {
                    if (link.url) {
                      router.visit(link.url, { preserveScroll: true, preserveState: true });
                    }
                  }}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                  className={[
                    'min-w-8 h-8 rounded-md px-2 text-xs font-medium',
                    link.active ? 'bg-[#0A0A0A] text-white' : 'text-[#737373] hover:bg-[#F5F5F5]',
                    !link.url ? 'cursor-default opacity-40' : '',
                  ].join(' ')}
                />
              ))}
            </div>
          </div>
        )}

        <div className="flex items-center gap-1.5 text-xs text-[#737373]">
          <CheckCircle2 className="h-3 w-3 text-[#A3A3A3]" strokeWidth={2} />
          A program needs a final stage before it can be activated. The seeded "Graduated" stage is final by default.
        </div>
      </div>
    </>
  );
}

ProgramsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
