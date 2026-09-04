import { Users, User } from 'lucide-react';
import { cn, timeAgo, formatPhone } from '@/cekbot-admin/lib/utils';

export default function ConversationList({ conversations, selectedId, onSelect, multiSession }) {
  if (!conversations.length) {
    return (
      <div className="grid flex-1 place-items-center p-6 text-center">
        <div>
          <p className="text-[13px] font-semibold text-white/70">Tiada perbualan lagi</p>
          <p className="mt-1 text-[12px] text-white/40">Mesej masuk akan muncul di sini.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="scroll-thin flex-1 overflow-y-auto">
      {conversations.map((c) => {
        const active = c.id === selectedId;
        const title = c.name || formatPhone(c.phone);
        return (
          <button
            key={c.id}
            type="button"
            onClick={() => onSelect(c)}
            className={cn(
              'flex w-full items-center gap-3 border-b border-white/5 px-3 py-3 text-left transition-colors',
              active ? 'bg-emerald-500/12' : 'hover:bg-white/5'
            )}
          >
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white/8 text-white/50">
              {c.is_group ? <Users className="h-5 w-5" strokeWidth={2} /> : <User className="h-5 w-5" strokeWidth={2} />}
            </span>
            <div className="min-w-0 flex-1">
              <div className="flex items-center justify-between gap-2">
                <span className="truncate text-[13.5px] font-semibold text-white">{title}</span>
                <span className="shrink-0 text-[11px] text-white/35">{timeAgo(c.last_message_at)}</span>
              </div>
              <div className="mt-0.5 flex items-center justify-between gap-2">
                <span className="truncate text-[12px] text-white/45">{c.last_message_preview || '—'}</span>
                {c.unread_count > 0 && (
                  <span className="grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-emerald-500 px-1.5 text-[11px] font-bold text-white">
                    {c.unread_count}
                  </span>
                )}
              </div>
              {multiSession && c.session && (
                <span className="mt-1 inline-block rounded-md bg-white/5 px-1.5 py-0.5 text-[10px] font-medium text-white/40">
                  {c.session.label}
                </span>
              )}
            </div>
          </button>
        );
      })}
    </div>
  );
}
