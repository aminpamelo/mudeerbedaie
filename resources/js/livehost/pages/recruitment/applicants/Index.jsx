import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { Inbox, Search, Star, User2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';

function platformTone(slug) {
  const map = {
    tiktok: 'bg-[#0A0A0A] text-white',
    shopee: 'bg-[#FEE2E2] text-[#B91C1C]',
    facebook: 'bg-[#E0E7FF] text-[#1E40AF]',
  };
  return map[slug] ?? 'bg-[#F5F5F5] text-[#525252]';
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

function ApplicantCard({ applicant }) {
  return (
    <Link
      href={`/livehost/recruitment/applicants/${applicant.id}`}
      className="block rounded-lg border border-[#EAEAEA] bg-white p-3 shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-all hover:border-[#D4D4D4] hover:shadow-[0_2px_6px_rgba(0,0,0,0.06)]"
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
        <RatingStars value={applicant.rating} />
      </div>
      {applicant.platforms?.length > 0 && (
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
      {applicant.applied_at_human && (
        <div className="mt-2 text-[11px] text-[#A3A3A3]">Applied {applicant.applied_at_human}</div>
      )}
    </Link>
  );
}

function StageColumn({ stage, applicants }) {
  return (
    <div className="flex w-[280px] shrink-0 flex-col rounded-[12px] bg-[#F5F5F5]">
      <div className="flex items-center justify-between border-b border-[#EAEAEA] px-3 py-2.5">
        <div className="flex items-center gap-2">
          <span className="text-[13px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
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
      <div className="flex min-h-[120px] flex-1 flex-col gap-2 p-2">
        {applicants.length === 0 ? (
          <div className="flex h-full min-h-[100px] flex-col items-center justify-center rounded-md border border-dashed border-[#E5E5E5] text-center text-[11px] text-[#A3A3A3]">
            No applicants in this stage
          </div>
        ) : (
          applicants.map((a) => <ApplicantCard key={a.id} applicant={a} />)
        )}
      </div>
    </div>
  );
}

function UngroupedList({ applicants, title }) {
  if (applicants.length === 0) {
    return null;
  }
  return (
    <div className="rounded-[12px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="border-b border-[#F0F0F0] px-5 py-3 text-[13px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
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
              {a.applied_at_human && (
                <div className="shrink-0 text-[11px] text-[#A3A3A3]">{a.applied_at_human}</div>
              )}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}

export default function ApplicantsIndex() {
  const { campaign, campaigns, stages, applicants, filters } = usePage().props;

  const applicantsByStage = useMemo(() => {
    const map = new Map();
    (stages ?? []).forEach((s) => map.set(s.id, []));
    (applicants ?? []).forEach((a) => {
      if (a.current_stage_id && map.has(a.current_stage_id)) {
        map.get(a.current_stage_id).push(a);
      }
    });
    return map;
  }, [stages, applicants]);

  const ungrouped = useMemo(
    () => (applicants ?? []).filter((a) => !a.current_stage_id || !(stages ?? []).some((s) => s.id === a.current_stage_id)),
    [applicants, stages],
  );

  const setCampaign = (id) => {
    router.get(
      '/livehost/recruitment/applicants',
      { campaign: id || undefined, status: filters?.status ?? 'active' },
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

  const statusTab = filters?.status ?? 'active';
  const totalCount = applicants?.length ?? 0;

  return (
    <>
      <Head title="Applicants" />
      <TopBar breadcrumb={['Live Host Desk', 'Recruitment', 'Applicants']} />

      <div className="flex min-h-[calc(100vh-56px)]">
        {/* Left sidebar: campaign picker + status tabs */}
        <aside className="w-[260px] shrink-0 border-r border-[#EAEAEA] bg-white p-5">
          <div>
            <div className="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-[#737373]">
              Campaign
            </div>
            <div className="relative">
              <Search className="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-[#A3A3A3]" />
              <select
                value={filters?.campaign ?? ''}
                onChange={(e) => setCampaign(e.target.value)}
                className="h-9 w-full appearance-none rounded-md border border-[#EAEAEA] bg-white pl-8 pr-3 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              >
                {(campaigns ?? []).length === 0 && <option value="">No campaigns</option>}
                {(campaigns ?? []).map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.title}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="mt-6">
            <div className="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-[#737373]">
              Status
            </div>
            <nav className="space-y-1">
              {[
                { id: 'active', label: 'Active' },
                { id: 'rejected', label: 'Rejected' },
                { id: 'hired', label: 'Hired' },
              ].map((tab) => (
                <button
                  key={tab.id}
                  type="button"
                  onClick={() => setStatusTab(tab.id)}
                  className={[
                    'flex w-full items-center justify-between rounded-md px-3 py-2 text-[13px] font-medium transition-colors',
                    statusTab === tab.id
                      ? 'bg-[#0A0A0A] text-white'
                      : 'text-[#525252] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]',
                  ].join(' ')}
                >
                  <span>{tab.label}</span>
                  {statusTab === tab.id && (
                    <span className="inline-flex min-w-[24px] justify-center rounded-full bg-white/20 px-1.5 text-[11px] tabular-nums">
                      {totalCount}
                    </span>
                  )}
                </button>
              ))}
            </nav>
          </div>

          {campaign && (
            <div className="mt-6 rounded-md bg-[#F5F5F5] p-3 text-[11.5px] text-[#525252]">
              <div className="mb-1 flex items-center gap-1.5 font-medium text-[#0A0A0A]">
                <User2 className="h-3.5 w-3.5" strokeWidth={2} /> {campaign.title}
              </div>
              <div className="text-[11px] text-[#737373]">
                /{campaign.slug}
              </div>
              <div className="mt-2 flex items-center gap-1.5">
                <span
                  className={[
                    'inline-flex items-center rounded-full px-2 py-0.5 text-[10.5px] font-medium',
                    campaign.status === 'open'
                      ? 'bg-[#ECFDF5] text-[#059669]'
                      : campaign.status === 'paused'
                        ? 'bg-[#FEF3C7] text-[#B45309]'
                        : campaign.status === 'closed'
                          ? 'bg-[#FFE4E6] text-[#9F1239]'
                          : 'bg-[#F5F5F5] text-[#525252]',
                  ].join(' ')}
                >
                  {campaign.status}
                </span>
              </div>
            </div>
          )}
        </aside>

        {/* Main content */}
        <main className="flex-1 p-8">
          {!campaign ? (
            <EmptyState
              title="No campaigns yet"
              description="Create a campaign before reviewing applicants."
              ctaLabel="Go to campaigns"
              ctaHref="/livehost/recruitment/campaigns"
            />
          ) : (
            <>
              <div className="mb-6 flex flex-wrap items-end justify-between gap-6">
                <div>
                  <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
                    {statusTab === 'active' ? 'Applicants' : statusTab === 'rejected' ? 'Rejected' : 'Hired'}
                  </h1>
                  <p className="mt-1.5 text-sm text-[#737373]">
                    {totalCount} {statusTab} applicant{totalCount === 1 ? '' : 's'} in{' '}
                    <span className="font-medium text-[#525252]">{campaign.title}</span>
                  </p>
                </div>
              </div>

              {statusTab === 'active' ? (
                applicants.length === 0 ? (
                  <EmptyState
                    title="No active applicants"
                    description="When candidates submit the public form, they'll appear here."
                  />
                ) : (
                  <div className="flex gap-4 overflow-x-auto pb-2">
                    {(stages ?? []).map((stage) => (
                      <StageColumn
                        key={stage.id}
                        stage={stage}
                        applicants={applicantsByStage.get(stage.id) ?? []}
                      />
                    ))}
                    {ungrouped.length > 0 && (
                      <StageColumn
                        stage={{ id: 0, name: 'Unassigned', is_final: false }}
                        applicants={ungrouped}
                      />
                    )}
                  </div>
                )
              ) : (
                <UngroupedList
                  applicants={applicants}
                  title={statusTab === 'rejected' ? 'Rejected applicants' : 'Hired applicants'}
                />
              )}
            </>
          )}
        </main>
      </div>
    </>
  );
}

function EmptyState({ title, description, ctaLabel, ctaHref }) {
  return (
    <div className="grid place-items-center rounded-[16px] border border-dashed border-[#EAEAEA] bg-white px-8 py-20 text-center">
      <Inbox className="mb-3 h-10 w-10 text-[#D4D4D4]" strokeWidth={1.5} />
      <div className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">{title}</div>
      <p className="mt-1 max-w-md text-sm text-[#737373]">{description}</p>
      {ctaLabel && ctaHref && (
        <Link
          href={ctaHref}
          className="mt-4 inline-flex items-center rounded-md bg-[#0A0A0A] px-4 py-2 text-sm font-medium text-white hover:bg-[#262626]"
        >
          {ctaLabel}
        </Link>
      )}
    </div>
  );
}

ApplicantsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
