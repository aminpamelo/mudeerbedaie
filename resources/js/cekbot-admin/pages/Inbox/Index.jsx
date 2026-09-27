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
import { getEcho } from '@/cekbot-admin/echo';

export default function Index() {
  const { props } = usePage();
  const conversations = props.conversations ?? [];
  const sessions = props.sessions ?? [];
  const filterSessionId = props.filterSessionId ?? '';

  const [selected, setSelected] = useState(null);
  const [messages, setMessages] = useState([]);
  const [notes, setNotes] = useState([]);
  const [loadingMessages, setLoadingMessages] = useState(false);
  const [sending, setSending] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [live, setLive] = useState(false);
  const [mobileView, setMobileView] = useState('list');
  const selectedIdRef = useRef(null);
  useEffect(() => { selectedIdRef.current = selected?.id ?? null; }, [selected?.id]);

  const loadMessages = useCallback(async (id, { silent = false } = {}) => {
    if (!silent) setLoadingMessages(true);
    try {
      const { data } = await axios.get(route('cekbot.inbox.messages', id));
      setMessages(data.messages);
      setNotes(data.notes || []);
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

  function refresh() {
    if (refreshing) return;
    setRefreshing(true);
    router.reload({
      only: ['conversations'],
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        if (selected) loadMessages(selected.id, { silent: true });
        toast.success('Inbox dikemas kini');
      },
      onError: () => toast.error('Gagal menyegar. Cuba lagi.'),
      onFinish: () => setRefreshing(false),
    });
  }

  // Auto-refresh the OPEN chat every 5s while the tab is visible. Paused when a
  // live socket is connected (it drives updates then) or when the tab is hidden.
  useEffect(() => {
    if (!selected) return undefined;
    const id = setInterval(() => {
      if (!live && document.visibilityState === 'visible') loadMessages(selectedIdRef.current, { silent: true });
    }, 5000);
    return () => clearInterval(id);
  }, [selected?.id, loadMessages, live]);

  // Auto-refresh the CONVERSATION LIST every 8s while visible, and do an instant
  // catch-up the moment the operator returns to the tab. No requests fire while
  // the tab is hidden or while the live socket is handling updates.
  useEffect(() => {
    const reloadList = () => router.reload({ only: ['conversations'], preserveScroll: true, preserveState: true });
    const id = setInterval(() => {
      if (!live && document.visibilityState === 'visible') reloadList();
    }, 8000);
    const onVisible = () => {
      if (document.visibilityState !== 'visible') return;
      reloadList();
      if (selectedIdRef.current) loadMessages(selectedIdRef.current, { silent: true });
    };
    document.addEventListener('visibilitychange', onVisible);
    window.addEventListener('focus', onVisible);
    return () => {
      clearInterval(id);
      document.removeEventListener('visibilitychange', onVisible);
      window.removeEventListener('focus', onVisible);
    };
  }, [live, loadMessages]);

  // Real-time inbox via Laravel Reverb (WebSocket). Instantly refreshes the
  // conversation list and the open chat when a message is stored server-side.
  useEffect(() => {
    const echo = getEcho();
    if (!echo) return undefined;

    const conn = echo.connector?.pusher?.connection;
    const onState = () => setLive(conn?.state === 'connected');
    conn?.bind('state_change', onState);
    onState();

    const channel = echo.private('cekbot-inbox').listen('.message.new', (e) => {
      router.reload({ only: ['conversations'], preserveScroll: true, preserveState: true });
      if (selectedIdRef.current && Number(e.conversation_id) === Number(selectedIdRef.current)) {
        loadMessages(selectedIdRef.current, { silent: true });
      } else if (e.direction === 'in') {
        toast('💬 Mesej baru masuk', { id: 'cekbot-new-msg' });
      }
    });

    return () => {
      conn?.unbind('state_change', onState);
      echo.leave('cekbot-inbox');
    };
  }, [loadMessages]);

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

  function assign(userId) {
    if (!selected) return;
    router.post(route('cekbot.inbox.assign', selected.id), { assigned_to: userId }, {
      preserveScroll: true, preserveState: true, onSuccess: () => loadMessages(selected.id, { silent: true }),
    });
  }

  function setConvLabels(labels) {
    if (!selected) return;
    router.post(route('cekbot.inbox.labels', selected.id), { labels }, {
      preserveScroll: true, preserveState: true, onSuccess: () => loadMessages(selected.id, { silent: true }),
    });
  }

  function addNote(body, done) {
    if (!selected) return;
    router.post(route('cekbot.inbox.notes', selected.id), { body }, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => { loadMessages(selected.id, { silent: true }); if (done) done(); },
    });
  }

  return (
    <CekbotLayout
      title="Mesej"
      subtitle="Inbox WhatsApp masuk & balasan"
      actions={
        <div className="flex items-center gap-2">
          <span
            className={`inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[12px] font-semibold ${live ? 'bg-emerald-500/12 text-emerald-300' : 'bg-amber-500/12 text-amber-300'}`}
            title={live ? 'Sambungan masa nyata aktif' : 'Auto-segerak setiap beberapa saat semasa tab aktif'}
          >
            <span className={`h-2 w-2 rounded-full animate-pulse ${live ? 'bg-emerald-400' : 'bg-amber-400'}`} />
            {live ? 'Live' : 'Auto'}
          </span>
          <Button variant="secondary" onClick={refresh} disabled={refreshing}>
            <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} strokeWidth={2.2} /> {refreshing ? 'Menyegar…' : 'Segar semula'}
          </Button>
        </div>
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
            staff={props.staff || []}
            availableLabels={props.availableLabels || []}
            notes={notes}
            onAssign={assign}
            onLabels={setConvLabels}
            onAddNote={addNote}
            canReply
          />
        </div>
      </div>
    </CekbotLayout>
  );
}
