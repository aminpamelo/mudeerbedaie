import { useEffect, useRef, useState } from 'react';
import { ArrowLeft, Send, Loader2, Check, CheckCheck, User, Users, Bot } from 'lucide-react';
import { cn, clockTime, formatPhone, contactDisplay, mediaLabel } from '@/cekbot-admin/lib/utils';

function AckIcon({ ack }) {
  if (!ack) return null;
  const a = String(ack).toLowerCase();
  if (a.includes('read')) return <CheckCheck className="h-3.5 w-3.5 text-sky-300" strokeWidth={2.4} />;
  if (a.includes('deliver') || a.includes('server') || a === 'sent') return <CheckCheck className="h-3.5 w-3.5 text-white/40" strokeWidth={2.4} />;
  if (a === 'failed') return <span className="text-[10px] font-semibold text-rose-400">gagal</span>;
  return <Check className="h-3.5 w-3.5 text-white/40" strokeWidth={2.4} />;
}

function MessageBubble({ message }) {
  const out = message.direction === 'out';
  const bot = out && !message.sent_by; // outbound without a user = bot auto-reply
  return (
    <div className={cn('flex', out ? 'justify-end' : 'justify-start')}>
      <div className={cn(
        'max-w-[78%] rounded-2xl px-3.5 py-2 text-[13.5px] leading-relaxed shadow-sm',
        out ? 'bg-emerald-600/90 text-white' : 'bg-white/8 text-white/90'
      )}>
        {bot && (
          <span className="mb-0.5 flex items-center gap-1 text-[10.5px] font-semibold text-white/70">
            <Bot className="h-3 w-3" strokeWidth={2.4} /> Cekbot
          </span>
        )}
        {message.body
          ? <p className="whitespace-pre-wrap break-words">{message.body}</p>
          : <p className="italic text-white/60">{mediaLabel(message.type) || '💬 Mesej'}</p>}
        <div className={cn('mt-1 flex items-center justify-end gap-1 text-[10.5px]', out ? 'text-white/70' : 'text-white/35')}>
          {out && message.sent_by && <span className="mr-1">{message.sent_by}</span>}
          <span>{clockTime(message.sent_at)}</span>
          {out && <AckIcon ack={message.ack} />}
        </div>
      </div>
    </div>
  );
}

export default function ChatPanel({ conversation, messages, loading, onSend, sending, onBack, canReply, onHandover, onRelease }) {
  const [text, setText] = useState('');
  const scrollRef = useRef(null);

  useEffect(() => {
    const el = scrollRef.current;
    if (el) el.scrollTop = el.scrollHeight;
  }, [messages, loading]);

  if (!conversation) {
    return (
      <div className="hidden flex-1 place-items-center p-8 text-center lg:grid">
        <div>
          <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-white/5">
            <User className="h-6 w-6 text-white/30" strokeWidth={1.8} />
          </div>
          <p className="mt-3 text-[13.5px] font-semibold text-white/60">Pilih perbualan</p>
          <p className="mt-1 text-[12.5px] text-white/35">Pilih satu chat di sebelah kiri untuk lihat mesej.</p>
        </div>
      </div>
    );
  }

  const title = contactDisplay(conversation.name, conversation.phone, conversation.is_group);
  const digits = String(conversation.phone || '').replace(/\D/g, '');
  const subPhone = (conversation.is_group || digits.length > 14) ? null : formatPhone(conversation.phone);

  function submit(e) {
    e.preventDefault();
    const value = text.trim();
    if (!value) return;
    onSend(value, () => setText(''));
  }

  return (
    <div className="flex min-w-0 flex-1 flex-col">
      <div className="flex items-center gap-3 border-b border-white/8 px-4 py-3">
        <button type="button" onClick={onBack} className="grid h-8 w-8 place-items-center rounded-lg text-white/60 hover:bg-white/10 lg:hidden" aria-label="Kembali">
          <ArrowLeft className="h-4 w-4" strokeWidth={2.2} />
        </button>
        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/8 text-white/50">
          {conversation.is_group ? <Users className="h-4 w-4" /> : <User className="h-4 w-4" />}
        </span>
        <div className="min-w-0">
          <p className="truncate text-[14px] font-semibold text-white">{title}</p>
          <p className="truncate text-[11.5px] text-white/40">
            {[subPhone, conversation.session?.label].filter(Boolean).join(' · ') || '—'}
          </p>
        </div>

        <div className="ml-auto flex shrink-0 items-center gap-2">
          {conversation.handed_over ? (
            <>
              <span className="hidden items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-[10.5px] font-semibold text-amber-300 sm:inline-flex">
                Diambil alih{conversation.handed_over.by ? ` · ${conversation.handed_over.by}` : ''}
              </span>
              <button type="button" onClick={onRelease}
                className="flex items-center gap-1.5 rounded-lg bg-white/8 px-2.5 py-1.5 text-[12px] font-semibold text-white/70 hover:bg-white/12">
                <Bot className="h-3.5 w-3.5" strokeWidth={2.2} /> Serah ke bot
              </button>
            </>
          ) : (
            <button type="button" onClick={onHandover}
              className="rounded-lg bg-white/8 px-2.5 py-1.5 text-[12px] font-semibold text-white/70 hover:bg-white/12">
              Ambil alih
            </button>
          )}
        </div>
      </div>

      <div ref={scrollRef} className="scroll-thin flex-1 space-y-2 overflow-y-auto px-4 py-4">
        {loading ? (
          <div className="grid h-full place-items-center"><Loader2 className="h-6 w-6 animate-spin text-white/40" /></div>
        ) : messages.length === 0 ? (
          <div className="grid h-full place-items-center text-center text-[12.5px] text-white/35">Belum ada mesej.</div>
        ) : (
          messages.map((m) => <MessageBubble key={m.id} message={m} />)
        )}
      </div>

      <form onSubmit={submit} className="flex items-end gap-2 border-t border-white/8 p-3">
        <textarea
          value={text}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) submit(e); }}
          rows={1}
          placeholder={canReply ? 'Taip mesej…' : 'Balasan perlu nombor bersambung'}
          disabled={!canReply}
          className="max-h-32 min-h-[42px] flex-1 resize-none rounded-xl border-0 bg-white/8 px-3.5 py-2.5 text-[13.5px] text-white ring-1 ring-inset ring-white/10 placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-emerald-500/60 disabled:opacity-50"
        />
        <button
          type="submit"
          disabled={sending || !text.trim() || !canReply}
          className="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-xl bg-gradient-to-br from-emerald-500 to-green-500 text-white shadow-[0_10px_24px_-12px_rgba(16,185,129,0.9)] transition disabled:cursor-not-allowed disabled:opacity-40"
          aria-label="Hantar"
        >
          {sending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" strokeWidth={2.2} />}
        </button>
      </form>
    </div>
  );
}
