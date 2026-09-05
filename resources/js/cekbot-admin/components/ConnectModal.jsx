import { useEffect, useRef, useState, useCallback } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { CheckCircle2, Loader2, AlertCircle, Phone, RefreshCw } from 'lucide-react';
import { Modal, Button, Field, Input } from '@/cekbot-admin/components/Ui';
import { csrfToken, formatPhone } from '@/cekbot-admin/lib/utils';

const POLL_MS = 3000;

export default function ConnectModal({ session, onClose, dashboardUrl }) {
  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState(null);
  const [qr, setQr] = useState(null);
  const [phone, setPhone] = useState(null);
  const [error, setError] = useState(null);

  const [showPairing, setShowPairing] = useState(false);
  const [pairPhone, setPairPhone] = useState('');
  const [pairCode, setPairCode] = useState(null);
  const [pairLoading, setPairLoading] = useState(false);
  const [pairError, setPairError] = useState(null);
  const [pairSent, setPairSent] = useState(false);

  const pollRef = useRef(null);
  const mounted = useRef(true);
  const closingRef = useRef(false);

  const postHeaders = { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' };

  const stopPolling = useCallback(() => {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
  }, []);

  const onLinked = useCallback(() => {
    if (closingRef.current) return;
    closingRef.current = true;
    stopPolling();
    setStatus('WORKING');
    setTimeout(() => {
      router.reload({ only: ['sessions'], preserveScroll: true });
      onClose();
    }, 1600);
  }, [onClose, stopPolling]);

  const apply = useCallback((data) => {
    if (!mounted.current) return;
    setStatus(data.status);
    if (data.qr) setQr(data.qr);
    if (data.phone) setPhone(data.phone);
    if (data.status === 'WORKING') onLinked();
  }, [onLinked]);

  const startPolling = useCallback(() => {
    stopPolling();
    pollRef.current = setInterval(async () => {
      try {
        const { data } = await axios.get(route('cekbot.sessions.status', session.id));
        if (data.ok) apply(data);
      } catch {
        /* transient — keep polling */
      }
    }, POLL_MS);
  }, [apply, session.id, stopPolling]);

  const connect = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await axios.post(route('cekbot.sessions.connect', session.id), {}, { headers: postHeaders });
      if (!data.ok) {
        setError(data.error || 'Gagal menyambung ke server WAHA.');
        return;
      }
      apply(data);
      if (data.status !== 'WORKING') startPolling();
    } catch (e) {
      setError(e.response?.data?.error || 'Server WAHA tidak dapat dihubungi.');
    } finally {
      if (mounted.current) setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session.id, apply, startPolling]);

  useEffect(() => {
    mounted.current = true;
    closingRef.current = false;
    connect();
    return () => {
      mounted.current = false;
      stopPolling();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session.id]);

  async function requestPairing(e) {
    e.preventDefault();
    setPairLoading(true);
    setPairError(null);
    setPairCode(null);
    setPairSent(false);
    try {
      const { data } = await axios.post(
        route('cekbot.sessions.pairing', session.id),
        { phone: pairPhone },
        { headers: postHeaders },
      );
      if (!data.ok) {
        setPairError(data.error || 'Gagal meminta kod.');
        return;
      }
      if (data.code) {
        setPairCode(data.code);
      } else {
        setPairSent(true);
      }
    } catch (err) {
      setPairError(err.response?.data?.error || 'Gagal meminta kod pairing.');
    } finally {
      setPairLoading(false);
    }
  }

  const linked = status === 'WORKING';

  return (
    <Modal
      open
      onClose={onClose}
      size="md"
      title={linked ? 'Berjaya disambung' : `Sambung ${session.label}`}
      hint={linked ? null : 'Imbas kod QR dengan aplikasi WhatsApp pada telefon nombor tersebut.'}
    >
      {error ? (
        <div className="space-y-4 py-4 text-center">
          <div className="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-rose-500/15">
            <AlertCircle className="h-6 w-6 text-rose-400" strokeWidth={2} />
          </div>
          <p className="text-[13.5px] text-white/70">{error}</p>
          <div className="flex flex-wrap items-center justify-center gap-2">
            <Button variant="secondary" onClick={connect}>
              <RefreshCw className="h-4 w-4" strokeWidth={2.2} /> Cuba lagi
            </Button>
            {dashboardUrl && (
              <Button variant="primary" href={dashboardUrl} target="_blank" rel="noopener">
                Buka Dashboard WAHA
              </Button>
            )}
          </div>
        </div>
      ) : linked ? (
        <div className="space-y-3 py-6 text-center">
          <div className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-500/15">
            <CheckCircle2 className="h-9 w-9 text-emerald-400" strokeWidth={2} />
          </div>
          <p className="text-[15px] font-bold text-white">WhatsApp berjaya dipautkan!</p>
          {phone && <p className="text-[13.5px] font-medium text-emerald-300">{formatPhone(phone)}</p>}
          <p className="text-[12.5px] text-white/40">Menyegarkan senarai…</p>
        </div>
      ) : (
        <div className="space-y-5">
          <div className="flex flex-col items-center gap-3">
            <div className="grid h-64 w-64 place-items-center rounded-2xl bg-white p-3">
              {qr ? (
                <img src={qr} alt="Kod QR WhatsApp" className="h-full w-full object-contain" />
              ) : (
                <div className="flex flex-col items-center gap-2 text-slate-400">
                  <Loader2 className="h-7 w-7 animate-spin" strokeWidth={2} />
                  <span className="text-[12px]">Menyediakan kod QR…</span>
                </div>
              )}
            </div>
            <div className="flex items-center gap-1.5 text-[12px] text-white/45">
              <Loader2 className="h-3.5 w-3.5 animate-spin" strokeWidth={2.2} />
              Menunggu imbasan…
            </div>
          </div>

          <ol className="space-y-1.5 rounded-xl bg-white/5 p-4 text-[12.5px] text-white/60">
            <li>1. Buka <span className="font-semibold text-white/80">WhatsApp</span> pada telefon.</li>
            <li>2. Tekan <span className="font-semibold text-white/80">Tetapan › Peranti Terpaut</span>.</li>
            <li>3. Tekan <span className="font-semibold text-white/80">Pautkan peranti</span> dan imbas kod di atas.</li>
          </ol>

          <div className="border-t border-white/8 pt-4">
            {!showPairing ? (
              <button
                type="button"
                onClick={() => setShowPairing(true)}
                className="flex items-center gap-2 text-[12.5px] font-semibold text-emerald-400 hover:text-emerald-300"
              >
                <Phone className="h-4 w-4" strokeWidth={2.2} />
                Tak boleh imbas? Guna kod telefon
              </button>
            ) : (
              <form onSubmit={requestPairing} className="space-y-3">
                <Field label="Nombor telefon (format antarabangsa)" hint="Cth: 60123456789 (tanpa +)" error={pairError}>
                  <div className="flex gap-2">
                    <Input
                      value={pairPhone}
                      onChange={(e) => setPairPhone(e.target.value)}
                      placeholder="60123456789"
                      inputMode="numeric"
                    />
                    <Button type="submit" variant="secondary" loading={pairLoading}>Dapatkan kod</Button>
                  </div>
                </Field>
                {pairCode && (
                  <div className="rounded-xl bg-emerald-500/10 p-3 text-center">
                    <p className="text-[11px] uppercase tracking-wide text-emerald-300/70">Kod pairing</p>
                    <p className="mt-1 font-mono text-[22px] font-bold tracking-[0.3em] text-emerald-300">{pairCode}</p>
                    <p className="mt-1 text-[11.5px] text-white/50">Masukkan kod ini di WhatsApp › Peranti Terpaut › Pautkan dengan nombor telefon.</p>
                  </div>
                )}
                {pairSent && !pairCode && (
                  <p className="text-[12px] text-white/50">Permintaan dihantar. Jika tiada kod dipaparkan, sila guna kaedah QR di atas.</p>
                )}
              </form>
            )}
          </div>
        </div>
      )}
    </Modal>
  );
}
