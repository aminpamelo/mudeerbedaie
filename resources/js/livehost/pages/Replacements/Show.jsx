import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowLeft, Check, X } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

const REASON_LABELS = {
  sick: 'Sakit',
  family: 'Kecemasan keluarga',
  personal: 'Urusan peribadi',
  other: 'Lain-lain',
};

const DAY_NAMES = [
  'Ahad',
  'Isnin',
  'Selasa',
  'Rabu',
  'Khamis',
  'Jumaat',
  'Sabtu',
];

function formatTime(value) {
  if (!value) {
    return '';
  }
  const match = String(value).match(/^(\d{1,2}):(\d{2})/);
  if (!match) {
    return String(value);
  }
  return `${match[1].padStart(2, '0')}:${match[2]}`;
}

function dayLabel(dayOfWeek) {
  if (dayOfWeek === null || dayOfWeek === undefined) {
    return '—';
  }
  return DAY_NAMES[Number(dayOfWeek)] ?? '—';
}

function formatDateTime(iso) {
  if (!iso) {
    return '—';
  }
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

function useNow(intervalMs = 30_000) {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), intervalMs);
    return () => clearInterval(id);
  }, [intervalMs]);
  return now;
}

function formatExpiresIn(expiresAt, now) {
  if (!expiresAt) {
    return '—';
  }
  const target = Date.parse(expiresAt);
  if (Number.isNaN(target)) {
    return '—';
  }
  const diffMs = target - now;
  if (diffMs <= 0) {
    return 'Tamat tempoh';
  }
  const totalMinutes = Math.floor(diffMs / 60_000);
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  if (hours > 0) {
    return `${hours}h ${minutes}m`;
  }
  return `${minutes}m`;
}

function statusBannerClasses(status) {
  switch (status) {
    case 'assigned':
      return 'border-[#A7F3D0] bg-[#ECFDF5] text-[#065F46]';
    case 'rejected':
      return 'border-[#FECACA] bg-[#FEF2F2] text-[#991B1B]';
    case 'expired':
    case 'withdrawn':
      return 'border-[#E5E5E5] bg-[#F5F5F5] text-[#525252]';
    default:
      return 'border-[#FDE68A] bg-[#FFFBEB] text-[#92400E]';
  }
}

export default function Show() {
  const { request: req, availableHosts } = usePage().props;
  const now = useNow();
  const isPending = req.status === 'pending';

  // Sorted ascending by priorReplacementsCount so the freshest hosts show first.
  const sortedHosts = [...(availableHosts ?? [])].sort(
    (a, b) =>
      Number(a.priorReplacementsCount ?? 0) -
      Number(b.priorReplacementsCount ?? 0)
  );

  const [selectedHostId, setSelectedHostId] = useState('');
  const [showRejectForm, setShowRejectForm] = useState(false);

  const assignForm = useForm({ replacement_host_id: '' });
  const rejectForm = useForm({ rejection_reason: '' });

  const handleAssign = (event) => {
    event.preventDefault();
    if (!selectedHostId) {
      return;
    }
    assignForm.transform((data) => ({
      ...data,
      replacement_host_id: Number(selectedHostId),
    }));
    assignForm.post(`/livehost/replacements/${req.id}/assign`, {
      preserveScroll: true,
    });
  };

  const handleReject = (event) => {
    event.preventDefault();
    rejectForm.post(`/livehost/replacements/${req.id}/reject`, {
      preserveScroll: true,
      onSuccess: () => setShowRejectForm(false),
    });
  };

  const slotLabel = `${dayLabel(req.slot?.dayOfWeek)} · ${formatTime(req.slot?.startTime)} – ${formatTime(req.slot?.endTime)}`;

  return (
    <>
      <Head title={`Permohonan ganti #${req.id}`} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Permohonan ganti', `#${req.id}`]}
        actions={
          <Link href="/livehost/replacements">
            <Button
              variant="ghost"
              className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
              Kembali
            </Button>
          </Link>
        }
      />

      <div className="space-y-6 p-8">
        {/* Status banner for non-pending */}
        {!isPending && (
          <div
            className={`rounded-[16px] border px-5 py-4 text-sm ${statusBannerClasses(req.status)}`}
          >
            {req.status === 'assigned' && (
              <div>
                <div className="font-semibold">Permohonan telah ditugaskan.</div>
                {req.replacementHost?.name && (
                  <div className="mt-1">
                    Pengganti:{' '}
                    <strong>{req.replacementHost.name}</strong>
                  </div>
                )}
              </div>
            )}
            {req.status === 'rejected' && (
              <div>
                <div className="font-semibold">Permohonan telah ditolak.</div>
                {req.rejectionReason && (
                  <div className="mt-1 italic">"{req.rejectionReason}"</div>
                )}
              </div>
            )}
            {req.status === 'expired' && (
              <div>
                <div className="font-semibold">Permohonan tamat tempoh.</div>
                <div className="mt-1">
                  Tiada tindakan diambil dalam tempoh yang ditetapkan.
                </div>
              </div>
            )}
            {req.status === 'withdrawn' && (
              <div>
                <div className="font-semibold">Permohonan ditarik balik oleh hos.</div>
              </div>
            )}
          </div>
        )}

        {/* Top section: request details */}
        <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="flex items-start justify-between gap-6">
            <div className="min-w-0">
              <div className="text-[11px] font-medium uppercase tracking-wide text-[#737373]">
                Permohonan #{req.id}
              </div>
              <div className="mt-1 text-2xl font-semibold tracking-[-0.02em] text-[#0A0A0A]">
                {req.originalHost?.name ?? '—'}
              </div>
              <div className="mt-1 text-[13px] text-[#737373]">
                {req.originalHost?.priorRequests90d ?? 0} permohonan ganti dalam 90
                hari lalu
              </div>
            </div>
            {isPending && (
              <div className="text-right">
                <div className="text-[11px] font-medium uppercase tracking-wide text-[#737373]">
                  Tamat dalam
                </div>
                <div className="mt-1 font-mono text-2xl font-semibold tabular-nums text-[#0A0A0A]">
                  {formatExpiresIn(req.expiresAt, now)}
                </div>
              </div>
            )}
          </div>

          <div className="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 border-t border-[#F0F0F0] pt-5 md:grid-cols-2">
            <DetailRow label="Slot" value={slotLabel} />
            <DetailRow
              label="Akaun platform"
              value={req.slot?.platformAccount ?? '—'}
            />
            <DetailRow
              label="Skop"
              value={
                req.scope === 'permanent' ? (
                  <span className="inline-flex items-center rounded-md bg-[#FDF2F8] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-[#9D174D] ring-1 ring-inset ring-[#FBCFE8]">
                    Permanent
                  </span>
                ) : (
                  <span className="text-[13px] text-[#0A0A0A]">
                    Satu tarikh · {req.targetDate ?? '—'}
                  </span>
                )
              }
            />
            <DetailRow
              label="Sebab"
              value={REASON_LABELS[req.reasonCategory] ?? req.reasonCategory ?? '—'}
            />
            <DetailRow
              label="Nota"
              value={
                req.reasonNote ? (
                  <span className="italic text-[#0A0A0A]">"{req.reasonNote}"</span>
                ) : (
                  <span className="text-[#737373]">—</span>
                )
              }
            />
            <DetailRow
              label="Dimohon pada"
              value={formatDateTime(req.requestedAt)}
            />
            <DetailRow
              label="Tamat pada"
              value={formatDateTime(req.expiresAt)}
            />
          </div>
        </section>

        {/* Middle section: available replacement hosts (pending only) */}
        {isPending && (
          <section className="rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            <header className="border-b border-[#F0F0F0] px-6 py-4">
              <h2 className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
                Senarai pengganti yang tersedia
              </h2>
              <p className="mt-0.5 text-[12.5px] text-[#737373]">
                Hos yang tidak mempunyai jadual lain pada hari + slot yang sama.
              </p>
            </header>

            {sortedHosts.length === 0 ? (
              <div className="px-6 py-12 text-center text-sm text-[#737373]">
                Tiada hos lain yang tersedia untuk slot ini.
              </div>
            ) : (
              <ul className="divide-y divide-[#F0F0F0]">
                {sortedHosts.map((host) => {
                  const checked = String(host.id) === String(selectedHostId);
                  return (
                    <li key={host.id}>
                      <label
                        className={[
                          'flex cursor-pointer items-center gap-4 px-6 py-4 transition-colors',
                          checked ? 'bg-[#ECFDF5]' : 'hover:bg-[#FAFAFA]',
                        ].join(' ')}
                      >
                        <input
                          type="radio"
                          name="replacement_host"
                          value={host.id}
                          checked={checked}
                          onChange={() => setSelectedHostId(String(host.id))}
                          className="h-4 w-4 accent-[#10B981]"
                        />
                        <div className="flex-1">
                          <div className="text-[14px] font-medium tracking-[-0.01em] text-[#0A0A0A]">
                            {host.name}
                          </div>
                          <div className="mt-0.5 text-[12px] text-[#737373]">
                            {host.priorReplacementsCount ?? 0} replacement
                            {(host.priorReplacementsCount ?? 0) === 1 ? '' : 's'}{' '}
                            done in last 90 days
                          </div>
                        </div>
                      </label>
                    </li>
                  );
                })}
              </ul>
            )}
          </section>
        )}

        {/* Footer actions (pending only) */}
        {isPending && (
          <section className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {/* Assign */}
            <form
              onSubmit={handleAssign}
              className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
            >
              <div className="mb-3">
                <h3 className="text-[14px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                  Tetapkan pengganti
                </h3>
                <p className="mt-0.5 text-[12px] text-[#737373]">
                  Pilih seorang hos di atas, kemudian klik tetapkan.
                </p>
              </div>
              {assignForm.errors.replacement_host_id && (
                <div className="mb-3 rounded-md bg-[#FEF2F2] px-3 py-2 text-[12px] text-[#991B1B]">
                  {assignForm.errors.replacement_host_id}
                </div>
              )}
              <Button
                type="submit"
                disabled={!selectedHostId || assignForm.processing}
                className="h-9 w-full gap-1.5 bg-[#10B981] text-white hover:bg-[#059669] disabled:opacity-50"
              >
                <Check className="h-3.5 w-3.5" strokeWidth={2.5} />
                {assignForm.processing ? 'Sedang ditetapkan…' : 'Tetapkan pengganti'}
              </Button>
            </form>

            {/* Reject */}
            <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
              <div className="mb-3">
                <h3 className="text-[14px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                  Tolak permohonan
                </h3>
                <p className="mt-0.5 text-[12px] text-[#737373]">
                  Berikan sebab supaya hos tahu kenapa permohonan ditolak.
                </p>
              </div>

              {!showRejectForm ? (
                <Button
                  type="button"
                  onClick={() => setShowRejectForm(true)}
                  className="h-9 w-full gap-1.5 border border-[#F43F5E] bg-transparent text-[#F43F5E] hover:bg-[#FFF1F2]"
                >
                  <X className="h-3.5 w-3.5" strokeWidth={2.5} />
                  Tolak permohonan
                </Button>
              ) : (
                <form onSubmit={handleReject} className="space-y-3">
                  <textarea
                    rows={3}
                    autoFocus
                    value={rejectForm.data.rejection_reason}
                    onChange={(event) =>
                      rejectForm.setData('rejection_reason', event.target.value)
                    }
                    placeholder="Sebab penolakan…"
                    className="w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#F43F5E]/20"
                  />
                  {rejectForm.errors.rejection_reason && (
                    <div className="rounded-md bg-[#FEF2F2] px-3 py-2 text-[12px] text-[#991B1B]">
                      {rejectForm.errors.rejection_reason}
                    </div>
                  )}
                  <div className="flex gap-2">
                    <Button
                      type="submit"
                      disabled={
                        !rejectForm.data.rejection_reason.trim() ||
                        rejectForm.processing
                      }
                      className="h-9 flex-1 bg-[#F43F5E] text-white hover:bg-[#E11D48] disabled:opacity-50"
                    >
                      {rejectForm.processing ? 'Sedang ditolak…' : 'Sahkan tolak'}
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      onClick={() => {
                        setShowRejectForm(false);
                        rejectForm.reset();
                        rejectForm.clearErrors();
                      }}
                      disabled={rejectForm.processing}
                      className="h-9 text-[#737373] hover:text-[#0A0A0A]"
                    >
                      Batal
                    </Button>
                  </div>
                </form>
              )}
            </div>
          </section>
        )}
      </div>
    </>
  );
}

Show.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function DetailRow({ label, value }) {
  return (
    <div>
      <div className="text-[11px] font-medium uppercase tracking-wide text-[#737373]">
        {label}
      </div>
      <div className="mt-1 text-[13px] text-[#0A0A0A]">{value}</div>
    </div>
  );
}
