import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
  ChevronRight,
  ExternalLink,
  Gauge,
  Grid3x3,
  Loader2,
  ShieldCheck,
  Sparkles,
  Upload,
  UserMinus,
  Users,
  Video,
  X,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';

/* Coverage buckets — same colours as the Coverage Matrix. */
const COVERAGE_TILES = [
  { key: 'needs_upload', label: 'Belum upload', hint: 'host kena upload', icon: Upload, dot: '#EF4444', tint: 'text-[#B91C1C]', bg: 'bg-[#FEF2F2]', ring: 'ring-[#FBD5D5]' },
  { key: 'needs_verify', label: 'Belum verify', hint: 'PIC kena verify', icon: ShieldCheck, dot: '#F59E0B', tint: 'text-[#B45309]', bg: 'bg-[#FFFBEB]', ring: 'ring-[#FDE68A]' },
  { key: 'verified', label: 'Verified', hint: 'selesai', icon: ShieldCheck, dot: '#10B981', tint: 'text-[#047857]', bg: 'bg-[#ECFDF5]', ring: 'ring-[#A7F3D0]' },
  { key: 'suggestions', label: 'TikTok suggestion', hint: 'live tiada slot', icon: Sparkles, dot: '#7C3AED', tint: 'text-[#6D28D9]', bg: 'bg-[#F7F5FE]', ring: 'ring-[#E9E3FB]' },
];

function formatRM(n) {
  const num = Number(n) || 0;
  const hasSen = Math.round(num) !== num;
  return `RM ${num.toLocaleString(undefined, { minimumFractionDigits: hasSen ? 2 : 0, maximumFractionDigits: 2 })}`;
}

export default function Dashboard() {
  const { auth, coverage = {}, mentoring = {}, pendingReplacements = 0 } = usePage().props;
  const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';
  const [outstanding, setOutstanding] = useState(null); // 'needs_upload' | 'needs_verify' | null

  return (
    <>
      <Head title="Dashboard" />
      <TopBar breadcrumb={['Live Host Desk', 'Dashboard']} />

      <div className="space-y-6 p-4 sm:p-6 lg:p-8">
        <PageHeader firstName={firstName} monthLabel={coverage.month_label ?? mentoring.month_label} />

        {pendingReplacements > 0 && <PendingReplacementsBanner count={pendingReplacements} />}

        <CoveragePanel coverage={coverage} onOpen={setOutstanding} />
        <MentoringPanel mentoring={mentoring} />
      </div>

      {outstanding && <CoverageOutstandingModal bucket={outstanding} onClose={() => setOutstanding(null)} />}
    </>
  );
}

const BUCKET_META = {
  needs_upload: { title: 'Belum upload', hint: 'host kena upload', dot: '#EF4444', tint: 'text-[#B91C1C]', done: 'Semua sesi dah di-upload 🎉' },
  needs_verify: { title: 'Belum verify', hint: 'PIC kena verify', dot: '#F59E0B', tint: 'text-[#B45309]', done: 'Semua sesi dah diverify 🎉' },
};

/** Drill-in modal: outstanding sessions for a coverage bucket, grouped by host. */
function CoverageOutstandingModal({ bucket, onClose }) {
  const [data, setData] = useState(null);
  const meta = BUCKET_META[bucket] ?? BUCKET_META.needs_upload;

  useEffect(() => {
    setData(null);
    fetch(`/livehost/coverage-outstanding?bucket=${bucket}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((r) => r.json())
      .then(setData)
      .catch(() => setData({ hosts: [], total: 0, month_label: '' }));
  }, [bucket]);

  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-[16px] bg-white shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="flex items-start justify-between gap-3 border-b border-[#F0F0F0] px-5 py-4">
          <div className="flex items-center gap-2.5">
            <span className={`grid h-9 w-9 place-items-center rounded-xl bg-[#F5F5F5] ${meta.tint}`}>
              {bucket === 'needs_upload' ? <Upload className="h-4 w-4" strokeWidth={2.25} /> : <ShieldCheck className="h-4 w-4" strokeWidth={2.25} />}
            </span>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-[15px] font-semibold text-[#0A0A0A]">{meta.title}</h3>
                {data && <span className={`rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[11px] font-semibold tabular-nums ${meta.tint}`}>{data.total}</span>}
              </div>
              <p className="mt-0.5 text-[12px] text-[#737373]">{meta.hint} · {data?.month_label ?? '…'} · grouped by host</p>
            </div>
          </div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"><X className="h-4 w-4" strokeWidth={2} /></button>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto px-4 py-3">
          {!data && <div className="grid place-items-center py-12 text-[#A3A3A3]"><Loader2 className="h-5 w-5 animate-spin" /></div>}
          {data && data.hosts.length === 0 && (
            <div className="py-12 text-center text-[13px] text-[#737373]">{meta.done}</div>
          )}
          {data && data.hosts.map((h) => (
            <div key={h.host_id ?? 'unassigned'} className="mb-2.5 overflow-hidden rounded-[12px] border border-[#EEEEEE]">
              <div className="flex items-center justify-between gap-2 border-b border-[#F0F0F0] bg-[#FAFAFA] px-3 py-2">
                <div className="flex items-center gap-2">
                  <span className="grid h-6 w-6 place-items-center rounded-full bg-[#E5E7EB] text-[9px] font-bold text-[#525252]">{h.initials}</span>
                  <span className="text-[13px] font-semibold text-[#0A0A0A]">{h.host_name}</span>
                </div>
                <span className={`rounded-full bg-white px-1.5 py-0.5 text-[11px] font-semibold tabular-nums ring-1 ring-[#EAEAEA] ${meta.tint}`}>{h.count}</span>
              </div>
              <ul className="divide-y divide-[#F5F5F5]">
                {h.sessions.map((s) => (
                  <li key={s.id}>
                    <a href={s.url} className="group flex items-center justify-between gap-2 px-3 py-2 hover:bg-[#FAFAFA]">
                      <div className="min-w-0">
                        <div className="flex items-center gap-1.5 text-[12.5px] font-medium text-[#0A0A0A]">
                          <span className="tabular-nums">{s.date_human}</span>
                          <span className="text-[#D4D4D4]">·</span>
                          <span className="tabular-nums text-[#525252]">{s.time}</span>
                        </div>
                        {s.account && <div className="mt-0.5 truncate text-[11px] text-[#A3A3A3]">{s.account}</div>}
                      </div>
                      <ExternalLink className="h-3.5 w-3.5 shrink-0 text-[#C4C4C4] group-hover:text-[#0A0A0A]" strokeWidth={2} />
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

Dashboard.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function PageHeader({ firstName, monthLabel }) {
  return (
    <div className="flex flex-wrap items-end justify-between gap-4 sm:gap-8">
      <div>
        <h1 className="text-2xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A] sm:text-3xl">
          Good afternoon, {firstName}
        </h1>
        <p className="mt-1.5 text-sm text-[#737373]">
          Settlement &amp; mentoring health for <span className="font-medium text-[#0A0A0A]">{monthLabel ?? 'this month'}</span>
        </p>
      </div>
      <span className="inline-flex items-center gap-2 rounded-lg border border-[#EAEAEA] bg-white px-3 py-1.5">
        <span className="text-[11px] font-medium uppercase tracking-wide text-[#737373]">Range</span>
        <span className="text-xs font-semibold text-[#0A0A0A]">{monthLabel ?? 'This month'}</span>
      </span>
    </div>
  );
}

function PendingReplacementsBanner({ count }) {
  return (
    <Link
      href="/livehost/replacements"
      className="group flex items-center justify-between gap-4 rounded-[16px] border border-[#FDE68A] bg-gradient-to-r from-[#FFFBEB] to-[#FEF3C7] p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-colors hover:from-[#FEF3C7] hover:to-[#FDE68A]"
    >
      <div className="flex items-center gap-4">
        <div className="grid h-11 w-11 place-items-center rounded-xl bg-[#F59E0B] text-white">
          <UserMinus className="h-5 w-5" strokeWidth={2.25} />
        </div>
        <div>
          <div className="text-[11px] font-medium uppercase tracking-wide text-[#92400E]">Tindakan diperlukan</div>
          <div className="mt-0.5 text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">Permohonan ganti tertunda</div>
        </div>
      </div>
      <div className="flex items-center gap-3">
        <div className="text-3xl font-semibold tabular-nums tracking-[-0.02em] text-[#0A0A0A]">{count}</div>
        <ChevronRight className="h-5 w-5 text-[#92400E] transition-transform group-hover:translate-x-0.5" strokeWidth={2.25} />
      </div>
    </Link>
  );
}

/* ---------------- Session Slots · Coverage ---------------- */

const CLICKABLE_TILES = { needs_upload: true, needs_verify: true };

function CoveragePanel({ coverage, onOpen }) {
  const settledPct = coverage.settled_pct ?? null;
  const outstanding = (coverage.needs_upload ?? 0) + (coverage.needs_verify ?? 0);

  return (
    <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)] sm:p-6">
      <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <div className="grid h-9 w-9 place-items-center rounded-xl bg-[#F5F5F5] text-[#525252]"><Grid3x3 className="h-[18px] w-[18px]" strokeWidth={2} /></div>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">Session Slots · Coverage</h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">Settlement state across every account · {coverage.month_label ?? 'this month'}</p>
          </div>
        </div>
        <Link href="/livehost/session-slots/matrix" className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#0A0A0A] px-3 text-[12.5px] font-medium text-white transition-colors hover:bg-[#262626]">
          Open matrix <ChevronRight className="h-3.5 w-3.5" strokeWidth={2.25} />
        </Link>
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {COVERAGE_TILES.map((t) => {
          const Icon = t.icon;
          const clickable = CLICKABLE_TILES[t.key] && (coverage[t.key] ?? 0) > 0;
          const inner = (
            <>
              <div className="flex items-center justify-between">
                <span className={`grid h-8 w-8 place-items-center rounded-lg bg-white/70 ${t.tint}`}><Icon className="h-4 w-4" strokeWidth={2.25} /></span>
                {clickable ? <ChevronRight className={`h-4 w-4 ${t.tint} opacity-40 transition-transform group-hover:translate-x-0.5 group-hover:opacity-100`} strokeWidth={2.5} /> : <span className="h-2 w-2 rounded-full" style={{ backgroundColor: t.dot }} />}
              </div>
              <div className={`mt-3 text-[26px] font-semibold tabular-nums leading-none tracking-[-0.02em] ${t.tint}`}>{coverage[t.key] ?? 0}</div>
              <div className="mt-1.5 text-[12.5px] font-medium text-[#0A0A0A]">{t.label}</div>
              <div className="text-[11px] text-[#737373]">{clickable ? 'tap to see hosts →' : t.hint}</div>
            </>
          );
          return clickable ? (
            <button key={t.key} type="button" onClick={() => onOpen(t.key)} className={`group rounded-[14px] ${t.bg} p-4 text-left ring-1 ${t.ring} transition-all hover:shadow-[0_2px_10px_rgba(0,0,0,0.06)] focus:outline-none focus-visible:ring-2`}>
              {inner}
            </button>
          ) : (
            <div key={t.key} className={`rounded-[14px] ${t.bg} p-4 ring-1 ${t.ring}`}>{inner}</div>
          );
        })}
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-[#F0F0F0] pt-4">
        <div className="min-w-[180px] flex-1">
          <div className="mb-1 flex items-center justify-between text-[12px]">
            <span className="font-medium text-[#525252]">Settled</span>
            <span className="font-semibold tabular-nums text-[#0A0A0A]">{settledPct ?? '—'}%</span>
          </div>
          <div className="h-1.5 w-full overflow-hidden rounded-full bg-[#F0F0F0]">
            <div className="h-full rounded-full bg-[#10B981] transition-all" style={{ width: `${settledPct ?? 0}%` }} />
          </div>
          <div className="mt-1.5 text-[11px] text-[#737373]">{coverage.verified ?? 0} of {coverage.total_sessions ?? 0} sessions verified</div>
        </div>
        <Metric label="Accounts outstanding" value={`${coverage.accounts_outstanding ?? 0}`} sub={`of ${coverage.accounts ?? 0} accounts`} tone={outstanding > 0 ? 'text-[#B91C1C]' : 'text-[#047857]'} />
        <Metric label="Sessions today" value={`${coverage.sessions_today ?? 0}`} sub="scheduled" />
      </div>
    </section>
  );
}

/* ---------------- Mentoring Overview ---------------- */

function MentoringPanel({ mentoring }) {
  const programs = mentoring.programs ?? [];
  const video = mentoring.video ?? {};

  return (
    <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)] sm:p-6">
      <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <div className="grid h-9 w-9 place-items-center rounded-xl bg-[#ECFDF5] text-[#047857]"><Gauge className="h-[18px] w-[18px]" strokeWidth={2} /></div>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">Mentoring Overview</h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">Performance across active programs · {mentoring.month_label ?? 'this month'}</p>
          </div>
        </div>
        <Link href="/livehost/mentoring/overview" className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#0A0A0A] px-3 text-[12.5px] font-medium text-white transition-colors hover:bg-[#262626]">
          Open overview <ChevronRight className="h-3.5 w-3.5" strokeWidth={2.25} />
        </Link>
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Metric icon={Users} label="Active mentees" value={`${mentoring.active_mentees ?? 0}`} sub={`${mentoring.active_programs ?? 0} program${(mentoring.active_programs ?? 0) === 1 ? '' : 's'}`} boxed />
        <Metric label="Sales (month)" value={formatRM(mentoring.sales_month)} sub="effective GMV" boxed />
        <Metric label="Avg attitude" value={mentoring.avg_attitude != null ? `${mentoring.avg_attitude}` : '—'} sub="/ 100" boxed />
        <Metric icon={Video} label="Daily video" value={`${video.posted ?? 0}/${video.active_mentees ?? 0}`} sub={`${video.pct ?? 0}% today · ${video.videos_today ?? 0} logged`} boxed tint={video.missing > 0 ? 'text-[#6D28D9]' : 'text-[#047857]'} />
      </div>

      <div className="mt-4 border-t border-[#F0F0F0] pt-4">
        <div className="mb-2 text-[11px] font-semibold uppercase tracking-[0.05em] text-[#737373]">Per program</div>
        {programs.length === 0 ? (
          <div className="rounded-[12px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] py-8 text-center text-[12.5px] text-[#A3A3A3]">
            No active mentoring programs.
          </div>
        ) : (
          <div className="flex flex-col divide-y divide-[#F0F0F0]">
            {programs.map((p) => (
              <Link key={p.id} href="/livehost/mentoring/overview" className="group flex items-center justify-between gap-3 py-2.5 transition-colors hover:bg-[#FAFAFA] -mx-2 px-2 rounded-lg">
                <div className="min-w-0">
                  <div className="truncate text-[13.5px] font-semibold text-[#0A0A0A] group-hover:text-[#047857]">{p.title}</div>
                  <div className="text-[11.5px] text-[#737373]">{p.mentees} active mentee{p.mentees === 1 ? '' : 's'}</div>
                </div>
                <div className="flex items-center gap-3 shrink-0">
                  <div className="text-right">
                    <div className="text-[13px] font-semibold tabular-nums text-[#0A0A0A]">{formatRM(p.sales_month)}</div>
                    <div className="text-[10.5px] uppercase tracking-wide text-[#A3A3A3]">this month</div>
                  </div>
                  <ChevronRight className="h-4 w-4 text-[#C4C4C4] transition-transform group-hover:translate-x-0.5 group-hover:text-[#047857]" strokeWidth={2.25} />
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}

function Metric({ icon: Icon, label, value, sub, tone = 'text-[#0A0A0A]', boxed = false }) {
  return (
    <div className={boxed ? 'rounded-[14px] border border-[#EAEAEA] bg-white p-3.5' : ''}>
      <div className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-[#737373]">
        {Icon && <Icon className="h-3.5 w-3.5" strokeWidth={2} />} {label}
      </div>
      <div className={`mt-1 text-[20px] font-semibold tabular-nums leading-none tracking-[-0.02em] ${tone}`}>{value}</div>
      {sub && <div className="mt-1 text-[11px] text-[#737373]">{sub}</div>}
    </div>
  );
}
