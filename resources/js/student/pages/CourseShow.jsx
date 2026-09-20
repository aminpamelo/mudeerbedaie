import { Head, usePage } from '@inertiajs/react';
import {
  ArrowLeft, ArrowRight, BookOpen, GraduationCap, Video, FolderOpen,
  CheckCircle, Clock, PlayCircle, Sparkles, TrendingUp, Users,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import EmptyState from '@/student/components/EmptyState';
import { cn, t } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Hero                                                               */
/* ------------------------------------------------------------------ */
function Hero({ course, stats }) {
  return (
    <div className="hero-banner fade-up relative overflow-hidden rounded-3xl ring-1 ring-brand-100">
      <div className="pattern-islamic absolute inset-0 opacity-60" />
      <div className="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/40 blur-3xl" />

      <div className="relative p-6 sm:p-8">
        <a
          href="/my/courses"
          className="mb-5 inline-flex items-center gap-1.5 rounded-xl bg-white/70 px-3.5 py-2 text-[13px] font-semibold text-brand-ink ring-1 ring-brand-100 transition-colors hover:bg-white"
        >
          <ArrowLeft className="h-4 w-4" strokeWidth={2} />
          {t('student.courses.back_to_courses')}
        </a>

        <div className="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
          <div className="min-w-0">
            {course.has_access && (
              <div className="mb-2 inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-emerald-700 ring-1 ring-emerald-200">
                <CheckCircle className="h-3 w-3" strokeWidth={2.5} />
                {t('student.courses.enrolled')}
              </div>
            )}
            <h1 className="text-[26px] font-extrabold leading-tight tracking-[-0.03em] text-ink sm:text-[32px]">
              {course.name}
            </h1>
            {course.teacher_name && (
              <div className="mt-2 flex items-center gap-2">
                <div className="grid h-6 w-6 place-items-center rounded-full bg-brand-soft text-[10px] font-bold text-brand-ink">
                  {course.teacher_name.charAt(0)}
                </div>
                <p className="text-[14px] font-medium text-ink-2">{course.teacher_name}</p>
              </div>
            )}
          </div>

          {course.has_access ? (
            <div className="flex gap-3">
              <div className="flex items-center gap-3 rounded-2xl bg-white/75 px-5 py-3.5 ring-1 ring-brand-100 backdrop-blur-sm">
                <div className="grid h-10 w-10 place-items-center rounded-xl bg-brand-soft">
                  <TrendingUp className="h-5 w-5 text-brand" strokeWidth={2} />
                </div>
                <div>
                  <p className="text-[11px] font-medium uppercase tracking-wider text-muted">{t('student.courses.overall_progress')}</p>
                  <p className="text-[22px] font-extrabold leading-tight text-ink">{stats.overallProgress}%</p>
                </div>
              </div>
              <div className="flex items-center gap-3 rounded-2xl bg-white/75 px-5 py-3.5 ring-1 ring-brand-100 backdrop-blur-sm">
                <div className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-100">
                  <GraduationCap className="h-5 w-5 text-emerald-600" strokeWidth={2} />
                </div>
                <div>
                  <p className="text-[11px] font-medium uppercase tracking-wider text-muted">{t('navigation.classes')}</p>
                  <p className="text-[22px] font-extrabold leading-tight text-ink">{stats.classCount}</p>
                </div>
              </div>
            </div>
          ) : (
            <div className="flex items-center gap-3 rounded-2xl bg-white/75 px-5 py-3.5 ring-1 ring-brand-100 backdrop-blur-sm">
              <div>
                <p className="text-[11px] font-medium uppercase tracking-wider text-muted">{t('student.courses.available')}</p>
                <p className="text-[22px] font-extrabold leading-tight text-ink">
                  {course.fee === 0 ? t('student.courses.free') : course.fee_formatted}
                  {course.billing_interval && course.fee !== 0 && (
                    <span className="text-[13px] font-medium text-muted"> {t('student.courses.per_month')}</span>
                  )}
                </p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Section wrapper                                                    */
/* ------------------------------------------------------------------ */
function Section({ icon: Icon, title, action, children, delay = 0 }) {
  return (
    <section className="fade-up" style={{ animationDelay: `${delay}s` }}>
      <div className="mb-3 flex items-center justify-between">
        <h2 className="flex items-center gap-2 text-[15px] font-bold text-ink">
          {Icon && <Icon className="h-4 w-4 text-violet-500" strokeWidth={2} />}
          {title}
        </h2>
        {action}
      </div>
      {children}
    </section>
  );
}

/* ------------------------------------------------------------------ */
/*  Class card (enrolled)                                              */
/* ------------------------------------------------------------------ */
function ClassCard({ cls, index }) {
  return (
    <a
      href={`/my/classes/${cls.id}`}
      className="fade-up group flex flex-col rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/[0.04] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-violet-200/40 hover:ring-violet-200/60"
      style={{ animationDelay: `${0.04 * index + 0.1}s` }}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h3 className="text-[14px] font-bold leading-snug text-ink line-clamp-2 group-hover:text-violet-700">{cls.title}</h3>
          {cls.teacher_name && (
            <p className="mt-1 truncate text-[12px] font-medium text-muted">{cls.teacher_name}</p>
          )}
        </div>
        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-violet-100 text-violet-600 transition-colors group-hover:bg-violet-600 group-hover:text-white">
          <ArrowRight className="h-4 w-4" strokeWidth={2.2} />
        </div>
      </div>

      {/* progress */}
      <div className="mt-3">
        <div className="mb-1 flex items-center justify-between text-[11px]">
          <span className="font-medium text-muted">{t('student.courses.sessions_done', { done: cls.completed_sessions, total: cls.total_sessions })}</span>
          <span className="font-bold text-violet-600">{cls.progress}%</span>
        </div>
        <div className="h-2 overflow-hidden rounded-full bg-violet-100">
          <div className="h-full rounded-full bg-gradient-to-r from-violet-500 to-rose-500 transition-all" style={{ width: `${cls.progress}%` }} />
        </div>
      </div>

      <div className="mt-3 flex items-center justify-between border-t border-violet-50 pt-3">
        {cls.next_session ? (
          <span className="flex items-center gap-1.5 text-[11px] font-medium text-muted">
            <Clock className="h-3.5 w-3.5 text-violet-400" strokeWidth={2} />
            {t('student.courses.next_session')}: {cls.next_session}
          </span>
        ) : <span />}
        <span className="flex items-center gap-1 text-[12px] font-bold text-violet-600 group-hover:text-violet-800">
          {t('student.courses.continue_learning')}
        </span>
      </div>
    </a>
  );
}

/* ------------------------------------------------------------------ */
/*  Page                                                               */
/* ------------------------------------------------------------------ */
export default function CourseShow() {
  const { course, myClasses, recordings, resources, previewClasses, stats } = usePage().props;

  const hero = <Hero course={course} stats={stats} />;

  return (
    <StudentLayout hero={hero}>
      <Head title={course.name} />

      <div className="space-y-7 pt-5">
        {course.has_access ? (
          <>
            {/* Overall progress */}
            {stats.totalSessions > 0 && (
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm">
                <div className="mb-1.5 flex items-center justify-between text-[13px]">
                  <span className="font-semibold text-ink">{t('student.courses.overall_progress')}</span>
                  <span className="font-bold text-violet-600">
                    {t('student.courses.sessions_done', { done: stats.completedSessions, total: stats.totalSessions })}
                  </span>
                </div>
                <div className="h-2.5 overflow-hidden rounded-full bg-violet-100">
                  <div className="h-full rounded-full bg-gradient-to-r from-violet-500 via-purple-500 to-rose-500 transition-all" style={{ width: `${stats.overallProgress}%` }} />
                </div>
              </div>
            )}

            {/* My classes */}
            <Section
              icon={GraduationCap}
              title={t('student.courses.my_classes_in_course')}
              delay={0.05}
              action={
                <a href="/my/classes" className="text-[12px] font-semibold text-violet-600 hover:text-violet-800">
                  {t('student.courses.view_all_classes')}
                </a>
              }
            >
              {myClasses.length > 0 ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  {myClasses.map((cls, i) => <ClassCard key={cls.id} cls={cls} index={i} />)}
                </div>
              ) : (
                <EmptyState icon={GraduationCap} title={t('student.courses.enrolled')} description={t('student.courses.enrolled_no_classes')} />
              )}
            </Section>

            {/* Recordings */}
            <Section icon={Video} title={t('student.courses.recordings')} delay={0.1}>
              {recordings.length > 0 ? (
                <div className="glass-card overflow-hidden rounded-2xl shadow-sm">
                  <div className="divide-y divide-violet-50">
                    {recordings.map((r) => (
                      <a
                        key={r.id}
                        href={r.recording_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-violet-50/40"
                      >
                        <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-100">
                          <PlayCircle className="h-5 w-5 text-rose-600" strokeWidth={1.8} />
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-[13px] font-semibold text-ink">{r.class_title}</p>
                          <p className="text-[11px] text-muted">{r.session_date}</p>
                        </div>
                        <span className="flex items-center gap-1 text-[12px] font-semibold text-violet-600">
                          <Video className="h-3.5 w-3.5" strokeWidth={2} />
                          {t('student.courses.watch')}
                        </span>
                      </a>
                    ))}
                  </div>
                </div>
              ) : (
                <EmptyState icon={Video} title={t('student.courses.no_recordings')} description={t('student.courses.no_recordings_desc')} />
              )}
            </Section>

            {/* Materials */}
            <Section icon={FolderOpen} title={t('student.courses.materials')} delay={0.15}>
              {resources.length > 0 ? (
                <div className="glass-card overflow-hidden rounded-2xl shadow-sm">
                  <div className="divide-y divide-violet-50">
                    {resources.map((r) => (
                      <a
                        key={r.id}
                        href={r.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-violet-50/40"
                      >
                        <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-violet-100">
                          <FolderOpen className="h-5 w-5 text-violet-600" strokeWidth={1.8} />
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-[13px] font-semibold text-ink">{r.title}</p>
                          <p className="text-[11px] text-muted">{r.type} · {r.created_at}</p>
                        </div>
                      </a>
                    ))}
                  </div>
                </div>
              ) : (
                <EmptyState icon={FolderOpen} title={t('student.courses.no_materials')} description={t('student.courses.no_materials_desc')} />
              )}
            </Section>
          </>
        ) : (
          <>
            {/* Description */}
            {(course.description || course.short_description) && (
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm">
                <p className="whitespace-pre-line text-[14px] leading-relaxed text-muted">
                  {course.description || course.short_description}
                </p>
              </div>
            )}

            {/* Curriculum preview */}
            {previewClasses.length > 0 && (
              <Section icon={BookOpen} title={t('student.courses.course_content')} delay={0.05}>
                <div className="glass-card overflow-hidden rounded-2xl shadow-sm">
                  <div className="divide-y divide-violet-50">
                    {previewClasses.map((c, i) => (
                      <div key={i} className="flex items-center gap-4 px-4 py-3">
                        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-violet-100 text-[13px] font-bold text-violet-600">
                          {i + 1}
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-[13px] font-semibold text-ink">{c.title}</p>
                          {c.teacher_name && <p className="text-[11px] text-muted">{c.teacher_name}</p>}
                        </div>
                        <span className="flex items-center gap-1 text-[11px] font-medium text-muted-2">
                          <Users className="h-3.5 w-3.5" strokeWidth={2} />
                          {t('student.courses.sessions_count', { count: c.sessions_count })}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </Section>
            )}

            {/* Enrol CTA */}
            <div className="fade-up overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 via-purple-600 to-rose-500 p-6 text-center shadow-lg shadow-violet-300/40" style={{ animationDelay: '0.1s' }}>
              <div className="mx-auto mb-3 grid h-14 w-14 place-items-center rounded-2xl bg-white/15 ring-1 ring-white/20">
                <Sparkles className="h-7 w-7 text-white" strokeWidth={1.8} />
              </div>
              <h3 className="text-[18px] font-extrabold text-white">{t('student.courses.not_enrolled_cta_title')}</h3>
              <p className="mx-auto mt-1.5 max-w-sm text-[13px] leading-relaxed text-white/80">
                {t('student.courses.not_enrolled_cta_desc')}
              </p>
              <a
                href={course.enroll_url}
                className="mt-5 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-[14px] font-bold text-violet-700 shadow-md transition-all hover:shadow-xl"
              >
                {t('student.courses.enroll_now')}
                <ArrowRight className="h-4 w-4" strokeWidth={2.5} />
              </a>
            </div>
          </>
        )}
      </div>
    </StudentLayout>
  );
}
