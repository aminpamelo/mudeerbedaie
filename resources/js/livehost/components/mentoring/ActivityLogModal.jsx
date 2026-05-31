import { router } from '@inertiajs/react';
import { useState } from 'react';
import { X } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

const ACTIVITY_TYPES = [
  { value: 'coaching', label: 'Coaching' },
  { value: 'meeting', label: 'Meeting' },
  { value: 'training', label: 'Training' },
  { value: 'check_in', label: 'Check-in' },
  { value: 'other', label: 'Other' },
];

function nowLocalInput() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function fromLocalInput(value) {
  if (!value) return null;
  const d = new Date(value);
  if (isNaN(d.getTime())) return null;
  return d.toISOString();
}

/**
 * Log a coaching/meeting/training touchpoint against a program. When `mentees`
 * is provided a picker is shown (program-wide vs a specific mentee); when
 * `presetMenteeId` is provided the activity is fixed to that mentee.
 */
export default function ActivityLogModal({ programId, mentees = null, presetMenteeId = null, onClose }) {
  const [type, setType] = useState('coaching');
  const [title, setTitle] = useState('');
  const [notes, setNotes] = useState('');
  const [occurredAt, setOccurredAt] = useState(nowLocalInput());
  const [menteeId, setMenteeId] = useState(presetMenteeId ? String(presetMenteeId) : '');
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  const submit = () => {
    setBusy(true);
    setErrors({});
    router.post(
      `/livehost/mentoring/programs/${programId}/activities`,
      {
        type,
        title,
        notes: notes || null,
        occurred_at: fromLocalInput(occurredAt),
        mentee_id: menteeId ? Number(menteeId) : null,
      },
      {
        preserveScroll: true,
        onSuccess: () => onClose(),
        onError: (e) => setErrors(e ?? {}),
        onFinish: () => setBusy(false),
      },
    );
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="w-full max-w-md rounded-[16px] bg-white p-6 shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="mb-4 flex items-center justify-between gap-3">
          <div className="text-[18px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">Log activity</div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]" aria-label="Close">
            <X className="h-4 w-4" strokeWidth={2} />
          </button>
        </div>

        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-[12px] font-medium text-[#0A0A0A]">Type</Label>
              <select value={type} onChange={(e) => setType(e.target.value)} className="h-10 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20">
                {ACTIVITY_TYPES.map((t) => (
                  <option key={t.value} value={t.value}>{t.label}</option>
                ))}
              </select>
            </div>
            <div>
              <Label className="text-[12px] font-medium text-[#0A0A0A]">When</Label>
              <Input type="datetime-local" value={occurredAt} onChange={(e) => setOccurredAt(e.target.value)} />
              {errors.occurred_at && <p className="mt-1 text-xs text-[#F43F5E]">{errors.occurred_at}</p>}
            </div>
          </div>

          <div>
            <Label className="text-[12px] font-medium text-[#0A0A0A]">Title</Label>
            <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="e.g. 1:1 coaching on hook openings" autoFocus />
            {errors.title && <p className="mt-1 text-xs text-[#F43F5E]">{errors.title}</p>}
          </div>

          {Array.isArray(mentees) && (
            <div>
              <Label className="text-[12px] font-medium text-[#0A0A0A]">Mentee <span className="text-[#A3A3A3]">(optional)</span></Label>
              <select value={menteeId} onChange={(e) => setMenteeId(e.target.value)} className="h-10 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20">
                <option value="">Program-wide</option>
                {mentees.map((m) => (
                  <option key={m.id} value={m.id}>{m.name}</option>
                ))}
              </select>
            </div>
          )}

          <div>
            <Label className="text-[12px] font-medium text-[#0A0A0A]">Notes <span className="text-[#A3A3A3]">(optional)</span></Label>
            <textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={3} placeholder="What was covered, action items…" className="w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
          </div>
        </div>

        <div className="mt-5 flex justify-end gap-2 border-t border-[#F0F0F0] pt-4">
          <Button type="button" variant="ghost" disabled={busy} onClick={onClose}>Cancel</Button>
          <Button type="button" disabled={busy || !title.trim()} onClick={submit} className="bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50">
            {busy ? 'Saving…' : 'Log activity'}
          </Button>
        </div>
      </div>
    </div>
  );
}

export { ACTIVITY_TYPES };
