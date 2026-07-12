import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
  CalendarDays,
  CheckCircle2,
  ExternalLink,
  Film,
  Link2,
  Plus,
  Trash2,
  Video,
} from 'lucide-react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';

/**
 * Daily Video log (Pocket) — the host records the video(s) they made today with
 * a title and an optional link. Making a daily video is a mentoring KPI, so a
 * compliance banner nudges the host until at least one video is logged for the
 * day. Multiple videos per day are supported; history is grouped by date.
 * Data comes from {@link \App\Http\Controllers\LiveHostPocket\DailyVideoController}.
 */
export default function DailyVideos() {
  const { enrollment, categories = [], today, history, stats } = usePage().props;

  if (!enrollment) {
    return <NotEnrolled />;
  }

  const loggedToday = Boolean(stats?.logged_today);

  return (
    <>
      <Head title="Videos" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <div className="px-1 pt-3 pb-4">
          <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Daily video
          </div>
          <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">
            Log today&rsquo;s video.
          </h1>
          <p className="mt-1 text-[12px] leading-relaxed text-[var(--fg-2)]">
            Record every video you made today — a title, and a link if you have one.
          </p>
        </div>

        <ComplianceBanner loggedToday={loggedToday} count={today?.videos?.length ?? 0} />

        <LogForm categories={categories} todayDate={today?.date} />

        <SectionHeading>{today?.label ?? 'Today'}</SectionHeading>
        <TodayList videos={today?.videos ?? []} />

        <MonthStats stats={stats} />

        {history.length > 0 && (
          <>
            <SectionHeading>Earlier this month</SectionHeading>
            <History history={history} />
          </>
        )}
      </div>
    </>
  );
}

DailyVideos.layout = (page) => <PocketLayout>{page}</PocketLayout>;

function ComplianceBanner({ loggedToday, count }) {
  if (loggedToday) {
    return (
      <div className="mb-3 flex items-center gap-2.5 rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[14px] py-3">
        <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[color-mix(in_srgb,var(--accent)_12%,transparent)] text-[var(--accent)]">
          <CheckCircle2 className="h-[18px] w-[18px]" strokeWidth={2.2} />
        </span>
        <div className="min-w-0">
          <div className="text-[13px] font-semibold text-[var(--fg)]">Done for today</div>
          <div className="text-[11.5px] text-[var(--fg-2)]">
            {count} video{count === 1 ? '' : 's'} logged. Add more anytime.
          </div>
        </div>
      </div>
    );
  }

  return (
    <div
      className="mb-3 flex items-center gap-2.5 rounded-[14px] border border-[var(--accent)] px-[14px] py-3"
      style={{ backgroundImage: 'linear-gradient(165deg, var(--accent-soft), transparent 60%)' }}
    >
      <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[var(--accent)] text-[var(--accent-ink)]">
        <Video className="h-[18px] w-[18px]" strokeWidth={2.2} />
      </span>
      <div className="min-w-0">
        <div className="text-[13px] font-semibold text-[var(--fg)]">No video yet today</div>
        <div className="text-[11.5px] text-[var(--fg-2)]">Log the video you made to stay on track.</div>
      </div>
    </div>
  );
}

function LogForm({ categories, todayDate }) {
  const [date, setDate] = useState(todayDate ?? '');
  const [title, setTitle] = useState('');
  const [category, setCategory] = useState('');
  const [link, setLink] = useState('');
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  const canSubmit = title.trim() && category && date && !busy;

  const submit = (e) => {
    e.preventDefault();
    if (!canSubmit) return;
    setBusy(true);
    router.post(
      '/live-host/videos',
      { date, title: title.trim(), category, link: link.trim() || null },
      {
        preserveScroll: true,
        onSuccess: () => { setTitle(''); setCategory(''); setLink(''); setDate(todayDate ?? ''); setErrors({}); },
        onError: (e) => setErrors(e),
        onFinish: () => setBusy(false),
      },
    );
  };

  return (
    <form onSubmit={submit} className="mb-4 rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[14px]">
      <label className="mb-1 block font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        Date
      </label>
      <div className="flex items-center gap-2 rounded-[11px] border border-[var(--hair-2)] bg-[var(--app-bg)] px-3">
        <CalendarDays className="h-4 w-4 shrink-0 text-[var(--fg-3)]" strokeWidth={2} />
        <input
          type="date"
          value={date}
          max={todayDate}
          onChange={(e) => setDate(e.target.value)}
          className="w-full bg-transparent py-[10px] text-[13px] text-[var(--fg)] focus:outline-none"
        />
      </div>
      {errors.date && <p className="mt-1 text-[11px] text-[var(--hot)]">{errors.date}</p>}

      <label className="mb-1.5 mt-3 block font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        Category
      </label>
      <div className="flex flex-wrap gap-1.5">
        {categories.map((c) => {
          const active = category === c.key;
          return (
            <button
              key={c.key}
              type="button"
              onClick={() => setCategory(c.key)}
              aria-pressed={active}
              className={`rounded-full border px-3 py-[7px] text-[12px] font-semibold transition active:scale-95 ${
                active
                  ? 'border-[var(--accent)] bg-[var(--accent)] text-[var(--accent-ink)]'
                  : 'border-[var(--hair-2)] bg-[var(--app-bg)] text-[var(--fg-2)]'
              }`}
            >
              {c.label}
            </button>
          );
        })}
      </div>
      {errors.category && <p className="mt-1 text-[11px] text-[var(--hot)]">{errors.category}</p>}

      <label className="mb-1 mt-3 block font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        Video title
      </label>
      <input
        type="text"
        value={title}
        onChange={(e) => setTitle(e.target.value)}
        placeholder="e.g. Skincare routine demo"
        maxLength={255}
        className="w-full rounded-[11px] border border-[var(--hair-2)] bg-[var(--app-bg)] px-3 py-[10px] text-[13px] text-[var(--fg)] placeholder:text-[var(--fg-3)] focus:border-[var(--accent)] focus:outline-none"
      />
      {errors.title && <p className="mt-1 text-[11px] text-[var(--hot)]">{errors.title}</p>}

      <label className="mb-1 mt-3 block font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        Link <span className="text-[var(--fg-3)]">· optional</span>
      </label>
      <div className="flex items-center gap-2 rounded-[11px] border border-[var(--hair-2)] bg-[var(--app-bg)] px-3">
        <Link2 className="h-4 w-4 shrink-0 text-[var(--fg-3)]" strokeWidth={2} />
        <input
          type="url"
          inputMode="url"
          value={link}
          onChange={(e) => setLink(e.target.value)}
          placeholder="https://…"
          className="w-full bg-transparent py-[10px] text-[13px] text-[var(--fg)] placeholder:text-[var(--fg-3)] focus:outline-none"
        />
      </div>
      {errors.link && <p className="mt-1 text-[11px] text-[var(--hot)]">{errors.link}</p>}

      <button
        type="submit"
        disabled={!canSubmit}
        className="mt-3 flex w-full items-center justify-center gap-1.5 rounded-[11px] bg-[var(--accent)] px-0 py-[11px] font-sans text-[13px] font-bold text-[var(--accent-ink)] transition active:scale-[0.98] disabled:opacity-40"
      >
        <Plus className="h-4 w-4" strokeWidth={2.4} />
        {busy ? 'Logging…' : 'Log video'}
      </button>
    </form>
  );
}

function TodayList({ videos }) {
  if (videos.length === 0) {
    return (
      <div className="mb-4 rounded-[14px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-5 text-center text-[12px] text-[var(--fg-3)]">
        No videos logged yet today.
      </div>
    );
  }

  return (
    <div className="mb-4">
      {videos.map((v) => (
        <VideoCard key={v.id} video={v} />
      ))}
    </div>
  );
}

function VideoCard({ video }) {
  const remove = () => {
    if (!window.confirm('Remove this video?')) return;
    router.delete(`/live-host/videos/${video.id}`, { preserveScroll: true });
  };

  return (
    <div className="mb-[6px] flex items-start gap-2.5 rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-[11px]">
      <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-[10px] bg-[var(--accent-soft)] text-[var(--accent)]">
        <Film className="h-4 w-4" strokeWidth={2} />
      </span>
      <div className="min-w-0 flex-1">
        <div className="text-[13px] font-semibold leading-snug text-[var(--fg)]">{video.title}</div>
        <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
          {video.category_label && (
            <span className="rounded-full bg-[var(--accent-soft)] px-2 py-0.5 text-[10px] font-semibold text-[var(--accent)]">
              {video.category_label}
            </span>
          )}
          {video.time_human && (
            <span className="font-mono text-[9.5px] uppercase tracking-[0.1em] text-[var(--fg-3)]">
              {video.time_human}
            </span>
          )}
          {video.link && (
            <a
              href={video.link}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1 text-[11px] font-medium text-[var(--accent)]"
            >
              <ExternalLink className="h-3 w-3" strokeWidth={2.2} /> Open link
            </a>
          )}
        </div>
      </div>
      <button
        type="button"
        onClick={remove}
        aria-label="Remove video"
        className="shrink-0 rounded-lg p-1.5 text-[var(--fg-3)] transition hover:bg-[var(--app-bg)] hover:text-[var(--hot)] active:scale-95"
      >
        <Trash2 className="h-4 w-4" strokeWidth={2} />
      </button>
    </div>
  );
}

function MonthStats({ stats }) {
  if (!stats) return null;
  return (
    <div className="mb-2 grid grid-cols-2 gap-2">
      <StatTile label={`Videos in ${stats.month_label}`} value={stats.month_videos} />
      <StatTile label={`Days logged in ${stats.month_label}`} value={stats.month_days} />
    </div>
  );
}

function StatTile({ label, value }) {
  return (
    <div className="rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[14px] py-3">
      <div className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">{label}</div>
      <div className="mt-[6px] font-display text-[24px] font-medium leading-none tracking-[-0.04em] tabular-nums text-[var(--fg)]">
        {value}
      </div>
    </div>
  );
}

function History({ history }) {
  return (
    <div>
      {history.map((group) => (
        <div key={group.date} className="mb-3">
          <div className="mb-1 flex items-center justify-between px-1">
            <span className="font-mono text-[9.5px] font-bold uppercase tracking-[0.12em] text-[var(--fg-3)]">
              {group.date_human}
            </span>
            <span className="font-mono text-[9.5px] text-[var(--fg-3)]">
              {group.count} video{group.count === 1 ? '' : 's'}
            </span>
          </div>
          {group.videos.map((v) => (
            <VideoCard key={v.id} video={v} />
          ))}
        </div>
      ))}
    </div>
  );
}

function SectionHeading({ children }) {
  return (
    <div className="mt-2 mb-2 flex items-baseline justify-between px-1">
      <h3 className="font-display text-[13px] font-medium tracking-[-0.015em] text-[var(--fg)]">{children}</h3>
    </div>
  );
}

function NotEnrolled() {
  return (
    <>
      <Head title="Videos" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <div className="px-1 pt-3 pb-4">
          <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Daily video
          </div>
          <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">
            Daily videos.
          </h1>
        </div>
        <div className="rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-4 py-8 text-center">
          <span className="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-full bg-[var(--accent-soft)] text-[var(--accent)]">
            <Video className="h-5 w-5" strokeWidth={2} />
          </span>
          <div className="text-[13px] font-semibold text-[var(--fg)]">Not in a mentoring program</div>
          <p className="mx-auto mt-1 max-w-[260px] text-[12px] leading-relaxed text-[var(--fg-2)]">
            The daily video log is part of new-host mentoring. It&rsquo;ll appear here once you&rsquo;re enrolled.
          </p>
        </div>
      </div>
    </>
  );
}
