import { Head, usePage } from '@inertiajs/react';
import {
  GraduationCap, PlayCircle, Library, BookOpen, CalendarDays, Clock,
  CheckCircle2, ChevronRight, Archive,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader from '@/student/components/PageHeader';
import EmptyState from '@/student/components/EmptyState';
import { cn } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Bits                                                               */
/* ------------------------------------------------------------------ */
function Thumb({ src, className }) {
  if (src) {
    return <img src={src} alt="" className={cn('shrink-0 object-cover ring-1 ring-line', className)} />;
  }
  return (
    <div className={cn('grid shrink-0 place-items-center bg-gradient-to-br from-violet-100 to-rose-100 ring-1 ring-line', className)}>
      <BookOpen className="h-1/2 w-1/2 text-brand/60" strokeWidth={1.5} />
    </div>
  );
}

function Badge({ tone = 'emerald', icon: Icon, children }) {
  const tones = {
    emerald: 'bg-emerald-100 text-emerald-700',
    violet: 'bg-brand-soft text-brand-ink',
    slate: 'bg-slate-100 text-slate-600',
  };
  return (
    <span className={cn('inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold', tones[tone])}>
      {Icon && <Icon className="h-3 w-3" strokeWidth={2.4} />}
      {children}
    </span>
  );
}

function MiniRing({ value, size = 56, stroke = 6 }) {
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const offset = c - (Math.min(100, Math.max(0, value)) / 100) * c;
  return (
    <div className="relative shrink-0" style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="#EDE9FE" strokeWidth={stroke} />
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="#7C3AED" strokeWidth={stroke} strokeLinecap="round" strokeDasharray={c} strokeDashoffset={offset} />
      </svg>
      <div className="absolute inset-0 grid place-items-center text-[12px] font-extrabold text-brand-ink">{value}%</div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Stat cards                                                         */
/* ------------------------------------------------------------------ */
function StatRow({ stats }) {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div className="glass-card fade-up flex items-center gap-4 rounded-2xl p-4">
        <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-brand-soft text-brand">
          <GraduationCap className="h-6 w-6" strokeWidth={2} />
        </div>
        <div className="min-w-0">
          <p className="text-[13px] font-semibold text-muted">Kelas Lengkap</p>
          <p className="text-[26px] font-extrabold leading-tight text-ink">{stats.completed}</p>
          <p className="truncate text-[11px] text-muted-2">Semua modul selesai</p>
        </div>
      </div>

      <div className="glass-card fade-up flex items-center gap-4 rounded-2xl p-4" style={{ animationDelay: '0.04s' }}>
        <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-emerald-100 text-emerald-600">
          <PlayCircle className="h-6 w-6" strokeWidth={2} />
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-[13px] font-semibold text-muted">Kelas Aktif</p>
          <p className="text-[26px] font-extrabold leading-tight text-ink">{stats.active}</p>
          <p className="truncate text-[11px] text-muted-2">dari {stats.total} kelas</p>
        </div>
        {stats.active > 0 && <MiniRing value={stats.activeProgress} />}
      </div>

      <div className="glass-card fade-up flex items-center gap-4 rounded-2xl p-4" style={{ animationDelay: '0.08s' }}>
        <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-sky-100 text-sky-600">
          <Library className="h-6 w-6" strokeWidth={2} />
        </div>
        <div className="min-w-0">
          <p className="text-[13px] font-semibold text-muted">Kelas Tamat</p>
          <p className="text-[26px] font-extrabold leading-tight text-ink">{stats.tamat}</p>
          <p className="truncate text-[11px] text-muted-2">Rakaman masih boleh diakses</p>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Section wrapper                                                    */
/* ------------------------------------------------------------------ */
function Section({ icon: Icon, title, subtitle, count, children, delay = 0 }) {
  return (
    <section className="fade-up" style={{ animationDelay: `${delay}s` }}>
      <div className="mb-3 flex items-center gap-2.5">
        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-soft">
          <Icon className="h-[18px] w-[18px] text-brand" strokeWidth={2} />
        </div>
        <div className="flex-1">
          <div className="flex items-center gap-2">
            <h3 className="text-[16px] font-bold text-ink">{title}</h3>
            <span className="grid h-5 min-w-5 place-items-center rounded-full bg-brand-soft px-1.5 text-[11px] font-bold text-brand-ink">{count}</span>
          </div>
          {subtitle && <p className="text-[12px] text-muted">{subtitle}</p>}
        </div>
      </div>
      <div className="space-y-3">{children}</div>
    </section>
  );
}

/* ------------------------------------------------------------------ */
/*  Cards                                                              */
/* ------------------------------------------------------------------ */
function ActiveCard({ cls }) {
  return (
    <a href={`/my/classes/${cls.classId}`} className="glass-card card-hover block rounded-2xl p-4">
      <div className="flex gap-4">
        <Thumb src={cls.thumbnail} className="h-20 w-20 rounded-xl sm:h-[88px] sm:w-[88px]" />
        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0">
              <h4 className="truncate text-[15px] font-bold text-ink">{cls.title}</h4>
              <p className="text-[12px] text-muted">Modul {cls.moduleDone} dari {cls.moduleTotal}</p>
            </div>
            <Badge tone="emerald" icon={PlayCircle}>Aktif</Badge>
          </div>

          <div className="mt-2 flex items-center gap-2">
            <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-brand-100">
              <div className="h-full rounded-full bg-gradient-to-r from-violet-500 to-rose-500" style={{ width: `${cls.progress}%` }} />
            </div>
            <span className="shrink-0 text-[12px] font-bold text-brand">{cls.progress}%</span>
          </div>

          <div className="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11.5px] text-muted">
            {cls.startLabel && (
              <span className="inline-flex items-center gap-1.5"><CalendarDays className="h-3.5 w-3.5 text-brand/70" strokeWidth={2} />{cls.startLabel}</span>
            )}
            {cls.scheduleLabel && (
              <span className="inline-flex items-center gap-1.5"><Clock className="h-3.5 w-3.5 text-brand/70" strokeWidth={2} />{cls.scheduleLabel}</span>
            )}
          </div>
        </div>
      </div>

      <div className="mt-3 flex justify-end">
        <span className="inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-[12.5px] font-semibold text-white">
          <PlayCircle className="h-4 w-4" strokeWidth={2.2} />
          {cls.hasRecording ? 'Tonton Rakaman' : 'Lihat Kelas'}
        </span>
      </div>
    </a>
  );
}

function DoneCard({ cls, tone, badge, badgeIcon, action }) {
  return (
    <a href={`/my/classes/${cls.classId}`} className="glass-card card-hover flex items-center gap-4 rounded-2xl p-4">
      <Thumb src={cls.thumbnail} className="h-14 w-14 rounded-xl sm:h-16 sm:w-16" />
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <h4 className="truncate text-[14.5px] font-bold text-ink">{cls.title}</h4>
          <Badge tone={tone} icon={badgeIcon}>{badge}</Badge>
        </div>
        <p className="truncate text-[12px] text-muted">
          {cls.moduleTotal} modul{cls.endLabel ? ` • ${cls.endLabel}` : ''}
        </p>
      </div>
      <span className="hidden shrink-0 items-center gap-1.5 rounded-xl border border-brand-100 px-3.5 py-2 text-[12.5px] font-semibold text-brand-ink sm:inline-flex">
        {action}
      </span>
      <ChevronRight className="h-5 w-5 shrink-0 text-muted-2" strokeWidth={2} />
    </a>
  );
}

/* ================================================================== */
/*  Page                                                               */
/* ================================================================== */
export default function Classes() {
  const { stats, aktif, lengkap, tamat } = usePage().props;
  const isEmpty = aktif.length === 0 && lengkap.length === 0 && tamat.length === 0;

  const hero = (
    <PageHeader
      icon={GraduationCap}
      title="Kelas Saya"
      subtitle="Teruskan perjalanan ilmu anda bersama BeDaie."
    />
  );

  return (
    <StudentLayout hero={hero}>
      <Head title="Kelas Saya" />

      <div className="space-y-6 pt-5">
        <StatRow stats={stats} />

        {isEmpty ? (
          <EmptyState
            icon={GraduationCap}
            title="Belum ada kelas"
            description="Anda belum mendaftar dalam mana-mana kelas. Terokai kursus untuk mula belajar."
            action={
              <a href="/my/courses" className="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-2.5 text-[13px] font-semibold text-white">
                <BookOpen className="h-4 w-4" strokeWidth={2} /> Terokai Kursus
              </a>
            }
          />
        ) : (
          <>
            {aktif.length > 0 && (
              <Section icon={PlayCircle} title="Kelas Aktif" subtitle="Kelas yang sedang anda ikuti. Teruskan pembelajaran anda!" count={aktif.length}>
                {aktif.map((c) => <ActiveCard key={c.classId} cls={c} />)}
              </Section>
            )}

            {lengkap.length > 0 && (
              <Section icon={GraduationCap} title="Kelas Lengkap" subtitle="Anda telah menamatkan semua modul. Tahniah!" count={lengkap.length} delay={0.05}>
                {lengkap.map((c) => (
                  <DoneCard key={c.classId} cls={c} tone="violet" badge="Selesai" badgeIcon={CheckCircle2} action="Lihat Semula" />
                ))}
              </Section>
            )}

            {tamat.length > 0 && (
              <Section icon={Library} title="Kelas Tamat" subtitle="Kelas yang telah tamat tempoh tetapi masih boleh diakses rakamannya." count={tamat.length} delay={0.1}>
                {tamat.map((c) => (
                  <DoneCard key={c.classId} cls={c} tone="slate" badge={c.statusLabel || 'Tamat'} badgeIcon={Archive} action="Akses Rakaman" />
                ))}
              </Section>
            )}
          </>
        )}
      </div>
    </StudentLayout>
  );
}
