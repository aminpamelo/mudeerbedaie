import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { RefreshCw } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Button } from '@/cekbot-admin/components/Ui';
import ConversationList from '@/cekbot-admin/components/inbox/ConversationList';
import ChatPanel from '@/cekbot-admin/components/inbox/ChatPanel';
import { csrfToken } from '@/cekbot-admin/lib/utils';

export default function Index() {
  const { props } = usePage();
  const conversations = props.conversations ?? [];
  const sessions = props.sessions ?? [];
  const filterSessionId = props.filterSessionId ?? '';

  const [selected, setSelected] = useState(null);
  const [messages, setMessages] = useState([]);
  const [loadingMessages, setLoadingMessages] = useState(false);
  const [sending, setSending] = useState(false);
  const [mobileView, setMobileView] = useState('list');
  const pollRef = useRef(null);

  const loadMessages = useCallback(async (id, { silent = false } = {}) => {
    if (!silent) setLoadingMessages(true);
    try {
      const { data } = await axios.get(route('cekbot.inbox.messages', id));
      setMessages(data.messages);
      setSelected((prev) => (prev && prev.id === id ? { ...prev, ...data.conversation } : prev));
    } catch {
      /* transient */
    } finally {
      if (!silent) setLoadingMessages(false);
    }
  }, []);

  function selectConversation(c) {
    setSelected({ ...c, unread_count: 0 });
    setMessages([]);
    setMobileView('chat');
    loadMessages(c.id);
  }

  // Poll the open conversation for new messages.
  useEffect(() => {
    if (!selected) return undefined;
    pollRef.current = setInterval(() => loadMessages(selected.id, { silent: true }), 5000);
    return () => clearInterval(pollRef.current);
  }, [selected?.id, loadMessages]);

  // Periodically refresh the conversation list.
  useEffect(() => {
    const t = setInterval(() => router.reload({ only: ['conversations'], preserveScroll: true, preserveState: true }), 12000);
    return () => clearInterval(t);
  }, []);

  async function send(text, clear) {
    if (!selected) return;
    setSending(true);
    try {
      const { data } = await axios.post(
        route('cekbot.inbox.reply', selected.id),
        { message: text },
        { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } },
      );
      setMessages((prev) => [...prev, data.message]);
      clear();
      if (!data.success) toast.error(data.error || 'Gagal menghantar mesej.');
    } catch (e) {
      toast.error(e.response?.data?.error || e.response?.data?.message || 'Gagal menghantar mesej.');
    } finally {
      setSending(false);
    }
  }

  function changeSession(e) {
    const value = e.target.value;
    router.get(route('cekbot.inbox'), value ? { session: value } : {}, { preserveScroll: true });
  }

  function handover() {
    if (!selected) return;
    router.post(route('cekbot.inbox.handover', selected.id), {}, {
      preserveScroll: true, preserveState: true, onSuccess: () => loadMessages(selected.id, { silent: true }),
    });
  }

  function release() {
    if (!selected) return;
    router.post(route('cekbot.inbox.release', selected.id), {}, {
      preserveScroll: true, preserveState: true, onSuccess: () => loadMessages(selected.id, { silent: true }),
    });
  }

  return (
    <CekbotLayout
      title="Mesej"
      subtitle="Inbox WhatsApp masuk & balasan"
      actions={
        <Button variant="secondary" onClick={() => router.reload({ only: ['conversations'] })}>
          <RefreshCw className="h-4 w-4" strokeWidth={2.2} /> Segar semula
        </Button>
      }
    >
      <Head title="Mesej" />

      <div className="flex h-[calc(100dvh-13rem)] min-h-[440px] overflow-hidden rounded-2xl border border-white/8 bg-white/[0.03]">
        <div className={`flex w-full flex-col lg:w-[340px] lg:shrink-0 lg:border-r lg:border-white/8 ${mobileView === 'chat' ? 'hidden lg:flex' : 'flex'}`}>
          {sessions.length > 1 && (
            <div className="border-b border-white/8 p-2.5">
              <select
                value={filterSessionId || ''}
                onChange={changeSession}
                className="w-full cursor-pointer appearance-none rounded-lg border-0 bg-white/8 px-3 py-2 text-[12.5px] text-white ring-1 ring-inset ring-white/10 focus:outline-none focus:ring-2 focus:ring-emerald-500/60"
              >
                <option value="">Semua nombor</option>
                {sessions.map((s) => (
                  <option key={s.id} value={s.id}>{s.label}{s.phone_number ? ` (${s.phone_number})` : ''}</option>
                ))}
              </select>
            </div>
          )}
          <ConversationList
            conversations={conversations}
            selectedId={selected?.id}
            onSelect={selectConversation}
            multiSession={sessions.length > 1}
          />
        </div>

        <div className={`min-w-0 flex-1 ${mobileView === 'list' ? 'hidden lg:flex' : 'flex'}`}>
          <ChatPanel
            conversation={selected}
            messages={messages}
            loading={loadingMessages}
            onSend={send}
            sending={sending}
            onBack={() => setMobileView('list')}
            onHandover={handover}
            onRelease={release}
            canReply
          />
        </div>
      </div>
    </CekbotLayout>
  );
}
