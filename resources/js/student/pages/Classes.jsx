import { Head, router, usePage } from '@inertiajs/react';
import { useState, useCallback, useRef } from 'react';
import {
  GraduationCap, Search, ChevronRight, SlidersHorizontal,
  ChevronDown, X, CalendarDays,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader, { HeroStat } from '@/student/components/PageHeader';
import StatusBadge from '@/student/components/StatusBadge';
import EmptyState from '@/student/components/EmptyState';
import Pagination from '@/student/components/Pagination';
import { cn, t } from '@/student/lib/utils';

const statusVariantMap = { active: 'success', completed: 'purple', transferred: 'warning', quit: 'danger' };

function StatusChips({ statusCounts, current, onSelect }) {
  const chips = [
    { key: 'active', label: t('student.classes.active'), color: 'bg-emerald-100 text-emerald-800', dot: 'bg-emerald-500' },
    { key: 'completed', label: t('student.classes.completed'), color: 'bg-violet-100 text-violet-800', dot: 'bg-violet-500' },
  ];

  return (
    <div className="flex flex-wrap gap-2">
      {chips.map((c) => (
        <button
          key={c.key}
          type="button"
          onClick={() => onSelect(current === c.key ? '' : c.key)}
          className={cn(
            'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[12px] font-semibold transition-colors',
            current === c.key ? c.color : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
          )}
        >
          <span className={cn('h-2 w-2 rounded-full', current === c.key ? c.dot : 'bg-gray-400')} />
          {c.label}
          {statusCounts[c.key] > 0 && <span className="text-[10px] opacity-70">{statusCounts[c.key]}</span>}
        </button>
      ))}
    </div>
  );
}

function ClassCard({ cls, index }) {
  return (
    <a
      href={`/my/classes/${cls.class_id}`}
      className="fade-up glass-card group flex flex-col overflow-hidden rounded-2xl shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
      style={{ animationDelay: `${0.04 * index + 0.1}s` }}
    >
      <div className="p-4">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0 flex-1">
            <h3 className="truncate text-[14px] font-bold text-ink group-hover:text-violet-700 transition-colors">{cls.title}</h3>
            {cls.course_name && <p className="truncate text-[12px] text-muted">{cls.course_name}</p>}
          </div>
          <StatusBadge variant={statusVariantMap[cls.status] || 'gray'}>{cls.status}</StatusBadge>
        </div>

        {cls.teacher_name && (
          <div className="mt-2 flex items-center gap-2">
            <div className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-violet-100 text-[9px] font-bold text-violet-600">
              {cls.teacher_name.charAt(0)}
            </div>
            <p className="truncate text-[12px] font-medium text-muted">{cls.teacher_name}</p>
          </div>
        )}

        {/* Progress bar */}
        <div className="mt-3">
          <div className="mb-1 flex items-center justify-between text-[11px]">
            <span className="font-medium text-muted">{cls.completed_sessions}/{cls.total_sessions} sessions</span>
            <span className="font-bold text-violet-600">{cls.progress}%</span>
          </div>
          <div className="h-1.5 overflow-hidden rounded-full bg-violet-100">
            <div className="h-full rounded-full bg-gradient-to-r from-violet-500 to-rose-500 transition-all" style={{ width: `${cls.progress}%` }} />
          </div>
        </div>
      </div>

      <div className="flex items-center justify-center gap-1 border-t border-violet-100/60 bg-violet-50/30 py-2 text-[12px] font-semibold text-violet-600">
        View Class <ChevronRight className="h-3.5 w-3.5" strokeWidth={2} />
      </div>
    </a>
  );
}

export default function Classes() {
  const { classStudents, courses, statusCounts, totalClasses, filters } = usePage().props;
  const [search, setSearch] = useState(filters.search || '');
  const [showFilters, setShowFilters] = useState(false);
  const debounceRef = useRef(null);

  const applyFilters = useCallback((newFilters) => {
    router.get('/my/classes', Object.fromEntries(
      Object.entries(newFilters).filter(([, v]) => v !== '')
    ), { preserveState: true, preserveScroll: true, replace: true });
  }, []);

  const handleSearch = (value) => {
    setSearch(value);
    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      applyFilters({ ...filters, search: value });
    }, 300);
  };

  const hasFilters = filters.search || filters.status || filters.course;

  const hero = (
    <PageHeader
      tag={t('student.classes.my_classes')}
      title={t('student.classes.my_classes')}
      subtitle={`${totalClasses} classes enrolled`}
    >
      <HeroStat icon={GraduationCap} label={t('student.classes.active')} value={statusCounts.active} />
    </PageHeader>
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={t('student.classes.my_classes')} />

      <div className="space-y-4 pt-4">
        {/* Status chips + schedule link */}
        <div className="fade-up flex flex-wrap items-center justify-between gap-3">
          <StatusChips statusCounts={statusCounts} current={filters.status} onSelect={(s) => applyFilters({ ...filters, status: s })} />
          <a
            href="/my/timetable"
            className="inline-flex items-center gap-1.5 rounded-full bg-violet-100 px-3.5 py-1.5 text-[12px] font-semibold text-violet-700 transition-colors hover:bg-violet-200"
          >
            <CalendarDays className="h-3.5 w-3.5" strokeWidth={2} />
            {t('student.classes.view_timetable')}
          </a>
        </div>

        {/* Search bar */}
        <div className="fade-up glass-card overflow-hidden rounded-2xl shadow-sm" style={{ animationDelay: '0.05s' }}>
          <div className="relative">
            <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-violet-400" strokeWidth={2} />
            <input
              type="text"
              value={search}
              onChange={(e) => handleSearch(e.target.value)}
              placeholder={t('student.classes.search_placeholder')}
              className="w-full border-0 bg-transparent py-3.5 pl-11 pr-4 text-[14px] text-ink placeholder:text-violet-300 outline-none"
            />
          </div>
        </div>

        {/* Course filter (mobile collapsible) */}
        {courses.length > 0 && (
          <div className="fade-up" style={{ animationDelay: '0.08s' }}>
            <button
              type="button"
              onClick={() => setShowFilters(!showFilters)}
              className="glass-card flex w-full items-center justify-between rounded-2xl px-4 py-3 text-[13px] font-medium shadow-sm lg:hidden"
            >
              <span className="flex items-center gap-2 text-violet-700">
                <SlidersHorizontal className="h-4 w-4" strokeWidth={2} />
                {t('student.classes.filters')}
              </span>
              <ChevronDown className={cn('h-4 w-4 text-violet-400 transition-transform', showFilters && 'rotate-180')} />
            </button>

            <div className={cn('mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2', showFilters ? '' : 'hidden lg:grid')}>
              <div className="glass-card rounded-2xl shadow-sm">
                <select
                  value={filters.course}
                  onChange={(e) => applyFilters({ ...filters, course: e.target.value })}
                  className="w-full rounded-2xl border-0 bg-transparent px-4 py-3 text-[13px] text-ink outline-none"
                >
                  <option value="">{t('student.courses.all_courses')}</option>
                  {courses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </div>
              {hasFilters && (
                <button
                  type="button"
                  onClick={() => { setSearch(''); applyFilters({ search: '', status: '', course: '' }); }}
                  className="flex items-center justify-center gap-1.5 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-[12px] font-semibold text-rose-600 hover:bg-rose-100"
                >
                  <X className="h-3.5 w-3.5" strokeWidth={2.5} />
                  {t('student.courses.clear_filters')}
                </button>
              )}
            </div>
          </div>
        )}

        {/* Class grid */}
        {classStudents.data.length > 0 ? (
          <>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {classStudents.data.map((cls, i) => <ClassCard key={cls.id} cls={cls} index={i} />)}
            </div>
            <Pagination links={classStudents.links} />
          </>
        ) : (
          <EmptyState
            icon={GraduationCap}
            title={hasFilters ? t('student.courses.no_courses_match') : t('student.classes.no_classes')}
            description={hasFilters ? t('student.courses.try_adjusting') : t('student.classes.no_classes_desc')}
            action={hasFilters ? (
              <button
                type="button"
                onClick={() => { setSearch(''); applyFilters({ search: '', status: '', course: '' }); }}
                className="inline-flex items-center gap-2 rounded-xl bg-[var(--color-accent)] px-5 py-2.5 text-[13px] font-semibold text-white shadow-md shadow-rose-300/30"
              >
                <X className="h-4 w-4" strokeWidth={2.5} />
                {t('student.courses.clear_filters')}
              </button>
            ) : null}
          />
        )}
      </div>
    </StudentLayout>
  );
}
