import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
  CheckCircle2,
  Copy,
  Link as LinkIcon,
  Pause,
  Pencil,
  Play,
  Plus,
  Trash2,
  Users,
  XCircle,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

function StatusBadge({ status }) {
  const map = {
    draft: { label: 'Draft', tone: 'bg-[#F5F5F5] text-[#525252]' },
    open: { label: 'Open', tone: 'bg-[#ECFDF5] text-[#059669]' },
    paused: { label: 'Paused', tone: 'bg-[#FEF3C7] text-[#B45309]' },
    closed: { label: 'Closed', tone: 'bg-[#FFE4E6] text-[#9F1239]' },
  };
  const entry = map[status] ?? map.draft;
  return (
    <span
      className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${entry.tone}`}
    >
      {entry.label}
    </span>
  );
}

function formatDate(iso) {
  if (!iso) {
    return '—';
  }
  try {
    return new Date(iso).toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  } catch {
    return iso;
  }
}

export default function CampaignsIndex() {
  const { campaigns } = usePage().props;
  const [copiedId, setCopiedId] = useState(null);

  const rows = useMemo(() => campaigns?.data ?? [], [campaigns]);

  const handleCopy = async (url, id) => {
    if (!url || typeof navigator === 'undefined') {
      return;
    }
    try {
      await navigator.clipboard.writeText(url);
      setCopiedId(id);
      window.setTimeout(() => setCopiedId(null), 1500);
    } catch {
      /* noop */
    }
  };

  const runLifecycle = (verb, campaign) => {
    const url = `/livehost/recruitment/campaigns/${campaign.id}/${verb}`;
    router.patch(
      url,
      {},
      {
        preserveScroll: true,
      }
    );
  };

  const handleDelete = (campaign) => {
    if (!window.confirm(`Delete the "${campaign.title}" campaign? This can't be undone.`)) {
      return;
    }
    router.delete(`/livehost/recruitment/campaigns/${campaign.id}`, {
      preserveScroll: true,
    });
  };

  const newCampaignAction = (
    <Link href="/livehost/recruitment/campaigns/create">
      <Button size="sm" className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
        <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
        New campaign
      </Button>
    </Link>
  );

  return (
    <>
      <Head title="Recruitment Campaigns" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Recruitment', 'Campaigns']}
        actions={newCampaignAction}
      />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Recruitment Campaigns
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              {campaigns?.total ?? 0} total campaign{(campaigns?.total ?? 0) === 1 ? '' : 's'}
            </p>
          </div>
        </div>

        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {rows.length === 0 ? (
            <div className="py-16 text-center">
              <div className="text-sm text-[#737373]">No recruitment campaigns yet.</div>
              <Link
                href="/livehost/recruitment/campaigns/create"
                className="mt-2 inline-block text-sm font-medium text-[#059669] hover:text-[#047857]"
              >
                Create your first campaign
              </Link>
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                  <th className="px-5 py-3 text-left">Title</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-right">Applicants</th>
                  <th className="px-5 py-3 text-right">Target</th>
                  <th className="px-5 py-3 text-left">Opens</th>
                  <th className="px-5 py-3 text-left">Closes</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((campaign) => (
                  <tr
                    key={campaign.id}
                    className="border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]"
                  >
                    <td className="px-5 py-3.5">
                      <Link
                        href={`/livehost/recruitment/campaigns/${campaign.id}/edit`}
                        className="group block min-w-0"
                      >
                        <div className="truncate text-[13.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A] group-hover:text-[#059669]">
                          {campaign.title}
                        </div>
                        <div className="mt-0.5 truncate text-[11.5px] text-[#737373]">
                          /{campaign.slug}
                        </div>
                      </Link>
                    </td>
                    <td className="px-5 py-3.5">
                      <StatusBadge status={campaign.status} />
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums font-semibold">
                      {campaign.applicants_count}
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[#525252]">
                      {campaign.target_count ?? '—'}
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] text-[#525252]">
                      {formatDate(campaign.opens_at)}
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] text-[#525252]">
                      {formatDate(campaign.closes_at)}
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <div className="inline-flex flex-wrap items-center justify-end gap-1">
                        <Link
                          href={`/livehost/recruitment/campaigns/${campaign.id}/edit`}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="Edit"
                        >
                          <Pencil className="h-[14px] w-[14px]" strokeWidth={2} />
                        </Link>
                        <Link
                          href={`/livehost/recruitment/applicants?campaign=${campaign.id}`}
                          className="inline-flex h-8 items-center gap-1 rounded-md px-2 text-[11.5px] font-medium text-[#525252] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="View applicants for this campaign"
                        >
                          <Users className="h-[12px] w-[12px]" strokeWidth={2.25} /> Applicants
                        </Link>
                        {campaign.public_url && (
                          <button
                            type="button"
                            onClick={() => handleCopy(campaign.public_url, campaign.id)}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                            title={copiedId === campaign.id ? 'Copied!' : 'Copy public link'}
                          >
                            {copiedId === campaign.id ? (
                              <CheckCircle2
                                className="h-[14px] w-[14px] text-[#059669]"
                                strokeWidth={2.25}
                              />
                            ) : (
                              <Copy className="h-[14px] w-[14px]" strokeWidth={2} />
                            )}
                          </button>
                        )}
                        {campaign.status === 'draft' && (
                          <button
                            type="button"
                            onClick={() => runLifecycle('publish', campaign)}
                            className="inline-flex h-8 items-center gap-1 rounded-md px-2 text-[11.5px] font-medium text-[#059669] hover:bg-[#ECFDF5]"
                            title="Publish"
                          >
                            <Play className="h-[12px] w-[12px]" strokeWidth={2.25} /> Publish
                          </button>
                        )}
                        {campaign.status === 'open' && (
                          <button
                            type="button"
                            onClick={() => runLifecycle('pause', campaign)}
                            className="inline-flex h-8 items-center gap-1 rounded-md px-2 text-[11.5px] font-medium text-[#B45309] hover:bg-[#FEF3C7]"
                            title="Pause"
                          >
                            <Pause className="h-[12px] w-[12px]" strokeWidth={2.25} /> Pause
                          </button>
                        )}
                        {campaign.status === 'paused' && (
                          <button
                            type="button"
                            onClick={() => runLifecycle('publish', campaign)}
                            className="inline-flex h-8 items-center gap-1 rounded-md px-2 text-[11.5px] font-medium text-[#059669] opacity-60 cursor-not-allowed"
                            title="Paused campaigns cannot be re-opened; close and create a new one."
                            disabled
                          >
                            <Play className="h-[12px] w-[12px]" strokeWidth={2.25} /> Resume
                          </button>
                        )}
                        {(campaign.status === 'open' || campaign.status === 'paused') && (
                          <button
                            type="button"
                            onClick={() => {
                              if (
                                window.confirm(
                                  `Close "${campaign.title}"? You won't be able to re-open it.`
                                )
                              ) {
                                runLifecycle('close', campaign);
                              }
                            }}
                            className="inline-flex h-8 items-center gap-1 rounded-md px-2 text-[11.5px] font-medium text-[#9F1239] hover:bg-[#FFE4E6]"
                            title="Close"
                          >
                            <XCircle className="h-[12px] w-[12px]" strokeWidth={2.25} /> Close
                          </button>
                        )}
                        {campaign.applicants_count === 0 && (
                          <button
                            type="button"
                            onClick={() => handleDelete(campaign)}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#FFF1F2] hover:text-[#F43F5E]"
                            title="Delete"
                          >
                            <Trash2 className="h-[14px] w-[14px]" strokeWidth={2} />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {campaigns?.last_page > 1 && (
          <div className="flex items-center justify-between">
            <div className="text-xs text-[#737373]">
              Showing {campaigns.from}–{campaigns.to} of {campaigns.total}
            </div>
            <div className="flex gap-1">
              {campaigns.links.map((link, index) => (
                <button
                  key={`${link.label}-${index}`}
                  type="button"
                  disabled={!link.url}
                  onClick={() => {
                    if (link.url) {
                      router.visit(link.url, {
                        preserveScroll: true,
                        preserveState: true,
                      });
                    }
                  }}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                  className={[
                    'min-w-8 h-8 rounded-md px-2 text-xs font-medium',
                    link.active
                      ? 'bg-[#0A0A0A] text-white'
                      : 'text-[#737373] hover:bg-[#F5F5F5]',
                    !link.url ? 'cursor-default opacity-40' : '',
                  ].join(' ')}
                />
              ))}
            </div>
          </div>
        )}

        <div className="text-xs text-[#737373]">
          <LinkIcon className="mr-1 inline-block h-3 w-3" strokeWidth={2} />
          Public application URLs are only shown for open campaigns.
        </div>
      </div>
    </>
  );
}

CampaignsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
