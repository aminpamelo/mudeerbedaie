import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import {
  ArrowLeft, BookOpen, Calendar, ClipboardList, FolderOpen,
  TrendingUp, Megaphone, ChevronLeft, ChevronRight, CheckCircle,
  Clock, Video, X,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader from '@/student/components/PageHeader';
import StatusBadge from '@/student/components/StatusBadge';
import EmptyState from '@/student/components/EmptyState';
import { cn, t } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Tab Nav                                                            */
/* ------------------------------------------------------------------ */
function TabNav({ activeTab, onTab, unreadCount }) {
  const tabs = [
    { key: 'overview', label: t('student.classes.overview'), icon: BookOpen },
    { key: 'timetable', label: t('student.classes.timetable'), icon: Calendar },
    { key: 'sessions', label: t('student.classes.sessions_tab'), icon: ClipboardList },
    { key: 'resources', label: t('student.classes.resources_tab'), icon: FolderOpen },
    { key: 'progress', label: t('student.classes.progress_tab'), icon: TrendingUp },
    { key: 'announcements', label: t('student.classes.announcements_tab'), icon: Megaphone, badge: unreadCount },
  ];

  return (
    <div className="fade-up -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
      <div className="flex gap-1 min-w-max">
        {tabs.map((tab) => {
          const Icon = tab.icon;
          const active = activeTab === tab.key;
          return (
            <button
              key={tab.key}
              type="button"
              onClick={() => onTab(tab.key)}
              className={cn(
                'flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-[12px] font-semibold transition-all whitespace-nowrap',
                active
                  ? 'hero-gradient text-white shadow-md shadow-violet-300/40'
                  : 'text-muted hover:bg-violet-50 hover:text-violet-700'
              )}
            >
              <Icon className="h-3.5 w-3.5" strokeWidth={active ? 2.2 : 1.8} />
              {tab.label}
              {tab.badge > 0 && (
                <span className="grid h-4 min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[9px] font-bold text-white">
                  {tab.badge}
                </span>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Session Modal                                                      */
/* ------------------------------------------------------------------ */
function SessionModal({ session, onClose }) {
  if (!session) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4" onClick={onClose}>
      <div className="glass-card w-full max-w-md rounded-2xl p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-[17px] font-bold text-ink">{t('student.classes.session_details')}</h3>
          <button onClick={onClose} className="grid h-8 w-8 place-items-center rounded-xl hover:bg-violet-50">
            <X className="h-5 w-5 text-muted" />
          </button>
        </div>
        <div className="space-y-3 text-[13px]">
          <div><span className="text-muted">Date</span><p className="font-medium">{session.session_date}</p></div>
          {session.session_time && <div><span className="text-muted">Time</span><p className="font-medium">{session.session_time}</p></div>}
          {session.duration_minutes && <div><span className="text-muted">Duration</span><p className="font-medium">{session.duration_minutes} min</p></div>}
          <div><span className="text-muted">Status</span><p><StatusBadge variant={session.status === 'completed' ? 'success' : session.status === 'ongoing' ? 'info' : session.status === 'cancelled' ? 'danger' : 'gray'}>{session.status}</StatusBadge></p></div>
          {session.attended !== undefined && <div><span className="text-muted">Attendance</span><p><StatusBadge variant={session.attended ? 'success' : 'warning'}>{session.attended ? 'Present' : 'Absent'}</StatusBadge></p></div>}
          {session.notes && <div><span className="text-muted">Notes</span><p className="mt-0.5">{session.notes}</p></div>}
          {session.recording_url && (
            <a href={session.recording_url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-1.5 text-violet-600 hover:text-violet-800">
              <Video className="h-4 w-4" /> {t('student.classes.watch_recording') || 'Watch Recording'}
            </a>
          )}
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Week Calendar (shared)                                             */
/* ------------------------------------------------------------------ */
function WeekCalendar({ weekData, onSelectSession, periodLabel, onPrev, onNext, onToday }) {
  const formatTime = (t24) => {
    if (!t24) return '';
    const [h, m] = t24.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    return `${h % 12 || 12}:${m.toString().padStart(2, '0')} ${ampm}`;
  };

  const statusColor = (s) => {
    if (s === 'ongoing') return 'border-l-emerald-500 bg-emerald-50/50';
    if (s === 'completed' || s === 'no_show') return 'border-l-gray-300 bg-gray-50/50';
    if (s === 'cancelled') return 'border-l-red-300 bg-red-50/50';
    return 'border-l-violet-400 bg-violet-50/30';
  };

  return (
    <div className="glass-card overflow-hidden rounded-2xl shadow-sm">
      {/* Navigation */}
      <div className="flex items-center justify-between border-b border-violet-100/60 px-4 py-3">
        <button type="button" onClick={onPrev} className="grid h-8 w-8 place-items-center rounded-lg hover:bg-violet-50"><ChevronLeft className="h-4 w-4 text-violet-600" /></button>
        <div className="text-center">
          <p className="text-[13px] font-bold text-ink">{periodLabel}</p>
          <button type="button" onClick={onToday} className="text-[11px] font-semibold text-violet-600 hover:text-violet-800">{t('student.timetable.today')}</button>
        </div>
        <button type="button" onClick={onNext} className="grid h-8 w-8 place-items-center rounded-lg hover:bg-violet-50"><ChevronRight className="h-4 w-4 text-violet-600" /></button>
      </div>

      {/* Grid */}
      <div className="grid grid-cols-7 divide-x divide-violet-100/60">
        {weekData.map((day) => (
          <div key={day.date} className={cn('min-h-[120px]', day.isToday && 'bg-violet-50/30')}>
            <div className={cn('px-2 py-1.5 text-center border-b border-violet-100/40', day.isToday && 'bg-violet-100/50')}>
              <p className="text-[10px] font-semibold uppercase text-muted">{day.dayName}</p>
              <p className={cn('text-[14px] font-bold', day.isToday ? 'text-violet-600' : 'text-ink')}>{day.dayNumber}</p>
            </div>
            <div className="space-y-1 p-1.5">
              {day.items.map((item, i) => (
                <button
                  key={i}
                  type="button"
                  onClick={() => !item.isSlot && onSelectSession?.(item)}
                  className={cn(
                    'w-full rounded-lg border-l-2 px-2 py-1.5 text-left text-[10px] transition-colors',
                    statusColor(item.status),
                    !item.isSlot && 'cursor-pointer hover:ring-1 hover:ring-violet-200',
                    item.isSlot && 'opacity-50 border-dashed'
                  )}
                >
                  <p className="font-semibold text-ink truncate">{formatTime(item.time)}</p>
                  {item.classTitle && <p className="truncate text-muted">{item.classTitle}</p>}
                </button>
              ))}
              {day.items.length === 0 && (
                <p className="py-3 text-center text-[10px] text-muted-2">-</p>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Overview Tab                                                       */
/* ------------------------------------------------------------------ */
function OverviewTab({ cls, stats, sessions }) {
  const recentSessions = sessions.filter((s) => s.status === 'completed' || s.status === 'no_show').slice(0, 5);

  return (
    <div className="space-y-5">
      {/* Stats */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {[
          { label: t('student.classes.total_sessions'), value: stats.totalSessions, color: 'text-violet-600' },
          { label: t('student.stats.completed'), value: stats.completedSessions, color: 'text-emerald-600' },
          { label: t('student.classes.attended'), value: stats.attended, color: 'text-blue-600' },
          { label: t('student.classes.attendance_rate'), value: `${stats.attendanceRate}%`, color: 'text-purple-600' },
        ].map((s, i) => (
          <div key={i} className="glass-card rounded-2xl p-4 text-center shadow-sm">
            <p className={cn('text-[20px] font-extrabold', s.color)}>{s.value}</p>
            <p className="mt-0.5 text-[11px] font-medium text-muted">{s.label}</p>
          </div>
        ))}
      </div>

      {/* Class info */}
      <div className="glass-card rounded-2xl p-5 shadow-sm">
        <h3 className="mb-3 text-[15px] font-bold text-ink">{t('student.classes.class_info')}</h3>
        <div className="grid grid-cols-1 gap-3 text-[13px] sm:grid-cols-2">
          {cls.course_name && <div><span className="text-muted">Course</span><p className="font-medium">{cls.course_name}</p></div>}
          {cls.teacher_name && <div><span className="text-muted">Teacher</span><p className="font-medium">{cls.teacher_name}</p></div>}
          {cls.duration_minutes && <div><span className="text-muted">Duration</span><p className="font-medium">{cls.duration_minutes} min per session</p></div>}
          <div><span className="text-muted">Status</span><p><StatusBadge variant={cls.enrollment_status === 'active' ? 'success' : 'gray'}>{cls.enrollment_status}</StatusBadge></p></div>
        </div>
        {cls.description && <p className="mt-3 text-[12px] leading-relaxed text-muted">{cls.description}</p>}
      </div>

      {/* Recent sessions */}
      {recentSessions.length > 0 && (
        <div className="glass-card overflow-hidden rounded-2xl shadow-sm">
          <div className="border-b border-violet-100/60 px-4 py-3">
            <h3 className="text-[14px] font-bold text-ink">{t('student.classes.recent_sessions')}</h3>
          </div>
          <div className="divide-y divide-violet-50">
            {recentSessions.map((s) => (
              <div key={s.id} className="flex items-center justify-between px-4 py-3">
                <div>
                  <p className="text-[13px] font-medium text-ink">{s.session_date}</p>
                  {s.session_time && <p className="text-[12px] text-muted">{s.session_time}</p>}
                </div>
                <div className="flex items-center gap-2">
                  {s.attended && <CheckCircle className="h-4 w-4 text-emerald-500" />}
                  {s.recording_url && <Video className="h-4 w-4 text-violet-400" />}
                  <StatusBadge variant={s.status === 'completed' ? 'success' : 'gray'}>{s.status}</StatusBadge>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Sessions Tab                                                       */
/* ------------------------------------------------------------------ */
function SessionsTab({ sessions, onSelect }) {
  if (sessions.length === 0) return <EmptyState icon={ClipboardList} title={t('student.classes.no_sessions')} description={t('student.classes.no_sessions_desc')} />;

  return (
    <div className="glass-card overflow-hidden rounded-2xl shadow-sm">
      <div className="divide-y divide-violet-50">
        {sessions.map((s) => (
          <button
            key={s.id}
            type="button"
            onClick={() => onSelect(s)}
            className="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-violet-50/40"
          >
            <div className="min-w-0 flex-1">
              <p className="text-[13px] font-semibold text-ink">{s.session_date}</p>
              <div className="flex items-center gap-3 text-[12px] text-muted">
                {s.session_time && <span>{s.session_time}</span>}
                {s.duration_minutes && <span>{s.duration_minutes} min</span>}
              </div>
            </div>
            <div className="flex items-center gap-2 shrink-0">
              {s.attended && <CheckCircle className="h-4 w-4 text-emerald-500" />}
              {s.recording_url && <Video className="h-4 w-4 text-violet-400" />}
              <StatusBadge variant={s.status === 'completed' ? 'success' : s.status === 'ongoing' ? 'info' : s.status === 'cancelled' ? 'danger' : 'gray'}>{s.status}</StatusBadge>
            </div>
          </button>
        ))}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Resources Tab                                                      */
/* ------------------------------------------------------------------ */
function ResourcesTab({ resources }) {
  if (!resources || resources.length === 0) return <EmptyState icon={FolderOpen} title={t('student.classes.no_resources')} description={t('student.classes.no_resources_desc')} />;

  return (
    <div className="glass-card overflow-hidden rounded-2xl shadow-sm">
      <div className="divide-y divide-violet-50">
        {resources.map((r) => (
          <a key={r.id} href={r.url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-violet-50/40">
            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-violet-100">
              <FolderOpen className="h-5 w-5 text-violet-600" strokeWidth={1.8} />
            </div>
            <div className="min-w-0 flex-1">
              <p className="truncate text-[13px] font-semibold text-ink">{r.title}</p>
              <p className="text-[11px] text-muted">{r.type} {r.file_size ? `\u2022 ${r.file_size}` : ''} \u2022 {r.created_at}</p>
            </div>
          </a>
        ))}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Progress Tab                                                       */
/* ------------------------------------------------------------------ */
function ProgressTab({ stats }) {
  return (
    <div className="space-y-5">
      <div className="glass-card rounded-2xl p-5 shadow-sm">
        <h3 className="mb-4 text-[15px] font-bold text-ink">{t('student.classes.your_progress')}</h3>
        <div className="space-y-4">
          <div>
            <div className="mb-1 flex justify-between text-[12px]">
              <span className="font-medium text-muted">{t('student.classes.attendance_rate')}</span>
              <span className="font-bold text-violet-600">{stats.attendanceRate}%</span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-violet-100">
              <div className="h-full rounded-full bg-gradient-to-r from-violet-500 to-rose-500 transition-all" style={{ width: `${stats.attendanceRate}%` }} />
            </div>
          </div>
          <div>
            <div className="mb-1 flex justify-between text-[12px]">
              <span className="font-medium text-muted">{t('student.classes.sessions_completed')}</span>
              <span className="font-bold text-violet-600">{stats.completedSessions}/{stats.totalSessions}</span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-violet-100">
              <div className="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 transition-all" style={{ width: `${stats.totalSessions > 0 ? (stats.completedSessions / stats.totalSessions) * 100 : 0}%` }} />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Announcements Tab                                                  */
/* ------------------------------------------------------------------ */
function AnnouncementsTab({ announcements }) {
  if (announcements.length === 0) return <EmptyState icon={Megaphone} title={t('student.classes.no_announcements')} description={t('student.classes.no_announcements_desc')} />;

  return (
    <div className="space-y-4">
      {announcements.map((a) => (
        <div key={a.id} className={cn('glass-card rounded-2xl p-4 shadow-sm', !a.is_read && 'ring-2 ring-violet-300/60')}>
          <div className="flex items-start justify-between gap-2">
            <h4 className="text-[14px] font-bold text-ink">{a.title}</h4>
            {!a.is_read && <span className="h-2 w-2 shrink-0 rounded-full bg-violet-500 mt-1.5" />}
          </div>
          <div className="mt-2 text-[13px] leading-relaxed text-muted whitespace-pre-line">{a.body}</div>
          <div className="mt-3 flex items-center gap-3 text-[11px] text-muted-2">
            {a.author_name && <span>{a.author_name}</span>}
            <span>{a.published_at}</span>
          </div>
        </div>
      ))}
    </div>
  );
}

/* ================================================================== */
/*  Page                                                               */
/* ================================================================== */
export default function ClassShow() {
  const { class: cls, stats, sessions, resources, announcements, unreadAnnouncementCount, weekData: initialWeekData } = usePage().props;

  // Sync tab with URL
  const initialTab = new URLSearchParams(window.location.search).get('tab') || 'overview';
  const [activeTab, setActiveTab] = useState(initialTab);
  const [selectedSession, setSelectedSession] = useState(null);
  const [weekData, setWeekData] = useState(initialWeekData);
  const [periodLabel, setPeriodLabel] = useState('');
  const [currentDate, setCurrentDate] = useState(new Date());

  // Compute period label from weekData
  useEffect(() => {
    if (weekData.length >= 7) {
      const start = new Date(weekData[0].date);
      const end = new Date(weekData[6].date);
      const fmt = (d) => d.toLocaleDateString('en', { month: 'short', day: 'numeric' });
      setPeriodLabel(`${fmt(start)} - ${fmt(end)}, ${end.getFullYear()}`);
    }
  }, [weekData]);

  const switchTab = (tab) => {
    setActiveTab(tab);
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
  };

  const navigateWeek = useCallback(async (dir) => {
    const d = new Date(currentDate);
    d.setDate(d.getDate() + dir * 7);
    setCurrentDate(d);

    const res = await fetch(`/my/classes/${cls.id}/timetable-sessions?date=${d.toISOString().split('T')[0]}`);
    const data = await res.json();
    setWeekData(data);
  }, [currentDate, cls.id]);

  const goToToday = useCallback(async () => {
    const d = new Date();
    setCurrentDate(d);
    const res = await fetch(`/my/classes/${cls.id}/timetable-sessions?date=${d.toISOString().split('T')[0]}`);
    const data = await res.json();
    setWeekData(data);
  }, [cls.id]);

  const hero = (
    <PageHeader title={cls.title} subtitle={cls.course_name}>
      <StatusBadge variant={cls.enrollment_status === 'active' ? 'success' : 'gray'} size="lg">{cls.enrollment_status}</StatusBadge>
    </PageHeader>
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={cls.title} />

      <div className="space-y-5 pt-4">
        <div className="fade-up mb-2">
          <a href="/my/classes" className="flex items-center gap-1.5 rounded-xl bg-violet-50 px-4 py-2 text-[13px] font-semibold text-violet-700 transition-colors hover:bg-violet-100 w-fit">
            <ArrowLeft className="h-4 w-4" strokeWidth={2} />
            {t('student.classes.back_to_classes')}
          </a>
        </div>

        <TabNav activeTab={activeTab} onTab={switchTab} unreadCount={unreadAnnouncementCount} />

        {activeTab === 'overview' && <OverviewTab cls={cls} stats={stats} sessions={sessions} />}

        {activeTab === 'timetable' && (
          <WeekCalendar
            weekData={weekData}
            periodLabel={periodLabel}
            onPrev={() => navigateWeek(-1)}
            onNext={() => navigateWeek(1)}
            onToday={goToToday}
            onSelectSession={setSelectedSession}
          />
        )}

        {activeTab === 'sessions' && <SessionsTab sessions={sessions} onSelect={setSelectedSession} />}
        {activeTab === 'resources' && <ResourcesTab resources={resources} />}
        {activeTab === 'progress' && <ProgressTab stats={stats} />}
        {activeTab === 'announcements' && <AnnouncementsTab announcements={announcements} />}
      </div>

      <SessionModal session={selectedSession} onClose={() => setSelectedSession(null)} />
    </StudentLayout>
  );
}
