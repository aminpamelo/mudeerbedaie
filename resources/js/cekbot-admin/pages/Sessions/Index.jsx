import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, Smartphone, AlertCircle, QrCode, Info } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Button, EmptyState, Modal } from '@/cekbot-admin/components/Ui';
import SessionCard from '@/cekbot-admin/components/SessionCard';
import AddNumberModal from '@/cekbot-admin/components/AddNumberModal';
import ConnectModal from '@/cekbot-admin/components/ConnectModal';

export default function Index() {
  const { props } = usePage();
  const sessions = props.sessions ?? [];
  const waha = props.waha ?? {};
  const canManage = waha.canManage !== false; // default allow when tier unknown
  const dashboardUrl = waha.dashboardUrl;

  const [addOpen, setAddOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [connectSession, setConnectSession] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [deleteBusy, setDeleteBusy] = useState(false);

  // Auto-open the QR modal for a number that was just added.
  const handledFlash = useRef(null);
  useEffect(() => {
    const id = props.flash?.connectSessionId;
    if (id && handledFlash.current !== id) {
      handledFlash.current = id;
      const target = sessions.find((s) => s.id === id);
      if (target) setConnectSession(target);
    }
  }, [props.flash?.connectSessionId, sessions]);

  const connectedCount = sessions.filter((s) => s.is_working).length;

  function openAdd() {
    setEditing(null);
    setAddOpen(true);
  }

  function openEdit(session) {
    setEditing(session);
    setAddOpen(true);
  }

  function confirmDelete() {
    if (!deleting) return;
    setDeleteBusy(true);
    router.delete(route('cekbot.sessions.destroy', deleting.id), {
      preserveScroll: true,
      onFinish: () => {
        setDeleteBusy(false);
        setDeleting(null);
      },
    });
  }

  const primaryAction = (
    <div className="flex items-center gap-2">
      {dashboardUrl && (
        <Button variant="ghost" href={dashboardUrl} target="_blank" rel="noopener">
          <QrCode className="h-4 w-4" strokeWidth={2.2} /> Dashboard
        </Button>
      )}
      <Button variant="primary" onClick={openAdd}><Plus className="h-4 w-4" strokeWidth={2.4} /> Tambah nombor</Button>
    </div>
  );

  return (
    <CekbotLayout
      title="Nombor WhatsApp"
      subtitle={sessions.length ? `${connectedCount} daripada ${sessions.length} nombor bersambung` : 'Urus nombor WhatsApp untuk cekbot'}
      actions={primaryAction}
    >
      <Head title="Nombor WhatsApp" />

      {!waha.configured && (
        <div className="mb-5 flex items-start gap-3 rounded-xl border border-rose-500/20 bg-rose-500/10 p-4">
          <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-rose-400" strokeWidth={2} />
          <div className="text-[13px] text-rose-100/90">
            <p className="font-semibold">Server WAHA belum dikonfigur.</p>
            <p className="mt-0.5 text-rose-100/70">Tetapkan URL &amp; API key WAHA di <span className="font-medium">Tetapan › WhatsApp</span> sebelum menyambung nombor.</p>
          </div>
        </div>
      )}

      {waha.configured && waha.reachable === false && (
        <div className="mb-5 flex items-start gap-3 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4">
          <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-amber-400" strokeWidth={2} />
          <div className="text-[13px] text-amber-100/90">
            <p className="font-semibold">Server WAHA tidak dapat dihubungi.</p>
            <p className="mt-0.5 text-amber-100/70">{waha.serverUrl} — status di bawah mungkin tidak dikemas kini.</p>
          </div>
        </div>
      )}

      {waha.configured && waha.reachable !== false && !canManage && (
        <div className="mb-5 flex flex-col gap-3 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-3">
            <Info className="mt-0.5 h-5 w-5 shrink-0 text-amber-400" strokeWidth={2} />
            <div className="text-[13px] text-amber-100/90">
              <p className="font-semibold">Server WAHA sekarang menyekat cipta/scan nombor melalui API (403).</p>
              <p className="mt-0.5 text-amber-100/70">
                WAHA <span className="font-medium">percuma</span> — ini isu konfigurasi server (imej lama atau API key terhad),
                bukan bayaran. Kemas kini imej WAHA / guna API key penuh untuk onboarding terus di sini. Buat sementara, scan di Dashboard WAHA.
                Butang &ldquo;Tambah nombor&rdquo; akan berfungsi automatik sebaik server dibenarkan.
              </p>
            </div>
          </div>
          {dashboardUrl && (
            <Button variant="secondary" href={dashboardUrl} target="_blank" rel="noopener" className="shrink-0">
              <QrCode className="h-4 w-4" strokeWidth={2.2} /> Buka Dashboard
            </Button>
          )}
        </div>
      )}

      {sessions.length === 0 ? (
        <EmptyState
          icon={Smartphone}
          title="Belum ada nombor"
          hint="Tambah nombor WhatsApp pertama, kemudian scan QR untuk menyambungkannya kepada cekbot."
          action={<Button variant="primary" onClick={openAdd}><Plus className="h-4 w-4" strokeWidth={2.4} /> Tambah nombor</Button>}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {sessions.map((session) => (
            <SessionCard
              key={session.id}
              session={session}
              canManage={canManage}
              dashboardUrl={dashboardUrl}
              onConnect={setConnectSession}
              onEdit={openEdit}
              onDelete={setDeleting}
            />
          ))}
        </div>
      )}

      <AddNumberModal open={addOpen} editing={editing} onClose={() => { setAddOpen(false); setEditing(null); }} />

      {connectSession && (
        <ConnectModal session={connectSession} dashboardUrl={dashboardUrl} onClose={() => setConnectSession(null)} />
      )}

      <Modal
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        size="sm"
        title="Padam nombor"
        hint={deleting ? `"${deleting.label}" akan di-log keluar dan dipadam dari server WAHA.` : null}
        footer={
          <>
            <Button variant="ghost" onClick={() => setDeleting(null)}>Batal</Button>
            <Button variant="danger" loading={deleteBusy} onClick={confirmDelete}>Padam</Button>
          </>
        }
      >
        <p className="text-[13.5px] text-white/60">Tindakan ini tidak boleh dibuat asal. Sejarah mesej pada server juga akan hilang.</p>
      </Modal>
    </CekbotLayout>
  );
}
