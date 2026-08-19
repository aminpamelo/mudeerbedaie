import { Head, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import { MessageSquare, Search, Trash2, ChevronRight, User, Plus } from 'lucide-react';
import MindpalLayout from '@/mindpal-admin/layouts/MindpalLayout';
import { Card, Badge, Button, Input, EmptyState, Pagination } from '@/mindpal-admin/components/Ui';
import { cn, formatNumber } from '@/mindpal-admin/lib/utils';

function ConversationRow({ conversation, onDelete }) {
  return (
    <Card className="group transition-colors hover:border-white/15">
      <div className="flex items-center gap-4 p-4">
        <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-violet-500/15">
          <MessageSquare className="h-4 w-4 text-violet-400" strokeWidth={2} />
        </div>

        <div className="min-w-0 flex-1">
          <a
            href={`/admin/mindpal/conversations/${conversation.id}`}
            className="block truncate text-[14px] font-semibold text-white hover:text-violet-400 transition-colors"
            onClick={(e) => {
              e.preventDefault();
              router.get(`/admin/mindpal/conversations/${conversation.id}`);
            }}
          >
            {conversation.title}
          </a>
          <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11.5px] text-white/40">
            {conversation.user && (
              <span className="flex items-center gap-1">
                <User className="h-3 w-3" />
                {conversation.user.name}
              </span>
            )}
            <span>{conversation.messages_count} messages</span>
            <span>{conversation.updated_at}</span>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <Badge color="violet">{conversation.messages_count}</Badge>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onDelete(conversation.id)}
            title="Delete"
          >
            <Trash2 className="h-3.5 w-3.5 text-rose-400" />
          </Button>
          <a
            href={`/admin/mindpal/conversations/${conversation.id}`}
            className="grid h-8 w-8 place-items-center rounded-lg text-white/30 transition-colors hover:bg-white/8 hover:text-white"
            onClick={(e) => {
              e.preventDefault();
              router.get(`/admin/mindpal/conversations/${conversation.id}`);
            }}
          >
            <ChevronRight className="h-4 w-4" />
          </a>
        </div>
      </div>
    </Card>
  );
}

export default function Conversations({ conversations, filters, totalConversations }) {
  const [search, setSearch] = useState(filters?.search || '');
  const [deleteConfirm, setDeleteConfirm] = useState(null);
  const [creating, setCreating] = useState(false);

  const handleNewConversation = useCallback(async () => {
    if (creating) return;
    setCreating(true);
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
      const res = await fetch('/admin/mindpal/conversations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
      });
      const data = await res.json();
      router.get(`/admin/mindpal/conversations/${data.id}`);
    } catch {
      setCreating(false);
    }
  }, [creating]);

  const applyFilters = useCallback((newSearch) => {
    router.get('/admin/mindpal/conversations', {
      search: newSearch || undefined,
    }, {
      preserveState: true,
      preserveScroll: true,
    });
  }, []);

  const handleSearch = (e) => {
    const v = e.target.value;
    setSearch(v);
    clearTimeout(window._mindpalConvSearchTimeout);
    window._mindpalConvSearchTimeout = setTimeout(() => applyFilters(v), 300);
  };

  const handleDelete = (id) => {
    if (deleteConfirm === id) {
      router.delete(`/admin/mindpal/conversations/${id}`, { preserveScroll: true });
      setDeleteConfirm(null);
    } else {
      setDeleteConfirm(id);
      setTimeout(() => setDeleteConfirm(null), 3000);
    }
  };

  const items = conversations?.data || [];

  return (
    <MindpalLayout
      title="Conversations"
      subtitle={`${formatNumber(totalConversations)} total conversations`}
      actions={
        <Button variant="primary" onClick={handleNewConversation} disabled={creating}>
          <Plus className="h-4 w-4" />
          {creating ? 'Starting...' : 'New Conversation'}
        </Button>
      }
    >
      <Head title="Conversations" />

      <div className="mb-4">
        <div className="relative">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/30" />
          <Input
            value={search}
            onChange={handleSearch}
            placeholder="Search conversations..."
            className="pl-10"
          />
        </div>
      </div>

      {items.length > 0 ? (
        <div className="space-y-3">
          {items.map((conv) => (
            <ConversationRow
              key={conv.id}
              conversation={conv}
              onDelete={handleDelete}
            />
          ))}
        </div>
      ) : (
        <EmptyState
          icon={MessageSquare}
          title="No conversations found"
          hint={filters?.search
            ? 'Try adjusting your search query'
            : 'Conversations will appear here when students start chatting'}
        />
      )}

      <Pagination
        links={conversations?.links}
        meta={conversations?.meta || { from: conversations?.from, to: conversations?.to, total: conversations?.total }}
      />
    </MindpalLayout>
  );
}
