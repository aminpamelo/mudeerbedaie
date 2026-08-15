import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Loader2, RefreshCw } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';

/**
 * "Sync TikTok" button + date-range popover for the Session Slots calendar.
 *
 * Pulls fresh TikTok LIVE performance data for the chosen dates ONLY. It never
 * verifies, links or locks any session — verification stays a manual desk step.
 */
export default function SyncTikTokButton({
  defaultFrom,
  defaultUntil,
  platformAccounts = [],
  currentShopId = '',
}) {
  const [open, setOpen] = useState(false);
  const [from, setFrom] = useState(defaultFrom ?? '');
  const [until, setUntil] = useState(defaultUntil ?? '');
  const [shopId, setShopId] = useState(currentShopId ? String(currentShopId) : '');
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState('');
  const panelRef = useRef(null);

  // Keep the range in step with whatever week the calendar is showing until the
  // user overrides it, then respect their choice.
  useEffect(() => {
    if (!open) {
      setFrom(defaultFrom ?? '');
      setUntil(defaultUntil ?? '');
      setShopId(currentShopId ? String(currentShopId) : '');
      setError('');
    }
  }, [defaultFrom, defaultUntil, currentShopId, open]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }
    const onClickOutside = (e) => {
      if (panelRef.current && !panelRef.current.contains(e.target)) {
        setOpen(false);
      }
    };
    const onEsc = (e) => {
      if (e.key === 'Escape') {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', onClickOutside);
    document.addEventListener('keydown', onEsc);
    return () => {
      document.removeEventListener('mousedown', onClickOutside);
      document.removeEventListener('keydown', onEsc);
    };
  }, [open]);

  const submit = () => {
    if (!from || !until || processing) {
      return;
    }
    router.post(
      '/livehost/session-slots/sync-tiktok',
      {
        from,
        until,
        platform_account_id: shopId || null,
      },
      {
        preserveScroll: true,
        onStart: () => {
          setProcessing(true);
          setError('');
        },
        onSuccess: () => setOpen(false),
        onError: (errs) => setError(errs?.until || errs?.from || 'Sync gagal — cuba lagi.'),
        onFinish: () => setProcessing(false),
      },
    );
  };

  return (
    <div className="relative" ref={panelRef}>
      <Button
        size="sm"
        variant="outline"
        onClick={() => setOpen((v) => !v)}
        className="h-9 gap-1.5 rounded-lg border-[#EAEAEA] text-[#0A0A0A] hover:bg-[#F5F5F5]"
      >
        <RefreshCw className="h-[13px] w-[13px]" strokeWidth={2.2} />
        Sync TikTok
      </Button>

      {open && (
        <div className="absolute right-0 top-full z-50 mt-2 w-[300px] rounded-xl border border-[#EAEAEA] bg-white p-4 shadow-xl">
          <div className="mb-1 text-[13px] font-semibold text-[#0A0A0A]">
            Sync data TikTok
          </div>
          <p className="mb-3 text-[11.5px] leading-snug text-[#737373]">
            Tarik data live TikTok untuk tarikh dipilih sahaja. Ini{' '}
            <span className="font-medium text-[#525252]">tidak</span> verify apa-apa sesi —
            verify tetap manual.
          </p>

          <div className="grid grid-cols-2 gap-2">
            <label className="flex flex-col gap-1">
              <span className="text-[10.5px] font-medium uppercase tracking-wide text-[#A3A3A3]">
                Dari
              </span>
              <input
                type="date"
                value={from}
                max={until || undefined}
                onChange={(e) => setFrom(e.target.value)}
                className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              />
            </label>
            <label className="flex flex-col gap-1">
              <span className="text-[10.5px] font-medium uppercase tracking-wide text-[#A3A3A3]">
                Hingga
              </span>
              <input
                type="date"
                value={until}
                min={from || undefined}
                onChange={(e) => setUntil(e.target.value)}
                className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              />
            </label>
          </div>

          <label className="mt-2 flex flex-col gap-1">
            <span className="text-[10.5px] font-medium uppercase tracking-wide text-[#A3A3A3]">
              Shop
            </span>
            <select
              value={shopId}
              onChange={(e) => setShopId(e.target.value)}
              className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            >
              <option value="">Semua shop TikTok</option>
              {platformAccounts.map((pa) => (
                <option key={pa.id} value={pa.id}>
                  {pa.name}
                </option>
              ))}
            </select>
          </label>

          {error && (
            <p className="mt-2 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-2.5 py-1.5 text-[11.5px] text-[#991B1B]">
              {error}
            </p>
          )}

          <div className="mt-4 flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() => setOpen(false)}
              className="rounded-lg px-3 py-1.5 text-[13px] font-medium text-[#737373] transition-colors hover:text-[#0A0A0A]"
            >
              Batal
            </button>
            <Button
              size="sm"
              onClick={submit}
              disabled={!from || !until || processing}
              className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]"
            >
              {processing ? (
                <Loader2 className="h-[13px] w-[13px] animate-spin" strokeWidth={2.2} />
              ) : (
                <RefreshCw className="h-[13px] w-[13px]" strokeWidth={2.2} />
              )}
              {processing ? 'Menyync…' : 'Sync sekarang'}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
