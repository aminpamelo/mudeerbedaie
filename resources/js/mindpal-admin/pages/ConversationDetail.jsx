import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, User, Bot, Trash2, FileText, Zap } from 'lucide-react';
import MindpalLayout from '@/mindpal-admin/layouts/MindpalLayout';
import { Card, Button, Badge, Modal } from '@/mindpal-admin/components/Ui';
import { cn } from '@/mindpal-admin/lib/utils';

function MessageBubble({ message }) {
  const isUser = message.role === 'user';

  return (
    <div className={cn('flex gap-3', isUser ? 'flex-row-reverse' : 'flex-row')}>
      <div className={cn(
        'grid h-8 w-8 shrink-0 place-items-center rounded-xl',
        isUser ? 'bg-violet-500/15' : 'bg-emerald-500/15'
      )}>
        {isUser
          ? <User className="h-4 w-4 text-violet-400" strokeWidth={2} />
          : <Bot className="h-4 w-4 text-emerald-400" strokeWidth={2} />
        }
      </div>

      <div className={cn('max-w-[75%] min-w-0', isUser ? 'items-end' : 'items-start')}>
        <div className={cn(
          'rounded-2xl px-4 py-3 text-[13.5px] leading-relaxed',
          isUser
            ? 'bg-violet-500/15 text-white ring-1 ring-inset ring-violet-500/20'
            : 'bg-white/6 text-white/90 ring-1 ring-inset ring-white/8'
        )}>
          <p className="whitespace-pre-wrap">{message.content}</p>
        </div>

        <div className={cn(
          'mt-1.5 flex flex-wrap items-center gap-2 text-[11px] text-white/35',
          isUser ? 'justify-end' : 'justify-start'
        )}>
          <span>{message.created_at}</span>
          {message.tokens_used > 0 && (
            <span className="flex items-center gap-0.5">
              <Zap className="h-3 w-3" />
              {message.tokens_used} tokens
            </span>
          )}
        </div>

        {message.sources && message.sources.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-1.5">
            {message.sources.map((src, i) => (
              <Badge key={i} color="blue">
                <FileText className="h-3 w-3" />
                {src.title || src.document_title || `Source ${i + 1}`}
              </Badge>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

export default function ConversationDetail({ conversation, messages }) {
  const [deleteOpen, setDeleteOpen] = useState(false);

  const handleDelete = () => {
    router.delete(`/admin/mindpal/conversations/${conversation.id}`, {
      onSuccess: () => setDeleteOpen(false),
    });
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
          {conversation.user && <span>Student: {conversation.user.name}</span>}
        </div>
      </Card>

      <div className="space-y-4">
        {messages.length > 0 ? (
          messages.map((msg) => (
            <MessageBubble key={msg.id} message={msg} />
          ))
        ) : (
          <p className="py-12 text-center text-[13px] text-white/40">No messages in this conversation</p>
        )}
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
