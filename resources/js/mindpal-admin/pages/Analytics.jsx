import { Head } from '@inertiajs/react';
import { MessageSquare, MessagesSquare, TrendingUp, CalendarDays, Zap, FileText, Users } from 'lucide-react';
import MindpalLayout from '@/mindpal-admin/layouts/MindpalLayout';
import { Card, StatTile } from '@/mindpal-admin/components/Ui';
import { cn, formatNumber } from '@/mindpal-admin/lib/utils';

function BarChart({ data }) {
  if (!data || data.length === 0) {
    return (
      <p className="py-12 text-center text-[13px] text-white/40">No message data in the last 30 days</p>
    );
  }

  const maxCount = Math.max(...data.map((d) => d.count), 1);

  return (
    <div className="flex items-end gap-[3px] overflow-x-auto pb-2" style={{ minHeight: 140 }}>
      {data.map((item) => {
        const heightPct = (item.count / maxCount) * 100;
        const day = new Date(item.date).getDate();

        return (
          <div key={item.date} className="group flex flex-col items-center gap-1" style={{ flex: '1 1 0', minWidth: 14 }}>
            <div className="relative w-full">
              <div
                className="mx-auto w-full max-w-[18px] rounded-t-md bg-gradient-to-t from-violet-600 to-violet-400 transition-all group-hover:from-violet-500 group-hover:to-violet-300"
                style={{ height: `${Math.max(heightPct, 3)}px` }}
                title={`${item.date}: ${item.count} messages`}
              />
              <div className="pointer-events-none absolute -top-7 left-1/2 -translate-x-1/2 rounded-md bg-white/10 px-1.5 py-0.5 text-[10px] font-semibold text-white opacity-0 backdrop-blur transition-opacity group-hover:opacity-100">
                {item.count}
              </div>
            </div>
            <span className="text-[9px] tabular-nums text-white/30">{day}</span>
          </div>
        );
      })}
    </div>
  );
}

function TopDocumentsTable({ documents }) {
  if (!documents || documents.length === 0) {
    return <p className="py-6 text-center text-[13px] text-white/40">No documents yet</p>;
  }

  return (
    <div className="divide-y divide-white/6">
      {documents.map((doc, i) => (
        <div key={doc.id} className="flex items-center gap-3 px-4 py-3">
          <span className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-violet-500/10 text-[11px] font-bold text-violet-400">
            {i + 1}
          </span>
          <div className="min-w-0 flex-1">
            <p className="truncate text-[13px] font-medium text-white">{doc.title}</p>
            <p className="text-[11px] text-white/40">
              {doc.total_pages ?? 0} pages, {doc.total_chunks ?? doc.chunks_count ?? 0} chunks
            </p>
          </div>
        </div>
      ))}
    </div>
  );
}

function ActiveUsersTable({ users }) {
  if (!users || users.length === 0) {
    return <p className="py-6 text-center text-[13px] text-white/40">No active users yet</p>;
  }

  return (
    <div className="divide-y divide-white/6">
      {users.map((item, i) => (
        <div key={i} className="flex items-center gap-3 px-4 py-3">
          <span className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-emerald-500/10 text-[11px] font-bold text-emerald-400">
            {i + 1}
          </span>
          <div className="min-w-0 flex-1">
            <p className="truncate text-[13px] font-medium text-white">{item.user?.name ?? 'Unknown'}</p>
            <p className="truncate text-[11px] text-white/40">{item.user?.email ?? ''}</p>
          </div>
          <span className="text-[13px] font-semibold tabular-nums text-white/70">{item.conversations_count}</span>
        </div>
      ))}
    </div>
  );
}

export default function Analytics({ stats, messagesPerDay, topDocuments, activeUsers, totalTokens }) {
  return (
    <MindpalLayout title="Analytics" subtitle="Usage statistics and insights">
      <Head title="Analytics" />

      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile icon={MessagesSquare} label="Total Messages" value={formatNumber(stats?.totalMessages)} tone="violet" />
        <StatTile icon={MessageSquare} label="Total Conversations" value={formatNumber(stats?.totalConversations)} tone="blue" />
        <StatTile icon={TrendingUp} label="Avg Messages / Conversation" value={stats?.avgMessagesPerConversation ?? 0} tone="green" />
        <StatTile icon={CalendarDays} label="Messages This Week" value={formatNumber(stats?.messagesThisWeek)} tone="amber" />
      </div>

      <Card className="mb-6">
        <div className="border-b border-white/8 px-4 py-3">
          <h2 className="text-[14px] font-bold text-white">Messages Per Day (Last 30 Days)</h2>
        </div>
        <div className="p-4">
          <BarChart data={messagesPerDay} />
        </div>
      </Card>

      <div className="mb-6 grid gap-4 lg:grid-cols-2">
        <Card>
          <div className="flex items-center justify-between border-b border-white/8 px-4 py-3">
            <h2 className="text-[14px] font-bold text-white">Top Documents</h2>
            <FileText className="h-4 w-4 text-white/30" />
          </div>
          <TopDocumentsTable documents={topDocuments} />
        </Card>

        <Card>
          <div className="flex items-center justify-between border-b border-white/8 px-4 py-3">
            <h2 className="text-[14px] font-bold text-white">Most Active Users</h2>
            <Users className="h-4 w-4 text-white/30" />
          </div>
          <ActiveUsersTable users={activeUsers} />
        </Card>
      </div>

      <Card className="px-4 py-3">
        <div className="flex items-center gap-3">
          <Zap className="h-4 w-4 text-amber-400" />
          <span className="text-[13px] text-white/50">Total Tokens Used:</span>
          <span className="text-[15px] font-bold tabular-nums text-white">{formatNumber(totalTokens)}</span>
        </div>
      </Card>
    </MindpalLayout>
  );
}
