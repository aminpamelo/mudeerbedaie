import { useEffect, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Users, Search, Download, MessageCircle, UserPlus, UserCheck, List, LayoutGrid, ChevronDown, X, Building2, Tag } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Select, Input, EmptyState } from '@/cekbot-admin/components/Ui';
import LeadBoard from '@/cekbot-admin/components/leads/LeadBoard';
import CategoryModal from '@/cekbot-admin/components/leads/CategoryModal';
import TaxonomyManagerModal from '@/cekbot-admin/components/leads/TaxonomyManagerModal';
import { cn, contactDisplay, formatPhone, formatDate, timeAgo, initialsFrom } from '@/cekbot-admin/lib/utils';
import { leadColor, avatarTint } from '@/cekbot-admin/lib/leadColors';

function Stat({ icon: Icon, label, value, tint }) {
  return (
    <Card className="flex items-center gap-3.5 p-4">
      <span className={cn('grid h-11 w-11 shrink-0 place-items-center rounded-xl', tint)}>
        <Icon className="h-5 w-5" strokeWidth={2} />
      </span>
      <div>
        <p className="text-[11px] font-semibold uppercase tracking-wide text-white/35">{label}</p>
        <p className="text-[22px] font-bold leading-tight tabular-nums text-white">{value}</p>
      </div>
    </Card>
  );
}

function FilterSelect({ icon: Icon, active, className, children, ...rest }) {
  return (
    <div className={cn('relative', className)}>
      {Icon && <Icon className={cn('pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2', active ? 'text-emerald-300' : 'text-white/35')} />}
      <Select {...rest} className={cn('w-full pl-9 pr-9', active && 'ring-emerald-500/40 text-white')}>{children}</Select>
      <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/35" />
    </div>
  );
}

function ActiveChip({ label, onClear }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500/12 py-1 pl-2.5 pr-1.5 text-[12px] font-medium text-emerald-200 ring-1 ring-inset ring-emerald-400/20">
      {label}
      <button type="button" onClick={onClear} className="grid h-4 w-4 place-items-center rounded text-emerald-200/70 hover:bg-emerald-400/20 hover:text-white" aria-label={`Buang tapisan ${label}`}>
        <X className="h-3 w-3" strokeWidth={2.5} />
      </button>
    </span>
  );
}

function CategoryChip({ category }) {
  if (!category) return <span className="text-white/25">—</span>;
  const c = leadColor(category.color);
  return (
    <span className={cn('inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11.5px] font-medium', c.chip)}>
      <span className={cn('h-1.5 w-1.5 rounded-full', c.dot)} /> {category.name}
    </span>
  );
}

function LabelChip({ color, name }) {
  const c = leadColor(color);
  return (
    <span className={cn('inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11.5px] font-medium', c.chip)}>
      <span className={cn('h-1.5 w-1.5 rounded-full', c.dot)} /> {name}
    </span>
  );
}

export default function Index() {
  const { props } = usePage();
  const view = props.view ?? 'list';
  const leads = props.leads ?? { data: [], links: [] };
  const board = props.board ?? [];
  const sessions = props.sessions ?? [];
  const labels = props.availableLabels ?? [];
  const stats = props.stats ?? {};
  const filters = props.filters ?? {};
  const colorOptions = props.colorOptions ?? [];
  const categories = props.categories ?? [];

  const labelByKey = Object.fromEntries(labels.map((l) => [l.key, l]));
  const labelItems = labels.map((l) => ({ id: l.id, name: l.name, color: l.color }));
  const categoryItems = categories.map((c) => ({ id: c.id, name: c.name, color: c.color, count: c.leads_count }));
  const [search, setSearch] = useState(filters.search ?? '');
  const [categoryModal, setCategoryModal] = useState({ open: false, editing: null });
  const [manageLabels, setManageLabels] = useState(false);
  const [manageCategories, setManageCategories] = useState(false);
  const first = useRef(true);

  function visit(params) {
    router.get(route('cekbot.leads'), { view, search, session: filters.session, label: filters.label, ...params }, { preserveState: true, preserveScroll: true, replace: true });
  }

  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => visit({ search }), 350);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  const exportHref = `${route('cekbot.leads.export')}?${new URLSearchParams(
    Object.entries({ search: filters.search, session: filters.session, label: filters.label }).filter(([, v]) => v)
  ).toString()}`;

  const hasActiveFilter = Boolean(filters.search || filters.session || filters.label);
  const sessionName = sessions.find((s) => String(s.id) === String(filters.session))?.label;
  const labelName = labels.find((l) => l.key === filters.label)?.name;
  const resultCount = view === 'board' ? board.reduce((sum, col) => sum + (col.total ?? 0), 0) : (leads.total ?? leads.data.length);

  function clearFilters() {
    setSearch('');
    visit({ search: '', session: null, label: null });
  }

  const ViewToggle = () => (
    <div className="flex items-center gap-0.5 rounded-xl bg-white/5 p-0.5 ring-1 ring-inset ring-white/10">
      {[['list', 'Senarai', List], ['board', 'Papan', LayoutGrid]].map(([v, label, Icon]) => (
        <button
          key={v}
          type="button"
          onClick={() => v !== view && visit({ view: v })}
          className={cn('flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[12.5px] font-semibold transition-colors',
            v === view ? 'bg-white/10 text-white shadow-sm' : 'text-white/45 hover:text-white')}
        >
          <Icon className="h-4 w-4" strokeWidth={2.2} /> {label}
        </button>
      ))}
    </div>
  );

  return (
    <CekbotLayout
      title="Leads"
      subtitle="Semua nombor yang pernah mesej anda"
      actions={
        <>
          <Button variant="secondary" onClick={() => setManageLabels(true)}><Tag className="h-4 w-4" /> Label</Button>
          <Button variant="secondary" onClick={() => setManageCategories(true)}><LayoutGrid className="h-4 w-4" /> Kategori</Button>
          <Button variant="secondary" href={exportHref} target="_blank"><Download className="h-4 w-4" /> Export CSV</Button>
        </>
      }
    >
      <Head title="Leads" />

      <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Stat icon={Users} label="Jumlah leads" value={stats.total ?? 0} tint="bg-emerald-500/15 text-emerald-300" />
        <Stat icon={UserPlus} label="Baru (7 hari)" value={stats.new_week ?? 0} tint="bg-blue-500/15 text-blue-300" />
        <Stat icon={UserCheck} label="Diserah ke staf" value={stats.handed_over ?? 0} tint="bg-violet-500/15 text-violet-300" />
      </div>

      {/* Toolbar */}
      <div className="mb-4 space-y-3">
        <div className="flex flex-col gap-2 lg:flex-row lg:items-center">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/30" />
            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari nama atau nombor…" className="pl-9" />
          </div>
          <div className="grid grid-cols-2 gap-2 lg:flex lg:shrink-0">
            <FilterSelect icon={Building2} active={!!filters.session} value={filters.session ?? ''} onChange={(e) => visit({ session: e.target.value || null })} className="lg:w-52">
              <option value="">Semua nombor bisnes</option>
              {sessions.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
            </FilterSelect>
            <FilterSelect icon={Tag} active={!!filters.label} value={filters.label ?? ''} onChange={(e) => visit({ label: e.target.value || null })} className="lg:w-44">
              <option value="">Semua label</option>
              {labels.map((l) => <option key={l.key} value={l.key}>{l.name}</option>)}
            </FilterSelect>
          </div>
          <ViewToggle />
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {hasActiveFilter ? (
            <>
              <span className="text-[12px] font-medium text-white/40">Tapisan:</span>
              {filters.search && <ActiveChip label={`"${filters.search}"`} onClear={() => setSearch('')} />}
              {filters.session && <ActiveChip label={sessionName ?? 'Nombor bisnes'} onClear={() => visit({ session: null })} />}
              {filters.label && <ActiveChip label={labelName ?? 'Label'} onClear={() => visit({ label: null })} />}
              <button type="button" onClick={clearFilters} className="text-[12px] font-medium text-white/45 underline-offset-2 hover:text-white hover:underline">Kosongkan semua</button>
            </>
          ) : (
            <span className="text-[12px] text-white/30">Tiada tapisan digunakan</span>
          )}
          <span className="ml-auto text-[12px] text-white/45"><b className="tabular-nums text-white/75">{resultCount}</b> lead</span>
        </div>
      </div>

      {view === 'board' ? (
        <LeadBoard
          board={board}
          colorOptions={colorOptions}
          onAddCategory={() => setCategoryModal({ open: true, editing: null })}
          onEditCategory={(col) => setCategoryModal({ open: true, editing: col })}
        />
      ) : leads.data.length === 0 ? (
        <EmptyState
          icon={Users}
          title="Belum ada leads"
          hint="Setiap kali seseorang mesej nombor WhatsApp anda, nombor mereka akan dikumpul di sini secara automatik."
        />
      ) : (
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-[13px]">
              <thead>
                <tr className="border-b border-white/8 text-[11px] uppercase tracking-wide text-white/40">
                  <th className="px-4 py-3 font-semibold">Nama</th>
                  <th className="px-4 py-3 font-semibold">Nombor</th>
                  <th className="px-4 py-3 font-semibold">Kategori</th>
                  <th className="px-4 py-3 font-semibold">Nombor bisnes</th>
                  <th className="px-4 py-3 font-semibold">Label</th>
                  <th className="px-4 py-3 text-center font-semibold">Mesej</th>
                  <th className="px-4 py-3 font-semibold">Kali terakhir</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody>
                {leads.data.map((lead) => (
                  <tr key={lead.id} className="border-b border-white/5 last:border-0 hover:bg-white/[0.03]">
                    <td className="px-4 py-2.5">
                      <div className="flex items-center gap-2.5">
                        <span className={cn('grid h-8 w-8 shrink-0 place-items-center rounded-full text-[11px] font-bold', avatarTint(lead.name || lead.phone))}>
                          {initialsFrom(contactDisplay(lead.name, lead.phone, false))}
                        </span>
                        <span className="font-semibold text-white">{contactDisplay(lead.name, lead.phone, false)}</span>
                        {lead.archived && <span className="text-[11px] text-white/30">(arkib)</span>}
                      </div>
                    </td>
                    <td className="px-4 py-2.5">
                      <a href={`https://wa.me/${lead.phone}`} target="_blank" rel="noopener" className="tabular-nums text-sky-300 hover:underline">{formatPhone(lead.phone)}</a>
                    </td>
                    <td className="px-4 py-2.5"><CategoryChip category={lead.category} /></td>
                    <td className="px-4 py-2.5 text-white/60">{lead.session?.label ?? '—'}</td>
                    <td className="px-4 py-2.5">
                      <div className="flex flex-wrap gap-1">
                        {(lead.labels || []).length === 0
                          ? <span className="text-white/25">—</span>
                          : lead.labels.map((k) => {
                              const meta = labelByKey[k];
                              return <LabelChip key={k} color={meta?.color ?? 'slate'} name={meta?.name ?? k} />;
                            })}
                      </div>
                    </td>
                    <td className="px-4 py-2.5 text-center tabular-nums text-white/70">{lead.messages_count}</td>
                    <td className="px-4 py-2.5 whitespace-nowrap text-white/55">{lead.last_message_at ? timeAgo(lead.last_message_at) : '—'}</td>
                    <td className="px-4 py-2.5 text-right">
                      <Link href={`/admin/cekbot/inbox?session=${lead.session?.id ?? ''}`} className="inline-flex items-center gap-1 text-[12px] font-medium text-white/50 hover:text-emerald-300">
                        <MessageCircle className="h-3.5 w-3.5" /> Inbox
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {leads.links && leads.links.length > 3 && (
            <div className="flex flex-wrap items-center justify-center gap-1 border-t border-white/8 px-4 py-3">
              {leads.links.map((link, i) => (
                <button
                  key={i}
                  type="button"
                  disabled={!link.url}
                  onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                  className={cn(
                    'min-w-[32px] rounded-lg px-2.5 py-1.5 text-[12.5px] font-medium transition-colors',
                    link.active ? 'bg-emerald-500/20 text-emerald-300' : 'text-white/50 hover:bg-white/8 hover:text-white',
                    !link.url && 'cursor-not-allowed opacity-30'
                  )}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          )}
        </Card>
      )}

      <CategoryModal
        open={categoryModal.open}
        editing={categoryModal.editing}
        colorOptions={colorOptions}
        onClose={() => setCategoryModal({ open: false, editing: null })}
      />

      <TaxonomyManagerModal
        open={manageLabels}
        onClose={() => setManageLabels(false)}
        title="Urus label"
        hint="Label untuk menanda perbualan (Inbox), tapis leads & sasar broadcast."
        items={labelItems}
        colorOptions={colorOptions}
        routeNames={{ store: 'cekbot.leads.labels.store', update: 'cekbot.leads.labels.update', destroy: 'cekbot.leads.labels.destroy' }}
        namePlaceholder="Cth: Baru, Pending, Penting"
        deleteConfirm={(it) => `Padam label "${it.name}"? Ia akan ditanggalkan dari semua perbualan.`}
        emptyText="Belum ada label."
      />

      <TaxonomyManagerModal
        open={manageCategories}
        onClose={() => setManageCategories(false)}
        title="Urus kategori"
        hint="Kategori jadi lajur dalam papan Kanban leads."
        items={categoryItems}
        colorOptions={colorOptions}
        routeNames={{ store: 'cekbot.leads.categories.store', update: 'cekbot.leads.categories.update', destroy: 'cekbot.leads.categories.destroy' }}
        namePlaceholder="Cth: Berminat, Follow-up, Deal"
        deleteConfirm={(it) => `Padam kategori "${it.name}"? Leads di dalamnya kembali ke "Tiada kategori".`}
        emptyText="Belum ada kategori."
      />
    </CekbotLayout>
  );
}
