import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { ShieldCheck, Server, ChevronDown } from 'lucide-react';
import { Modal, Field, Input, Textarea, Button } from '@/cekbot-admin/components/Ui';
import { cn } from '@/cekbot-admin/lib/utils';

const CLOUD = 'cloud_api';
const WAHA = 'waha';

const BLANK = {
  label: '',
  notes: '',
  provider: WAHA,
  phone_number: '',
  phone_number_id: '',
  waba_id: '',
  access_token: '',
  app_secret: '',
  verify_token: '',
  api_version: '',
};

export default function AddNumberModal({ open, editing, onClose, defaultApiVersion = 'v21.0' }) {
  const isEdit = Boolean(editing);
  const form = useForm(BLANK);
  const [advanced, setAdvanced] = useState(false);

  useEffect(() => {
    if (!open) return;
    const seed = {
      ...BLANK,
      label: editing?.label ?? '',
      notes: editing?.notes ?? '',
      provider: editing?.provider ?? WAHA,
      phone_number: editing?.phone_number ?? '',
      phone_number_id: editing?.phone_number_id ?? '',
      waba_id: editing?.waba_id ?? '',
      verify_token: editing?.verify_token ?? '',
      api_version: editing?.api_version ?? '',
    };
    form.setDefaults(seed);
    form.setData(seed);
    form.clearErrors();
    setAdvanced(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, editing?.id]);

  const isCloud = form.data.provider === CLOUD;

  function submit(e) {
    e.preventDefault();
    const opts = { preserveScroll: true, onSuccess: onClose };
    if (isEdit) {
      form.put(route('cekbot.sessions.update', editing.id), opts);
    } else {
      form.post(route('cekbot.sessions.store'), opts);
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="md"
      title={isEdit ? 'Edit nombor' : 'Tambah nombor WhatsApp'}
      hint={
        isEdit
          ? 'Kemas kini butiran nombor ini.'
          : 'Pilih jenis sambungan, kemudian isi butiran nombor.'
      }
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button type="submit" form="cekbot-session-form" variant="primary" loading={form.processing}>
            {isEdit ? 'Simpan' : (isCloud ? 'Tambah & sahkan' : 'Tambah nombor')}
          </Button>
        </>
      }
    >
      <form id="cekbot-session-form" onSubmit={submit} className="space-y-4">
        {!isEdit && (
          <Field label="Jenis sambungan">
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <ProviderOption
                active={isCloud}
                onClick={() => form.setData('provider', CLOUD)}
                icon={ShieldCheck}
                title="WhatsApp Rasmi"
                subtitle="Cloud API oleh Meta · stabil, patuh dasar"
                recommended
              />
              <ProviderOption
                active={!isCloud}
                onClick={() => form.setData('provider', WAHA)}
                icon={Server}
                title="WAHA (tak rasmi)"
                subtitle="Layari QR · server sendiri"
              />
            </div>
          </Field>
        )}

        <Field label="Label" hint="Nama untuk kenal nombor ini, cth: CS Utama, Team Sales." error={form.errors.label}>
          <Input
            value={form.data.label}
            onChange={(e) => form.setData('label', e.target.value)}
            placeholder="Cth: CS Utama"
            autoFocus
          />
        </Field>

        {isCloud && (
          <div className="space-y-4 rounded-xl border border-emerald-500/15 bg-emerald-500/[0.04] p-4">
            <p className="flex items-center gap-1.5 text-[12px] font-semibold text-emerald-300/90">
              <ShieldCheck className="h-3.5 w-3.5" strokeWidth={2.2} /> Kredensial WhatsApp Cloud API
            </p>

            <Field
              label="Phone Number ID"
              hint="Dari Meta › WhatsApp › API Setup."
              error={form.errors.phone_number_id}
            >
              <Input
                value={form.data.phone_number_id}
                onChange={(e) => form.setData('phone_number_id', e.target.value)}
                placeholder="Cth: 123456789012345"
                inputMode="numeric"
              />
            </Field>

            <Field
              label={isEdit ? 'Access token (kosongkan untuk kekalkan)' : 'Access token'}
              hint="Token kekal (permanent) dari Meta. Disimpan tersulit."
              error={form.errors.access_token}
            >
              <Input
                type="password"
                value={form.data.access_token}
                onChange={(e) => form.setData('access_token', e.target.value)}
                placeholder={isEdit ? '•••••••• (kekal)' : 'EAAG…'}
                autoComplete="new-password"
              />
            </Field>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Field label="Nombor paparan (pilihan)" hint="Cth: 60123456789" error={form.errors.phone_number}>
                <Input
                  value={form.data.phone_number}
                  onChange={(e) => form.setData('phone_number', e.target.value)}
                  placeholder="60123456789"
                  inputMode="numeric"
                />
              </Field>
              <Field label="WABA ID (pilihan)" error={form.errors.waba_id}>
                <Input
                  value={form.data.waba_id}
                  onChange={(e) => form.setData('waba_id', e.target.value)}
                  placeholder="WhatsApp Business Account ID"
                  inputMode="numeric"
                />
              </Field>
            </div>

            <button
              type="button"
              onClick={() => setAdvanced((v) => !v)}
              className="flex items-center gap-1.5 text-[12px] font-semibold text-white/50 hover:text-white/80"
            >
              <ChevronDown className={cn('h-4 w-4 transition-transform', advanced && 'rotate-180')} strokeWidth={2.2} />
              Tetapan lanjutan
            </button>

            {advanced && (
              <div className="space-y-4 border-t border-white/8 pt-4">
                <Field
                  label="App secret (pilihan)"
                  hint="Untuk sahkan webhook. Boleh dikongsi dari Tetapan Meta global jika kosong."
                  error={form.errors.app_secret}
                >
                  <Input
                    type="password"
                    value={form.data.app_secret}
                    onChange={(e) => form.setData('app_secret', e.target.value)}
                    placeholder={editing?.has_app_secret ? '•••••••• (kekal)' : 'App secret'}
                    autoComplete="new-password"
                  />
                </Field>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Field label="Verify token (pilihan)" hint="Auto-jana jika kosong." error={form.errors.verify_token}>
                    <Input
                      value={form.data.verify_token}
                      onChange={(e) => form.setData('verify_token', e.target.value)}
                      placeholder="Auto-jana"
                    />
                  </Field>
                  <Field label="Versi API (pilihan)" error={form.errors.api_version}>
                    <Input
                      value={form.data.api_version}
                      onChange={(e) => form.setData('api_version', e.target.value)}
                      placeholder={defaultApiVersion}
                    />
                  </Field>
                </div>
              </div>
            )}
          </div>
        )}

        <Field label="Nota (pilihan)" error={form.errors.notes}>
          <Textarea
            rows={2}
            value={form.data.notes}
            onChange={(e) => form.setData('notes', e.target.value)}
            placeholder="Nota ringkas untuk rujukan pasukan (pilihan)"
          />
        </Field>
      </form>
    </Modal>
  );
}

function ProviderOption({ active, onClick, icon: Icon, title, subtitle, recommended }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'relative flex items-start gap-3 rounded-xl border p-3 text-left transition-all',
        active
          ? 'border-emerald-400/60 bg-emerald-500/10 ring-1 ring-emerald-400/30'
          : 'border-white/10 bg-white/5 hover:bg-white/8',
      )}
    >
      <span className={cn('mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg', active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-white/8 text-white/50')}>
        <Icon className="h-4 w-4" strokeWidth={2.2} />
      </span>
      <span className="min-w-0">
        <span className="flex items-center gap-1.5 text-[13px] font-bold text-white">
          {title}
          {recommended && <span className="rounded-full bg-emerald-500/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-emerald-300">Disyor</span>}
        </span>
        <span className="mt-0.5 block text-[11.5px] leading-snug text-white/45">{subtitle}</span>
      </span>
    </button>
  );
}
