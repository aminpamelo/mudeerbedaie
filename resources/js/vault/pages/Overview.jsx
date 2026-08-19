import { Head } from '@inertiajs/react';
import { Key, FolderOpen, ClipboardList, ShieldCheck, Eye, Plus, Pencil, Trash2, Lock, KeyRound } from 'lucide-react';
import VaultLayout from '@/vault/layouts/VaultLayout';
import { Card, StatTile, Badge, EmptyState } from '@/vault/components/Ui';
import { cn, timeAgo } from '@/vault/lib/utils';

const ACTION_META = {
  created: { verb: 'created', color: 'green', icon: Plus },
  viewed: { verb: 'viewed', color: 'blue', icon: Eye },
  updated: { verb: 'updated', color: 'amber', icon: Pencil },
  deleted: { verb: 'deleted', color: 'red', icon: Trash2 },
  unlocked: { verb: 'unlocked the vault', color: 'green', icon: KeyRound },
  unlock_failed: { verb: 'failed to unlock the vault', color: 'red', icon: Lock },
  locked: { verb: 'locked the vault', color: 'amber', icon: Lock },
  password_set: { verb: 'set the vault password', color: 'amber', icon: KeyRound },
  password_changed: { verb: 'changed the vault password', color: 'amber', icon: KeyRound },
};

function CategoryList({ categories }) {
  if (!categories?.length) {
    return <p className="py-6 text-center text-[13px] text-white/40">No categories yet</p>;
  }

  return (
    <div className="divide-y divide-white/6">
      {categories.map((cat) => (
        <div key={cat.id} className="flex items-center justify-between gap-3 px-4 py-3">
          <div className="flex items-center gap-3 min-w-0">
            <span className="text-[16px]">{cat.icon || '📁'}</span>
            <span className="truncate text-[13.5px] font-medium text-white">{cat.name}</span>
          </div>
          <Badge color="slate">{cat.credentials_count} credential{cat.credentials_count !== 1 ? 's' : ''}</Badge>
        </div>
      ))}
    </div>
  );
}

function ActivityTimeline({ activity }) {
  if (!activity?.length) {
    return <p className="py-6 text-center text-[13px] text-white/40">No recent activity</p>;
  }

  return (
    <div className="divide-y divide-white/6">
      {activity.map((log) => {
        const meta = ACTION_META[log.action] || ACTION_META.viewed;
        const Icon = meta.icon;

        return (
          <div key={log.id} className="flex items-start gap-3 px-4 py-3">
            <span className={cn('mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg', {
              green: 'bg-emerald-500/15',
              blue: 'bg-sky-500/15',
              amber: 'bg-amber-500/15',
              red: 'bg-rose-500/15',
            }[meta.color])}>
              <Icon className={cn('h-3.5 w-3.5', {
                green: 'text-emerald-400',
                blue: 'text-sky-400',
                amber: 'text-amber-400',
                red: 'text-rose-400',
              }[meta.color])} strokeWidth={2.2} />
            </span>
            <div className="min-w-0 flex-1">
              <p className="text-[13px] text-white/80">
                <span className="font-semibold text-white">{log.user}</span>{' '}
                {meta.verb ?? log.action}{' '}
                {log.credential && <span className="font-semibold text-amber-400">{log.credential}</span>}
              </p>
              <p className="mt-0.5 text-[11.5px] text-white/40">{timeAgo(log.created_at)}</p>
            </div>
          </div>
        );
      })}
    </div>
  );
}

export default function Overview({ totalCredentials, byCategory, recentActivity }) {
  const categoriesCount = byCategory?.length ?? 0;
  const recentCount = recentActivity?.length ?? 0;

  return (
    <VaultLayout title="Overview" subtitle="Password vault dashboard">
      <Head title="Overview" />

      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile icon={Key} label="Total Credentials" value={totalCredentials ?? 0} tone="amber" />
        <StatTile icon={FolderOpen} label="Categories" value={categoriesCount} tone="blue" />
        <StatTile icon={ClipboardList} label="Recent Actions" value={recentCount} tone="green" />
        <StatTile icon={ShieldCheck} label="Encryption" value="AES-256" sub="All passwords encrypted" tone="slate" />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <div className="border-b border-white/8 px-4 py-3">
            <h2 className="text-[14px] font-bold text-white">Credentials by Category</h2>
          </div>
          <CategoryList categories={byCategory} />
        </Card>

        <Card>
          <div className="border-b border-white/8 px-4 py-3">
            <h2 className="text-[14px] font-bold text-white">Recent Activity</h2>
          </div>
          <ActivityTimeline activity={recentActivity} />
        </Card>
      </div>
    </VaultLayout>
  );
}
