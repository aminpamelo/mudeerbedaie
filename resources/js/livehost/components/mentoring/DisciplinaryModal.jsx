import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { ShieldAlert, Trash2, X } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

export const DISCIPLINARY_CATEGORIES = [
  { value: 'lateness', label: 'Lateness' },
  { value: 'absence', label: 'Absence' },
  { value: 'rule_violation', label: 'Rule violation' },
  { value: 'misconduct', label: 'Misconduct' },
  { value: 'other', label: 'Other' },
];

const SEVERITIES = [
  { value: 'minor', label: 'Minor' },
  { value: 'major', label: 'Major' },
];

export function categoryLabel(value) {
  return DISCIPLINARY_CATEGORIES.find((c) => c.value === value)?.label ?? value;
}

export function severityTone(severity) {
  return severity === 'major'
    ? 'bg-[#FEE2E2] text-[#B91C1C]'
    : 'bg-[#FEF3C7] text-[#B45309]';
}

function todayInput() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Record a disciplinary / conduct incident against a mentee. The existing log is
 * always fetched live and listed below the form (so a newly added record shows
 * up immediately). `reloadOnly` also refreshes the host page that opened it
 * (e.g. the performance grid's count badge or the mentee Conduct tab).
 */
export default function DisciplinaryModal({ mentee, records = null, reloadOnly = ['performance'], presetDate = null, onClose }) {
  const [incidentDate, setIncidentDate] = useState(presetDate || todayInput());
  const [category, setCategory] = useState('lateness');
  const [severity, setSeverity] = useState('minor');
  const [description, setDescription] = useState('');
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});
  const [list, setList] = useState(records ?? []);

  const fetchList = useCallback(() => {
    fetch(`/livehost/mentoring/mentees/${mentee.id}/disciplinary`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((data) => setList(data.records ?? []))
      .catch(() => {});
  }, [mentee.id]);

  useEffect(() => { fetchList(); }, [fetchList]);

  const reload = () => router.reload({ only: reloadOnly, preserveScroll: true });

  const submit = () => {
    setBusy(true);
    setErrors({});
    router.post(
      `/livehost/mentoring/mentees/${mentee.id}/disciplinary`,
      { incident_date: incidentDate, category, severity, description },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { setDescription(''); fetchList(); reload(); },
        onError: (e) => setErrors(e ?? {}),
        onFinish: () => setBusy(false),
      },
    );
  };

  const remove = (record) => {
    if (!window.confirm('Remove this disciplinary record?')) return;
    router.delete(`/livehost/mentoring/disciplinary/${record.id}`, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => { fetchList(); reload(); },
    });
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="max-h-[88vh] w-full max-w-lg overflow-y-auto rounded-[16px] bg-white p-6 shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="mb-4 flex items-center justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#FEF2F2] text-[#B91C1C]">
              <ShieldAlert className="h-4 w-4" strokeWidth={2} />
            </div>
            <div>
              <div className="text-[16px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">Disciplinary record</div>
              <div className="text-[12px] text-[#737373]">{mentee.name}</div>
            </div>
          </div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]" aria-label="Close">
            <X className="h-4 w-4" strokeWidth={2} />
          </button>
        </div>

        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-[12px] font-medium text-[#0A0A0A]">Date</Label>
              <Input type="date" value={incidentDate} onChange={(e) => setIncidentDate(e.target.value)} />
              {errors.incident_date && <p className="mt-1 text-xs text-[#F43F5E]">{errors.incident_date}</p>}
            </div>
            <div>
              <Label className="text-[12px] font-medium text-[#0A0A0A]">Severity</Label>
              <select value={severity} onChange={(e) => setSeverity(e.target.value)} className="h-10 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20">
                {SEVERITIES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
              </select>
            </div>
          </div>

          <div>
            <Label className="text-[12px] font-medium text-[#0A0A0A]">Category</Label>
            <select value={category} onChange={(e) => setCategory(e.target.value)} className="h-10 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20">
              {DISCIPLINARY_CATEGORIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
            </select>
          </div>

          <div>
            <Label className="text-[12px] font-medium text-[#0A0A0A]">What happened</Label>
            <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3} placeholder="Describe the incident…" className="w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
            {errors.description && <p className="mt-1 text-xs text-[#F43F5E]">{errors.description}</p>}
          </div>

          <div className="flex justify-end">
            <Button type="button" disabled={busy || !description.trim()} onClick={submit} className="bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50">
              {busy ? 'Saving…' : 'Add record'}
            </Button>
          </div>
        </div>

        <div className="mt-5 border-t border-[#F0F0F0] pt-4">
            <div className="mb-2 text-[11px] font-semibold uppercase tracking-[0.05em] text-[#737373]">History ({list.length})</div>
            {list.length === 0 ? (
              <p className="py-4 text-center text-[12.5px] text-[#A3A3A3]">No records. A clean sheet.</p>
            ) : (
              <ul className="space-y-2">
                {list.map((r) => (
                  <li key={r.id} className="group flex items-start justify-between gap-3 rounded-lg border border-[#F0F0F0] p-3">
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide ${severityTone(r.severity)}`}>{r.severity}</span>
                        <span className="text-[13px] font-semibold text-[#0A0A0A]">{categoryLabel(r.category)}</span>
                        <span className="text-[11px] text-[#A3A3A3]">{r.incident_date_human ?? r.incident_date}</span>
                      </div>
                      <div className="mt-1 whitespace-pre-wrap text-[12.5px] text-[#525252]">{r.description}</div>
                      {r.recorded_by && <div className="mt-1 text-[11px] text-[#A3A3A3]">by {r.recorded_by}</div>}
                    </div>
                    <button type="button" onClick={() => remove(r)} className="shrink-0 rounded-md p-1.5 text-[#A3A3A3] opacity-0 transition-opacity hover:bg-[#FFF1F2] hover:text-[#F43F5E] group-hover:opacity-100" title="Remove">
                      <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
      </div>
    </div>
  );
}
