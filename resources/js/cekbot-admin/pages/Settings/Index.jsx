import { Head, useForm, usePage } from '@inertiajs/react';
import { Server, CheckCircle2, XCircle, Copy } from 'lucide-react';
import toast from 'react-hot-toast';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Badge, Field, Input } from '@/cekbot-admin/components/Ui';

export default function Index() {
  const { props } = usePage();
  const status = props.status ?? {};
  const webhookUrl = props.webhookUrl;

  const form = useForm({
    waha_url: props.config?.waha_url ?? '',
    waha_key: props.config?.waha_key ?? '',
    webhook_secret: props.config?.webhook_secret ?? '',
  });

  function save(e) {
    e.preventDefault();
    form.put(route('cekbot.settings.update'), { preserveScroll: true });
  }

  function useLocalPreset() {
    form.setData({ ...form.data, waha_url: 'http://localhost:3000', waha_key: '' });
  }

  function copyWebhook() {
    navigator.clipboard?.writeText(webhookUrl);
    toast.success('URL webhook disalin.');
  }

  const reachable = status.reachable;

  return (
    <CekbotLayout title="Tetapan" subtitle="Sambungan server WAHA untuk Cekbot">
      <Head title="Tetapan" />

      <div className="grid gap-5 lg:grid-cols-2">
        <form onSubmit={save}>
          <Card className="p-5">
            <div className="mb-4 flex items-center gap-2">
              <span className="grid h-9 w-9 place-items-center rounded-xl bg-emerald-500/15"><Server className="h-4 w-4 text-emerald-400" /></span>
              <div>
                <h3 className="text-[14px] font-bold text-white">Server WAHA</h3>
                <p className="text-[12px] text-white/40">Guna local (Docker) untuk uji, tukar ke production bila sedia.</p>
              </div>
            </div>

            <div className="space-y-4">
              <Field label="URL server WAHA" error={form.errors.waha_url}>
                <Input value={form.data.waha_url} onChange={(e) => form.setData('waha_url', e.target.value)} placeholder="http://localhost:3000" />
              </Field>
              <Field label="API Key" hint="Kosongkan jika WAHA_NO_API_KEY=True (local)." error={form.errors.waha_key}>
                <Input value={form.data.waha_key} onChange={(e) => form.setData('waha_key', e.target.value)} placeholder="(kosong untuk local)" />
              </Field>
              <Field label="Webhook Secret (HMAC)" hint="Pilihan. Mesti sama dengan WHATSAPP_HOOK_HMAC_KEY di server." error={form.errors.webhook_secret}>
                <Input value={form.data.webhook_secret} onChange={(e) => form.setData('webhook_secret', e.target.value)} placeholder="(pilihan)" />
              </Field>

              <div className="flex items-center justify-between gap-2">
                <button type="button" onClick={useLocalPreset} className="text-[12.5px] font-semibold text-emerald-400 hover:text-emerald-300">
                  Guna Local Docker (localhost:3000)
                </button>
                <Button type="submit" variant="primary" loading={form.processing}>Simpan &amp; sambung</Button>
              </div>
            </div>
          </Card>
        </form>

        <div className="space-y-5">
          <Card className="p-5">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-[14px] font-bold text-white">Status sambungan</h3>
              {status.configured ? (
                <Badge color={reachable ? 'emerald' : 'red'}>
                  {reachable ? <CheckCircle2 className="h-3 w-3" /> : <XCircle className="h-3 w-3" />}
                  {reachable ? 'Bersambung' : 'Tak dapat dihubungi'}
                </Badge>
              ) : <Badge color="slate">Belum ditetapkan</Badge>}
            </div>
            <dl className="space-y-2 text-[13px]">
              <div className="flex justify-between"><dt className="text-white/45">Server</dt><dd className="text-white/80">{status.serverUrl || '—'}</dd></div>
              <div className="flex justify-between"><dt className="text-white/45">Versi</dt><dd className="text-white/80">{status.version || '—'}</dd></div>
              <div className="flex justify-between"><dt className="text-white/45">Tier</dt><dd className="text-white/80">{status.tier || '—'}</dd></div>
              <div className="flex justify-between"><dt className="text-white/45">Engine</dt><dd className="text-white/80">{status.engine || '—'}</dd></div>
              <div className="flex justify-between"><dt className="text-white/45">Sesi</dt><dd className="text-white/80">{status.sessions ?? 0}</dd></div>
            </dl>
          </Card>

          <Card className="p-5">
            <h3 className="mb-1.5 text-[14px] font-bold text-white">URL Webhook</h3>
            <p className="mb-3 text-[12px] text-white/45">Set di server WAHA (env <span className="font-mono text-white/60">WHATSAPP_HOOK_URL</span>) supaya mesej masuk sampai ke sini.</p>
            <div className="flex items-center gap-2 rounded-xl bg-white/8 p-2.5 ring-1 ring-inset ring-white/10">
              <code className="min-w-0 flex-1 truncate text-[12px] text-emerald-300">{webhookUrl}</code>
              <button type="button" onClick={copyWebhook} className="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-white/50 hover:bg-white/10 hover:text-white" aria-label="Salin">
                <Copy className="h-3.5 w-3.5" />
              </button>
            </div>
            <p className="mt-2 text-[11.5px] text-white/35">
              Nota: WAHA dalam Docker guna <span className="font-mono">host.docker.internal</span> untuk capai app di host.
            </p>
          </Card>
        </div>
      </div>
    </CekbotLayout>
  );
}
