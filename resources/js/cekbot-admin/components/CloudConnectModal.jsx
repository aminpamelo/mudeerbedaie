import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { CheckCircle2, Loader2, AlertCircle, RefreshCw, Copy, Check, ShieldCheck } from 'lucide-react';
import { Modal, Button } from '@/cekbot-admin/components/Ui';
import { csrfToken, formatPhone } from '@/cekbot-admin/lib/utils';

/**
 * Connect flow for an official WhatsApp Cloud API number: there is no QR — we
 * validate the stored credentials against Meta's Graph API and surface the
 * webhook callback URL + verify token the admin must paste into their Meta App.
 */
export default function CloudConnectModal({ session, cloud, onClose }) {
  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState(session.status ?? null);
  const [message, setMessage] = useState(null);
  const mounted = useRef(true);

  const postHeaders = { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' };

  const verify = useCallback(async () => {
    setLoading(true);
    setMessage(null);
    try {
      const { data } = await axios.post(route('cekbot.sessions.connect', session.id), {}, { headers: postHeaders });
      if (!mounted.current) return;
      if (!data.ok) {
        setStatus('FAILED');
        setMessage(data.error || 'Gagal menghubungi Meta Cloud API.');
        return;
      }
      setStatus(data.status);
      setMessage(data.message || null);
      if (data.status === 'WORKING') {
        router.reload({ only: ['sessions'], preserveScroll: true });
      }
    } catch (e) {
      if (mounted.current) {
        setStatus('FAILED');
        setMessage(e.response?.data?.error || 'Ralat rangkaian semasa menyemak kredensial.');
      }
    } finally {
      if (mounted.current) setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session.id]);

  useEffect(() => {
    mounted.current = true;
    verify();
    return () => { mounted.current = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session.id]);

  const connected = status === 'WORKING';

  return (
    <Modal
      open
      onClose={onClose}
      size="md"
      title={`Sambung ${session.label}`}
      hint="WhatsApp Rasmi (Cloud API) — tiada QR. Kami sahkan kredensial dengan Meta."
    >
      <div className="space-y-5">
        {/* Status card */}
        <div className={cardCls(loading ? 'pending' : connected ? 'ok' : 'fail')}>
          {loading ? (
            <>
              <Loader2 className="h-5 w-5 shrink-0 animate-spin text-white/60" strokeWidth={2.2} />
              <div className="text-[13px] text-white/70">Menyemak kredensial dengan Meta…</div>
            </>
          ) : connected ? (
            <>
              <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-400" strokeWidth={2.2} />
              <div>
                <p className="text-[13.5px] font-bold text-white">Kredensial sah — nombor bersedia.</p>
                {session.phone_number && (
                  <p className="mt-0.5 text-[12.5px] font-medium text-emerald-300">{formatPhone(session.phone_number)}</p>
                )}
              </div>
            </>
          ) : (
            <>
              <AlertCircle className="h-5 w-5 shrink-0 text-rose-400" strokeWidth={2.2} />
              <div>
                <p className="text-[13.5px] font-bold text-white">Belum bersambung.</p>
                {message && <p className="mt-0.5 text-[12px] text-rose-200/80">{message}</p>}
              </div>
            </>
          )}
        </div>

        {/* Webhook setup */}
        <div className="space-y-3 rounded-xl border border-white/8 bg-white/5 p-4">
          <p className="flex items-center gap-1.5 text-[12.5px] font-semibold text-white/80">
            <ShieldCheck className="h-4 w-4 text-emerald-400" strokeWidth={2.2} /> Sambungan webhook Meta
          </p>
          <p className="text-[12px] leading-relaxed text-white/50">
            Di Meta App › WhatsApp › Configuration, tetapkan <span className="font-semibold text-white/70">Callback URL</span> dan{' '}
            <span className="font-semibold text-white/70">Verify token</span> di bawah, kemudian langgan (subscribe) medan <span className="font-mono text-white/70">messages</span>.
          </p>

          <CopyRow label="Callback URL" value={cloud?.webhookUrl} />
          {session.verify_token && <CopyRow label="Verify token" value={session.verify_token} />}
        </div>

        <div className="flex items-center justify-between gap-2">
          <p className="text-[11.5px] text-white/40">Kemas kini kredensial melalui butang Edit pada kad nombor.</p>
          <Button variant="secondary" onClick={verify} loading={loading}>
            <RefreshCw className="h-4 w-4" strokeWidth={2.2} /> Semak semula
          </Button>
        </div>
      </div>
    </Modal>
  );
}

function cardCls(kind) {
  const base = 'flex items-center gap-3 rounded-xl border p-4';
  const tones = {
    pending: 'border-white/10 bg-white/5',
    ok: 'border-emerald-500/20 bg-emerald-500/10',
    fail: 'border-rose-500/20 bg-rose-500/10',
  };
  return `${base} ${tones[kind]}`;
}

function CopyRow({ label, value }) {
  const [copied, setCopied] = useState(false);

  function copy() {
    if (!value) return;
    navigator.clipboard?.writeText(value).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    });
  }

  return (
    <div>
      <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-white/40">{label}</p>
      <div className="flex items-center gap-2">
        <code className="min-w-0 flex-1 truncate rounded-lg bg-black/30 px-3 py-2 font-mono text-[12px] text-emerald-200/90 ring-1 ring-inset ring-white/10">
          {value || '—'}
        </code>
        <button
          type="button"
          onClick={copy}
          className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/8 text-white/60 ring-1 ring-inset ring-white/10 transition-colors hover:bg-white/12 hover:text-white"
          aria-label={`Salin ${label}`}
        >
          {copied ? <Check className="h-4 w-4 text-emerald-400" strokeWidth={2.4} /> : <Copy className="h-4 w-4" strokeWidth={2.2} />}
        </button>
      </div>
    </div>
  );
}
