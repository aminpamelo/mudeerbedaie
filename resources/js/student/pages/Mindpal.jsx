import { Head, usePage, router } from '@inertiajs/react';
import { useState, useRef, useEffect, useCallback } from 'react';
import {
  BrainCircuit, Plus, Send, Trash2, MessageSquare,
  FileText, Menu, X, AlertTriangle, Copy, Check, RefreshCw, Sparkles,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader, { HeroStat } from '@/student/components/PageHeader';
import { cn } from '@/student/lib/utils';

const ASSISTANT_NAME = 'Tanya Ilmu';

const SUGGESTED_PROMPTS = [
  'Ringkaskan topik utama dokumen ini',
  'Terangkan konsep penting dengan cara yang mudah',
  'Buatkan 3 soalan latihan untuk saya',
  'Apakah perkara yang saya patut fokus?',
];

/* ------------------------------------------------------------------ */
/*  Lightweight, XSS-safe markdown renderer (renders React nodes only) */
/* ------------------------------------------------------------------ */
function CitationPill({ pages, title }) {
  return (
    <span
      title={title ? `${title} — muka surat ${pages}` : `Muka surat ${pages}`}
      className="mx-0.5 inline-flex items-center gap-1 rounded-md bg-violet-50 px-1.5 py-0.5 align-baseline text-[11px] font-semibold text-violet-700 ring-1 ring-violet-200/70"
    >
      <FileText className="h-3 w-3" strokeWidth={2.2} />
      ms {pages}
    </span>
  );
}

function renderBold(text, keyBase) {
  const parts = text.split(/(\*\*[^*]+\*\*)/g);
  return parts.map((part, i) => {
    if (part.startsWith('**') && part.endsWith('**')) {
      return <strong key={`${keyBase}-b${i}`} className="font-semibold text-ink">{part.slice(2, -2)}</strong>;
    }
    return part;
  });
}

function renderInline(text, keyBase) {
  // Turn "[Book Title, Page 33]" or "[Book Title, Page 3; Page 10]" citations into pills, bold the rest.
  const citation = /\[([^\][]*?(?:Page|page|Halaman|halaman|m\.?\s?s\.?)[^\][]*?)\]/g;
  const nodes = [];
  let last = 0;
  let match;
  let i = 0;
  while ((match = citation.exec(text)) !== null) {
    if (match.index > last) {
      nodes.push(...renderBold(text.slice(last, match.index), `${keyBase}-t${i}`));
    }
    const inner = match[1];
    const commaIdx = inner.indexOf(',');
    const title = (commaIdx >= 0 ? inner.slice(0, commaIdx) : inner).trim();
    const pagePart = commaIdx >= 0 ? inner.slice(commaIdx + 1) : inner;
    const pages = (pagePart.match(/\d+/g) || []).join(', ');
    nodes.push(<CitationPill key={`${keyBase}-c${i}`} title={title} pages={pages} />);
    last = match.index + match[0].length;
    i += 1;
  }
  if (last < text.length) {
    nodes.push(...renderBold(text.slice(last), `${keyBase}-t${i}`));
  }
  return nodes;
}

function MarkdownContent({ text }) {
  const lines = String(text).replace(/\r\n/g, '\n').split('\n');
  const blocks = [];
  let list = null;
  let para = [];

  const flushPara = () => {
    if (para.length) {
      blocks.push({ type: 'p', text: para.join(' ') });
      para = [];
    }
  };
  const flushList = () => {
    if (list) {
      blocks.push(list);
      list = null;
    }
  };

  lines.forEach((raw) => {
    const line = raw.trim();
    if (line === '') { flushPara(); flushList(); return; }

    const heading = line.match(/^(#{1,3})\s+(.*)$/);
    const ordered = line.match(/^(\d+)\.\s+(.*)$/);
    const bullet = line.match(/^[-*]\s+(.*)$/);

    if (heading) {
      flushPara(); flushList();
      blocks.push({ type: 'h', level: heading[1].length, text: heading[2] });
    } else if (ordered) {
      flushPara();
      if (!list || list.type !== 'ol') { flushList(); list = { type: 'ol', items: [] }; }
      list.items.push(ordered[2]);
    } else if (bullet) {
      flushPara();
      if (!list || list.type !== 'ul') { flushList(); list = { type: 'ul', items: [] }; }
      list.items.push(bullet[1]);
    } else {
      flushList();
      para.push(line);
    }
  });
  flushPara();
  flushList();

  return (
    <div className="space-y-2.5 text-[14px] leading-relaxed">
      {blocks.map((block, i) => {
        if (block.type === 'h') {
          const size = block.level === 1 ? 'text-[16px]' : 'text-[15px]';
          return <p key={i} className={cn('font-bold text-ink', size)}>{renderInline(block.text, `h${i}`)}</p>;
        }
        if (block.type === 'ol') {
          return (
            <ol key={i} className="list-decimal space-y-1 pl-5 marker:font-semibold marker:text-violet-500">
              {block.items.map((it, j) => <li key={j}>{renderInline(it, `o${i}-${j}`)}</li>)}
            </ol>
          );
        }
        if (block.type === 'ul') {
          return (
            <ul key={i} className="list-disc space-y-1 pl-5 marker:text-violet-400">
              {block.items.map((it, j) => <li key={j}>{renderInline(it, `u${i}-${j}`)}</li>)}
            </ul>
          );
        }
        return <p key={i} className="break-words">{renderInline(block.text, `p${i}`)}</p>;
      })}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Source citations panel                                             */
/* ------------------------------------------------------------------ */
function SourceCards({ sources }) {
  const [openIndex, setOpenIndex] = useState(null);

  // Dedupe by document + page, keep the most relevant excerpt.
  const seen = new Map();
  sources.forEach((s) => {
    const key = `${s.document_title}|${s.page_number}`;
    if (!seen.has(key)) seen.set(key, s);
  });
  const unique = [...seen.values()];

  return (
    <div className="mt-2">
      <p className="mb-1.5 flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide text-muted-2">
        <FileText className="h-3 w-3" strokeWidth={2} /> Sumber rujukan
      </p>
      <div className="flex flex-col gap-1.5">
        {unique.map((src, i) => {
          const isOpen = openIndex === i;
          return (
            <div key={i} className="overflow-hidden rounded-xl bg-violet-50/70 ring-1 ring-violet-100">
              <button
                type="button"
                onClick={() => setOpenIndex(isOpen ? null : (src.excerpt ? i : null))}
                className="flex w-full items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-violet-100/60"
              >
                <span className="grid h-6 w-6 shrink-0 place-items-center rounded-lg bg-white text-violet-600 ring-1 ring-violet-100">
                  <FileText className="h-3.5 w-3.5" strokeWidth={2} />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-[12px] font-semibold text-ink">{src.document_title}</span>
                  <span className="text-[11px] text-muted">Muka surat {src.page_number}</span>
                </span>
                {src.excerpt && (
                  <span className="shrink-0 text-[11px] font-medium text-violet-500">
                    {isOpen ? 'Tutup' : 'Lihat'}
                  </span>
                )}
              </button>
              {isOpen && src.excerpt && (
                <p className="border-t border-violet-100 px-3 py-2 text-[12px] italic leading-relaxed text-muted">
                  “{src.excerpt}”
                </p>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Typing indicator                                                   */
/* ------------------------------------------------------------------ */
function TypingDots() {
  return (
    <span className="flex items-center gap-1 py-1">
      <span className="h-2 w-2 animate-bounce rounded-full bg-violet-400 [animation-delay:-0.3s]" />
      <span className="h-2 w-2 animate-bounce rounded-full bg-violet-400 [animation-delay:-0.15s]" />
      <span className="h-2 w-2 animate-bounce rounded-full bg-violet-400" />
    </span>
  );
}

/* ------------------------------------------------------------------ */
/*  Conversation Sidebar                                               */
/* ------------------------------------------------------------------ */
function ConversationSidebar({ conversations, activeId, onNew, onSelect, onDelete, onClose }) {
  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between border-b border-violet-100/60 p-4">
        <h2 className="text-[14px] font-bold text-ink">Perbualan</h2>
        <div className="flex items-center gap-2">
          <button
            onClick={onNew}
            className="flex items-center gap-1.5 rounded-xl bg-[var(--color-brand)] px-3 py-2 text-[12px] font-semibold text-white shadow-md shadow-violet-500/25 transition-colors hover:bg-violet-700"
          >
            <Plus className="h-3.5 w-3.5" strokeWidth={2.5} />
            <span>Baru</span>
          </button>
          {onClose && (
            <button
              onClick={onClose}
              className="grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-violet-50 hover:text-ink lg:hidden"
            >
              <X className="h-4 w-4" strokeWidth={2} />
            </button>
          )}
        </div>
      </div>

      <div className="flex-1 overflow-y-auto scroll-thin">
        {conversations.length === 0 ? (
          <div className="flex flex-col items-center px-4 py-10 text-center">
            <MessageSquare className="mb-3 h-8 w-8 text-violet-300" strokeWidth={1.5} />
            <p className="text-[13px] text-muted">Tiada perbualan lagi</p>
          </div>
        ) : (
          <div className="divide-y divide-violet-50 p-2">
            {conversations.map((conv) => (
              <div
                key={conv.id}
                className={cn(
                  'group flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors cursor-pointer',
                  conv.id === activeId ? 'bg-violet-100/80 ring-1 ring-violet-200' : 'hover:bg-violet-50/60'
                )}
                onClick={() => onSelect(conv.id)}
              >
                <div className="min-w-0 flex-1">
                  <p className={cn(
                    'truncate text-[13px] font-semibold',
                    conv.id === activeId ? 'text-[var(--color-brand)]' : 'text-ink'
                  )}>
                    {conv.title || 'Perbualan baru'}
                  </p>
                  <p className="text-[11px] text-muted">{conv.updated_at}</p>
                </div>
                <button
                  onClick={(e) => { e.stopPropagation(); onDelete(conv.id); }}
                  className="shrink-0 rounded-lg p-1.5 text-muted-2 opacity-0 transition-all hover:bg-red-50 hover:text-red-500 group-hover:opacity-100"
                  title="Padam"
                >
                  <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
                </button>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Chat Message Bubble                                                */
/* ------------------------------------------------------------------ */
function ChatMessage({ message, isStreaming, canRegenerate, onRegenerate }) {
  const isUser = message.role === 'user';
  const [copied, setCopied] = useState(false);

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(message.content || '');
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      /* clipboard unavailable */
    }
  };

  const showTyping = isStreaming && !isUser && !message.content;

  return (
    <div className={cn('group/msg flex gap-3', isUser ? 'justify-end' : 'justify-start')}>
      {!isUser && (
        <div className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-violet-700 text-white shadow-sm shadow-violet-500/30">
          <BrainCircuit className="h-4 w-4" strokeWidth={2} />
        </div>
      )}

      <div className={cn('flex max-w-[86%] flex-col sm:max-w-[74%]', isUser && 'items-end')}>
        <div
          className={cn(
            'rounded-2xl px-4 py-3 text-[14px] leading-relaxed',
            isUser
              ? 'rounded-br-md bg-[var(--color-brand)] text-white shadow-md shadow-violet-500/25'
              : 'glass-card rounded-bl-md text-ink shadow-sm'
          )}
        >
          {isUser ? (
            <div className="whitespace-pre-wrap break-words">{message.content}</div>
          ) : showTyping ? (
            <TypingDots />
          ) : (
            <MarkdownContent text={message.content} />
          )}
          {isStreaming && !isUser && message.content && (
            <span className="ml-0.5 inline-block h-4 w-1.5 animate-pulse rounded-full bg-violet-400 align-middle" />
          )}
        </div>

        {!isUser && message.sources && message.sources.length > 0 && !isStreaming && (
          <SourceCards sources={message.sources} />
        )}

        {/* Action row */}
        {!isUser && !isStreaming && message.content && (
          <div className="mt-1.5 flex items-center gap-1 opacity-0 transition-opacity group-hover/msg:opacity-100">
            <button
              onClick={handleCopy}
              className="flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-medium text-muted transition-colors hover:bg-violet-50 hover:text-violet-600"
              title="Salin jawapan"
            >
              {copied ? <Check className="h-3 w-3" strokeWidth={2.4} /> : <Copy className="h-3 w-3" strokeWidth={2} />}
              {copied ? 'Disalin' : 'Salin'}
            </button>
            {canRegenerate && (
              <button
                onClick={onRegenerate}
                className="flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-medium text-muted transition-colors hover:bg-violet-50 hover:text-violet-600"
                title="Jana semula jawapan"
              >
                <RefreshCw className="h-3 w-3" strokeWidth={2} />
                Jana semula
              </button>
            )}
          </div>
        )}

        {message.created_at && !isStreaming && (
          <p className={cn('mt-1 text-[10px] text-muted-2', isUser && 'text-right')}>{message.created_at}</p>
        )}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  No Documents Warning                                               */
/* ------------------------------------------------------------------ */
function NoDocumentsWarning() {
  return (
    <div className="mx-auto mb-4 flex max-w-md items-center gap-3 rounded-2xl border border-amber-200/60 bg-amber-50/80 px-4 py-3">
      <AlertTriangle className="h-5 w-5 shrink-0 text-amber-500" strokeWidth={2} />
      <div>
        <p className="text-[13px] font-semibold text-amber-800">Tiada dokumen</p>
        <p className="text-[12px] text-amber-600">
          Admin belum memuat naik dokumen. {ASSISTANT_NAME} memerlukan dokumen untuk menjawab soalan anda.
        </p>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Empty Chat State + suggested prompts                               */
/* ------------------------------------------------------------------ */
function EmptyChatState({ onPick, disabled }) {
  return (
    <div className="flex flex-1 flex-col items-center justify-center px-4 py-10 text-center">
      <div className="mb-5 grid h-20 w-20 place-items-center rounded-3xl bg-gradient-to-br from-violet-500 to-violet-700 text-white shadow-lg shadow-violet-500/30">
        <BrainCircuit className="h-9 w-9" strokeWidth={1.6} />
      </div>
      <h3 className="text-[19px] font-bold text-ink">Hai, saya {ASSISTANT_NAME} 👋</h3>
      <p className="mx-auto mt-2 max-w-sm text-[14px] leading-relaxed text-muted">
        Tanya apa-apa berkaitan bahan pembelajaran anda. Saya akan cari jawapan daripada dokumen kursus, lengkap dengan rujukan muka surat.
      </p>

      <div className="mt-6 grid w-full max-w-lg gap-2 sm:grid-cols-2">
        {SUGGESTED_PROMPTS.map((prompt) => (
          <button
            key={prompt}
            onClick={() => onPick(prompt)}
            disabled={disabled}
            className="group flex items-start gap-2 rounded-2xl border border-violet-100 bg-white/70 px-4 py-3 text-left text-[13px] font-medium text-ink transition-all hover:-translate-y-0.5 hover:border-violet-200 hover:bg-violet-50/70 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
          >
            <Sparkles className="mt-0.5 h-4 w-4 shrink-0 text-violet-400 transition-colors group-hover:text-violet-600" strokeWidth={2} />
            <span>{prompt}</span>
          </button>
        ))}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Input Bar                                                          */
/* ------------------------------------------------------------------ */
function InputBar({ onSend, disabled }) {
  const [text, setText] = useState('');
  const textareaRef = useRef(null);

  const handleSubmit = () => {
    const trimmed = text.trim();
    if (!trimmed || disabled) return;
    onSend(trimmed);
    setText('');
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit();
    }
  };

  const handleInput = (e) => {
    const el = e.target;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    setText(el.value);
  };

  return (
    <div className="border-t border-violet-100/60 bg-white/90 px-4 py-3 backdrop-blur-lg">
      <div className="mx-auto flex max-w-3xl items-end gap-3">
        <textarea
          ref={textareaRef}
          value={text}
          onChange={handleInput}
          onKeyDown={handleKeyDown}
          placeholder="Tanya apa-apa di sini..."
          disabled={disabled}
          rows={1}
          className="flex-1 resize-none rounded-xl border border-violet-200/60 bg-violet-50/50 px-4 py-2.5 text-[14px] text-ink placeholder:text-muted-2 focus:border-[var(--color-brand)] focus:outline-none focus:ring-2 focus:ring-violet-200 disabled:opacity-50"
        />
        <button
          onClick={handleSubmit}
          disabled={disabled || !text.trim()}
          className={cn(
            'grid h-10 w-10 shrink-0 place-items-center rounded-xl transition-all',
            disabled || !text.trim()
              ? 'bg-violet-100 text-violet-300 cursor-not-allowed'
              : 'bg-[var(--color-brand)] text-white shadow-md shadow-violet-500/25 hover:bg-violet-700'
          )}
        >
          <Send className="h-4 w-4" strokeWidth={2.2} />
        </button>
      </div>
    </div>
  );
}

/* ================================================================== */
/*  Page Component                                                     */
/* ================================================================== */
export default function Mindpal() {
  const { conversations, activeConversation, documentsCount } = usePage().props;
  const initialMessages = activeConversation?.messages;

  const [messages, setMessages] = useState(initialMessages || []);
  const [isStreaming, setIsStreaming] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const messagesEndRef = useRef(null);
  const activeConvId = activeConversation?.id || null;

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  useEffect(() => {
    setMessages(initialMessages || []);
  }, [initialMessages]);

  const handleSelectConversation = useCallback((id) => {
    setSidebarOpen(false);
    router.get(`/my/mindpal/${id}`, {}, { preserveState: false });
  }, []);

  const handleNewConversation = useCallback(() => {
    setSidebarOpen(false);
    router.get('/my/mindpal', {}, { preserveState: false });
  }, []);

  const handleDeleteConversation = useCallback((id) => {
    if (!window.confirm('Padam perbualan ini?')) return;
    router.delete(`/my/mindpal/conversations/${id}`, {
      preserveState: false,
      onSuccess: () => {
        if (id === activeConvId) {
          router.get('/my/mindpal', {}, { preserveState: false });
        }
      },
    });
  }, [activeConvId]);

  // Consume an SSE stream, updating the trailing assistant message live.
  const pumpStream = useCallback(async (response) => {
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let done = false;

    while (!done) {
      const result = await reader.read();
      if (result.done) break;

      buffer += decoder.decode(result.value, { stream: true });
      const lines = buffer.split('\n');
      buffer = lines.pop();

      for (const line of lines) {
        if (!line.startsWith('data: ')) continue;
        const data = line.slice(6);
        if (data === '[DONE]') { done = true; break; }
        try {
          const parsed = JSON.parse(data);
          if (parsed.token) {
            setMessages((prev) => {
              const updated = [...prev];
              const last = updated[updated.length - 1];
              if (last && last.role === 'assistant') {
                updated[updated.length - 1] = { ...last, content: last.content + parsed.token };
              }
              return updated;
            });
          }
          if (parsed.sources) {
            setMessages((prev) => {
              const updated = [...prev];
              const last = updated[updated.length - 1];
              if (last && last.role === 'assistant') {
                updated[updated.length - 1] = { ...last, sources: parsed.sources };
              }
              return updated;
            });
          }
        } catch {
          /* skip malformed chunk */
        }
      }
    }
  }, []);

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

  const handleSend = useCallback(async (message) => {
    if (isStreaming) return;

    let conversationId = activeConvId;
    setMessages((prev) => [...prev, { role: 'user', content: message, sources: [] }]);

    if (!conversationId) {
      try {
        const createRes = await fetch('/my/mindpal/conversations', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
          body: JSON.stringify({ message }),
        });
        const createData = await createRes.json();
        conversationId = createData.id;
        window.history.replaceState({}, '', `/my/mindpal/${conversationId}`);
      } catch {
        setMessages((prev) => [...prev, { role: 'assistant', content: 'Ralat semasa membuat perbualan. Sila cuba lagi.', sources: [] }]);
        return;
      }
    }

    setIsStreaming(true);
    setMessages((prev) => [...prev, { role: 'assistant', content: '', sources: [], _streaming: true }]);

    try {
      const response = await fetch(`/my/mindpal/${conversationId}/send`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'text/event-stream' },
        body: JSON.stringify({ message }),
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      await pumpStream(response);
    } catch {
      setMessages((prev) => {
        const updated = [...prev];
        const last = updated[updated.length - 1];
        if (last && last.role === 'assistant' && !last.content) {
          updated[updated.length - 1] = { ...last, content: 'Ralat semasa mendapatkan jawapan. Sila cuba lagi.' };
        }
        return updated;
      });
    } finally {
      setIsStreaming(false);
      setMessages((prev) => prev.map(({ _streaming, ...rest }) => rest));
      router.reload({ only: ['conversations', 'activeConversation'] });
    }
  }, [activeConvId, isStreaming, pumpStream]);

  const handleRegenerate = useCallback(async () => {
    if (isStreaming || !activeConvId) return;
    const hasQuestion = messages.some((m) => m.role === 'user');
    if (!hasQuestion) return;

    setIsStreaming(true);
    setMessages((prev) => {
      const updated = [...prev];
      const last = updated[updated.length - 1];
      const fresh = { role: 'assistant', content: '', sources: [], _streaming: true };
      if (last && last.role === 'assistant') {
        updated[updated.length - 1] = fresh;
      } else {
        updated.push(fresh);
      }
      return updated;
    });

    try {
      const response = await fetch(`/my/mindpal/${activeConvId}/regenerate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'text/event-stream' },
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      await pumpStream(response);
    } catch {
      setMessages((prev) => {
        const updated = [...prev];
        const last = updated[updated.length - 1];
        if (last && last.role === 'assistant' && !last.content) {
          updated[updated.length - 1] = { ...last, content: 'Ralat semasa menjana semula. Sila cuba lagi.' };
        }
        return updated;
      });
    } finally {
      setIsStreaming(false);
      setMessages((prev) => prev.map(({ _streaming, ...rest }) => rest));
      router.reload({ only: ['conversations', 'activeConversation'] });
    }
  }, [activeConvId, isStreaming, messages, pumpStream]);

  const lastAssistantIndex = (() => {
    for (let i = messages.length - 1; i >= 0; i -= 1) {
      if (messages[i].role === 'assistant') return i;
    }
    return -1;
  })();

  const hero = (
    <PageHeader title={ASSISTANT_NAME} subtitle="Pembantu pembelajaran AI anda">
      <HeroStat icon={BrainCircuit} label="Perbualan" value={conversations?.length || 0} />
      <HeroStat icon={FileText} label="Dokumen" value={documentsCount || 0} iconClassName="bg-emerald-400/20" />
    </PageHeader>
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={ASSISTANT_NAME} />

      <div className="pt-4">
        <div className="mb-3 lg:hidden">
          <button
            onClick={() => setSidebarOpen(true)}
            className="flex items-center gap-2 rounded-xl bg-white/80 px-4 py-2.5 text-[13px] font-semibold text-ink shadow-sm ring-1 ring-black/[0.04] backdrop-blur-sm transition-colors hover:bg-white"
          >
            <Menu className="h-4 w-4" strokeWidth={2} />
            <span>Perbualan ({conversations?.length || 0})</span>
          </button>
        </div>

        {documentsCount === 0 && <NoDocumentsWarning />}

        <div className="fade-up flex gap-4" style={{ minHeight: 'calc(100dvh - 340px)' }}>
          <div className="hidden w-[280px] shrink-0 lg:block">
            <div className="glass-card sticky top-24 overflow-hidden rounded-2xl shadow-sm" style={{ maxHeight: 'calc(100dvh - 200px)' }}>
              <ConversationSidebar
                conversations={conversations || []}
                activeId={activeConvId}
                onNew={handleNewConversation}
                onSelect={handleSelectConversation}
                onDelete={handleDeleteConversation}
              />
            </div>
          </div>

          {sidebarOpen && (
            <div className="fixed inset-0 z-50 lg:hidden">
              <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={() => setSidebarOpen(false)} />
              <div className="absolute bottom-0 left-0 right-0 max-h-[70dvh] overflow-hidden rounded-t-3xl bg-white shadow-2xl">
                <ConversationSidebar
                  conversations={conversations || []}
                  activeId={activeConvId}
                  onNew={handleNewConversation}
                  onSelect={handleSelectConversation}
                  onDelete={handleDeleteConversation}
                  onClose={() => setSidebarOpen(false)}
                />
              </div>
            </div>
          )}

          <div className="glass-card flex min-w-0 flex-1 flex-col overflow-hidden rounded-2xl shadow-sm">
            {!activeConvId && messages.length === 0 ? (
              <>
                <EmptyChatState onPick={handleSend} disabled={isStreaming || documentsCount === 0} />
                <InputBar onSend={handleSend} disabled={isStreaming || documentsCount === 0} />
              </>
            ) : (
              <>
                <div className="flex-1 overflow-y-auto scroll-thin px-4 py-4 sm:px-6">
                  <div className="mx-auto max-w-3xl space-y-5">
                    {messages.map((msg, i) => (
                      <ChatMessage
                        key={i}
                        message={msg}
                        isStreaming={msg._streaming || false}
                        canRegenerate={i === lastAssistantIndex && !isStreaming && !!activeConvId}
                        onRegenerate={handleRegenerate}
                      />
                    ))}
                    <div ref={messagesEndRef} />
                  </div>
                </div>

                <InputBar onSend={handleSend} disabled={isStreaming || documentsCount === 0} />
              </>
            )}
          </div>
        </div>
      </div>
    </StudentLayout>
  );
}
