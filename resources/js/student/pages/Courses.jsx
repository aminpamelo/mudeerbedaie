import { Head, router, usePage } from '@inertiajs/react';
import { useState, useCallback, useRef } from 'react';
import {
  BookOpen, Search, Users, GraduationCap, CheckCircle, X,
  SlidersHorizontal, ChevronDown, Sparkles, TrendingUp,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import { cn, formatMoney, t } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Hero Section                                                       */
/* ------------------------------------------------------------------ */
function HeroSection({ stats }) {
  return (
    <div className="hero-gradient relative overflow-hidden">
      <div className="dot-pattern">
        <div className="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-rose-500/20 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-10 -left-10 h-48 w-48 rounded-full bg-violet-400/20 blur-3xl" />

        <div className="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
          <div className="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div className="fade-up">
              <div className="mb-2 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-white/80 ring-1 ring-white/20">
                <Sparkles className="h-3 w-3" strokeWidth={2.5} />
                {t('student.courses.available_courses')}
              </div>
              <h1 className="text-[28px] font-extrabold leading-tight tracking-[-0.03em] text-white sm:text-[34px]">
                {t('student.courses.available_courses')}
              </h1>
              <p className="mt-1.5 max-w-md text-[14px] leading-relaxed text-white/65">
                {t('student.courses.discover_enroll')}
              </p>
            </div>

            <div className="fade-up flex gap-3" style={{ animationDelay: '0.1s' }}>
              <div className="flex items-center gap-3 rounded-2xl bg-white/10 px-5 py-3.5 ring-1 ring-white/15 backdrop-blur-sm">
                <div className="grid h-10 w-10 place-items-center rounded-xl bg-white/15">
                  <BookOpen className="h-5 w-5 text-white" strokeWidth={2} />
                </div>
                <div>
                  <p className="text-[11px] font-medium uppercase tracking-wider text-white/55">{t('student.courses.available')}</p>
                  <p className="text-[22px] font-extrabold leading-tight text-white">{stats?.totalCourses ?? 0}</p>
                </div>
              </div>
              <div className="flex items-center gap-3 rounded-2xl bg-white/10 px-5 py-3.5 ring-1 ring-white/15 backdrop-blur-sm">
                <div className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-400/20">
                  <TrendingUp className="h-5 w-5 text-emerald-300" strokeWidth={2} />
                </div>
                <div>
                  <p className="text-[11px] font-medium uppercase tracking-wider text-white/55">{t('student.courses.enrolled')}</p>
                  <p className="text-[22px] font-extrabold leading-tight text-white">{stats?.myEnrollments ?? 0}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Sidebar Filters (desktop) / Drawer (mobile)                        */
/* ------------------------------------------------------------------ */
function FilterPanel({ filters, teachers, onFilter, courseCount }) {
  const [search, setSearch] = useState(filters.search || '');
  const [showMobileFilters, setShowMobileFilters] = useState(false);
  const debounceRef = useRef(null);

  const activeFilterCount = [filters.teacher, filters.status, filters.fee].filter(Boolean).length;

  const handleSearch = useCallback((value) => {
    setSearch(value);
    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      onFilter({ ...filters, search: value });
    }, 300);
  }, [filters, onFilter]);

  const updateFilter = (key, value) => {
    onFilter({ ...filters, [key]: value });
  };

  const clearAll = () => {
    setSearch('');
    onFilter({ search: '', teacher: '', status: '', fee: '' });
  };

  const hasActiveFilters = filters.search || filters.teacher || filters.status || filters.fee;

  return (
    <>
      {/* ---- Desktop sidebar ---- */}
      <aside className="fade-up hidden w-[260px] shrink-0 lg:block" style={{ animationDelay: '0.1s' }}>
        <div className="sticky top-[72px] space-y-5">
          <div className="glass-card overflow-hidden rounded-2xl shadow-sm">
            <div className="border-b border-violet-100/60 bg-gradient-to-r from-violet-50 to-rose-50 px-4 py-3">
              <h3 className="text-[13px] font-bold uppercase tracking-wider text-violet-900/70">{t('student.common.search')} & {t('student.common.filter')}</h3>
            </div>
            <div className="space-y-4 p-4">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-violet-400" strokeWidth={2} />
                <input
                  type="text"
                  value={search}
                  onChange={(e) => handleSearch(e.target.value)}
                  placeholder={t('student.classes.search_placeholder')}
                  className="w-full rounded-xl border border-violet-200/60 bg-violet-50/50 py-2.5 pl-10 pr-3 text-[13px] text-ink placeholder:text-violet-300 outline-none transition-all focus:border-violet-400 focus:bg-white focus:ring-2 focus:ring-violet-200"
                />
              </div>

              <div>
                <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-muted">{t('student.timetable.teacher')}</label>
                <select
                  value={filters.teacher}
                  onChange={(e) => updateFilter('teacher', e.target.value)}
                  className="w-full rounded-xl border border-violet-200/60 bg-violet-50/50 px-3 py-2.5 text-[13px] text-ink outline-none transition-all focus:border-violet-400 focus:bg-white focus:ring-2 focus:ring-violet-200"
                >
                  <option value="">{t('student.courses.all_teachers')}</option>
                  {teachers.map((te) => (
                    <option key={te.id} value={te.id}>{te.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-muted">{t('student.classes.status')}</label>
                <select
                  value={filters.status}
                  onChange={(e) => updateFilter('status', e.target.value)}
                  className="w-full rounded-xl border border-violet-200/60 bg-violet-50/50 px-3 py-2.5 text-[13px] text-ink outline-none transition-all focus:border-violet-400 focus:bg-white focus:ring-2 focus:ring-violet-200"
                >
                  <option value="">{t('student.courses.all_courses')}</option>
                  <option value="enrolled">{t('student.courses.my_enrolled')}</option>
                  <option value="not_enrolled">{t('student.courses.not_enrolled')}</option>
                </select>
              </div>

              <div>
                <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-muted">{t('student.courses.any_price')}</label>
                <select
                  value={filters.fee}
                  onChange={(e) => updateFilter('fee', e.target.value)}
                  className="w-full rounded-xl border border-violet-200/60 bg-violet-50/50 px-3 py-2.5 text-[13px] text-ink outline-none transition-all focus:border-violet-400 focus:bg-white focus:ring-2 focus:ring-violet-200"
                >
                  <option value="">{t('student.courses.any_price')}</option>
                  <option value="free">{t('student.courses.free')}</option>
                  <option value="1-50">RM 1 - 50</option>
                  <option value="51-100">RM 51 - 100</option>
                  <option value="101+">RM 101+</option>
                </select>
              </div>

              {hasActiveFilters && (
                <button
                  type="button"
                  onClick={clearAll}
                  className="flex w-full items-center justify-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-[12px] font-semibold text-rose-600 transition-colors hover:bg-rose-100"
                >
                  <X className="h-3.5 w-3.5" strokeWidth={2.5} />
                  {t('student.courses.clear_filters')}
                </button>
              )}
            </div>
          </div>

          <div className="rounded-2xl bg-gradient-to-br from-violet-100/80 to-rose-100/60 p-4 text-center">
            <p className="text-[11px] font-semibold uppercase tracking-wider text-violet-600/70">{t('student.common.view')}</p>
            <p className="text-[20px] font-extrabold text-violet-800">{courseCount}</p>
            <p className="text-[12px] text-violet-500">{t('navigation.courses').toLowerCase()}</p>
          </div>
        </div>
      </aside>

      {/* ---- Mobile filter bar ---- */}
      <div className="mb-5 lg:hidden">
        <div className="glass-card mb-3 overflow-hidden rounded-2xl shadow-sm">
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

        <button
          type="button"
          onClick={() => setShowMobileFilters(!showMobileFilters)}
          className={cn(
            'glass-card flex w-full items-center justify-between rounded-2xl px-4 py-3 text-[13px] font-medium shadow-sm transition-colors',
            showMobileFilters ? 'bg-violet-50' : ''
          )}
        >
          <span className="flex items-center gap-2 text-violet-700">
            <SlidersHorizontal className="h-4 w-4" strokeWidth={2} />
            {t('student.classes.filters')}
            {activeFilterCount > 0 && (
              <span className="grid h-5 min-w-5 place-items-center rounded-full bg-[var(--color-accent)] px-1.5 text-[10px] font-bold text-white">
                {activeFilterCount}
              </span>
            )}
          </span>
          <ChevronDown className={cn('h-4 w-4 text-violet-400 transition-transform', showMobileFilters && 'rotate-180')} strokeWidth={2} />
        </button>

        {showMobileFilters && (
          <div className="fade-in mt-2 grid grid-cols-1 gap-3 rounded-2xl border border-violet-100 bg-white p-4 shadow-sm sm:grid-cols-3">
            <select
              value={filters.teacher}
              onChange={(e) => updateFilter('teacher', e.target.value)}
              className="rounded-xl border border-violet-200/60 bg-violet-50/50 px-3 py-2.5 text-[13px] text-ink outline-none focus:border-violet-400"
            >
              <option value="">{t('student.courses.all_teachers')}</option>
              {teachers.map((te) => (
                <option key={te.id} value={te.id}>{te.name}</option>
              ))}
            </select>
            <select
              value={filters.status}
              onChange={(e) => updateFilter('status', e.target.value)}
              className="rounded-xl border border-violet-200/60 bg-violet-50/50 px-3 py-2.5 text-[13px] text-ink outline-none focus:border-violet-400"
            >
              <option value="">{t('student.courses.all_courses')}</option>
              <option value="enrolled">{t('student.courses.my_enrolled')}</option>
              <option value="not_enrolled">{t('student.courses.not_enrolled')}</option>
            </select>
            <select
              value={filters.fee}
              onChange={(e) => updateFilter('fee', e.target.value)}
              className="rounded-xl border border-violet-200/60 bg-violet-50/50 px-3 py-2.5 text-[13px] text-ink outline-none focus:border-violet-400"
            >
              <option value="">{t('student.courses.any_price')}</option>
              <option value="free">{t('student.courses.free')}</option>
              <option value="1-50">RM 1 - 50</option>
              <option value="51-100">RM 51 - 100</option>
              <option value="101+">RM 101+</option>
            </select>
            {hasActiveFilters && (
              <button
                type="button"
                onClick={clearAll}
                className="flex items-center justify-center gap-1.5 rounded-xl bg-rose-50 px-3 py-2 text-[12px] font-semibold text-rose-600 sm:col-span-3"
              >
                <X className="h-3.5 w-3.5" strokeWidth={2.5} />
                {t('student.courses.clear_filters')}
              </button>
            )}
          </div>
        )}
      </div>
    </>
  );
}

/* ------------------------------------------------------------------ */
/*  Course Card                                                        */
/* ------------------------------------------------------------------ */
function CourseCard({ course, index }) {
  return (
    <div
      className="fade-up group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/[0.04] transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-violet-200/40 hover:ring-violet-200/60"
      style={{ animationDelay: `${0.04 * index + 0.12}s` }}
    >
      <div className="relative aspect-[16/9] overflow-hidden">
        {course.thumbnail_url ? (
          <img
            src={course.thumbnail_url}
            alt={course.name}
            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
          />
        ) : (
          <div className="flex h-full items-center justify-center bg-gradient-to-br from-violet-100 via-purple-50 to-rose-100">
            <div className="float grid h-16 w-16 place-items-center rounded-2xl bg-white/70 shadow-lg shadow-violet-200/50 backdrop-blur-sm">
              <BookOpen className="h-7 w-7 text-violet-400" strokeWidth={1.5} />
            </div>
          </div>
        )}

        <div className="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/30 to-transparent" />

        {course.is_enrolled && (
          <div className="absolute left-3 top-3 flex items-center gap-1 rounded-full bg-emerald-500 px-2.5 py-1 text-[11px] font-bold text-white shadow-lg shadow-emerald-500/30">
            <CheckCircle className="h-3 w-3" strokeWidth={2.5} />
            {t('student.courses.enrolled')}
          </div>
        )}

        <div className="absolute bottom-3 right-3">
          <span className={cn(
            'rounded-full px-3 py-1 text-[12px] font-bold shadow-lg backdrop-blur-sm',
            course.fee === 0
              ? 'bg-emerald-500/90 text-white'
              : 'bg-white/90 text-violet-700'
          )}>
            {course.fee_formatted}
            {course.billing_interval && <span className="font-medium opacity-70"> {t('student.courses.per_month')}</span>}
          </span>
        </div>
      </div>

      <div className="flex flex-1 flex-col p-4">
        <h3 className="text-[15px] font-bold leading-snug text-ink line-clamp-2 group-hover:text-violet-700 transition-colors">
          {course.name}
        </h3>

        {course.teacher_name && (
          <div className="mt-2 flex items-center gap-2">
            <div className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-violet-100 text-[9px] font-bold text-violet-600">
              {course.teacher_name.charAt(0)}
            </div>
            <p className="truncate text-[12px] font-medium text-muted">{course.teacher_name}</p>
          </div>
        )}

        {course.description && (
          <p className="mt-2 text-[12px] leading-relaxed text-muted-2 line-clamp-2">{course.description}</p>
        )}

        <div className="flex-1" />

        <div className="mt-3 flex items-center gap-3 border-t border-violet-50 pt-3">
          <div className="flex items-center gap-1.5 text-[11px] font-medium text-muted">
            <Users className="h-3.5 w-3.5 text-violet-400" strokeWidth={2} />
            <span>{t('student.courses.students', { count: course.student_count })}</span>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Pagination                                                         */
/* ------------------------------------------------------------------ */
function Pagination({ links }) {
  if (!links || links.length <= 3) return null;

  return (
    <div className="mt-8 flex flex-wrap items-center justify-center gap-1.5">
      {links.map((link, i) => (
        <button
          key={i}
          disabled={!link.url || link.active}
          onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
          className={cn(
            'min-w-[36px] rounded-xl px-3 py-2 text-[13px] font-semibold transition-all',
            link.active
              ? 'hero-gradient text-white shadow-md shadow-violet-300/40'
              : link.url
                ? 'text-muted hover:bg-violet-50 hover:text-violet-700'
                : 'cursor-default text-muted-2'
          )}
          dangerouslySetInnerHTML={{ __html: link.label }}
        />
      ))}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Empty State                                                        */
/* ------------------------------------------------------------------ */
function EmptyState({ hasFilters, onClear }) {
  return (
    <div className="fade-up rounded-2xl bg-white py-20 text-center shadow-sm ring-1 ring-black/[0.04]">
      <div className="mx-auto mb-5 grid h-20 w-20 place-items-center rounded-3xl bg-gradient-to-br from-violet-100 to-rose-100">
        <GraduationCap className="h-9 w-9 text-violet-400" strokeWidth={1.5} />
      </div>
      <h3 className="text-[18px] font-bold text-ink">
        {hasFilters ? t('student.courses.no_courses_match') : t('student.courses.no_courses_available')}
      </h3>
      <p className="mx-auto mt-2 max-w-sm text-[14px] leading-relaxed text-muted">
        {hasFilters ? t('student.courses.try_adjusting') : t('student.courses.check_back')}
      </p>
      {hasFilters && (
        <button
          type="button"
          onClick={onClear}
          className="mt-5 inline-flex items-center gap-2 rounded-xl bg-[var(--color-accent)] px-5 py-2.5 text-[13px] font-semibold text-white shadow-md shadow-rose-300/30 transition-all hover:bg-[var(--color-accent-ink)] hover:shadow-lg"
        >
          <X className="h-4 w-4" strokeWidth={2.5} />
          {t('student.courses.clear_filters')}
        </button>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Page Component                                                     */
/* ------------------------------------------------------------------ */
export default function Courses() {
  const { courses, teachers, filters, studentStats } = usePage().props;

  const applyFilters = useCallback((newFilters) => {
    router.get('/my/courses', Object.fromEntries(
      Object.entries(newFilters).filter(([, v]) => v !== '')
    ), {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  }, []);

  const hasFilters = filters.search || filters.teacher || filters.status || filters.fee;

  const hero = <HeroSection stats={studentStats} />;

  return (
    <StudentLayout hero={hero}>
      <Head title={t('navigation.courses')} />

      <div className="relative -mt-4 pt-0 lg:flex lg:gap-8 lg:pt-6">
        <FilterPanel
          filters={filters}
          teachers={teachers}
          onFilter={applyFilters}
          courseCount={courses.total ?? courses.data.length}
        />

        <div className="min-w-0 flex-1">
          {courses.data.length > 0 ? (
            <>
              <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                {courses.data.map((course, i) => (
                  <CourseCard key={course.id} course={course} index={i} />
                ))}
              </div>
              <Pagination links={courses.links} />
            </>
          ) : (
            <EmptyState
              hasFilters={!!hasFilters}
              onClear={() => applyFilters({ search: '', teacher: '', status: '', fee: '' })}
            />
          )}
        </div>
      </div>
    </StudentLayout>
  );
}
