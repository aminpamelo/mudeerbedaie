import { useEffect, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Loader2, Search, Check, X, ChevronDown } from 'lucide-react';
import Modal from './Modal';
import { cn } from '@/ceo/lib/utils';
import { useT } from '@/ceo/lib/i18n';

const FIELD =
  'w-full rounded-xl border border-[rgba(15,23,42,0.12)] bg-white/70 px-3 py-2 text-[13px] text-ink outline-none transition focus:border-[var(--color-brand)]';
const LABEL = 'mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-muted-2';

/**
 * Searchable multi-select of employees. A task can be co-owned by several people;
 * the first selected becomes the canonical assignee server-side.
 */
function MultiAssigneeSelect({ employees, value, onChange, t }) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const ref = useRef(null);

  useEffect(() => {
    function onDocClick(e) {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    }
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, []);

  const selectedSet = new Set(value);
  const byId = new Map(employees.map((e) => [e.id, e.name]));
  const needle = query.trim().toLowerCase();
  const filtered = needle ? employees.filter((e) => e.name.toLowerCase().includes(needle)) : employees;

  function toggle(id) {
    onChange(selectedSet.has(id) ? value.filter((x) => x !== id) : [...value, id]);
  }

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className={cn(FIELD, 'flex min-h-[38px] flex-wrap items-center gap-1.5 text-left')}
      >
        {value.length === 0 ? (
          <span className="text-muted-2">{t('tasks_select_assignee')}</span>
        ) : (
          value.map((id) => (
            <span
              key={id}
              className="inline-flex items-center gap-1 rounded-lg bg-[rgba(99,102,241,0.1)] px-1.5 py-0.5 text-[12px] font-medium text-[var(--color-brand-ink)]"
            >
              {byId.get(id) ?? id}
              <span
                role="button"
                tabIndex={-1}
                onClick={(e) => {
                  e.stopPropagation();
                  toggle(id);
                }}
                className="grid h-3.5 w-3.5 cursor-pointer place-items-center rounded hover:bg-black/10"
              >
                <X className="h-3 w-3" />
              </span>
            </span>
          ))
        )}
        <ChevronDown className="ml-auto h-4 w-4 shrink-0 text-muted-2" />
      </button>

      {open && (
        <div className="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-[rgba(15,23,42,0.12)] bg-white shadow-lg">
          <div className="relative border-b border-[rgba(15,23,42,0.08)]">
            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-2" />
            <input
              autoFocus
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder={t('tasks_search_staff')}
              className="w-full bg-transparent py-2 pl-8 pr-3 text-[13px] text-ink outline-none"
            />
          </div>
          <div className="max-h-52 overflow-y-auto py-1">
            {filtered.length === 0 ? (
              <div className="px-3 py-2 text-[12px] text-muted">{t('tasks_no_staff_found')}</div>
            ) : (
              filtered.map((e) => {
                const on = selectedSet.has(e.id);
                return (
                  <button
                    type="button"
                    key={e.id}
                    onClick={() => toggle(e.id)}
                    className="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[13px] text-ink transition-colors hover:bg-[rgba(15,23,42,0.04)]"
                  >
                    <span
                      className={cn(
                        'grid h-4 w-4 shrink-0 place-items-center rounded border transition-colors',
                        on ? 'border-transparent bg-[var(--color-brand)] text-white' : 'border-[rgba(15,23,42,0.25)]'
                      )}
                    >
                      {on && <Check className="h-3 w-3" strokeWidth={3} />}
                    </span>
                    {e.name}
                  </button>
                );
              })
            )}
          </div>
          {value.length > 0 && (
            <div className="border-t border-[rgba(15,23,42,0.08)] px-3 py-1.5 text-[11px] text-muted">
              {t('tasks_selected_count', { count: value.length })}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

/**
 * Create / edit a task from the CEO Task Monitoring board. Submits via Inertia so
 * the controller redirects back and the board + aggregates reload with fresh data.
 * Mount with a fresh `key` per open so useForm initialises from the right task.
 */
export default function TaskEditModal({ mode, task, employees, categories, statusOptions, priorityOptions, onClose }) {
  const t = useT();
  const isEdit = mode === 'edit';

  const initialAssignees = task?.assignees?.length
    ? task.assignees.map((a) => a.id)
    : task?.assigned_to
      ? [task.assigned_to]
      : [];

  const form = useForm({
    title: task?.title ?? '',
    description: task?.description ?? '',
    assignee_ids: initialAssignees,
    category_id: task?.category_id ? String(task.category_id) : '',
    priority: task?.priority ?? 'medium',
    status: task?.status ?? 'pending',
    deadline: task?.deadline ?? '',
  });

  const valid = form.data.title.trim() && form.data.assignee_ids.length > 0 && form.data.deadline;

  function submit(e) {
    e.preventDefault();

    // transform() only registers the callback in @inertiajs/react (returns
    // undefined), so it cannot be chained — call it, then submit on `form`.
    form.transform((data) => ({
      ...data,
      category_id: data.category_id || null,
      description: data.description || null,
      ...(isEdit ? {} : { status: undefined }),
    }));

    const options = {
      preserveScroll: true,
      only: ['board', 'tasks'],
      onSuccess: onClose,
    };

    if (isEdit) {
      form.patch(`/ceo/tasks/${task.id}`, options);
    } else {
      form.post('/ceo/tasks', options);
    }
  }

  return (
    <Modal title={isEdit ? t('tasks_edit_task') : t('tasks_new')} onClose={onClose} closeLabel={t('tasks_close')}>
      <form onSubmit={submit} className="space-y-4">
        <div>
          <label className={LABEL}>{t('tasks_f_title')} *</label>
          <input
            className={FIELD}
            value={form.data.title}
            onChange={(e) => form.setData('title', e.target.value)}
            placeholder="…"
            autoFocus
          />
          {form.errors.title && <p className="mt-1 text-[11px] text-[var(--color-rose-ink)]">{form.errors.title}</p>}
        </div>

        <div>
          <label className={LABEL}>{t('tasks_f_description')}</label>
          <textarea
            className={FIELD}
            rows={2}
            value={form.data.description}
            onChange={(e) => form.setData('description', e.target.value)}
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={LABEL}>{t('tasks_f_assignee')} *</label>
            <MultiAssigneeSelect
              employees={employees}
              value={form.data.assignee_ids}
              onChange={(ids) => form.setData('assignee_ids', ids)}
              t={t}
            />
            {form.errors.assignee_ids && (
              <p className="mt-1 text-[11px] text-[var(--color-rose-ink)]">{form.errors.assignee_ids}</p>
            )}
          </div>
          <div>
            <label className={LABEL}>{t('tasks_f_category')}</label>
            <select className={FIELD} value={form.data.category_id} onChange={(e) => form.setData('category_id', e.target.value)}>
              <option value="">{t('tasks_uncategorized')}</option>
              {categories.map((cat) => (
                <option key={cat.id} value={String(cat.id)}>
                  {cat.name}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
          <div>
            <label className={LABEL}>{t('tasks_f_priority')}</label>
            <select className={FIELD} value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)}>
              {priorityOptions.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          </div>
          {isEdit && (
            <div>
              <label className={LABEL}>{t('tasks_f_status')}</label>
              <select className={FIELD} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                {statusOptions.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
          )}
          <div>
            <label className={LABEL}>{t('tasks_f_deadline')} *</label>
            <input type="date" className={FIELD} value={form.data.deadline} onChange={(e) => form.setData('deadline', e.target.value)} />
            {form.errors.deadline && (
              <p className="mt-1 text-[11px] text-[var(--color-rose-ink)]">{form.errors.deadline}</p>
            )}
          </div>
        </div>

        <div className="mt-2 flex justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl px-4 py-2 text-[12.5px] font-semibold text-muted transition-colors hover:text-ink"
          >
            {t('tasks_cancel')}
          </button>
          <button
            type="submit"
            disabled={!valid || form.processing}
            className="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-[12.5px] font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-50"
            style={{ background: 'linear-gradient(90deg, var(--color-brand), var(--color-violet))' }}
          >
            {form.processing && <Loader2 className="h-4 w-4 animate-spin" />}
            {isEdit ? t('tasks_save_changes') : t('tasks_create')}
          </button>
        </div>
      </form>
    </Modal>
  );
}
