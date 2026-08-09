import { Head, usePage } from '@inertiajs/react';
import {
  BookOpen, GraduationCap, Calendar, ChevronRight, CheckCircle,
  Megaphone, ArrowRight, Sparkles, Clock,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader, { HeroStat } from '@/student/components/PageHeader';
import { cn, t } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Live Session Alert                                                 */
/* ------------------------------------------------------------------ */
function LiveAlert({ session }) {
  if (!session) return null;
  return (
    <div className="fade-up glass-card overflow-hidden rounded-2xl border border-emerald-200/60 bg-emerald-50/80 shadow-sm">
      <div className="flex items-center justify-between gap-3 p-4">
        <div className="flex items-center gap-3 min-w-0">
          <span className="relative flex h-3 w-3 shrink-0">
            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
            <span className="relative inline-flex h-3 w-3 rounded-full bg-emerald-500" />
          </span>
          <div className="min-w-0">
            <p className="text-[14px] font-bold text-emerald-800">{t('student.dashboard.live_now')}</p>
            <p className="truncate text-[12px] text-emerald-600">{session.classTitle}</p>
          </div>
        </div>
        {session.meetingUrl && (
          <a
            href={session.meetingUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="shrink-0 rounded-xl bg-emerald-600 px-4 py-2 text-[13px] font-semibold text-white shadow-md shadow-emerald-500/30 transition-colors hover:bg-emerald-700"
          >
            {t('student.dashboard.join')}
          </a>
        )}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Unread Announcements                                               */
/* ------------------------------------------------------------------ */
function AnnouncementWidget({ announcements, count }) {
  if (count <= 0) return null;
  return (
    <div className="fade-up glass-card overflow-hidden rounded-2xl border border-violet-200/60 shadow-sm" style={{ animationDelay: '0.05s' }}>
      <div className="flex items-center gap-3 border-b border-violet-100/60 px-4 py-3">
        <div className="grid h-8 w-8 place-items-center rounded-lg bg-violet-100">
          <Megaphone className="h-4 w-4 text-violet-600" strokeWidth={2} />
        </div>
        <p className="text-[14px] font-bold text-violet-900">{count} pengumuman baru</p>
      </div>
      <div className="divide-y divide-violet-50">
        {announcements.map((a) => (
          <a
            key={a.id}
            href={`/my/classes/${a.class_id}?tab=announcements`}
            className="flex items-center gap-2.5 px-4 py-2.5 text-[13px] transition-colors hover:bg-violet-50/60"
          >
            <ArrowRight className="h-3.5 w-3.5 shrink-0 text-violet-400" strokeWidth={2} />
            <span className="font-semibold text-violet-800 truncate">{a.class_title}</span>
            <span className="text-muted truncate">&mdash; {a.title}</span>
          </a>
        ))}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Today's Schedule                                                   */
/* ------------------------------------------------------------------ */
function TodaySchedule({ schedule }) {
  const statusBadge = (slot) => {
    if (slot.status === 'ongoing') return (
      <span className="flex items-center gap-1.5">
        <span className="h-2 w-2 animate-pulse rounded-full bg-emerald-500" />
        <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700">{t('student.status.live')}</span>
      </span>
    );
    if (slot.status === 'completed' || slot.status === 'no_show') return (
      <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-semibold text-gray-600">{t('student.status.done')}</span>
    );
    if (slot.isPast) return (
      <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700">{t('student.status.missed')}</span>
    );
    return (
      <span className="rounded-full bg-blue-100 px-2.5 py-0.5 text-[11px] font-semibold text-blue-700">{t('student.status.upcoming')}</span>
    );
  };

  const formatTime = (t24) => {
    const [h, m] = t24.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return { time: `${h12}:${m.toString().padStart(2, '0')}`, period: ampm };
  };

  return (
    <div className="fade-up glass-card overflow-hidden rounded-2xl shadow-sm" style={{ animationDelay: '0.1s' }}>
      <div className="flex items-center justify-between border-b border-violet-100/60 px-4 py-3">
        <h3 className="text-[14px] font-bold text-ink">{t('student.dashboard.today_schedule')}</h3>
        <span className="text-[12px] font-medium text-muted">
          {schedule.length} {schedule.length === 1 ? 'session' : 'sessions'}
        </span>
      </div>

      {schedule.length === 0 ? (
        <div className="flex flex-col items-center py-10 text-center">
          <div className="mb-3 grid h-14 w-14 place-items-center rounded-2xl bg-violet-50">
            <Calendar className="h-6 w-6 text-violet-300" strokeWidth={1.5} />
          </div>
          <p className="text-[13px] font-medium text-muted">{t('student.dashboard.no_sessions_today')}</p>
        </div>
      ) : (
        <div className="divide-y divide-violet-50">
          {schedule.map((slot, i) => {
            const { time, period } = formatTime(slot.time);
            return (
              <div key={i} className="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-violet-50/40">
                <div className="w-[60px] shrink-0 text-center">
                  <p className="text-[18px] font-bold leading-tight text-ink">{time}</p>
                  <p className="text-[10px] font-semibold uppercase text-muted">{period}</p>
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0">
                      <p className="truncate text-[13px] font-semibold text-ink">{slot.classTitle}</p>
                      <p className="truncate text-[12px] text-muted">{slot.courseName}</p>
                    </div>
                    {statusBadge(slot)}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      <a
        href="/my/timetable"
        className="flex items-center justify-center gap-1.5 border-t border-violet-100/60 bg-violet-50/30 py-3 text-[13px] font-semibold text-violet-600 transition-colors hover:bg-violet-50/60"
      >
        {t('student.dashboard.view_full_schedule')}
        <ChevronRight className="h-4 w-4" strokeWidth={2} />
      </a>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Quick Stats                                                        */
/* ------------------------------------------------------------------ */
function QuickStats({ stats }) {
  const items = [
    { label: t('student.stats.active_classes'), value: stats.activeClasses, color: 'text-violet-600' },
    { label: t('student.stats.this_week'), value: stats.thisWeekSessions, color: 'text-purple-600' },
    { label: t('student.stats.completed'), value: stats.completedSessions, color: 'text-emerald-600' },
  ];
  return (
    <div className="fade-up grid grid-cols-3 gap-3" style={{ animationDelay: '0.15s' }}>
      {items.map((s, i) => (
        <div key={i} className="glass-card rounded-2xl p-4 text-center shadow-sm">
          <p className={cn('text-[22px] font-extrabold leading-tight', s.color)}>{s.value}</p>
          <p className="mt-1 text-[11px] font-medium text-muted">{s.label}</p>
        </div>
      ))}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Upcoming Schedule                                                  */
/* ------------------------------------------------------------------ */
function UpcomingSchedule({ schedule }) {
  if (schedule.length === 0) return null;
  return (
    <div className="fade-up glass-card overflow-hidden rounded-2xl shadow-sm" style={{ animationDelay: '0.2s' }}>
      <div className="border-b border-violet-100/60 px-4 py-3">
        <h3 className="text-[14px] font-bold text-ink">{t('student.dashboard.upcoming_schedule')}</h3>
      </div>
      <div className="divide-y divide-violet-50">
        {schedule.map((slot, i) => {
          const [h, m] = slot.time.split(':').map(Number);
          const ampm = h >= 12 ? 'PM' : 'AM';
          const h12 = h % 12 || 12;
          return (
            <a key={i} href={`/my/classes/${slot.classId}`} className="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-violet-50/40">
              <div className="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-violet-50">
                <span className="text-[10px] font-semibold uppercase leading-none text-violet-500">{slot.dateMonth}</span>
                <span className="text-[18px] font-extrabold leading-none text-violet-700">{slot.dateDay}</span>
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-[13px] font-semibold text-ink">{slot.classTitle}</p>
                <p className="text-[12px] text-muted">
                  {h12}:{m.toString().padStart(2, '0')} {ampm}
                  {slot.durationMinutes ? ` \u2022 ${slot.durationMinutes} min` : ''}
                </p>
              </div>
              <ChevronRight className="h-5 w-5 shrink-0 text-violet-300" strokeWidth={1.8} />
            </a>
          );
        })}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Recent Activity                                                    */
/* ------------------------------------------------------------------ */
function RecentActivity({ activity }) {
  if (activity.length === 0) return null;
  return (
    <div className="fade-up glass-card overflow-hidden rounded-2xl shadow-sm" style={{ animationDelay: '0.25s' }}>
      <div className="border-b border-violet-100/60 px-4 py-3">
        <h3 className="text-[14px] font-bold text-ink">{t('student.dashboard.recent_activity')}</h3>
      </div>
      <div className="divide-y divide-violet-50">
        {activity.map((a, i) => (
          <div key={i} className="flex items-center gap-4 px-4 py-3">
            <div className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-100">
              <CheckCircle className="h-4 w-4 text-emerald-500" strokeWidth={2} />
            </div>
            <div className="min-w-0 flex-1">
              <p className="truncate text-[13px] font-semibold text-ink">{a.title}</p>
              <p className="text-[12px] text-muted">{a.description}</p>
            </div>
            <span className="shrink-0 text-[11px] text-muted-2">{a.dateHuman}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Quick Actions                                                      */
/* ------------------------------------------------------------------ */
function QuickActions() {
  return (
    <div className="fade-up grid grid-cols-2 gap-3" style={{ animationDelay: '0.3s' }}>
      <a
        href="/my/classes"
        className="glass-card flex flex-col rounded-2xl p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
      >
        <GraduationCap className="mb-2 h-7 w-7 text-violet-500" strokeWidth={1.8} />
        <p className="text-[13px] font-bold text-ink">{t('student.quick_actions.my_classes')}</p>
        <p className="text-[11px] text-muted">{t('student.quick_actions.view_enrolled')}</p>
      </a>
      <a
        href="/my/courses"
        className="glass-card flex flex-col rounded-2xl p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
      >
        <BookOpen className="mb-2 h-7 w-7 text-purple-500" strokeWidth={1.8} />
        <p className="text-[13px] font-bold text-ink">{t('student.quick_actions.browse_courses')}</p>
        <p className="text-[11px] text-muted">{t('student.quick_actions.find_new')}</p>
      </a>
    </div>
  );
}

/* ================================================================== */
/*  Page Component                                                     */
/* ================================================================== */
export default function Dashboard() {
  const {
    greeting, dateLabel, todaySchedule, upcomingSchedule,
    ongoingSession, stats, recentActivity,
    unreadAnnouncements, unreadAnnouncementCount, studentStats,
  } = usePage().props;

  const hero = (
    <PageHeader title={greeting} subtitle={dateLabel}>
      <HeroStat icon={GraduationCap} label={t('student.stats.active_classes')} value={stats.activeClasses} />
      <HeroStat icon={Clock} label={t('student.stats.this_week')} value={stats.thisWeekSessions} iconClassName="bg-emerald-400/20" />
    </PageHeader>
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={t('navigation.home')} />

      <div className="space-y-5 pt-4">
        <LiveAlert session={ongoingSession} />
        <AnnouncementWidget announcements={unreadAnnouncements} count={unreadAnnouncementCount} />
        <TodaySchedule schedule={todaySchedule} />
        <QuickStats stats={stats} />
        <UpcomingSchedule schedule={upcomingSchedule} />
        <RecentActivity activity={recentActivity} />
        <QuickActions />
      </div>
    </StudentLayout>
  );
}
