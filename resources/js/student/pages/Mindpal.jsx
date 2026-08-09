import { Head, usePage, router } from '@inertiajs/react';
import { useState, useRef, useEffect, useCallback } from 'react';
import {
  BrainCircuit, Plus, Send, Trash2, MessageSquare,
  FileText, Menu, X, AlertTriangle,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader, { HeroStat } from '@/student/components/PageHeader';
import { cn } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Conversation Sidebar                                               */
/* ------------------------------------------------------------------ */
function ConversationSidebar({ conversations, activeId, onNew, onSelect, onDelete, onClose }) {
  return (
    <div className="flex h-full flex-col">
      {/* Header + New Chat */}
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
          {/* Close button for mobile */}
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

      {/* Conversation list */}
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
                  conv.id === activeId
                    ? 'bg-violet-100/80 ring-1 ring-violet-200'
                    : 'hover:bg-violet-50/60'
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
                  <p className="text-[11px] text-muted">{conv.time_ago}</p>
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
function ChatMessage({ message, isStreaming }) {
  const isUser = message.role === 'user';

  return (
    <div className={cn('flex gap-3', isUser ? 'justify-end' : 'justify-start')}>
      {!isUser && (
        <div className="mt-1 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-violet-100">
          <BrainCircuit className="h-4 w-4 text-[var(--color-brand)]" strokeWidth={2} />
        </div>
      )}

      <div className={cn('max-w-[80%] sm:max-w-[70%]')}>
        <div
          className={cn(
            'rounded-2xl px-4 py-3 text-[14px] leading-relaxed',
            isUser
              ? 'bg-[var(--color-brand)] text-white rounded-br-md'
              : 'glass-card rounded-bl-md text-ink shadow-sm'
          )}
        >
          <div className="whitespace-pre-wrap break-words">{message.content}</div>
          {isStreaming && !isUser && (
            <span className="mt-1 inline-block h-4 w-1.5 animate-pulse rounded-full bg-violet-400" />
          )}
        </div>

        {/* Source citations */}
        {!isUser && message.sources && message.sources.length > 0 && (
          <div className="mt-1.5 flex flex-wrap gap-1.5">
            {message.sources.map((src, i) => (
              <span
                key={i}
                className="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-medium text-violet-600 ring-1 ring-violet-100"
              >
                <FileText className="h-3 w-3" strokeWidth={2} />
                {src.title}{src.page ? `, p.${src.page}` : ''}
              </span>
            ))}
          </div>
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
          Admin belum memuat naik dokumen. MindPal memerlukan dokumen untuk menjawab soalan anda.
        </p>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Empty Chat State                                                   */
/* ------------------------------------------------------------------ */
function EmptyChatState() {
  return (
    <div className="flex flex-1 flex-col items-center justify-center px-4 text-center">
      <div className="mb-5 grid h-20 w-20 place-items-center rounded-3xl bg-gradient-to-br from-violet-100 to-rose-100">
        <BrainCircuit className="h-9 w-9 text-violet-400" strokeWidth={1.5} />
      </div>
      <h3 className="text-[18px] font-bold text-ink">Mulakan perbualan baru</h3>
      <p className="mx-auto mt-2 max-w-sm text-[14px] leading-relaxed text-muted">
        Tanya soalan berkaitan bahan pembelajaran anda. MindPal akan mencari jawapan daripada dokumen kursus.
      </p>
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
    // Reset textarea height
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

  // Auto-resize textarea
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
          placeholder="Taip soalan anda..."
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
  const { conversations, activeConversation, messages: initialMessages, documentsCount } = usePage().props;

  const [messages, setMessages] = useState(initialMessages || []);
  const [isStreaming, setIsStreaming] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const messagesEndRef = useRef(null);
  const activeConvId = activeConversation?.id || null;

  // Scroll to bottom when messages change
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  // Sync messages when navigating between conversations
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
        // If deleting the active conversation, go back to index
        if (id === activeConvId) {
          router.get('/my/mindpal', {}, { preserveState: false });
        }
      },
    });
  }, [activeConvId]);

  const handleSend = useCallback(async (message) => {
    if (isStreaming) return;

    let conversationId = activeConvId;

    // Add user message to the UI immediately
    const userMessage = { role: 'user', content: message, sources: [] };
    setMessages((prev) => [...prev, userMessage]);

    // If no active conversation, create one first
    if (!conversationId) {
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const createRes = await fetch('/my/mindpal/conversations', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ message }),
        });
        const createData = await createRes.json();
        conversationId = createData.id;

        // Update URL without full page reload
        window.history.replaceState({}, '', `/my/mindpal/${conversationId}`);
      } catch {
        setMessages((prev) => [
          ...prev,
          { role: 'assistant', content: 'Ralat semasa membuat perbualan. Sila cuba lagi.', sources: [] },
        ]);
        return;
      }
    }

    // Add empty assistant message and start streaming
    setIsStreaming(true);
    setMessages((prev) => [...prev, { role: 'assistant', content: '', sources: [], _streaming: true }]);

    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
      const response = await fetch(`/my/mindpal/${conversationId}/send`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'text/event-stream',
        },
        body: JSON.stringify({ message }),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      let done = false;

      while (!done) {
        const result = await reader.read();
        if (result.done) break;

        buffer += decoder.decode(result.value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop(); // keep incomplete line

        for (const line of lines) {
          if (line.startsWith('data: ')) {
            const data = line.slice(6);
            if (data === '[DONE]') {
              done = true;
              break;
            }
            try {
              const parsed = JSON.parse(data);
              if (parsed.token) {
                setMessages((prev) => {
                  const updated = [...prev];
                  const last = updated[updated.length - 1];
                  if (last && last.role === 'assistant') {
                    updated[updated.length - 1] = {
                      ...last,
                      content: last.content + parsed.token,
                    };
                  }
                  return updated;
                });
              }
              // Handle sources if sent at end
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
              // skip malformed JSON
            }
          }
        }
      }
    } catch {
      setMessages((prev) => {
        const updated = [...prev];
        const last = updated[updated.length - 1];
        if (last && last.role === 'assistant' && !last.content) {
          updated[updated.length - 1] = {
            ...last,
            content: 'Ralat semasa mendapatkan jawapan. Sila cuba lagi.',
          };
        }
        return updated;
      });
    } finally {
      setIsStreaming(false);
      // Remove streaming flag
      setMessages((prev) =>
        prev.map((m) => {
          const { _streaming, ...rest } = m;
          return rest;
        })
      );
      // Reload to get updated conversation list
      router.reload({ only: ['conversations', 'activeConversation'] });
    }
  }, [activeConvId, isStreaming]);

  const hero = (
    <PageHeader title="MindPal" subtitle="Pembantu pembelajaran AI anda">
      <HeroStat icon={BrainCircuit} label="Perbualan" value={conversations?.length || 0} />
      <HeroStat icon={FileText} label="Dokumen" value={documentsCount || 0} iconClassName="bg-emerald-400/20" />
    </PageHeader>
  );

  return (
    <StudentLayout hero={hero}>
      <Head title="MindPal" />

      <div className="pt-4">
        {/* Mobile sidebar toggle */}
        <div className="mb-3 lg:hidden">
          <button
            onClick={() => setSidebarOpen(true)}
            className="flex items-center gap-2 rounded-xl bg-white/80 px-4 py-2.5 text-[13px] font-semibold text-ink shadow-sm ring-1 ring-black/[0.04] backdrop-blur-sm transition-colors hover:bg-white"
          >
            <Menu className="h-4 w-4" strokeWidth={2} />
            <span>Perbualan ({conversations?.length || 0})</span>
          </button>
        </div>

        {/* No documents warning */}
        {documentsCount === 0 && <NoDocumentsWarning />}

        {/* Two-panel layout */}
        <div className="fade-up flex gap-4" style={{ minHeight: 'calc(100dvh - 340px)' }}>
          {/* Sidebar — desktop always, mobile overlay */}
          <div className={cn(
            'hidden w-[280px] shrink-0 lg:block',
          )}>
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

          {/* Mobile sidebar overlay */}
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

          {/* Chat area */}
          <div className="glass-card flex min-w-0 flex-1 flex-col overflow-hidden rounded-2xl shadow-sm">
            {!activeConvId && messages.length === 0 ? (
              <>
                <EmptyChatState />
                <InputBar onSend={handleSend} disabled={isStreaming || documentsCount === 0} />
              </>
            ) : (
              <>
                {/* Messages */}
                <div className="flex-1 overflow-y-auto scroll-thin px-4 py-4 sm:px-6">
                  <div className="mx-auto max-w-3xl space-y-4">
                    {messages.map((msg, i) => (
                      <ChatMessage
                        key={i}
                        message={msg}
                        isStreaming={msg._streaming || false}
                      />
                    ))}
                    <div ref={messagesEndRef} />
                  </div>
                </div>

                {/* Input */}
                <InputBar onSend={handleSend} disabled={isStreaming || documentsCount === 0} />
              </>
            )}
          </div>
        </div>
      </div>
    </StudentLayout>
  );
}
