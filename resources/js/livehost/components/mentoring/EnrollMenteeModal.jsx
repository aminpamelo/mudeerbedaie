import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { UserPlus, X } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';
import SearchableSelect from '@/livehost/components/SearchableSelect';

/**
 * Enrol an existing live host into a mentoring program. Shared by the Mentee
 * board and the Monthly Performance tab so both entry points post the same
 * payload to `POST /livehost/mentoring/programs/{program}/mentees`.
 *
 * Pass `reloadOnly` (e.g. ['board'] or ['performance', 'board']) to limit the
 * partial reload to just the props the calling tab renders.
 */
export default function EnrollMenteeModal({ program, enrollableHosts, assignableMentors, reloadOnly, onClose }) {
  const [menteeId, setMenteeId] = useState('');
  const [mentorId, setMentorId] = useState('');
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  // Group full live hosts apart from part-time assistants so the picker makes
  // the distinction obvious. The backend already orders hosts before assistants,
  // so the group headers land in the right order.
  const hostOptions = useMemo(
    () =>
      (enrollableHosts ?? []).map((u) => ({
        value: String(u.id),
        label: u.name,
        hint: u.email,
        keywords: u.email,
        group: u.is_assistant ? 'Live Host Assistants' : 'Live Hosts',
        avatar: { initials: u.initials },
      })),
    [enrollableHosts],
  );

  // Live hosts first (top-host-eligible surfaced first, marked ★), then live host
  // assistants under their own group.
  const mentorOptions = useMemo(
    () =>
      (assignableMentors ?? []).map((u) => ({
        value: String(u.id),
        label: `${u.name}${u.is_top_host_eligible ? ' ★' : ''}`,
        hint: u.is_assistant ? 'Live host assistant' : u.is_top_host_eligible ? 'Top-host eligible' : undefined,
        keywords: u.name,
        group: u.is_assistant ? 'Live Host Assistants' : 'Mentors',
        avatar: { initials: u.initials },
      })),
    [assignableMentors],
  );

  const submit = () => {
    setBusy(true);
    setErrors({});
    router.post(
      `/livehost/mentoring/programs/${program.id}/mentees`,
      { mentee_user_id: menteeId ? Number(menteeId) : null, mentor_user_id: mentorId ? Number(mentorId) : null },
      {
        preserveScroll: true,
        ...(reloadOnly ? { preserveState: true, only: reloadOnly } : {}),
        onSuccess: () => onClose(),
        onError: (e) => setErrors(e ?? {}),
        onFinish: () => setBusy(false),
      },
    );
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="w-full max-w-md rounded-[16px] bg-white p-6 shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div className="flex items-center gap-2 text-[18px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
            <UserPlus className="h-4 w-4 text-[#10B981]" strokeWidth={2.25} />
            Enrol a mentee
          </div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]" aria-label="Close">
            <X className="h-4 w-4" strokeWidth={2} />
          </button>
        </div>
        <p className="mb-4 text-[13px] text-[#737373]">
          Pick an existing live host to enrol into <span className="font-medium text-[#0A0A0A]">{program.title}</span>. Hosts already
          being mentored elsewhere are hidden.
        </p>

        <div className="space-y-4">
          <div>
            <label className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">Live host</label>
            {(enrollableHosts ?? []).length === 0 ? (
              <div className="rounded-lg border border-dashed border-[#EAEAEA] bg-[#FAFAFA] px-3 py-4 text-center text-[12.5px] text-[#A3A3A3]">
                No available hosts — everyone is already in a program.
              </div>
            ) : (
              <SearchableSelect
                value={menteeId}
                onChange={setMenteeId}
                options={hostOptions}
                placeholder="— Select a host —"
                searchPlaceholder="Search by name or email…"
                emptyLabel="No matching host"
              />
            )}
            {errors.mentee_user_id && <p className="mt-1 text-xs text-[#F43F5E]">{errors.mentee_user_id}</p>}
          </div>

          <div>
            <label className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">Mentor (optional)</label>
            <SearchableSelect
              value={mentorId}
              onChange={setMentorId}
              options={mentorOptions}
              placeholder="— Use program leader —"
              searchPlaceholder="Search mentors…"
              emptyLabel="No matching mentor"
              allowClear
            />
            {errors.mentor_user_id && <p className="mt-1 text-xs text-[#F43F5E]">{errors.mentor_user_id}</p>}
          </div>
        </div>

        <div className="mt-5 flex justify-end gap-2 border-t border-[#F0F0F0] pt-4">
          <Button type="button" variant="ghost" disabled={busy} onClick={onClose}>Cancel</Button>
          <Button type="button" disabled={busy || !menteeId} onClick={submit} className="bg-[#10B981] text-white hover:bg-[#059669] disabled:opacity-50">
            {busy ? 'Enrolling…' : 'Enrol mentee'}
          </Button>
        </div>
      </div>
    </div>
  );
}
