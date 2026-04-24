import { useState } from 'react';
import { router } from '@inertiajs/react';

export default function VerifyLinkPanel({ session, candidates }) {
  const [selectedId, setSelectedId] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  if (session.verificationStatus !== 'pending') {
    return null;
  }

  const canSubmit = selectedId !== null && session.verificationStatus === 'pending';

  const submit = () => {
    if (!canSubmit) {
      return;
    }
    setSubmitting(true);
    router.post(
      `/livehost/sessions/${session.id}/verify-link`,
      { actual_live_record_id: selectedId },
      {
        preserveScroll: true,
        onFinish: () => setSubmitting(false),
      },
    );
  };

  const reject = () => {
    if (!window.confirm('Reject this session? This marks it as no actual live happened.')) {
      return;
    }
    router.post(
      `/livehost/sessions/${session.id}/verify`,
      { verification_status: 'rejected' },
      { preserveScroll: true },
    );
  };

  if (!candidates || candidates.length === 0) {
    return (
      <div className="rounded-[16px] border border-amber-200 bg-amber-50 p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <p className="font-medium text-amber-900">No TikTok records found for this day + host.</p>
        <p className="mt-1 text-sm text-amber-800">
          Upload the CSV report or wait for API sync. Verification is blocked until a record is linked.
        </p>
        <button
          type="button"
          onClick={reject}
          className="mt-3 rounded-md border border-red-400 bg-white px-3 py-1.5 text-sm text-red-700 hover:bg-red-50"
        >
          Reject this session (no actual live happened)
        </button>
      </div>
    );
  }

  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-4">
        <h3 className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
          Link this session to TikTok actual record
        </h3>
        <p className="mt-1 text-sm text-[#737373]">
          Pick the TikTok live that matches this scheduled session. GMV will lock from the selected record.
        </p>
      </div>

      <div className="space-y-2">
        {candidates.map((c) => (
          <label
            key={c.id}
            className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors ${
              selectedId === c.id
                ? 'border-blue-400 bg-blue-50'
                : 'border-zinc-200 hover:bg-zinc-50'
            }`}
          >
            <input
              type="radio"
              name="candidate"
              checked={selectedId === c.id}
              onChange={() => setSelectedId(c.id)}
              className="mt-1"
            />
            <div className="flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium text-[#0A0A0A]">
                  {c.launchedTime
                    ? new Date(c.launchedTime).toLocaleString('en-MY', {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                      })
                    : '—'}
                </span>
                {c.isSuggested && (
                  <span className="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800">
                    Suggested
                  </span>
                )}
                <span className="rounded bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-600">
                  {c.source === 'csv_import' ? 'CSV' : 'API'}
                </span>
                {c.creatorHandle && (
                  <span className="text-xs text-[#737373]">@{c.creatorHandle}</span>
                )}
              </div>
              <div className="mt-2 grid grid-cols-3 gap-2 text-sm text-zinc-600">
                <div>
                  <span className="text-xs text-zinc-500">Live-attrib GMV</span>
                  <div className="font-mono font-semibold text-[#0A0A0A]">
                    RM {Number(c.liveAttributedGmvMyr ?? 0).toFixed(2)}
                  </div>
                </div>
                <div>
                  <span className="text-xs text-zinc-500">Total GMV</span>
                  <div className="font-mono">RM {Number(c.gmvMyr ?? 0).toFixed(2)}</div>
                </div>
                <div>
                  <span className="text-xs text-zinc-500">Viewers / Items</span>
                  <div>
                    {c.viewers ?? '—'} / {c.itemsSold ?? '—'}
                  </div>
                </div>
              </div>
            </div>
          </label>
        ))}
      </div>

      <div className="mt-4 flex gap-2 pt-2">
        <button
          type="button"
          disabled={!canSubmit || submitting}
          onClick={submit}
          className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {submitting ? 'Linking…' : 'Link & verify'}
        </button>
        <button
          type="button"
          onClick={reject}
          className="rounded-md border border-red-400 bg-white px-4 py-2 text-sm text-red-700 hover:bg-red-50"
        >
          Reject
        </button>
      </div>
    </div>
  );
}
