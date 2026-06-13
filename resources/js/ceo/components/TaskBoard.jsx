import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, Search, ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';
import { cn, toneColor } from '@/ceo/lib/utils';
import { useT } from '@/ceo/lib/i18n';
import TaskEditModal from './TaskEditModal';
import Modal from './Modal';

const PRIORITY_TONE = { urgent: 'negative', high: 'warning', medium: 'info', low: 'muted' };

const INLINE =
  'rounded-lg border border-[rgba(15,23,42,0.1)] bg-white/70 px-2 py-1.5 text-[12px] font-medium text-ink outline-none transition focus:border-[var(--color-brand)]';

const GRID = 'lg:grid-cols-[1fr_150px_118px_140px_140px_64px]';

function currentQuery() {
  if (typeof window === 'undefined') return {};
  return Object.fromEntries(new URLSearchParams(window.location.search));
}

/**
 * Editable task list for the CEO Task Monitoring page. Filterable + paginated,
 * with inline editing of status / priority / deadline / assignee, plus create,
 * full-edit (modal) and delete. Every mutation goes through Inertia so the board
 * and the page aggregates refresh together.
 */
export default function TaskBoard({ board, employees, categories }) {
  const t = useT();
  const { data: rows, meta, filters, statusFilters, statusOptions, priorityOptions } = board;
  const [search, setSearch] = useState(filters.search ?? '');
  const [modal, setModal] = useState(null);
  const [confirmTask, setConfirmTask] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const searchTimer = useRef(null);

  function navigate(overrides, only = ['board']) {
    const params = { ...currentQuery(), ...overrides };
    Object.keys(params).forEach((k) => {
      if (params[k] === '' || params[k] == null) delete params[k];
    });
    router.get('/ceo/tasks', params, { only, preserveState: true, preserveScroll: true, replace: true });
  }

  function setFilter(key, value) {
    navigate({ [key]: value || undefined, page: undefined });
  }

  function onSearch(value) {
    setSearch(value);
    clearTimeout(searchTimer.current);
    searchTimer.current = setTimeout(() => navigate({ search: value || undefined, page: undefined }), 350);
  }

  function patchTask(id, payload) {
    // async so quick successive inline edits to a row don't cancel one another
    // (Inertia cancels in-flight synchronous visits by default).
    router.patch(`/ceo/tasks/${id}`, payload, { async: true, preserveScroll: true, preserveState: true, only: ['board', 'tasks'] });
  }

  function destroy() {
    if (!confirmTask) return;
    setDeleting(true);
    router.delete(`/ceo/tasks/${confirmTask.id}`, {
      preserveScroll: true,
      preserveState: true,
      only: ['board', 'tasks'],
      onFinish: () => {
        setDeleting(false);
        setConfirmTask(null);
      },
    });
  }

  const activeStatus = filters.status ?? 'open';

  return (
    <div className="glass-card flex flex-col gap-4 rounded-[20px] p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 className="font-display text-[15px] text-ink">{t('tasks_board_title')}</h3>
          <p className="text-[11px] text-muted">{t('tasks_board_subtitle')}</p>
        </div>
        <button
          type="button"
          onClick={() => setModal({ mode: 'create', task: null })}
          className="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-[12.5px] font-semibold text-white transition-transform hover:-translate-y-0.5"
          style={{ background: 'linear-gradient(90deg, var(--color-brand), var(--color-violet))' }}
        >
          <Plus className="h-4 w-4" strokeWidth={2.4} />
          {t('tasks_new')}
        </button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-2">
        <div className="glass inline-flex flex-wrap items-center gap-0.5 rounded-[12px] p-1">
          {statusFilters.map((f) => (
            <button
              key={f.value}
              type="button"
              onClick={() => setFilter('status', f.value)}
              className={cn(
                'rounded-[9px] px-2.5 py-1.5 text-[12px] font-semibold transition-all',
                activeStatus === f.value ? 'bg-ink text-white' : 'text-muted hover:text-ink'
              )}
            >
              {f.label}
            </button>
          ))}
        </div>
        <select value={filters.priority ?? ''} onChange={(e) => setFilter('priority', e.target.value)} className={INLINE}>
          <option value="">{t('tasks_all_priorities')}</option>
          {priorityOptions.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
        <div className="relative">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-2" />
          <input value={search} onChange={(e) => onSearch(e.target.value)} placeholder={t('tasks_search_ph')} className={cn(INLINE, 'pl-8')} />
        </div>
      </div>

      {/* List */}
      {rows.length === 0 ? (
        <div className="grid h-20 place-items-center rounded-xl bg-[rgba(15,23,42,0.04)] text-[12px] text-muted">{t('tasks_empty')}</div>
      ) : (
        <>
          <div className={cn('hidden gap-3 px-1 font-mono text-[10px] font-medium uppercase tracking-[0.1em] text-muted-2 lg:grid', GRID)}>
            <span>{t('tasks_col_task')}</span>
            <span>{t('tasks_col_assignee')}</span>
            <span>{t('tasks_col_priority')}</span>
            <span>{t('tasks_col_deadline')}</span>
            <span>{t('tasks_col_status')}</span>
            <span />
          </div>

          <div className="flex flex-col divide-y divide-[rgba(15,23,42,0.06)]">
            {rows.map((row) => (
              <div key={row.id} className={cn('flex flex-col gap-2 py-3 lg:grid lg:items-center lg:gap-3', GRID)}>
                <div className="flex min-w-0 items-start gap-2">
                  <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full" style={{ background: toneColor(PRIORITY_TONE[row.priority]) }} aria-hidden="true" />
                  <div className="min-w-0">
                    <button onClick={() => setModal({ mode: 'edit', task: row })} className="block max-w-full truncate text-left text-[13px] font-medium text-ink hover:text-[var(--color-brand-ink)]">
                      {row.title}
                    </button>
                    <div className="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-muted">
                      {row.category && (
                        <span className="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 font-medium" style={{ color: row.category.color, background: `${row.category.color}14` }}>
                          <span className="h-1.5 w-1.5 rounded-full" style={{ background: row.category.color }} />
                          {row.category.name}
                        </span>
                      )}
                      {row.source ? <span className="truncate">{row.source}</span> : <span className="text-muted-2">{t('tasks_standalone')}</span>}
                      {row.overdue && <span className="rounded-full bg-[rgba(244,63,94,0.12)] px-1.5 py-0.5 font-semibold text-[var(--color-rose-ink)]">{t('tasks_overdue_tag')}</span>}
                    </div>
                  </div>
                </div>

                <select value={row.assigned_to ? String(row.assigned_to) : ''} onChange={(e) => patchTask(row.id, { assigned_to: e.target.value })} className={INLINE} aria-label={t('tasks_col_assignee')}>
                  {employees.map((emp) => (
                    <option key={emp.id} value={String(emp.id)}>
                      {emp.name}
                    </option>
                  ))}
                </select>

                <select value={row.priority} onChange={(e) => patchTask(row.id, { priority: e.target.value })} className={INLINE} aria-label={t('tasks_col_priority')}>
                  {priorityOptions.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>

                <input
                  type="date"
                  value={row.deadline ?? ''}
                  onChange={(e) => e.target.value && patchTask(row.id, { deadline: e.target.value })}
                  className={cn(INLINE, row.overdue && 'text-[var(--color-rose-ink)]')}
                  aria-label={t('tasks_col_deadline')}
                />

                <select value={row.status} onChange={(e) => patchTask(row.id, { status: e.target.value })} className={INLINE} aria-label={t('tasks_col_status')}>
                  {statusOptions.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>

                <div className="flex items-center gap-1 lg:justify-end">
                  <button onClick={() => setModal({ mode: 'edit', task: row })} className="grid h-7 w-7 place-items-center rounded-lg text-muted-2 transition-colors hover:bg-white/70 hover:text-ink" aria-label={t('tasks_edit_task')}>
                    <Pencil className="h-3.5 w-3.5" />
                  </button>
                  <button onClick={() => setConfirmTask(row)} className="grid h-7 w-7 place-items-center rounded-lg text-muted-2 transition-colors hover:bg-[rgba(244,63,94,0.1)] hover:text-[var(--color-rose-ink)]" aria-label={t('tasks_delete')}>
                    <Trash2 className="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
            ))}
          </div>

          {meta.last_page > 1 && (
            <div className="flex items-center justify-between pt-1">
              <span className="text-[11px] text-muted">{t('tasks_page', { current: meta.current_page, last: meta.last_page })}</span>
              <div className="flex gap-1">
                <button disabled={meta.current_page <= 1} onClick={() => navigate({ page: meta.current_page - 1 })} className="grid h-7 w-7 place-items-center rounded-lg text-muted transition-colors hover:bg-white/70 hover:text-ink disabled:opacity-30" aria-label={t('tasks_page_prev')}>
                  <ChevronLeft className="h-4 w-4" />
                </button>
                <button disabled={meta.current_page >= meta.last_page} onClick={() => navigate({ page: meta.current_page + 1 })} className="grid h-7 w-7 place-items-center rounded-lg text-muted transition-colors hover:bg-white/70 hover:text-ink disabled:opacity-30" aria-label={t('tasks_page_next')}>
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {modal && (
        <TaskEditModal
          key={`${modal.mode}-${modal.task?.id ?? 'new'}`}
          mode={modal.mode}
          task={modal.task}
          employees={employees}
          categories={categories}
          statusOptions={statusOptions}
          priorityOptions={priorityOptions}
          onClose={() => setModal(null)}
        />
      )}

      {confirmTask && (
        <Modal title={t('tasks_delete')} size="sm" onClose={() => setConfirmTask(null)} closeLabel={t('tasks_close')}>
          <p className="text-[13px] text-ink-2">{t('tasks_confirm_delete')}</p>
          <p className="mt-1 text-[12px] font-medium text-muted">{confirmTask.title}</p>
          <div className="mt-5 flex justify-end gap-2">
            <button type="button" onClick={() => setConfirmTask(null)} className="rounded-xl px-4 py-2 text-[12.5px] font-semibold text-muted transition-colors hover:text-ink">
              {t('tasks_cancel')}
            </button>
            <button type="button" onClick={destroy} disabled={deleting} className="inline-flex items-center gap-1.5 rounded-xl bg-[var(--color-rose)] px-4 py-2 text-[12.5px] font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:opacity-50">
              {deleting && <Loader2 className="h-4 w-4 animate-spin" />}
              {t('tasks_delete')}
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}
