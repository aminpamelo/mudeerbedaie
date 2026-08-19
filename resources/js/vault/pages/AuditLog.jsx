import { Head, router } from '@inertiajs/react';
import { ClipboardList, Eye, Plus, Pencil, Trash2, Lock, KeyRound } from 'lucide-react';
import VaultLayout from '@/vault/layouts/VaultLayout';
import { Card, Badge, Select, EmptyState, Pagination } from '@/vault/components/Ui';
import { cn, timeAgo, formatDate } from '@/vault/lib/utils';

const ACTION_META = {
  created: { label: 'Created', color: 'green' },
  viewed: { label: 'Viewed', color: 'blue' },
  updated: { label: 'Updated', color: 'amber' },
  deleted: { label: 'Deleted', color: 'red' },
  unlocked: { label: 'Unlocked', color: 'green' },
  unlock_failed: { label: 'Unlock failed', color: 'red' },
  locked: { label: 'Locked', color: 'amber' },
  password_set: { label: 'Password set', color: 'amber' },
  password_changed: { label: 'Password changed', color: 'amber' },
};

const ACTION_ICONS = {
  created: Plus,
  viewed: Eye,
  updated: Pencil,
  deleted: Trash2,
  unlocked: KeyRound,
  unlock_failed: Lock,
  locked: Lock,
  password_set: KeyRound,
  password_changed: KeyRound,
};

function ChangesSummary({ changes }) {
  if (!changes) return null;

  const changedKeys = [];
  if (changes.old && changes.new) {
    for (const key of Object.keys(changes.new)) {
      if (changes.old[key] !== changes.new[key]) {
        changedKeys.push(key);
      }
    }
  }

  if (changedKeys.length === 0) return null;

  return (
    <span className="text-[11.5px] text-white/40">
      Changed: {changedKeys.join(', ')}
    </span>
  );
}

export default function AuditLog({ logs, filters }) {
  const items = logs?.data ?? [];
  const paginationLinks = logs?.links;
  const paginationMeta = logs?.meta ?? logs;

  const applyFilter = (key, value) => {
    router.get('/admin/vault/audit-log', {
      ...filters,
      [key]: value || undefined,
    }, { preserveState: true, preserveScroll: true });
  };

  return (
    <VaultLayout
      title="Audit Log"
      subtitle="Track who accessed and modified credentials"
    >
      <Head title="Audit Log" />

      {/* Filters */}
      <div className="mb-5">
        <Select
          value={filters?.action ?? ''}
          onChange={(e) => applyFilter('action', e.target.value)}
          className="w-auto min-w-[160px]"
        >
          <option value="">All Actions</option>
          <option value="created">Created</option>
          <option value="viewed">Viewed</option>
          <option value="updated">Updated</option>
          <option value="deleted">Deleted</option>
          <option value="unlocked">Unlocked</option>
          <option value="unlock_failed">Unlock failed</option>
          <option value="locked">Locked</option>
          <option value="password_set">Password set</option>
          <option value="password_changed">Password changed</option>
        </Select>
      </div>

      {items.length === 0 ? (
        <EmptyState
          icon={ClipboardList}
          title="No activity yet"
          hint="Actions on credentials will appear here."
        />
      ) : (
        <div className="space-y-2">
          {items.map((log) => {
            const meta = ACTION_META[log.action] || ACTION_META.viewed;
            const Icon = ACTION_ICONS[log.action] || Eye;

            return (
              <Card key={log.id} className="flex items-start gap-4 p-4">
                <span className={cn('mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-lg', {
                  green: 'bg-emerald-500/15',
                  blue: 'bg-sky-500/15',
                  amber: 'bg-amber-500/15',
                  red: 'bg-rose-500/15',
                }[meta.color])}>
                  <Icon className={cn('h-4 w-4', {
                    green: 'text-emerald-400',
                    blue: 'text-sky-400',
                    amber: 'text-amber-400',
                    red: 'text-rose-400',
                  }[meta.color])} strokeWidth={2} />
                </span>

                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 flex-wrap">
                    <Badge color={meta.color}>{meta.label}</Badge>
                    <span className="text-[13px] font-semibold text-white">{log.user}</span>
                    {log.credential && (
                      <span className="text-[13px] text-white/50">
                        on <span className="font-medium text-amber-400">{log.credential}</span>
                      </span>
                    )}
                  </div>

                  <div className="mt-1.5 flex flex-wrap items-center gap-3">
                    <ChangesSummary changes={log.changes} />
                    <span className="text-[11.5px] text-white/30">{log.ip_address}</span>
                  </div>
                </div>

                <div className="shrink-0 text-right">
                  <p className="text-[12px] font-medium text-white/50">{timeAgo(log.created_at)}</p>
                  <p className="mt-0.5 text-[11px] text-white/30">{formatDate(log.created_at)}</p>
                </div>
              </Card>
            );
          })}
        </div>
      )}

      <Pagination links={paginationLinks} meta={paginationMeta} />
    </VaultLayout>
  );
}
