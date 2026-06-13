import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import Modal from './Modal';
import { useT } from '@/ceo/lib/i18n';

const FIELD =
  'w-full rounded-xl border border-[rgba(15,23,42,0.12)] bg-white/70 px-3 py-2 text-[13px] text-ink outline-none transition focus:border-[var(--color-brand)]';
const LABEL = 'mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-muted-2';

/**
 * Create / edit a task from the CEO Task Monitoring board. Submits via Inertia so
 * the controller redirects back and the board + aggregates reload with fresh data.
 * Mount with a fresh `key` per open so useForm initialises from the right task.
 */
export default function TaskEditModal({ mode, task, employees, categories, statusOptions, priorityOptions, onClose }) {
  const t = useT();
  const isEdit = mode === 'edit';

  const form = useForm({
    title: task?.title ?? '',
    description: task?.description ?? '',
    assigned_to: task?.assigned_to ? String(task.assigned_to) : '',
    category_id: task?.category_id ? String(task.category_id) : '',
    priority: task?.priority ?? 'medium',
    status: task?.status ?? 'pending',
    deadline: task?.deadline ?? '',
  });

  const valid = form.data.title.trim() && form.data.assigned_to && form.data.deadline;

  function submit(e) {
    e.preventDefault();
    form
      .transform((data) => ({
        ...data,
        category_id: data.category_id || null,
        description: data.description || null,
        ...(isEdit ? {} : { status: undefined }),
      }))
      [isEdit ? 'patch' : 'post'](isEdit ? `/ceo/tasks/${task.id}` : '/ceo/tasks', {
        preserveScroll: true,
        only: ['board', 'tasks'],
        onSuccess: onClose,
      });
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
            <select className={FIELD} value={form.data.assigned_to} onChange={(e) => form.setData('assigned_to', e.target.value)}>
              <option value="" disabled>
                {t('tasks_select_assignee')}
              </option>
              {employees.map((emp) => (
                <option key={emp.id} value={String(emp.id)}>
                  {emp.name}
                </option>
              ))}
            </select>
            {form.errors.assigned_to && (
              <p className="mt-1 text-[11px] text-[var(--color-rose-ink)]">{form.errors.assigned_to}</p>
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
