import { Head } from '@inertiajs/react';
import { FileText, CheckCircle, Layers, MessageSquare, MessagesSquare } from 'lucide-react';
import MindpalLayout from '@/mindpal-admin/layouts/MindpalLayout';
import { Card, StatTile, Badge } from '@/mindpal-admin/components/Ui';
import { cn, formatNumber } from '@/mindpal-admin/lib/utils';

const STATUS_BADGE = {
  processing: { color: 'yellow', label: 'Processing' },
  ready: { color: 'green', label: 'Ready' },
  failed: { color: 'red', label: 'Failed' },
};

function RecentDocuments({ documents }) {
  if (!documents?.length) {
    return <p className="py-6 text-center text-[13px] text-white/40">No documents uploaded yet</p>;
  }

  return (
    <div className="divide-y divide-white/6">
      {documents.map((doc) => {
        const badge = STATUS_BADGE[doc.status] || STATUS_BADGE.processing;
        return (
          <div key={doc.id} className="flex items-center justify-between gap-3 px-4 py-3">
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <span className="truncate text-[13.5px] font-medium text-white">{doc.title}</span>
                <Badge color={badge.color}>{badge.label}</Badge>
              </div>
              <div className="mt-0.5 flex items-center gap-3 text-[11.5px] text-white/40">
                {doc.uploader && <span>{doc.uploader}</span>}
                <span>{doc.created_at}</span>
                {doc.total_pages != null && <span>{doc.total_pages} pages</span>}
                {doc.total_chunks != null && <span>{doc.total_chunks} chunks</span>}
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

export default function Overview({ stats, recentDocuments }) {
  return (
    <MindpalLayout title="Overview" subtitle="MindPal knowledge base dashboard">
      <Head title="Overview" />

      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <StatTile icon={FileText} label="Total Documents" value={formatNumber(stats?.totalDocuments)} tone="violet" />
        <StatTile icon={CheckCircle} label="Ready" value={formatNumber(stats?.readyDocuments)} tone="green" />
        <StatTile icon={Layers} label="Total Chunks" value={formatNumber(stats?.totalChunks)} tone="blue" />
        <StatTile icon={MessageSquare} label="Conversations" value={formatNumber(stats?.totalConversations)} tone="amber" />
        <StatTile icon={MessagesSquare} label="Messages" value={formatNumber(stats?.totalMessages)} tone="slate" />
      </div>

      <Card>
        <div className="border-b border-white/8 px-4 py-3">
          <h2 className="text-[14px] font-bold text-white">Recent Documents</h2>
        </div>
        <RecentDocuments documents={recentDocuments} />
      </Card>
    </MindpalLayout>
  );
}
