import { useEffect, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Pencil, Trash2, Zap, Bot, Sparkles } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Badge, Field, Textarea, Toggle, EmptyState } from '@/cekbot-admin/components/Ui';
import RuleModal from '@/cekbot-admin/components/autoreply/RuleModal';
import { cn } from '@/cekbot-admin/lib/utils';

export default function Index() {
  const { props } = usePage();
  const sessions = props.sessions ?? [];
  const aiAvailable = props.aiAvailable;

  const [selectedId, setSelectedId] = useState(sessions[0]?.id ?? null);
  const selected = sessions.find((s) => s.id === selectedId) ?? sessions[0] ?? null;
  const [ruleModal, setRuleModal] = useState({ open: false, editing: null });

  const settings = useForm({
    bot_enabled: false, reply_to_groups: false, checks_enabled: false, welcome_message: '', default_reply: '', ai_enabled: false, ai_system_prompt: '',
  });

  useEffect(() => {
    if (selected) {
      settings.setData({
        bot_enabled: selected.settings.bot_enabled,
        reply_to_groups: selected.settings.reply_to_groups,
        checks_enabled: selected.settings.checks_enabled,
        welcome_message: selected.settings.welcome_message ?? '',
        default_reply: selected.settings.default_reply ?? '',
        ai_enabled: selected.settings.ai_enabled,
        ai_system_prompt: selected.settings.ai_system_prompt ?? '',
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedId, sessions]);

  function saveSettings(e) {
    e.preventDefault();
    settings.put(route('cekbot.auto-reply.settings', selected.id), { preserveScroll: true });
  }

  function toggleRule(rule) {
    router.put(route('cekbot.auto-reply.rules.update', rule.id), { ...rule, is_active: !rule.is_active }, { preserveScroll: true });
  }

  function deleteRule(rule) {
    if (!window.confirm(`Padam peraturan "${rule.name}"?`)) return;
    router.delete(route('cekbot.auto-reply.rules.destroy', rule.id), { preserveScroll: true });
  }

  if (!sessions.length) {
    return (
      <CekbotLayout title="Auto-Reply" subtitle="Balasan automatik untuk mesej masuk">
        <Head title="Auto-Reply" />
        <EmptyState icon={Zap} title="Belum ada nombor" hint="Tambah nombor WhatsApp dahulu sebelum sediakan auto-reply."
          action={<Button variant="primary" href="/admin/cekbot"><Plus className="h-4 w-4" /> Tambah nombor</Button>} />
      </CekbotLayout>
    );
  }

  return (
    <CekbotLayout title="Auto-Reply" subtitle="Balasan automatik untuk mesej masuk">
      <Head title="Auto-Reply" />

      {sessions.length > 1 && (
        <div className="mb-5 flex flex-wrap gap-2">
          {sessions.map((s) => (
            <button key={s.id} type="button" onClick={() => setSelectedId(s.id)}
              className={cn('rounded-xl px-3.5 py-2 text-[13px] font-semibold transition-colors',
                s.id === (selected?.id) ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-400/20' : 'bg-white/5 text-white/60 hover:bg-white/10')}>
              {s.label}
            </button>
          ))}
        </div>
      )}

      <div className="grid gap-5 lg:grid-cols-2">
        {/* Bot settings */}
        <form onSubmit={saveSettings}>
          <Card className="p-5">
            <div className="mb-4 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="grid h-9 w-9 place-items-center rounded-xl bg-emerald-500/15"><Bot className="h-4 w-4 text-emerald-400" /></span>
                <div>
                  <h3 className="text-[14px] font-bold text-white">Bot untuk {selected.label}</h3>
                  <p className="text-[12px] text-white/40">Hidupkan bot & tetapkan mesej lalai.</p>
                </div>
              </div>
              <Toggle checked={settings.data.bot_enabled} onChange={(v) => settings.setData('bot_enabled', v)} />
            </div>

            <div className={cn('space-y-4 transition-opacity', !settings.data.bot_enabled && 'opacity-50')}>
              <label className="flex items-center justify-between gap-3">
                <span className="text-[13px] text-white/70">Balas dalam group juga</span>
                <Toggle checked={settings.data.reply_to_groups} onChange={(v) => settings.setData('reply_to_groups', v)} disabled={!settings.data.bot_enabled} />
              </label>

              <label className="flex items-center justify-between gap-3">
                <span className="text-[13px] text-white/70">Semak pesanan automatik <span className="text-white/35">(cth: "status pesanan ORD123")</span></span>
                <Toggle checked={settings.data.checks_enabled} onChange={(v) => settings.setData('checks_enabled', v)} disabled={!settings.data.bot_enabled} />
              </label>

              <Field label="Mesej alu-aluan (mesej pertama)" error={settings.errors.welcome_message}>
                <Textarea rows={2} value={settings.data.welcome_message} onChange={(e) => settings.setData('welcome_message', e.target.value)}
                  placeholder="Cth: Salam! Terima kasih hubungi kami 🙏" disabled={!settings.data.bot_enabled} />
              </Field>

              <Field label="Balasan lalai (bila tiada peraturan sepadan)" error={settings.errors.default_reply}>
                <Textarea rows={2} value={settings.data.default_reply} onChange={(e) => settings.setData('default_reply', e.target.value)}
                  placeholder="Cth: Maaf, kami akan balas secepat mungkin." disabled={!settings.data.bot_enabled} />
              </Field>

              {/* Fasa 4 — AI */}
              <div className="rounded-xl border border-white/8 bg-white/[0.03] p-3.5">
                <label className="flex items-center justify-between gap-3">
                  <span className="flex items-center gap-1.5 text-[13px] font-semibold text-white/80">
                    <Sparkles className="h-4 w-4 text-violet-300" /> Jawapan AI (Tanya Ilmu)
                  </span>
                  <Toggle checked={settings.data.ai_enabled} onChange={(v) => settings.setData('ai_enabled', v)} disabled={!settings.data.bot_enabled || !aiAvailable} />
                </label>
                {!aiAvailable && <p className="mt-1 text-[11.5px] text-amber-300/80">Perlu OpenAI dikonfigur (MindPal) untuk guna AI.</p>}
                {settings.data.ai_enabled && (
                  <Field className="mt-3" label="Arahan sistem AI (pilihan)">
                    <Textarea rows={2} value={settings.data.ai_system_prompt} onChange={(e) => settings.setData('ai_system_prompt', e.target.value)}
                      placeholder="Cth: Anda ejen khidmat pelanggan syarikat kami. Jawab ringkas dalam BM." disabled={!settings.data.bot_enabled} />
                  </Field>
                )}
              </div>

              <div className="flex justify-end">
                <Button type="submit" variant="primary" loading={settings.processing}>Simpan tetapan</Button>
              </div>
            </div>
          </Card>
        </form>

        {/* Rules */}
        <Card className="flex flex-col p-5">
          <div className="mb-4 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <span className="grid h-9 w-9 place-items-center rounded-xl bg-emerald-500/15"><Zap className="h-4 w-4 text-emerald-400" /></span>
              <div>
                <h3 className="text-[14px] font-bold text-white">Peraturan keyword</h3>
                <p className="text-[12px] text-white/40">{selected.rules.length} peraturan</p>
              </div>
            </div>
            <Button size="sm" variant="primary" onClick={() => setRuleModal({ open: true, editing: null })}><Plus className="h-3.5 w-3.5" /> Tambah</Button>
          </div>

          {selected.rules.length === 0 ? (
            <div className="grid flex-1 place-items-center rounded-xl border border-dashed border-white/10 py-10 text-center">
              <p className="text-[12.5px] text-white/40">Belum ada peraturan. Tambah satu untuk balas keyword tertentu.</p>
            </div>
          ) : (
            <div className="space-y-2">
              {selected.rules.map((rule) => (
                <div key={rule.id} className="rounded-xl border border-white/8 bg-white/[0.03] p-3.5">
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-1.5">
                        <span className="text-[13px] font-semibold text-white">{rule.name}</span>
                        <Badge color={rule.is_active ? 'emerald' : 'slate'}>{rule.is_active ? 'Aktif' : 'Off'}</Badge>
                      </div>
                      <div className="mt-1.5 flex flex-wrap gap-1">
                        {rule.keywords.map((k, i) => (
                          <span key={i} className="rounded-md bg-white/8 px-1.5 py-0.5 text-[11px] text-white/60">{k}</span>
                        ))}
                      </div>
                      <p className="mt-1.5 line-clamp-2 text-[12px] text-white/45">{rule.reply_body}</p>
                    </div>
                    <div className="flex shrink-0 items-center gap-1">
                      <Toggle checked={rule.is_active} onChange={() => toggleRule(rule)} />
                      <Button size="sm" variant="ghost" onClick={() => setRuleModal({ open: true, editing: rule })} aria-label="Edit"><Pencil className="h-3.5 w-3.5" /></Button>
                      <Button size="sm" variant="danger" onClick={() => deleteRule(rule)} aria-label="Padam"><Trash2 className="h-3.5 w-3.5" /></Button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </Card>
      </div>

      <RuleModal open={ruleModal.open} sessionId={selected.id} editing={ruleModal.editing} onClose={() => setRuleModal({ open: false, editing: null })} />
    </CekbotLayout>
  );
}
