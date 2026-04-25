import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';

/**
 * Schedule — weekly read-only roster.
 *
 * Props from {@link \App\Http\Controllers\LiveHostPocket\ScheduleController::index}:
 *   - `days` — array of 7 day buckets (Sunday-first) with nested `schedules`.
 *   - `totalSlots` — total active slots assigned to this host.
 *
 * Each slot may carry a `replacementRequest` payload describing an active
 * (pending/assigned) replacement request. That drives the per-card UI state:
 * "Mohon ganti" (default), "Menunggu PIC" + withdraw (pending), "Telah
 * diganti" (assigned one_date). Assigned permanent slots are hidden from the
 * list because assignment ownership has transferred away.
 */
export default function Schedule() {
  const { days, totalSlots } = usePage().props;
  const buckets = Array.isArray(days) ? days : [];
  const total = Number.isFinite(totalSlots) ? totalSlots : 0;

  return (
    <>
      <Head title="Schedule" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <div className="px-1 pt-3 pb-4">
          <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Weekly roster
          </div>
          <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">
            Your schedule
          </h1>
          <div className="mt-2 font-mono text-[11px] tracking-[0.02em] text-[var(--fg-2)]">
            {total} {total === 1 ? 'slot' : 'slots'} assigned
          </div>
        </div>

        <div>
          {buckets.map((bucket) => (
            <DayBucket key={bucket.dayOfWeek} bucket={bucket} />
          ))}
        </div>

        <PicCallout />
      </div>
    </>
  );
}

Schedule.layout = (page) => <PocketLayout>{page}</PocketLayout>;

function DayBucket({ bucket }) {
  const hasSchedules = (bucket.schedules?.length ?? 0) > 0;

  return (
    <section className="mb-4">
      <div className="mb-2 flex items-center gap-2 px-1">
        <span className="font-display text-[13px] font-medium tracking-[-0.015em] text-[var(--fg)]">
          {bucket.dayName}
        </span>
        <span
          className="inline-flex items-center rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]"
          style={{ backgroundColor: 'var(--hair)' }}
        >
          {bucket.dayShort}
        </span>
      </div>

      {hasSchedules ? (
        <div>
          {bucket.schedules.map((slot) => (
            <SlotCard key={slot.id} slot={slot} dayName={bucket.dayName} />
          ))}
        </div>
      ) : (
        <div className="rounded-[12px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-3 text-center font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          No slots
        </div>
      )}
    </section>
  );
}

function SlotCard({ slot, dayName }) {
  const [modalOpen, setModalOpen] = useState(false);
  const withdraw = useForm({});

  const request = slot.replacementRequest;

  // Assigned permanent: the slot has transferred away. Hide for snappy UX.
  if (request && request.status === 'assigned' && request.scope === 'permanent') {
    return null;
  }

  const range = `${slot.startTime} – ${slot.endTime}`;
  const platform = slot.platformAccount ?? slot.platformType ?? 'Platform';

  const dotColor =
    slot.platformType === 'tiktok'
      ? 'var(--fg-1)'
      : slot.platformType === 'facebook'
        ? 'var(--cool)'
        : 'var(--hot)';

  const isPending = request && request.status === 'pending';
  const isAssignedOneDate =
    request && request.status === 'assigned' && request.scope === 'one_date';

  const handleWithdraw = () => {
    if (!request) return;
    const ok = window.confirm('Tarik balik permohonan ganti slot ini?');
    if (!ok) return;
    withdraw.delete(`/live-host/replacement-requests/${request.id}`, {
      preserveScroll: true,
    });
  };

  return (
    <>
      <div className="mb-[6px] rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-[10px]">
        <div className="flex items-center justify-between gap-2">
          <span className="inline-flex items-center gap-[5px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
            <span
              className="h-1 w-1"
              style={{ backgroundColor: dotColor }}
              aria-hidden="true"
            />
            {platform}
          </span>
          <div className="flex items-center gap-[5px]">
            {slot.isRecurring ? (
              <span
                className="inline-flex items-center rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]"
                style={{ backgroundColor: 'var(--hair)' }}
              >
                WEEKLY
              </span>
            ) : null}
            {isPending ? (
              <span
                className="inline-flex items-center rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-white"
                style={{ backgroundColor: 'var(--hot)' }}
              >
                MENUNGGU PIC
              </span>
            ) : null}
            {isAssignedOneDate ? (
              <span
                className="inline-flex items-center rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-white"
                style={{ backgroundColor: 'var(--cool)' }}
              >
                TELAH DIGANTI
              </span>
            ) : null}
          </div>
        </div>
        <div className="mt-1 font-mono text-[13px] font-bold tabular-nums text-[var(--fg)]">
          {range}
        </div>

        {slot.remarks ? (
          <div className="mt-[6px] text-[11px] leading-snug text-[var(--fg-2)]">
            {slot.remarks}
          </div>
        ) : null}

        {isAssignedOneDate && request.replacementHostName ? (
          <div className="mt-[6px] text-[11px] leading-snug text-[var(--fg-2)]">
            Pengganti:{' '}
            <span className="font-medium text-[var(--fg)]">
              {request.replacementHostName}
            </span>
            {request.targetDate ? (
              <span className="text-[var(--fg-3)]"> ({request.targetDate})</span>
            ) : null}
          </div>
        ) : null}

        {isPending ? (
          <div className="mt-[8px] flex min-h-[36px] items-center">
            <button
              type="button"
              onClick={handleWithdraw}
              disabled={withdraw.processing}
              className="inline-flex h-[28px] items-center font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--hot)] transition active:opacity-60 disabled:opacity-50"
            >
              Tarik balik
            </button>
          </div>
        ) : null}

        {!request ? (
          <div className="mt-[8px] flex min-h-[36px] items-center border-t border-[var(--hair)] pt-[8px]">
            <button
              type="button"
              onClick={() => setModalOpen(true)}
              className="inline-flex h-[28px] items-center font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)] transition active:opacity-60 hover:text-[var(--fg)]"
            >
              Mohon ganti
            </button>
          </div>
        ) : null}
      </div>

      {modalOpen ? (
        <RequestModal
          slot={slot}
          dayName={dayName}
          onClose={() => setModalOpen(false)}
        />
      ) : null}
    </>
  );
}

function PicCallout() {
  return (
    <div className="mt-2 rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-3 text-center text-[11px] leading-snug text-[var(--fg-2)]">
      To claim or release slots, ask your PIC.
    </div>
  );
}

const REASON_OPTIONS = [
  { value: 'sick', label: 'Sakit / Sick' },
  { value: 'family', label: 'Kecemasan keluarga / Family emergency' },
  { value: 'personal', label: 'Urusan peribadi / Personal' },
  { value: 'other', label: 'Lain-lain / Other' },
];

function todayIso() {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

/**
 * RequestModal — host submits a replacement request for a given slot.
 *
 * Posts to `/live-host/replacement-requests` (named
 * `live-host.replacement-requests.store`). Uses literal URLs because this
 * pocket app does not expose Ziggy's `route()` helper on the JS side (other
 * pocket pages use literal URL strings too).
 */
function RequestModal({ slot, dayName, onClose }) {
  const form = useForm({
    live_schedule_assignment_id: slot.id,
    scope: 'one_date',
    target_date: '',
    reason_category: 'sick',
    reason_note: '',
  });

  const timeRange = `${slot.startTime} – ${slot.endTime}`;

  // Lock body scroll while modal is open.
  useEffect(() => {
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = prev;
    };
  }, []);

  // Close on Escape key.
  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  const submit = (e) => {
    e.preventDefault();
    const payload = { ...form.data };
    if (payload.scope !== 'one_date') {
      payload.target_date = '';
    }
    form.transform(() => payload);
    form.post('/live-host/replacement-requests', {
      preserveScroll: true,
      onSuccess: () => onClose(),
    });
  };

  const errors = form.errors ?? {};

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 px-4 pb-4 pt-16 sm:items-center sm:pt-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="replacement-modal-title"
      onClick={onClose}
    >
      <div
        className="w-full max-w-[440px] overflow-hidden rounded-[18px] border border-[var(--hair)] bg-[var(--app-bg)] shadow-xl"
        onClick={(e) => e.stopPropagation()}
      >
        <form onSubmit={submit}>
          <div className="border-b border-[var(--hair)] px-4 pt-4 pb-3">
            <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
              {dayName} · {timeRange}
            </div>
            <h2
              id="replacement-modal-title"
              className="font-display text-[18px] font-medium leading-[1.1] tracking-[-0.02em] text-[var(--fg)]"
            >
              Mohon Ganti Slot
            </h2>
          </div>

          <div className="max-h-[60vh] overflow-y-auto px-4 py-4">
            {/* Scope */}
            <fieldset className="mb-4">
              <legend className="mb-2 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
                Jenis gantian
              </legend>
              <div className="flex flex-col gap-2">
                <label className="flex min-h-[36px] cursor-pointer items-center gap-[10px] rounded-[10px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-2">
                  <input
                    type="radio"
                    name="scope"
                    value="one_date"
                    checked={form.data.scope === 'one_date'}
                    onChange={() => form.setData('scope', 'one_date')}
                    className="h-4 w-4"
                  />
                  <span className="text-[13px] text-[var(--fg)]">
                    Tarikh ini sahaja
                  </span>
                </label>
                <label className="flex min-h-[36px] cursor-pointer items-center gap-[10px] rounded-[10px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-2">
                  <input
                    type="radio"
                    name="scope"
                    value="permanent"
                    checked={form.data.scope === 'permanent'}
                    onChange={() => form.setData('scope', 'permanent')}
                    className="h-4 w-4"
                  />
                  <span className="text-[13px] text-[var(--fg)]">
                    Secara kekal (lepaskan slot ini)
                  </span>
                </label>
              </div>
            </fieldset>

            {/* Target date (one_date only) */}
            {form.data.scope === 'one_date' ? (
              <div className="mb-4">
                <label
                  htmlFor="rr-target-date"
                  className="mb-1 block font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]"
                >
                  Tarikh
                </label>
                <input
                  id="rr-target-date"
                  type="date"
                  min={todayIso()}
                  value={form.data.target_date}
                  onChange={(e) => form.setData('target_date', e.target.value)}
                  className="h-[36px] w-full rounded-[10px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 text-[13px] text-[var(--fg)]"
                />
                <p className="mt-1 text-[11px] leading-snug text-[var(--fg-3)]">
                  Hanya tarikh pada hari {dayName} sahaja. Pengesahan sebenar
                  dibuat di pelayan.
                </p>
                {errors.target_date ? (
                  <p className="mt-1 text-[11px] text-[var(--hot)]">
                    {errors.target_date}
                  </p>
                ) : null}
              </div>
            ) : null}

            {/* Reason category */}
            <div className="mb-4">
              <label
                htmlFor="rr-reason"
                className="mb-1 block font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]"
              >
                Sebab
              </label>
              <select
                id="rr-reason"
                value={form.data.reason_category}
                onChange={(e) => form.setData('reason_category', e.target.value)}
                className="h-[36px] w-full rounded-[10px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 text-[13px] text-[var(--fg)]"
              >
                {REASON_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
              {errors.reason_category ? (
                <p className="mt-1 text-[11px] text-[var(--hot)]">
                  {errors.reason_category}
                </p>
              ) : null}
            </div>

            {/* Reason note */}
            <div className="mb-4">
              <label
                htmlFor="rr-note"
                className="mb-1 block font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]"
              >
                Catatan (pilihan)
              </label>
              <textarea
                id="rr-note"
                maxLength={500}
                rows={3}
                value={form.data.reason_note}
                onChange={(e) => form.setData('reason_note', e.target.value)}
                placeholder="Catatan tambahan (pilihan)"
                className="w-full rounded-[10px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-2 text-[13px] leading-snug text-[var(--fg)]"
              />
              {errors.reason_note ? (
                <p className="mt-1 text-[11px] text-[var(--hot)]">
                  {errors.reason_note}
                </p>
              ) : null}
            </div>

            {/* Commission warning */}
            <div
              className="mb-2 rounded-[10px] border border-[var(--hair)] px-3 py-2 text-[11px] leading-snug text-[var(--fg-2)]"
              style={{ backgroundColor: 'var(--hair)' }}
            >
              Komisen untuk slot ini akan diberikan kepada pengganti, bukan
              anda.
            </div>

            {errors.live_schedule_assignment_id ? (
              <p className="mt-1 text-[11px] text-[var(--hot)]">
                {errors.live_schedule_assignment_id}
              </p>
            ) : null}
          </div>

          <div className="flex items-center justify-end gap-[10px] border-t border-[var(--hair)] px-4 py-3">
            <button
              type="button"
              onClick={onClose}
              disabled={form.processing}
              className="inline-flex h-[36px] items-center rounded-[10px] border border-[var(--hair)] px-4 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)] transition active:opacity-60 disabled:opacity-50"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={form.processing}
              className="inline-flex h-[36px] items-center rounded-[10px] bg-[var(--fg)] px-4 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--app-bg)] transition active:opacity-80 disabled:opacity-50"
            >
              {form.processing ? 'Menghantar…' : 'Hantar permohonan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
