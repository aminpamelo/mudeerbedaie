import { Head, usePage } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import {
  Calendar, ChevronLeft, ChevronRight, X, Clock, CheckCircle, Video, ArrowLeft,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader, { HeroStat } from '@/student/components/PageHeader';
import StatusBadge from '@/student/components/StatusBadge';
import { cn, t } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Session Modal                                                      */
/* ------------------------------------------------------------------ */
function SessionModal({ session, onClose }) {
  if (!session) return null;

  const formatTime = (t24) => {
    if (!t24) return '';
    const [h, m] = t24.split(':').map(Number);
    return `${h % 12 || 12}:${m.toString().padStart(2, '0')} ${h >= 12 ? 'PM' : 'AM'}`;
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4" onClick={onClose}>
      <div className="glass-card w-full max-w-md rounded-2xl p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-[17px] font-bold text-ink">Session Details</h3>
          <button onClick={onClose} className="grid h-8 w-8 place-items-center rounded-xl hover:bg-violet-50">
            <X className="h-5 w-5 text-muted" />
          </button>
        </div>
        <div className="space-y-3 text-[13px]">
          <div><span className="text-muted">Class</span><p className="font-semibold text-ink">{session.classTitle}</p></div>
          {session.courseName && <div><span className="text-muted">Course</span><p className="font-medium">{session.courseName}</p></div>}
          {session.teacherName && <div><span className="text-muted">Teacher</span><p className="font-medium">{session.teacherName}</p></div>}
          {session.time && <div><span className="text-muted">Time</span><p className="font-medium">{formatTime(session.time)}</p></div>}
          {session.durationMinutes && <div><span className="text-muted">Duration</span><p className="font-medium">{session.durationMinutes} min</p></div>}
          <div><span className="text-muted">Status</span><p><StatusBadge variant={session.status === 'completed' ? 'success' : session.status === 'ongoing' ? 'info' : session.status === 'cancelled' ? 'danger' : 'gray'}>{session.status}</StatusBadge></p></div>
          {session.attended !== undefined && !session.isSlot && (
            <div><span className="text-muted">Attendance</span><p><StatusBadge variant={session.attended ? 'success' : 'warning'}>{session.attended ? 'Present' : 'Absent'}</StatusBadge></p></div>
          )}
          {session.notes && <div><span className="text-muted">Notes</span><p className="mt-0.5 whitespace-pre-line">{session.notes}</p></div>}
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Week Grid                                                          */
/* ------------------------------------------------------------------ */
function WeekGrid({ weekData, onSelectSession }) {
  const formatTime = (t24) => {
    if (!t24) return '';
    const [h, m] = t24.split(':').map(Number);
    return `${h % 12 || 12}:${m.toString().padStart(2, '0')}`;
  };

  const statusColor = (s) => {
    if (s === 'ongoing') return 'border-l-emerald-500 bg-emerald-50/50';
    if (s === 'completed' || s === 'no_show') return 'border-l-gray-300 bg-gray-50/50';
    if (s === 'cancelled') return 'border-l-red-300 bg-red-50/50';
    return 'border-l-violet-400 bg-violet-50/30';
  };

  return (
    <div className="glass-card overflow-hidden rounded-2xl shadow-sm">
      {/* Desktop grid */}
      <div className="hidden lg:grid grid-cols-7 divide-x divide-violet-100/60">
        {weekData.map((day) => (
          <div key={day.date} className={cn('min-h-[160px]', day.isToday && 'bg-violet-50/30')}>
            <div className={cn('px-2 py-2 text-center border-b border-violet-100/40', day.isToday && 'bg-violet-100/50')}>
              <p className="text-[10px] font-semibold uppercase text-muted">{day.dayName}</p>
              <p className={cn('text-[16px] font-bold', day.isToday ? 'text-violet-600' : 'text-ink')}>{day.dayNumber}</p>
            </div>
            <div className="space-y-1.5 p-2">
              {day.items.map((item, i) => (
                <button
                  key={i}
                  type="button"
                  onClick={() => !item.isSlot && onSelectSession?.(item)}
                  className={cn(
                    'w-full rounded-lg border-l-2 px-2 py-1.5 text-left text-[11px] transition-colors',
                    statusColor(item.status),
                    !item.isSlot && 'cursor-pointer hover:ring-1 hover:ring-violet-200',
                    item.isSlot && 'opacity-50 border-dashed'
                  )}
                >
                  <p className="font-bold text-ink">{formatTime(item.time)}</p>
                  <p className="truncate font-medium text-ink">{item.classTitle}</p>
                  {item.courseName && <p className="truncate text-muted">{item.courseName}</p>}
                  {item.status === 'ongoing' && <span className="mt-0.5 inline-flex items-center gap-1 text-[9px] font-bold text-emerald-600"><span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />{t('student.status.live')}</span>}
                </button>
              ))}
              {day.items.length === 0 && <p className="py-4 text-center text-[11px] text-muted-2">{t('student.timetable_page.no_sessions')}</p>}
            </div>
          </div>
        ))}
      </div>

      {/* Mobile cards */}
      <div className="lg:hidden divide-y divide-violet-100/60">
        {weekData.map((day) => (
          <div key={day.date} className={cn('px-4 py-3', day.isToday && 'bg-violet-50/30')}>
            <div className="mb-2 flex items-center gap-2">
              <span className={cn('text-[13px] font-bold', day.isToday ? 'text-violet-600' : 'text-ink')}>{day.dayName}</span>
              <span className="text-[13px] font-bold text-ink">{day.dayNumber}</span>
              {day.isToday && <span className="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-600">{t('student.timetable.today')}</span>}
            </div>
            {day.items.length > 0 ? (
              <div className="space-y-2">
                {day.items.map((item, i) => (
                  <button
                    key={i}
                    type="button"
                    onClick={() => !item.isSlot && onSelectSession?.(item)}
                    className={cn(
                      'flex w-full items-center gap-3 rounded-xl border-l-2 p-3 text-left transition-colors',
                      statusColor(item.status),
                      !item.isSlot && 'hover:ring-1 hover:ring-violet-200',
                      item.isSlot && 'opacity-50 border-dashed'
                    )}
                  >
                    <div className="min-w-0 flex-1">
                      <p className="text-[13px] font-semibold text-ink">{item.classTitle}</p>
                      <div className="flex items-center gap-2 text-[11px] text-muted">
                        <span>{formatTime(item.time)}</span>
                        {item.durationMinutes && <span>{item.durationMinutes} min</span>}
                      </div>
                    </div>
                    <StatusBadge variant={item.status === 'completed' ? 'success' : item.status === 'ongoing' ? 'info' : item.status === 'cancelled' ? 'danger' : 'gray'}>
                      {item.status === 'ongoing' ? 'LIVE' : item.status}
                    </StatusBadge>
                  </button>
                ))}
              </div>
            ) : (
              <p className="text-[12px] text-muted-2">{t('student.timetable_page.no_sessions')}</p>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

/* ================================================================== */
/*  Page                                                               */
/* ================================================================== */
export default function Timetable() {
  const { weekData: initialWeekData, periodLabel: initialPeriodLabel, currentDate: initialDate, stats, classOptions } = usePage().props;

  const [weekData, setWeekData] = useState(initialWeekData);
  const [periodLabel, setPeriodLabel] = useState(initialPeriodLabel);
  const [currentDate, setCurrentDate] = useState(new Date(initialDate));
  const [classFilter, setClassFilter] = useState('all');
  const [selectedSession, setSelectedSession] = useState(null);
  const [loading, setLoading] = useState(false);

  const fetchWeek = useCallback(async (date, cls) => {
    setLoading(true);
    const params = new URLSearchParams({ date: date.toISOString().split('T')[0] });
    if (cls && cls !== 'all') params.set('class', cls);

    const res = await fetch(`/my/timetable/sessions?${params}`);
    const data = await res.json();
    setWeekData(data.weekData);
    setPeriodLabel(data.periodLabel);
    setLoading(false);
  }, []);

  const navigate = (dir) => {
    const d = new Date(currentDate);
    d.setDate(d.getDate() + dir * 7);
    setCurrentDate(d);
    fetchWeek(d, classFilter);
  };

  const goToToday = () => {
    const d = new Date();
    setCurrentDate(d);
    fetchWeek(d, classFilter);
  };

  const handleClassFilter = (value) => {
    setClassFilter(value);
    fetchWeek(currentDate, value);
  };

  const hero = (
    <PageHeader
      tag={t('navigation.schedule')}
      title={t('navigation.schedule')}
      subtitle={periodLabel}
    >
      <HeroStat icon={Calendar} label={t('student.timetable_page.this_week')} value={stats.sessionsThisWeek} />
      <HeroStat icon={Clock} label={t('student.timetable_page.upcoming')} value={stats.upcomingSessions} iconClassName="bg-emerald-400/20" />
    </PageHeader>
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={t('navigation.schedule')} />

      <div className="space-y-5 pt-4">
        <div className="fade-up">
          <a href="/my/classes" className="inline-flex w-fit items-center gap-1.5 rounded-xl bg-violet-50 px-4 py-2 text-[13px] font-semibold text-violet-700 transition-colors hover:bg-violet-100">
            <ArrowLeft className="h-4 w-4" strokeWidth={2} />
            {t('student.classes.back_to_classes')}
          </a>
        </div>

        {/* Controls */}
        <div className="fade-up flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <button type="button" onClick={() => navigate(-1)} className="grid h-9 w-9 place-items-center rounded-xl bg-violet-50 text-violet-600 hover:bg-violet-100">
              <ChevronLeft className="h-4 w-4" strokeWidth={2.5} />
            </button>
            <span className="text-[14px] font-bold text-ink min-w-[180px] text-center">{periodLabel}</span>
            <button type="button" onClick={() => navigate(1)} className="grid h-9 w-9 place-items-center rounded-xl bg-violet-50 text-violet-600 hover:bg-violet-100">
              <ChevronRight className="h-4 w-4" strokeWidth={2.5} />
            </button>
            <button type="button" onClick={goToToday} className="rounded-xl bg-violet-50 px-3 py-2 text-[12px] font-semibold text-violet-600 hover:bg-violet-100">
              Today
            </button>
          </div>

          {classOptions.length > 1 && (
            <div className="glass-card rounded-2xl shadow-sm">
              <select
                value={classFilter}
                onChange={(e) => handleClassFilter(e.target.value)}
                className="rounded-2xl border-0 bg-transparent px-4 py-2.5 text-[13px] text-ink outline-none"
              >
                <option value="all">{t('student.timetable_page.all_classes')}</option>
                {classOptions.map((c) => <option key={c.id} value={c.id}>{c.title}</option>)}
              </select>
            </div>
          )}
        </div>

        {/* Quick stats */}
        <div className="fade-up grid grid-cols-2 gap-3" style={{ animationDelay: '0.05s' }}>
          <div className="glass-card rounded-2xl p-4 text-center shadow-sm">
            <p className="text-[20px] font-extrabold text-violet-600">{stats.sessionsThisWeek}</p>
            <p className="text-[11px] font-medium text-muted">Sessions This Week</p>
          </div>
          <div className="glass-card rounded-2xl p-4 text-center shadow-sm">
            <p className="text-[20px] font-extrabold text-emerald-600">{stats.upcomingSessions}</p>
            <p className="text-[11px] font-medium text-muted">Upcoming Sessions</p>
          </div>
        </div>

        {/* Week grid */}
        <div className={cn('fade-up transition-opacity', loading && 'opacity-50')} style={{ animationDelay: '0.1s' }}>
          <WeekGrid weekData={weekData} onSelectSession={setSelectedSession} />
        </div>
      </div>

      <SessionModal session={selectedSession} onClose={() => setSelectedSession(null)} />
    </StudentLayout>
  );
}
