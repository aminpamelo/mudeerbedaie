import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ArrowDown, ArrowLeft, ArrowUp, Crown, Pencil, Plus, Trash2, X } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

const EMPTY = {
  name: '',
  color: '#34D399',
  is_top: false,
  description: '',
  min_sessions: '',
  min_hours: '',
  min_gmv_myr: '',
  min_attendance_pct: '',
  monthly_sales_target: '',
  is_active: true,
};

function thresholdSummary(level) {
  const parts = [];
  if (level.min_sessions != null) parts.push(`${level.min_sessions} sessions`);
  if (level.min_hours != null) parts.push(`${level.min_hours}h live`);
  if (level.min_gmv_myr != null) parts.push(`RM ${Number(level.min_gmv_myr).toLocaleString()} GMV`);
  if (level.min_attendance_pct != null) parts.push(`${level.min_attendance_pct}% attendance`);
  return parts.length ? parts.join(' · ') : 'No threshold — entry level';
}

function LevelModal({ level, onClose }) {
  const isEdit = Boolean(level?.id);
  const [draft, setDraft] = useState(
    level
      ? {
          name: level.name ?? '',
          color: level.color ?? '#34D399',
          is_top: !!level.is_top,
          description: level.description ?? '',
          min_sessions: level.min_sessions ?? '',
          min_hours: level.min_hours ?? '',
          min_gmv_myr: level.min_gmv_myr ?? '',
          min_attendance_pct: level.min_attendance_pct ?? '',
          monthly_sales_target: level.monthly_sales_target ?? '',
          is_active: level.is_active ?? true,
        }
      : { ...EMPTY },
  );
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  const set = (k, v) => setDraft((d) => ({ ...d, [k]: v }));

  const payload = () => ({
    name: draft.name,
    color: draft.color || null,
    is_top: draft.is_top,
    description: draft.description || null,
    min_sessions: draft.min_sessions === '' ? null : Number(draft.min_sessions),
    min_hours: draft.min_hours === '' ? null : Number(draft.min_hours),
    min_gmv_myr: draft.min_gmv_myr === '' ? null : Number(draft.min_gmv_myr),
    min_attendance_pct: draft.min_attendance_pct === '' ? null : Number(draft.min_attendance_pct),
    monthly_sales_target: draft.monthly_sales_target === '' ? null : Number(draft.monthly_sales_target),
    is_active: draft.is_active,
  });

  const submit = () => {
    setBusy(true);
    setErrors({});
    const opts = {
      preserveScroll: true,
      onSuccess: () => onClose(),
      onError: (e) => setErrors(e ?? {}),
      onFinish: () => setBusy(false),
    };
    if (isEdit) {
      router.put(`/livehost/mentoring/levels/${level.id}`, payload(), opts);
    } else {
      router.post('/livehost/mentoring/levels', payload(), opts);
    }
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="w-full max-w-lg rounded-[16px] bg-white p-6 shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="mb-4 flex items-center justify-between gap-3">
          <div className="text-[18px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">{isEdit ? 'Edit level' : 'New level'}</div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]" aria-label="Close">
            <X className="h-4 w-4" strokeWidth={2} />
          </button>
        </div>

        <div className="space-y-4">
          <div className="grid grid-cols-[1fr_auto] gap-3">
            <div>
              <Label className="text-[12px] font-medium text-[#0A0A0A]">Name</Label>
              <Input value={draft.name} onChange={(e) => set('name', e.target.value)} autoFocus placeholder="e.g. Pro" />
              {errors.name && <p className="mt-1 text-xs text-[#F43F5E]">{errors.name}</p>}
            </div>
            <div>
              <Label className="text-[12px] font-medium text-[#0A0A0A]">Colour</Label>
              <input type="color" value={draft.color} onChange={(e) => set('color', e.target.value)} className="h-10 w-16 cursor-pointer rounded-lg border border-[#EAEAEA] bg-white p-1" />
            </div>
          </div>

          <div>
            <Label className="text-[12px] font-medium text-[#0A0A0A]">Description <span className="text-[#A3A3A3]">(optional)</span></Label>
            <textarea value={draft.description} onChange={(e) => set('description', e.target.value)} rows={2} className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
          </div>

          <div className="rounded-[12px] border border-[#F0F0F0] bg-[#FAFAFA] p-4">
            <div className="mb-3 text-[11px] font-semibold uppercase tracking-[0.07em] text-[#737373]">
              Auto-suggest thresholds <span className="font-normal normal-case text-[#A3A3A3]">(monthly; leave blank to skip)</span>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <NumField label="Min sessions" value={draft.min_sessions} onChange={(v) => set('min_sessions', v)} error={errors.min_sessions} />
              <NumField label="Min hours live" value={draft.min_hours} onChange={(v) => set('min_hours', v)} error={errors.min_hours} step="0.5" />
              <NumField label="Min GMV (RM)" value={draft.min_gmv_myr} onChange={(v) => set('min_gmv_myr', v)} error={errors.min_gmv_myr} />
              <NumField label="Min attendance %" value={draft.min_attendance_pct} onChange={(v) => set('min_attendance_pct', v)} error={errors.min_attendance_pct} max="100" />
            </div>
          </div>

          <div className="rounded-[12px] border border-[#F0F0F0] bg-[#FAFAFA] p-4">
            <Label className="text-[12px] font-medium text-[#0A0A0A]">
              Monthly sales target <span className="font-normal text-[#A3A3A3]">(units — drives the Sales KPI on the monthly performance grid)</span>
            </Label>
            <Input
              type="number"
              min="0"
              value={draft.monthly_sales_target}
              onChange={(e) => set('monthly_sales_target', e.target.value)}
              className="mt-1 bg-white tabular-nums"
              placeholder="e.g. 120"
            />
            {errors.monthly_sales_target && <p className="mt-1 text-xs text-[#F43F5E]">{errors.monthly_sales_target}</p>}
          </div>

          <div className="flex items-center gap-4">
            <label className="inline-flex cursor-pointer items-center gap-2 text-[12.5px] text-[#0A0A0A]">
              <input type="checkbox" checked={draft.is_top} onChange={(e) => set('is_top', e.target.checked)} className="h-3.5 w-3.5 rounded border-[#D4D4D4] accent-[#F59E0B]" />
              <span className="inline-flex items-center gap-1"><Crown className="h-3.5 w-3.5 text-[#F59E0B]" strokeWidth={2.25} /> Top-host level</span>
            </label>
            <label className="inline-flex cursor-pointer items-center gap-2 text-[12.5px] text-[#0A0A0A]">
              <input type="checkbox" checked={draft.is_active} onChange={(e) => set('is_active', e.target.checked)} className="h-3.5 w-3.5 rounded border-[#D4D4D4] accent-[#059669]" />
              Active
            </label>
          </div>
        </div>

        <div className="mt-5 flex justify-end gap-2 border-t border-[#F0F0F0] pt-4">
          <Button type="button" variant="ghost" disabled={busy} onClick={onClose}>Cancel</Button>
          <Button type="button" disabled={busy || !draft.name.trim()} onClick={submit} className="bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50">
            {busy ? 'Saving…' : isEdit ? 'Save level' : 'Add level'}
          </Button>
        </div>
      </div>
    </div>
  );
}

function NumField({ label, value, onChange, error, step, max }) {
  return (
    <div>
      <Label className="text-[11.5px] font-medium text-[#525252]">{label}</Label>
      <Input type="number" min="0" max={max} step={step} value={value} onChange={(e) => onChange(e.target.value)} className="bg-white tabular-nums" />
      {error && <p className="mt-1 text-xs text-[#F43F5E]">{error}</p>}
    </div>
  );
}

export default function LevelsIndex() {
  const { levels } = usePage().props;
  const [modal, setModal] = useState(null); // null | { } (new) | level (edit)

  const sorted = useMemo(() => [...(levels ?? [])].sort((a, b) => a.position - b.position), [levels]);

  const reorder = (level, direction) => {
    const index = sorted.findIndex((l) => l.id === level.id);
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= sorted.length) return;
    const next = [...sorted];
    [next[index], next[target]] = [next[target], next[index]];
    router.put('/livehost/mentoring/levels/reorder', { level_ids: next.map((l) => l.id) }, { preserveScroll: true });
  };

  const destroy = (level) => {
    const warn = level.mentees_count > 0
      ? `${level.mentees_count} mentee(s) are on "${level.name}". Deleting it clears their level. Continue?`
      : `Delete the "${level.name}" level?`;
    if (!window.confirm(warn)) return;
    router.delete(`/livehost/mentoring/levels/${level.id}`, { preserveScroll: true });
  };

  return (
    <>
      <Head title="Mentoring Levels" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Mentoring', 'Levels']}
        actions={
          <div className="flex items-center gap-2">
            <Link href="/livehost/mentoring/programs">
              <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
                <ArrowLeft className="h-3.5 w-3.5" /> Programs
              </Button>
            </Link>
            <Button size="sm" onClick={() => setModal({})} className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
              <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} /> New level
            </Button>
          </div>
        }
      />

      <div className="space-y-6 p-8">
        <div>
          <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">Performance levels</h1>
          <p className="mt-1.5 text-sm text-[#737373]">
            Customize the ladder mentees climb. Lower position = entry level; the top-host level is the graduation target.
            Thresholds drive the auto-suggested level on each mentee.
          </p>
        </div>

        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {sorted.length === 0 ? (
            <div className="py-16 text-center text-sm text-[#737373]">No levels yet. Add your first level.</div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                  <th className="w-20 px-3 py-3 text-left">Order</th>
                  <th className="px-5 py-3 text-left">Level</th>
                  <th className="px-5 py-3 text-left">Auto-suggest thresholds</th>
                  <th className="px-5 py-3 text-right">Mentees</th>
                  <th className="px-5 py-3 text-center">Active</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {sorted.map((level, index) => (
                  <tr key={level.id} className="border-t border-[#F0F0F0] hover:bg-[#FAFAFA]">
                    <td className="px-3 py-3.5">
                      <div className="flex items-center gap-0.5">
                        <button type="button" onClick={() => reorder(level, 'up')} disabled={index === 0} className="inline-flex h-6 w-6 items-center justify-center rounded text-[#737373] hover:bg-[#F0F0F0] disabled:opacity-30">
                          <ArrowUp className="h-3.5 w-3.5" strokeWidth={2} />
                        </button>
                        <button type="button" onClick={() => reorder(level, 'down')} disabled={index === sorted.length - 1} className="inline-flex h-6 w-6 items-center justify-center rounded text-[#737373] hover:bg-[#F0F0F0] disabled:opacity-30">
                          <ArrowDown className="h-3.5 w-3.5" strokeWidth={2} />
                        </button>
                      </div>
                    </td>
                    <td className="px-5 py-3.5">
                      <div className="flex items-center gap-2.5">
                        <span className="inline-block h-4 w-4 shrink-0 rounded-full ring-1 ring-black/5" style={{ backgroundColor: level.color || '#A3A3A3' }} />
                        <div className="min-w-0">
                          <div className="flex items-center gap-1.5">
                            <span className="text-[13.5px] font-semibold text-[#0A0A0A]">{level.name}</span>
                            {level.is_top && (
                              <span className="inline-flex items-center gap-1 rounded-full bg-[#FFFBEB] px-1.5 py-0.5 text-[9.5px] font-semibold uppercase tracking-wide text-[#B45309]">
                                <Crown className="h-2.5 w-2.5" strokeWidth={2.5} /> Top
                              </span>
                            )}
                          </div>
                          {level.description && <div className="truncate text-[11.5px] text-[#737373]">{level.description}</div>}
                        </div>
                      </div>
                    </td>
                    <td className="px-5 py-3.5 text-[12px] text-[#525252]">
                      {thresholdSummary(level)}
                      {level.monthly_sales_target != null && (
                        <div className="mt-0.5 text-[11px] text-[#737373]">
                          Sales KPI target: <span className="font-medium tabular-nums text-[#0A0A0A]">{level.monthly_sales_target}</span>/mo
                        </div>
                      )}
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[#525252]">{level.mentees_count}</td>
                    <td className="px-5 py-3.5 text-center">
                      {level.is_active ? (
                        <span className="inline-flex items-center rounded-full bg-[#ECFDF5] px-2 py-0.5 text-[10.5px] font-medium text-[#059669]">Active</span>
                      ) : (
                        <span className="inline-flex items-center rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[10.5px] font-medium text-[#A3A3A3]">Off</span>
                      )}
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <div className="inline-flex items-center justify-end gap-1">
                        <button type="button" onClick={() => setModal(level)} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]" title="Edit">
                          <Pencil className="h-[14px] w-[14px]" strokeWidth={2} />
                        </button>
                        <button type="button" onClick={() => destroy(level)} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#FFF1F2] hover:text-[#F43F5E]" title="Delete">
                          <Trash2 className="h-[14px] w-[14px]" strokeWidth={2} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {modal !== null && <LevelModal level={modal.id ? modal : null} onClose={() => setModal(null)} />}
    </>
  );
}

LevelsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
