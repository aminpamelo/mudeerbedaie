import { Head, usePage } from '@inertiajs/react';
import {
  BookOpen, PlayCircle, GraduationCap, Library, CalendarDays, Clock,
  UserRound, Megaphone, ChevronRight, ArrowRight, Radio, Sparkles,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import { HADITH_QUOTES, MOTIVATION_LINES, pickDaily } from '@/student/components/BrandArt';
import { cn } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Hero — hadith quote                                                */
/* ------------------------------------------------------------------ */
function QuoteHero({ dateLabel }) {
  const quote = pickDaily(HADITH_QUOTES);
  return (
    <div className="hero-banner fade-up relative overflow-hidden rounded-3xl ring-1 ring-brand-100">
      <div className="pattern-islamic absolute inset-0 opacity-60" />
      <div className="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/40 blur-3xl" />
      {dateLabel && (
        <div className="absolute right-5 top-5 hidden rounded-full bg-white/65 px-3 py-1 text-[11px] font-semibold text-brand-ink ring-1 ring-brand-100 sm:block">
          {dateLabel}
        </div>
      )}
      <div className="relative flex items-start gap-4 p-6 sm:items-center sm:gap-5 sm:p-8">
        <div className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-white/70 ring-1 ring-brand-100 sm:h-16 sm:w-16">
          <BookOpen className="h-7 w-7 text-brand sm:h-8 sm:w-8" strokeWidth={1.7} />
        </div>
        <div className="min-w-0 flex-1 pr-2">
          <p className="text-[15px] font-medium italic leading-relaxed text-ink-2 sm:text-[17px]">
            &ldquo;{quote.text}&rdquo;
          </p>
          <p className="mt-2 text-[13px] font-semibold text-brand-ink">&mdash; {quote.source}</p>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Live now                                                           */
/* ------------------------------------------------------------------ */
function LiveAlert({ session }) {
  if (!session) return null;
  return (
    <a
      href={session.meetingUrl || `/my/classes/${session.classId}`}
      target={session.meetingUrl ? '_blank' : undefined}
      rel="noopener noreferrer"
      className="fade-up flex items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm"
    >
      <div className="flex min-w-0 items-center gap-3">
        <span className="relative flex h-3 w-3 shrink-0">
          <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
          <span className="relative inline-flex h-3 w-3 rounded-full bg-emerald-500" />
        </span>
        <div className="min-w-0">
          <p className="text-[14px] font-bold text-emerald-800">Kelas sedang berlangsung</p>
          <p className="truncate text-[12px] text-emerald-600">{session.classTitle}</p>
        </div>
      </div>
      <span className="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-[13px] font-semibold text-white">
        <Radio className="h-4 w-4" strokeWidth={2} /> Sertai
      </span>
    </a>
  );
}

/* ------------------------------------------------------------------ */
/*  Stat tiles                                                         */
/* ------------------------------------------------------------------ */
function StatTiles({ stats }) {
  const tiles = [
    { key: 'aktif', label: 'Kelas Aktif', value: stats.active, sub: `dari ${stats.total} kelas`, icon: PlayCircle, ring: 'bg-emerald-100 text-emerald-600', href: '/my/classes' },
    { key: 'lengkap', label: 'Kelas Lengkap', value: stats.completed, sub: 'Semua modul selesai', icon: GraduationCap, ring: 'bg-brand-soft text-brand', href: '/my/classes' },
    { key: 'tamat', label: 'Kelas Tamat', value: stats.tamat, sub: 'Rakaman masih boleh diakses', icon: Library, ring: 'bg-sky-100 text-sky-600', href: '/my/classes' },
  ];
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
      {tiles.map((tl, i) => {
        const Icon = tl.icon;
        return (
          <a
            key={tl.key}
            href={tl.href}
            className="glass-card card-hover fade-up flex items-center gap-4 rounded-2xl p-4"
            style={{ animationDelay: `${0.04 * i}s` }}
          >
            <div className={cn('grid h-12 w-12 shrink-0 place-items-center rounded-2xl', tl.ring)}>
              <Icon className="h-6 w-6" strokeWidth={2} />
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-[13px] font-semibold text-muted">{tl.label}</p>
              <p className="text-[26px] font-extrabold leading-tight text-ink">{tl.value}</p>
              <p className="truncate text-[11px] text-muted-2">{tl.sub}</p>
            </div>
            <ChevronRight className="h-5 w-5 shrink-0 text-muted-2" strokeWidth={2} />
          </a>
        );
      })}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Progress ring                                                      */
/* ------------------------------------------------------------------ */
function ProgressRing({ value, size = 128, stroke = 12 }) {
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const offset = c - (Math.min(100, Math.max(0, value)) / 100) * c;
  return (
    <div className="relative shrink-0" style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="#EDE9FE" strokeWidth={stroke} />
        <circle
          cx={size / 2} cy={size / 2} r={r} fill="none" stroke="url(#ringGrad)"
          strokeWidth={stroke} strokeLinecap="round" strokeDasharray={c} strokeDashoffset={offset}
          style={{ transition: 'stroke-dashoffset 0.8s ease' }}
        />
        <defs>
          <linearGradient id="ringGrad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stopColor="#8B5CF6" />
            <stop offset="100%" stopColor="#F43F5E" />
          </linearGradient>
        </defs>
      </svg>
      <div className="absolute inset-0 grid place-items-center text-center">
        <div>
          <p className="text-[26px] font-extrabold leading-none text-ink">{value}%</p>
          <p className="mt-1 text-[10px] font-semibold uppercase tracking-wide text-muted">Keseluruhan</p>
        </div>
      </div>
    </div>
  );
}

function SectionHead({ icon: Icon, title, subtitle, href, action = 'Lihat Semua' }) {
  return (
    <div className="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
      <div className="flex items-center gap-2.5">
        {Icon && (
          <div className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand-soft">
            <Icon className="h-4 w-4 text-brand" strokeWidth={2} />
          </div>
        )}
        <div>
          <h3 className="text-[15px] font-bold text-ink">{title}</h3>
          {subtitle && <p className="text-[12px] text-muted">{subtitle}</p>}
        </div>
      </div>
      {href && (
        <a href={href} className="mt-1 inline-flex shrink-0 items-center gap-1 text-[12px] font-semibold text-brand transition-colors hover:text-brand-ink">
          {action} <ArrowRight className="h-3.5 w-3.5" strokeWidth={2} />
        </a>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Progress Kelas                                                     */
/* ------------------------------------------------------------------ */
function ProgressKelas({ overall, classes }) {
  return (
    <div className="glass-card fade-up flex h-full flex-col rounded-2xl">
      <SectionHead icon={Sparkles} title="Progress Kelas" subtitle="Perkembangan pembelajaran anda" href="/my/classes" />
      <div className="flex flex-col items-center gap-6 p-5 sm:flex-row sm:items-center">
        <ProgressRing value={overall} />
        <div className="w-full min-w-0 flex-1 space-y-3.5">
          {classes.length === 0 && (
            <p className="text-[13px] text-muted">Tiada kelas aktif buat masa ini.</p>
          )}
          {classes.map((c) => (
            <a key={c.id} href={`/my/classes/${c.id}`} className="flex items-center gap-3">
              <Thumb src={c.thumbnail} className="h-11 w-11 rounded-xl" />
              <div className="min-w-0 flex-1">
                <p className="truncate text-[13px] font-bold text-ink">{c.title}</p>
                <p className="text-[11px] text-muted">Modul {c.moduleDone} dari {c.moduleTotal}</p>
                <div className="mt-1.5 flex items-center gap-2">
                  <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-brand-100">
                    <div className="h-full rounded-full bg-gradient-to-r from-violet-500 to-rose-500" style={{ width: `${c.progress}%` }} />
                  </div>
                  <span className="shrink-0 text-[11px] font-bold text-brand">{c.progress}%</span>
                </div>
              </div>
            </a>
          ))}
        </div>
      </div>
    </div>
  );
}

function Thumb({ src, className, icon: Icon = BookOpen }) {
  if (src) {
    return <img src={src} alt="" className={cn('shrink-0 object-cover ring-1 ring-line', className)} />;
  }
  return (
    <div className={cn('grid shrink-0 place-items-center bg-gradient-to-br from-violet-100 to-rose-100 ring-1 ring-line', className)}>
      <Icon className="h-1/2 w-1/2 text-brand/60" strokeWidth={1.6} />
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Kelas Seterusnya                                                   */
/* ------------------------------------------------------------------ */
function KelasSeterusnya({ next }) {
  return (
    <div className="glass-card fade-up flex h-full flex-col rounded-2xl" style={{ animationDelay: '0.05s' }}>
      <SectionHead icon={CalendarDays} title="Kelas Seterusnya" href="/my/timetable" />
      {next ? (
        <div className="flex flex-1 flex-col p-5">
          <div className="flex items-start gap-4">
            <div className="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-brand-soft text-center">
              <div>
                <p className="text-[22px] font-extrabold leading-none text-brand-ink">{next.dateDay}</p>
                <p className="text-[11px] font-semibold uppercase text-brand">{next.dateMonth}</p>
              </div>
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-[15px] font-bold text-ink">{next.title}</p>
              {next.courseName && <p className="truncate text-[12px] text-muted">{next.courseName}</p>}
              <div className="mt-2 space-y-1.5">
                <p className="flex items-center gap-2 text-[12px] text-ink-2">
                  <Clock className="h-3.5 w-3.5 text-brand" strokeWidth={2} /> {next.timeRange}
                </p>
                {next.teacherName && (
                  <p className="flex items-center gap-2 text-[12px] text-ink-2">
                    <UserRound className="h-3.5 w-3.5 text-brand" strokeWidth={2} /> {next.teacherName}
                  </p>
                )}
              </div>
            </div>
          </div>
          <a
            href={`/my/classes/${next.classId}`}
            className="mt-4 inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-soft px-4 py-2.5 text-[13px] font-semibold text-brand-ink transition-colors hover:bg-brand-100"
          >
            Lihat Kelas <ArrowRight className="h-4 w-4" strokeWidth={2} />
          </a>
        </div>
      ) : (
        <div className="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center">
          <CalendarDays className="h-8 w-8 text-brand/30" strokeWidth={1.5} />
          <p className="text-[13px] font-medium text-muted">Tiada kelas dijadualkan</p>
          <a href="/my/timetable" className="text-[12px] font-semibold text-brand hover:text-brand-ink">Lihat jadual penuh &rarr;</a>
        </div>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Pengumuman                                                         */
/* ------------------------------------------------------------------ */
const DOT = { amber: 'bg-amber-400', sky: 'bg-sky-400', emerald: 'bg-emerald-400', violet: 'bg-violet-400' };

function Pengumuman({ items }) {
  return (
    <div className="glass-card fade-up flex h-full flex-col rounded-2xl" style={{ animationDelay: '0.1s' }}>
      <SectionHead icon={Megaphone} title="Pengumuman" href="/my/classes" />
      {items.length ? (
        <div className="flex-1 divide-y divide-line">
          {items.map((a) => (
            <a key={a.id} href={`/my/classes/${a.classId}?tab=announcements`} className="flex items-start gap-3 px-5 py-3 transition-colors hover:bg-brand-soft/40">
              <span className={cn('mt-1.5 h-2 w-2 shrink-0 rounded-full', DOT[a.color] || DOT.violet)} />
              <div className="min-w-0 flex-1">
                <p className="text-[13px] font-semibold leading-snug text-ink">{a.title}</p>
                <p className="mt-0.5 text-[11px] text-muted-2">{a.dateLabel}</p>
              </div>
            </a>
          ))}
        </div>
      ) : (
        <div className="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center">
          <Megaphone className="h-8 w-8 text-brand/30" strokeWidth={1.5} />
          <p className="text-[13px] font-medium text-muted">Tiada pengumuman baharu</p>
        </div>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Jadual Kelas (calendar) + Aktiviti                                 */
/* ------------------------------------------------------------------ */
const WEEKDAYS = ['Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab', 'Ahd'];

function JadualKelas({ calendar, activities }) {
  return (
    <div className="glass-card fade-up rounded-2xl" style={{ animationDelay: '0.05s' }}>
      <SectionHead icon={CalendarDays} title="Jadual Kelas" subtitle="Kelas &amp; aktiviti anda" href="/my/timetable" />
      <div className="grid grid-cols-1 gap-5 p-5 lg:grid-cols-2">
        {/* Calendar */}
        <div>
          <div className="mb-3 flex items-center justify-between">
            <p className="text-[14px] font-bold text-ink">{calendar.monthLabel}</p>
          </div>
          <div className="grid grid-cols-7 gap-1 text-center">
            {WEEKDAYS.map((d) => (
              <div key={d} className="pb-1 text-[10px] font-bold uppercase text-muted-2">{d}</div>
            ))}
            {calendar.days.map((d, i) => (
              <div key={i} className="relative grid aspect-square place-items-center">
                <span
                  className={cn(
                    'grid h-8 w-8 place-items-center rounded-full text-[12px] font-semibold',
                    d.isToday && 'bg-brand text-white shadow-md shadow-brand/30',
                    !d.isToday && d.inMonth && 'text-ink',
                    !d.inMonth && 'text-muted-2/40'
                  )}
                >
                  {d.day}
                </span>
                {d.hasEvent && !d.isToday && (
                  <span className={cn('absolute bottom-0.5 h-1 w-1 rounded-full', d.inMonth ? 'bg-brand' : 'bg-muted-2/40')} />
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Activities */}
        <div className="lg:border-l lg:border-line lg:pl-5">
          <p className="mb-3 text-[14px] font-bold text-ink">Aktiviti Bulan Ini</p>
          {activities.length ? (
            <div className="space-y-3">
              {activities.map((a, i) => (
                <div key={i} className="flex items-start gap-3">
                  <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-soft text-[11px] font-bold leading-tight text-brand-ink">
                    {a.dateLabel.split(' ')[0]}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-[13px] font-semibold text-ink">{a.title}</p>
                    <p className="text-[11px] text-muted">{a.time}</p>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-[13px] text-muted">Tiada aktiviti akan datang bulan ini.</p>
          )}
          <a href="/my/timetable" className="mt-4 inline-flex items-center gap-1 text-[12px] font-semibold text-brand hover:text-brand-ink">
            Lihat Semua Aktiviti <ArrowRight className="h-3.5 w-3.5" strokeWidth={2} />
          </a>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Motivation + popular courses                                       */
/* ------------------------------------------------------------------ */
function MotivationCard() {
  const line = pickDaily(MOTIVATION_LINES);
  return (
    <div className="fade-up relative overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 via-violet-700 to-fuchsia-700 p-5 text-white shadow-lg shadow-brand/20" style={{ animationDelay: '0.1s' }}>
      <div className="pattern-islamic absolute inset-0 opacity-20" />
      <div className="relative">
        <p className="text-[15px] font-medium italic leading-relaxed">&ldquo;{line}&rdquo;</p>
        <a href="/my/courses" className="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2 text-[13px] font-bold text-brand-ink transition-transform hover:-translate-y-0.5">
          Lihat Kursus <ArrowRight className="h-4 w-4" strokeWidth={2.2} />
        </a>
      </div>
    </div>
  );
}

function KursusPopular({ courses }) {
  if (!courses.length) return null;
  return (
    <div className="glass-card fade-up rounded-2xl" style={{ animationDelay: '0.15s' }}>
      <SectionHead icon={BookOpen} title="Kursus Popular" href="/my/courses" />
      <div className="divide-y divide-line">
        {courses.map((c) => (
          <a key={c.id} href="/my/courses" className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-brand-soft/40">
            <Thumb src={c.thumbnail} className="h-10 w-10 rounded-lg" />
            <div className="min-w-0 flex-1">
              <p className="truncate text-[13px] font-semibold text-ink">{c.name}</p>
              {c.shortDescription && <p className="truncate text-[11px] text-muted">{c.shortDescription}</p>}
            </div>
            <ChevronRight className="h-4 w-4 shrink-0 text-muted-2" strokeWidth={2} />
          </a>
        ))}
      </div>
    </div>
  );
}

/* ================================================================== */
/*  Page                                                               */
/* ================================================================== */
export default function Dashboard() {
  const {
    dateLabel, stats, overallProgress, activeClassProgress,
    nextClass, ongoingSession, announcements, calendar, monthActivities, popularCourses,
  } = usePage().props;

  return (
    <StudentLayout hero={<QuoteHero dateLabel={dateLabel} />}>
      <Head title="Utama" />

      <div className="space-y-5 pt-5">
        <LiveAlert session={ongoingSession} />

        <StatTiles stats={stats} />

        <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-12">
          <div className="md:col-span-2 xl:col-span-5"><ProgressKelas overall={overallProgress} classes={activeClassProgress} /></div>
          <div className="xl:col-span-4"><KelasSeterusnya next={nextClass} /></div>
          <div className="xl:col-span-3"><Pengumuman items={announcements} /></div>
        </div>

        <div className="grid grid-cols-1 gap-5 xl:grid-cols-12">
          <div className="xl:col-span-8"><JadualKelas calendar={calendar} activities={monthActivities} /></div>
          <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:col-span-4 xl:grid-cols-1">
            <MotivationCard />
            <KursusPopular courses={popularCourses} />
          </div>
        </div>
      </div>
    </StudentLayout>
  );
}
