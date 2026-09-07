import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, Megaphone, Trash2, Clock, Check } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Badge, EmptyState } from '@/cekbot-admin/components/Ui';
import BroadcastModal from '@/cekbot-admin/components/broadcast/BroadcastModal';
import { formatDate } from '@/cekbot-admin/lib/utils';

const STATUS = {
  draft: { label: 'Draf', color: 'slate' },
  scheduled: { label: 'Dijadualkan', color: 'blue' },
  sending: { label: 'Menghantar…', color: 'amber' },
  sent: { label: 'Selesai', color: 'emerald' },
  failed: { label: 'Gagal', color: 'red' },
};

export default function Index() {
  const { props } = usePage();
  const broadcasts = props.broadcasts ?? [];
  const sessions = props.sessions ?? [];
  const [open, setOpen] = useState(false);

  function del(b) {
    if (!window.confirm(`Padam broadcast "${b.name}"?`)) return;
    router.delete(route('cekbot.broadcast.destroy', b.id), { preserveScroll: true });
  }

  const canCompose = sessions.length > 0;

  return (
    <CekbotLayout
      title="Broadcast"
      subtitle="Hantar mesej pukal & follow-up berjadual"
      actions={canCompose && <Button variant="primary" onClick={() => setOpen(true)}><Plus className="h-4 w-4" strokeWidth={2.4} /> Broadcast baru</Button>}
    >
      <Head title="Broadcast" />

      {broadcasts.length === 0 ? (
        <EmptyState
          icon={Megaphone}
          title="Belum ada broadcast"
          hint={canCompose ? 'Hantar mesej kepada pelanggan yang pernah mesej nombor anda, atau jadualkan untuk kemudian.' : 'Tambah nombor WhatsApp dahulu sebelum broadcast.'}
          action={canCompose
            ? <Button variant="primary" onClick={() => setOpen(true)}><Plus className="h-4 w-4" strokeWidth={2.4} /> Broadcast baru</Button>
            : <Button variant="primary" href="/admin/cekbot"><Plus className="h-4 w-4" strokeWidth={2.4} /> Tambah nombor</Button>}
        />
      ) : (
        <div className="space-y-3">
          {broadcasts.map((b) => {
            const st = STATUS[b.status] ?? STATUS.draft;
            const pct = b.total_recipients ? Math.round((b.sent_count / b.total_recipients) * 100) : 0;
            return (
              <Card key={b.id} className="p-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="text-[14px] font-bold text-white">{b.name}</h3>
                      <Badge color={st.color}>{st.label}</Badge>
                      {b.session && <span className="text-[11.5px] text-white/40">{b.session}</span>}
                    </div>
                    <p className="mt-1 line-clamp-2 text-[12.5px] text-white/50">{b.message}</p>
                  </div>
                  <Button size="sm" variant="danger" onClick={() => del(b)} aria-label="Padam"><Trash2 className="h-3.5 w-3.5" /></Button>
                </div>

                <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[12px] text-white/50">
                  <span className="flex items-center gap-1">
                    <Check className="h-3.5 w-3.5 text-emerald-400" strokeWidth={2.4} />
                    {b.sent_count}/{b.total_recipients} dihantar{b.failed_count ? ` · ${b.failed_count} gagal` : ''}
                  </span>
                  {b.status === 'scheduled' && b.scheduled_at && (
                    <span className="flex items-center gap-1 text-sky-300"><Clock className="h-3.5 w-3.5" /> {formatDate(b.scheduled_at)} {new Date(b.scheduled_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}</span>
                  )}
                  <span className="text-white/30">{b.created_ago}</span>
                </div>

                {(b.status === 'sending' || b.status === 'sent') && b.total_recipients > 0 && (
                  <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-white/8">
                    <div className="h-full rounded-full bg-emerald-500 transition-all" style={{ width: `${pct}%` }} />
                  </div>
                )}
              </Card>
            );
          })}
        </div>
      )}

      <BroadcastModal open={open} sessions={sessions} availableLabels={props.availableLabels ?? []} onClose={() => setOpen(false)} />
    </CekbotLayout>
  );
}
