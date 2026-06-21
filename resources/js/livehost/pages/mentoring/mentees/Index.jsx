import { Head, Link, router, usePage } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import MenteeBoard from '@/livehost/components/mentoring/MenteeBoard';

function StatusBadge({ status }) {
  const tone = {
    active: 'bg-[#ECFDF5] text-[#047857] ring-[#A7F3D0]',
    paused: 'bg-[#FEF3C7] text-[#B45309] ring-[#FDE68A]',
    completed: 'bg-[#EEF2FF] text-[#4338CA] ring-[#C7D2FE]',
    draft: 'bg-[#F5F5F5] text-[#525252] ring-[#E5E5E5]',
  }[status] ?? 'bg-[#F5F5F5] text-[#525252] ring-[#E5E5E5]';

  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium capitalize ring-1 ring-inset ${tone}`}>
      <span className={`inline-block h-1.5 w-1.5 rounded-full ${status === 'active' ? 'bg-[#10B981]' : status === 'paused' ? 'bg-[#F59E0B]' : status === 'completed' ? 'bg-[#6366F1]' : 'bg-[#A3A3A3]'}`} />
      {status}
    </span>
  );
}

export default function MenteesIndex() {
  const { program, programs, stages, mentees, counts, filters, assignableMentors, enrollableHosts } = usePage().props;

  const c = counts ?? { active: 0, graduated: 0, dropped: 0 };

  const setProgram = (id) => {
    router.get('/livehost/mentoring/mentees', { program: id || undefined }, { preserveScroll: true, preserveState: true, replace: true });
  };

  return (
    <>
      <Head title="Mentees" />
      <TopBar breadcrumb={['Live Host Desk', 'Mentoring', 'Mentees']} />

      <div className="px-8 pb-12 pt-8">
        {!program ? (
          <EmptyNoPrograms />
        ) : (
          <>
            <header className="mb-7">
              <div className="flex flex-wrap items-start justify-between gap-6">
                <div className="min-w-0">
                  <div className="mb-2 flex items-center gap-2.5">
                    <StatusBadge status={program.status} />
                    {program.leader && <span className="text-[11.5px] text-[#737373]">Led by {program.leader.name}</span>}
                  </div>
                  <h1 className="max-w-[780px] text-[32px] font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">{program.title}</h1>
                  <p className="mt-1.5 text-[13.5px] text-[#737373]">
                    <span className="font-medium text-[#404040]">{c.active}</span> active
                    <span className="mx-1.5 text-[#D4D4D4]">·</span>
                    <span className="font-medium text-[#404040]">{c.graduated}</span> graduated
                    <span className="mx-1.5 text-[#D4D4D4]">·</span>
                    <span className="font-medium text-[#404040]">{c.dropped}</span> dropped
                  </p>
                </div>
                <Link href={`/livehost/mentoring/programs/${program.id}/edit`} className="inline-flex items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-3.5 py-2 text-[13px] font-medium text-[#404040] hover:border-[#D4D4D4] hover:bg-[#FAFAFA]">
                  Program settings
                </Link>
              </div>
            </header>

            {(programs ?? []).length > 1 && (
              <div className="mb-6 flex flex-wrap items-center gap-3 border-b border-[#EAEAEA] pb-4">
                <div className="relative">
                  <select value={filters?.program ?? ''} onChange={(e) => setProgram(e.target.value)} className="h-9 appearance-none rounded-lg border border-[#EAEAEA] bg-white pl-3 pr-8 text-[13px] font-medium text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20">
                    {(programs ?? []).map((p) => (
                      <option key={p.id} value={p.id}>{p.title}</option>
                    ))}
                  </select>
                  <svg className="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#737373]" viewBox="0 0 20 20" fill="none">
                    <path d="M6 8l4 4 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                  </svg>
                </div>
              </div>
            )}

            <MenteeBoard
              program={program}
              stages={stages}
              mentees={mentees}
              counts={counts}
              assignableMentors={assignableMentors}
              enrollableHosts={enrollableHosts}
              initialStatus={filters?.status ?? 'active'}
            />
          </>
        )}
      </div>
    </>
  );
}

function EmptyNoPrograms() {
  return (
    <div className="grid place-items-center rounded-[16px] border border-dashed border-[#EAEAEA] bg-white px-8 py-20 text-center">
      <Inbox className="mb-3 h-10 w-10 text-[#D4D4D4]" strokeWidth={1.5} />
      <div className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">No programs yet</div>
      <p className="mt-1 max-w-md text-sm text-[#737373]">Create a mentoring program before enrolling mentees.</p>
      <Link href="/livehost/mentoring/programs/create" className="mt-4 inline-flex items-center rounded-md bg-[#0A0A0A] px-4 py-2 text-sm font-medium text-white hover:bg-[#262626]">
        Create a program
      </Link>
    </div>
  );
}

MenteesIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
