import { useState } from 'react';
import { router } from '@inertiajs/react';
import { QrCode, LogOut, Pencil, Trash2, RefreshCw, Phone } from 'lucide-react';
import { Card, Badge, Button } from '@/cekbot-admin/components/Ui';
import { statusMeta } from '@/cekbot-admin/components/status';
import { cn, formatPhone } from '@/cekbot-admin/lib/utils';

export default function SessionCard({ session, onConnect, onEdit, onDelete, canManage = true, dashboardUrl }) {
  const [busy, setBusy] = useState(null);
  const meta = statusMeta(session.status);
  const StatusIcon = meta.Icon;
  const working = session.status === 'WORKING';
  const recoverable = session.status === 'FAILED' || session.status === 'STOPPED';

  function act(action, confirmText) {
    if (confirmText && !window.confirm(confirmText)) return;
    setBusy(action);
    router.post(route(`cekbot.sessions.${action}`, session.id), {}, {
      preserveScroll: true,
      onFinish: () => setBusy(null),
    });
  }

  return (
    <Card className="flex flex-col gap-4 p-5">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="truncate text-[15px] font-bold text-white">{session.label}</h3>
            <Badge color={meta.color}>
              <StatusIcon className={cn('h-3 w-3', meta.spin && 'animate-spin')} strokeWidth={2.4} />
              {meta.label}
            </Badge>
          </div>

          {session.phone_number ? (
            <p className="mt-1.5 flex items-center gap-1.5 text-[13px] font-medium text-emerald-300/90">
              <Phone className="h-3.5 w-3.5" strokeWidth={2.2} />
              {formatPhone(session.phone_number)}
            </p>
          ) : (
            <p className="mt-1.5 text-[12.5px] text-white/40">Belum ada nombor dipautkan</p>
          )}

          <p className="mt-2 font-mono text-[11px] text-white/30">{session.session_name}</p>
        </div>
      </div>

      {session.notes && <p className="text-[12.5px] leading-relaxed text-white/50">{session.notes}</p>}

      <div className="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-white/8 pt-3">
        <span className="text-[11px] text-white/30">
          {session.creator ? `${session.creator} · ` : ''}{session.created_ago}
        </span>

        <div className="flex flex-wrap items-center justify-end gap-1.5">
          {!canManage ? (
            <>
              {!working && dashboardUrl && (
                <Button size="sm" variant="primary" href={dashboardUrl} target="_blank" rel="noopener">
                  <QrCode className="h-3.5 w-3.5" strokeWidth={2.2} /> Scan di Dashboard
                </Button>
              )}
              <Button size="sm" variant="ghost" onClick={() => onEdit(session)} aria-label="Edit label">
                <Pencil className="h-3.5 w-3.5" strokeWidth={2.2} />
              </Button>
            </>
          ) : (
            <>
              {working ? (
                <Button size="sm" variant="secondary" loading={busy === 'logout'}
                  onClick={() => act('logout', `Log keluar "${session.label}"? Nombor akan diputuskan dan perlu scan semula untuk sambung.`)}>
                  <LogOut className="h-3.5 w-3.5" strokeWidth={2.2} /> Log keluar
                </Button>
              ) : (
                <Button size="sm" variant="primary" onClick={() => onConnect(session)}>
                  <QrCode className="h-3.5 w-3.5" strokeWidth={2.2} /> Sambung
                </Button>
              )}

              {recoverable && (
                <Button size="sm" variant="secondary" loading={busy === 'restart'} onClick={() => act('restart')}>
                  <RefreshCw className={cn('h-3.5 w-3.5', busy === 'restart' && 'animate-spin')} strokeWidth={2.2} /> Restart
                </Button>
              )}

              <Button size="sm" variant="ghost" onClick={() => onEdit(session)} aria-label="Edit">
                <Pencil className="h-3.5 w-3.5" strokeWidth={2.2} />
              </Button>
              <Button size="sm" variant="danger" onClick={() => onDelete(session)} aria-label="Padam">
                <Trash2 className="h-3.5 w-3.5" strokeWidth={2.2} />
              </Button>
            </>
          )}
        </div>
      </div>
    </Card>
  );
}
