import { Head, usePage } from '@inertiajs/react';
import { Smartphone, MessageCircle, Bot, Inbox, ArrowDownLeft, ArrowUpRight, User } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card } from '@/cekbot-admin/components/Ui';
import { cn, formatPhone } from '@/cekbot-admin/lib/utils';

function Tile({ icon: Icon, label, value, sub, tone = 'emerald' }) {
  const tones = {
    emerald: 'bg-emerald-500/15 text-emerald-400',
    sky: 'bg-sky-500/15 text-sky-400',
    violet: 'bg-violet-500/15 text-violet-400',
    amber: 'bg-amber-500/15 text-amber-400',
  };
  return (
    <Card className="p-4">
      <span className={cn('grid h-9 w-9 place-items-center rounded-lg', tones[tone])}>
        <Icon className="h-4 w-4" strokeWidth={2.2} />
      </span>
      <p className="mt-3 text-[24px] font-extrabold tabular-nums leading-none text-white">{value}</p>
      <p className="mt-1.5 text-[12.5px] font-medium text-white/70">{label}</p>
      {sub && <p className="text-[11.5px] text-white/40">{sub}</p>}
    </Card>
  );
}

export default function Index() {
  const { props } = usePage();
  const stats = props.stats ?? {};
  const series = props.series ?? [];
  const perNumber = props.perNumber ?? [];
  const max = Math.max(1, ...series.map((d) => Math.max(d.in, d.out)));

  return (
    <CekbotLayout title="Analitik" subtitle="Prestasi cekbot & mesej">
      <Head title="Analitik" />

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Tile icon={Smartphone} label="Nombor bersambung" value={`${stats.connected}/${stats.numbers}`} tone="emerald" />
        <Tile icon={MessageCircle} label="Perbualan aktif" value={stats.conversations} sub={`${stats.unread} belum dibaca`} tone="sky" />
        <Tile icon={ArrowDownLeft} label="Masuk hari ini" value={stats.in_today} tone="violet" />
        <Tile icon={ArrowUpRight} label="Keluar hari ini" value={stats.out_today} tone="amber" />
      </div>

      <div className="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Tile icon={Bot} label="Balasan bot (7 hari)" value={stats.bot_7d} tone="emerald" />
        <Tile icon={User} label="Balasan manusia (7 hari)" value={stats.human_7d} tone="sky" />
      </div>

      <Card className="mt-5 p-5">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-[14px] font-bold text-white">Mesej 7 hari</h3>
          <div className="flex items-center gap-3 text-[11.5px] text-white/50">
            <span className="flex items-center gap-1"><span className="h-2.5 w-2.5 rounded-sm bg-emerald-500" /> Masuk</span>
            <span className="flex items-center gap-1"><span className="h-2.5 w-2.5 rounded-sm bg-sky-500" /> Keluar</span>
          </div>
        </div>
        <div className="flex h-40 items-end justify-between gap-2">
          {series.map((d, i) => (
            <div key={i} className="flex flex-1 flex-col items-center gap-1.5">
              <div className="flex h-32 w-full items-end justify-center gap-1">
                <div className="w-1/2 rounded-t bg-emerald-500/80" style={{ height: `${(d.in / max) * 100}%` }} title={`Masuk: ${d.in}`} />
                <div className="w-1/2 rounded-t bg-sky-500/80" style={{ height: `${(d.out / max) * 100}%` }} title={`Keluar: ${d.out}`} />
              </div>
              <span className="text-[10.5px] text-white/40">{d.label}</span>
            </div>
          ))}
        </div>
      </Card>

      <Card className="mt-5 overflow-hidden">
        <div className="border-b border-white/8 px-5 py-3.5">
          <h3 className="text-[14px] font-bold text-white">Ikut nombor</h3>
        </div>
        {perNumber.length === 0 ? (
          <div className="grid place-items-center py-10 text-[12.5px] text-white/40">
            <Inbox className="mb-2 h-6 w-6 text-white/25" /> Tiada nombor lagi.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-[13px]">
              <thead className="text-[11.5px] uppercase tracking-wide text-white/40">
                <tr className="border-b border-white/8">
                  <th className="px-5 py-2.5 font-semibold">Nombor</th>
                  <th className="px-3 py-2.5 font-semibold">Status</th>
                  <th className="px-3 py-2.5 text-right font-semibold">Perbualan</th>
                  <th className="px-3 py-2.5 text-right font-semibold">Masuk 7h</th>
                  <th className="px-5 py-2.5 text-right font-semibold">Keluar 7h</th>
                </tr>
              </thead>
              <tbody>
                {perNumber.map((n, i) => (
                  <tr key={i} className="border-b border-white/5 last:border-0">
                    <td className="px-5 py-2.5">
                      <div className="font-semibold text-white">{n.label}</div>
                      {n.phone && <div className="text-[11.5px] text-white/40">{formatPhone(n.phone)}</div>}
                    </td>
                    <td className="px-3 py-2.5">
                      <span className={cn('inline-block h-2 w-2 rounded-full', n.is_working ? 'bg-emerald-400' : 'bg-white/25')} />
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums text-white/80">{n.conversations}</td>
                    <td className="px-3 py-2.5 text-right tabular-nums text-emerald-300">{n.in_7d}</td>
                    <td className="px-5 py-2.5 text-right tabular-nums text-sky-300">{n.out_7d}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </CekbotLayout>
  );
}
