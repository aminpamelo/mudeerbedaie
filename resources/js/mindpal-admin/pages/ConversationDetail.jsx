import { Head, router } from '@inertiajs/react';
import { useState, useRef, useEffect, useCallback } from 'react';
import { ArrowLeft, User, Bot, Trash2, FileText, Zap, Send } from 'lucide-react';
import MindpalLayout from '@/mindpal-admin/layouts/MindpalLayout';
import { Card, Button, Badge, Modal } from '@/mindpal-admin/components/Ui';
import { cn } from '@/mindpal-admin/lib/utils';

/* ------------------------------------------------------------------ */
/*  Lightweight, XSS-safe markdown renderer (dark theme)               */
/* ------------------------------------------------------------------ */
function CitationPill({ pages, title }) {
  return (
    <span
      title={title ? `${title} — page ${pages}` : `Page ${pages}`}
      className="mx-0.5 inline-flex items-center gap-1 rounded-md bg-violet-500/15 px-1.5 py-0.5 align-baseline text-[11px] font-semibold text-violet-300 ring-1 ring-inset ring-violet-500/25"
    >
      <FileText className="h-3 w-3" strokeWidth={2.2} />
      p.{pages}
    </span>
  );
}

function renderBold(text, keyBase) {
  return text.split(/(\*\*[^*]+\*\*)/g).map((part, i) => {
    if (part.startsWith('**') && part.endsWith('**')) {
      return <strong key={`${keyBase}-b${i}`} className="font-semibold text-white">{part.slice(2, -2)}</strong>;
    }
    return part;
  });
}

function renderInline(text, keyBase) {
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
    const comma = inner.indexOf(',');
    const title = (comma >= 0 ? inner.slice(0, comma) : inner).trim();
    const pages = ((comma >= 0 ? inner.slice(comma + 1) : inner).match(/\d+/g) || []).join(', ');
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
  const flushPara = () => { if (para.length) { blocks.push({ type: 'p', text: para.join(' ') }); para = []; } };
  const flushList = () => { if (list) { blocks.push(list); list = null; } };

  lines.forEach((raw) => {
    const line = raw.trim();
    // A blank line ends a paragraph but should NOT split a list — keep the
    // list open so items separated by blank lines still number 1, 2, 3...
    if (line === '') { flushPara(); return; }
    const heading = line.match(/^(#{1,3})\s+(.*)$/);
    const ordered = line.match(/^(\d+)\.\s+(.*)$/);
    const bullet = line.match(/^[-*]\s+(.*)$/);
    if (heading) {
      flushPara(); flushList();
      blocks.push({ type: 'h', text: heading[2] });
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
    <div className="space-y-2.5">
      {blocks.map((block, i) => {
        if (block.type === 'h') {
          return <p key={i} className="font-bold text-white">{renderInline(block.text, `h${i}`)}</p>;
        }
        if (block.type === 'ol') {
          return (
            <ol key={i} className="list-decimal space-y-1 pl-5 marker:font-semibold marker:text-violet-400">
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
/*  Message bubble                                                     */
/* ------------------------------------------------------------------ */
function MessageBubble({ message, isStreaming }) {
  const isUser = message.role === 'user';
  const showTyping = isStreaming && !isUser && !message.content;

  return (
    <div className={cn('flex gap-3', isUser ? 'flex-row-reverse' : 'flex-row')}>
      <div className={cn(
        'grid h-8 w-8 shrink-0 place-items-center rounded-xl',
        isUser ? 'bg-violet-500/15' : 'bg-emerald-500/15'
      )}>
        {isUser
          ? <User className="h-4 w-4 text-violet-400" strokeWidth={2} />
          : <Bot className="h-4 w-4 text-emerald-400" strokeWidth={2} />}
      </div>

      <div className={cn('flex max-w-[75%] min-w-0 flex-col', isUser ? 'items-end' : 'items-start')}>
        <div className={cn(
          'rounded-2xl px-4 py-3 text-[13.5px] leading-relaxed',
          isUser
            ? 'bg-violet-500/15 text-white ring-1 ring-inset ring-violet-500/20'
            : 'bg-white/6 text-white/90 ring-1 ring-inset ring-white/8'
        )}>
          {isUser ? (
            <p className="whitespace-pre-wrap">{message.content}</p>
          ) : showTyping ? (
            <span className="flex items-center gap-1 py-1">
              <span className="h-2 w-2 animate-bounce rounded-full bg-emerald-400/70 [animation-delay:-0.3s]" />
              <span className="h-2 w-2 animate-bounce rounded-full bg-emerald-400/70 [animation-delay:-0.15s]" />
              <span className="h-2 w-2 animate-bounce rounded-full bg-emerald-400/70" />
            </span>
          ) : (
            <MarkdownContent text={message.content} />
          )}
          {isStreaming && !isUser && message.content && (
            <span className="ml-0.5 inline-block h-4 w-1.5 animate-pulse rounded-full bg-emerald-400/70 align-middle" />
          )}
        </div>

        {(message.created_at || message.tokens_used > 0) && (
          <div className={cn('mt-1.5 flex flex-wrap items-center gap-2 text-[11px] text-white/35', isUser ? 'justify-end' : 'justify-start')}>
            {message.created_at && <span>{message.created_at}</span>}
            {message.tokens_used > 0 && (
              <span className="flex items-center gap-0.5">
                <Zap className="h-3 w-3" />
                {message.tokens_used} tokens
              </span>
            )}
          </div>
        )}

        {!isUser && message.sources && message.sources.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-1.5">
            {message.sources.map((src, i) => (
              <Badge key={i} color="blue">
                <FileText className="h-3 w-3" />
                {(src.title || src.document_title || `Source ${i + 1}`)}
                {src.page_number ? `, p.${src.page_number}` : ''}
              </Badge>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

export default function ConversationDetail({ conversation, messages: initialMessages }) {
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [messages, setMessages] = useState(initialMessages || []);
  const [text, setText] = useState('');
  const [isStreaming, setIsStreaming] = useState(false);
  const messagesEndRef = useRef(null);
  const textareaRef = useRef(null);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleDelete = () => {
    router.delete(`/admin/mindpal/conversations/${conversation.id}`, {
      onSuccess: () => setDeleteOpen(false),
    });
  };

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
              const u = [...prev];
              const last = u[u.length - 1];
              if (last && last.role === 'assistant') {
                u[u.length - 1] = { ...last, content: last.content + parsed.token };
              }
              return u;
            });
          }
          if (parsed.sources) {
            setMessages((prev) => {
              const u = [...prev];
              const last = u[u.length - 1];
              if (last && last.role === 'assistant') {
                u[u.length - 1] = { ...last, sources: parsed.sources };
              }
              return u;
            });
          }
        } catch {
          /* skip malformed chunk */
        }
      }
    }
  }, []);

  const handleSend = useCallback(async () => {
    const message = text.trim();
    if (!message || isStreaming) return;

    setText('');
    if (textareaRef.current) textareaRef.current.style.height = 'auto';

    setMessages((prev) => [
      ...prev,
      { role: 'user', content: message, sources: [] },
      { role: 'assistant', content: '', sources: [], _streaming: true },
    ]);
    setIsStreaming(true);

    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
      const response = await fetch(`/admin/mindpal/conversations/${conversation.id}/send`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'text/event-stream' },
        body: JSON.stringify({ message }),
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      await pumpStream(response);
    } catch {
      setMessages((prev) => {
        const u = [...prev];
        const last = u[u.length - 1];
        if (last && last.role === 'assistant' && !last.content) {
          u[u.length - 1] = { ...last, content: 'Error getting a response. Please try again.' };
        }
        return u;
      });
    } finally {
      setIsStreaming(false);
      setMessages((prev) => prev.map(({ _streaming, ...rest }) => rest));
    }
  }, [text, isStreaming, conversation.id, pumpStream]);

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  const handleInput = (e) => {
    const el = e.target;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 140) + 'px';
    setText(el.value);
  };

  return (
    <MindpalLayout
      title={conversation.title}
      subtitle={conversation.user ? `${conversation.user.name} (${conversation.user.email})` : 'Unknown user'}
      actions={
        <div className="flex items-center gap-2">
          <Button variant="secondary" href="/admin/mindpal/conversations">
            <ArrowLeft className="h-4 w-4" />
            Back
          </Button>
          <Button variant="danger" onClick={() => setDeleteOpen(true)}>
            <Trash2 className="h-4 w-4" />
            Delete
          </Button>
        </div>
      }
    >
      <Head title={conversation.title} />

      <Card className="mb-4 px-4 py-3">
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[12.5px] text-white/50">
          <span>Started: {conversation.created_at}</span>
          <span>{messages.length} messages</span>
          {conversation.user && <span>User: {conversation.user.name}</span>}
        </div>
      </Card>

      <div className="space-y-4 pb-4">
        {messages.length > 0 ? (
          messages.map((msg, i) => (
            <MessageBubble key={msg.id ?? `live-${i}`} message={msg} isStreaming={msg._streaming || false} />
          ))
        ) : (
          <p className="py-12 text-center text-[13px] text-white/40">
            No messages yet — ask the knowledge base something below.
          </p>
        )}
        <div ref={messagesEndRef} />
      </div>

      {/* Chat input */}
      <div className="sticky bottom-4 mt-2">
        <Card className="flex items-end gap-2 p-2">
          <textarea
            ref={textareaRef}
            value={text}
            onChange={handleInput}
            onKeyDown={handleKeyDown}
            placeholder="Ask the knowledge base anything..."
            rows={1}
            disabled={isStreaming}
            className="flex-1 resize-none bg-transparent px-3 py-2 text-[13.5px] text-white placeholder:text-white/30 focus:outline-none disabled:opacity-50"
          />
          <Button variant="primary" onClick={handleSend} disabled={isStreaming || !text.trim()}>
            <Send className="h-4 w-4" />
            {isStreaming ? 'Thinking...' : 'Send'}
          </Button>
        </Card>
      </div>

      <Modal
        open={deleteOpen}
        onClose={() => setDeleteOpen(false)}
        title="Delete Conversation"
        hint="This action cannot be undone"
        footer={
          <>
            <Button variant="secondary" onClick={() => setDeleteOpen(false)}>Cancel</Button>
            <Button variant="danger" onClick={handleDelete}>Delete</Button>
          </>
        }
      >
        <p className="text-[13.5px] text-white/70">
          Are you sure you want to delete this conversation and all its messages?
        </p>
      </Modal>
    </MindpalLayout>
  );
}
