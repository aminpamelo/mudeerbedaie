import { Head } from '@inertiajs/react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';

// ─────────────────────────────────────────────────────────────────────────────
// Mock data: 3 TikTok Shop accounts, varied sessions across the week.
// ─────────────────────────────────────────────────────────────────────────────

const ACCOUNTS = [
  {
    id: 'beauty',
    name: 'Beauty Glow TH',
    platform: 'TikTok Shop',
    color: '#EC4899',
    soft: '#FDF2F8',
    text: '#9D174D',
    border: '#F9A8D4',
  },
  {
    id: 'fashion',
    name: 'Fashion Hub MY',
    platform: 'TikTok Shop',
    color: '#8B5CF6',
    soft: '#F5F3FF',
    text: '#5B21B6',
    border: '#C4B5FD',
  },
  {
    id: 'home',
    name: 'Home Essentials SG',
    platform: 'TikTok Shop',
    color: '#F97316',
    soft: '#FFF7ED',
    text: '#9A3412',
    border: '#FDBA74',
  },
];

// Sessions — (accountId, dayOfWeek 0=Sun..6=Sat, startHour, durationHr, hostName)
const SESSIONS = [
  // Sunday
  { id: 1, account: 'beauty', dow: 0, start: 7, dur: 2, host: 'Sarah Chen' },
  { id: 2, account: 'fashion', dow: 0, start: 10, dur: 2, host: 'Nora Aziz' },
  { id: 3, account: 'home', dow: 0, start: 14, dur: 2, host: 'Ali Rahman' },
  // Monday
  { id: 4, account: 'beauty', dow: 1, start: 7, dur: 2, host: 'Sarah Chen' },
  { id: 5, account: 'fashion', dow: 1, start: 7, dur: 2, host: 'Mei Lin' },
  { id: 6, account: 'home', dow: 1, start: 11, dur: 2, host: null },
  { id: 7, account: 'fashion', dow: 1, start: 15, dur: 2, host: 'Nora Aziz' },
  // Tuesday
  { id: 8, account: 'beauty', dow: 2, start: 9, dur: 2, host: 'Sarah Chen' },
  { id: 9, account: 'home', dow: 2, start: 13, dur: 2, host: 'Ali Rahman' },
  // Wednesday
  { id: 10, account: 'fashion', dow: 3, start: 8, dur: 3, host: 'Mei Lin' },
  { id: 11, account: 'beauty', dow: 3, start: 14, dur: 2, host: 'Sarah Chen' },
  { id: 12, account: 'home', dow: 3, start: 14, dur: 2, host: 'Ali Rahman' },
  // Thursday
  { id: 13, account: 'home', dow: 4, start: 10, dur: 2, host: 'Ali Rahman' },
  { id: 14, account: 'fashion', dow: 4, start: 13, dur: 2, host: 'Nora Aziz' },
  // Friday
  { id: 15, account: 'beauty', dow: 5, start: 7, dur: 2, host: 'Sarah Chen' },
  { id: 16, account: 'home', dow: 5, start: 15, dur: 3, host: 'Ali Rahman' },
  // Saturday
  { id: 17, account: 'fashion', dow: 6, start: 9, dur: 2, host: 'Mei Lin' },
  { id: 18, account: 'beauty', dow: 6, start: 13, dur: 2, host: 'Sarah Chen' },
  { id: 19, account: 'home', dow: 6, start: 13, dur: 2, host: 'Ali Rahman' },
];

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const DAYS_FULL = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const HOUR_START = 6;
const HOUR_END = 20;
const HOUR_PX = 56;

const accountById = (id) => ACCOUNTS.find((a) => a.id === id);

const formatHour = (h) => {
  if (h === 0 || h === 24) return '12 AM';
  if (h === 12) return '12 PM';
  return h > 12 ? `${h - 12} PM` : `${h} AM`;
};

const formatTime = (h) => {
  const suffix = h >= 12 ? 'PM' : 'AM';
  const display = h === 0 ? 12 : h > 12 ? h - 12 : h;
  return `${display}:00 ${suffix}`;
};

// ─────────────────────────────────────────────────────────────────────────────
// Shared: Header row + hour axis
// ─────────────────────────────────────────────────────────────────────────────

function DayHeader({ columnTemplate, extraSub = null }) {
  return (
    <div
      className="grid border-b border-[#EAEAEA] bg-[#FAFAFA]"
      style={{ gridTemplateColumns: columnTemplate }}
    >
      <div className="border-r border-[#EAEAEA]"></div>
      {DAYS.map((d, i) => (
        <div
          key={d}
          className="border-r border-[#EAEAEA] px-3 py-2 last:border-r-0"
        >
          <div className="font-mono text-[10px] uppercase tracking-wider text-[#A3A3A3]">{d}</div>
          <div className="text-[13px] font-semibold text-[#0A0A0A]">{DAYS_FULL[i]}</div>
          {extraSub}
        </div>
      ))}
    </div>
  );
}

function HourAxis() {
  const hours = Array.from({ length: HOUR_END - HOUR_START }, (_, i) => HOUR_START + i);
  return (
    <div
      className="relative border-r border-[#EAEAEA]"
      style={{ height: `${(HOUR_END - HOUR_START) * HOUR_PX}px` }}
    >
      {hours.map((h) => (
        <div
          key={h}
          className="absolute left-0 right-0 pr-2 pt-0.5 text-right"
          style={{ top: `${(h - HOUR_START) * HOUR_PX}px` }}
        >
          <span className="font-mono text-[10px] font-medium text-[#A3A3A3] tabular-nums">
            {formatHour(h)}
          </span>
        </div>
      ))}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Option 1: Swim lanes per account (each day split into 3 vertical lanes)
// ─────────────────────────────────────────────────────────────────────────────

function Option1SwimLanes() {
  const columnTemplate = `56px repeat(7, 1fr)`;
  const totalHeight = (HOUR_END - HOUR_START) * HOUR_PX;

  return (
    <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div
        className="grid border-b border-[#EAEAEA] bg-[#FAFAFA]"
        style={{ gridTemplateColumns: columnTemplate }}
      >
        <div className="border-r border-[#EAEAEA]"></div>
        {DAYS.map((d, i) => (
          <div key={d} className="border-r border-[#EAEAEA] last:border-r-0">
            <div className="px-2 py-2">
              <div className="font-mono text-[10px] uppercase tracking-wider text-[#A3A3A3]">{d}</div>
              <div className="text-[13px] font-semibold text-[#0A0A0A]">{DAYS_FULL[i]}</div>
            </div>
            <div className="grid grid-cols-3 border-t border-[#F5F5F5]">
              {ACCOUNTS.map((a) => (
                <div
                  key={a.id}
                  className="border-r border-[#F5F5F5] px-1 py-1 text-center last:border-r-0"
                  title={a.name}
                >
                  <span
                    className="font-mono text-[8px] font-bold uppercase tracking-wide"
                    style={{ color: a.text }}
                  >
                    {a.name.split(' ')[0].slice(0, 6)}
                  </span>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>

      <div
        className="grid"
        style={{ gridTemplateColumns: columnTemplate, height: `${totalHeight}px` }}
      >
        <HourAxis />
        {DAYS.map((_, dow) => (
          <div
            key={dow}
            className="relative grid grid-cols-3 border-r border-[#EAEAEA] last:border-r-0"
            style={{
              backgroundImage: 'linear-gradient(to bottom, rgba(0,0,0,0.04) 1px, transparent 1px)',
              backgroundSize: `100% ${HOUR_PX}px`,
            }}
          >
            {ACCOUNTS.map((account) => (
              <div
                key={account.id}
                className="relative border-r border-[#F5F5F5] last:border-r-0"
              >
                {SESSIONS.filter((s) => s.dow === dow && s.account === account.id).map((s) => {
                  const top = (s.start - HOUR_START) * HOUR_PX;
                  const height = s.dur * HOUR_PX;
                  return (
                    <div
                      key={s.id}
                      className="absolute left-0.5 right-0.5 overflow-hidden rounded border p-1 text-left"
                      style={{
                        top: `${top}px`,
                        height: `${height}px`,
                        backgroundColor: account.soft,
                        borderColor: account.border,
                      }}
                    >
                      <div className="font-mono text-[9px] font-semibold tabular-nums" style={{ color: account.text }}>
                        {formatTime(s.start)}
                      </div>
                      {height >= 48 && (
                        <div className="mt-0.5 truncate text-[9px] font-medium text-[#0A0A0A]">
                          {s.host ?? 'Unassigned'}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Option 2: Grouped sections (calendar grid repeats per account)
// ─────────────────────────────────────────────────────────────────────────────

function Option2GroupedSections() {
  const columnTemplate = `56px repeat(7, 1fr)`;
  const totalHeight = (HOUR_END - HOUR_START) * HOUR_PX;

  return (
    <div className="space-y-4">
      {ACCOUNTS.map((account) => {
        const accountSessions = SESSIONS.filter((s) => s.account === account.id);
        return (
          <div
            key={account.id}
            className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
          >
            {/* Account group header */}
            <div
              className="flex items-center justify-between border-b px-4 py-2.5"
              style={{ backgroundColor: account.soft, borderColor: account.border }}
            >
              <div className="flex items-center gap-2">
                <span
                  className="inline-block h-2 w-2 rounded-sm"
                  style={{ backgroundColor: account.color }}
                ></span>
                <span className="text-[13px] font-semibold" style={{ color: account.text }}>
                  {account.name}
                </span>
                <span className="font-mono text-[10px] uppercase tracking-wide text-[#A3A3A3]">
                  {account.platform}
                </span>
              </div>
              <span className="font-mono text-[10px] tabular-nums text-[#737373]">
                {accountSessions.length} sessions
              </span>
            </div>

            <DayHeader columnTemplate={columnTemplate} />

            <div
              className="grid"
              style={{ gridTemplateColumns: columnTemplate, height: `${totalHeight}px` }}
            >
              <HourAxis />
              {DAYS.map((_, dow) => (
                <div
                  key={dow}
                  className="relative border-r border-[#EAEAEA] last:border-r-0"
                  style={{
                    backgroundImage: 'linear-gradient(to bottom, rgba(0,0,0,0.04) 1px, transparent 1px)',
                    backgroundSize: `100% ${HOUR_PX}px`,
                  }}
                >
                  {accountSessions
                    .filter((s) => s.dow === dow)
                    .map((s) => {
                      const top = (s.start - HOUR_START) * HOUR_PX;
                      const height = s.dur * HOUR_PX;
                      return (
                        <div
                          key={s.id}
                          className="absolute left-1 right-1 overflow-hidden rounded-lg border p-2"
                          style={{
                            top: `${top}px`,
                            height: `${height}px`,
                            backgroundColor: account.soft,
                            borderColor: account.border,
                          }}
                        >
                          <div className="font-mono text-[11px] font-semibold tabular-nums" style={{ color: account.text }}>
                            {formatTime(s.start)}
                          </div>
                          <div className="mt-0.5 font-mono text-[9px] text-[#A3A3A3]">
                            {s.dur * 60}min
                          </div>
                          <div className="mt-1 truncate text-[11px] font-medium text-[#0A0A0A]">
                            {s.host ?? 'Unassigned'}
                          </div>
                        </div>
                      );
                    })}
                </div>
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Option 3: Stacked blocks side-by-side in same cell (chips)
// ─────────────────────────────────────────────────────────────────────────────

function Option3StackedChips() {
  const columnTemplate = `56px repeat(7, 1fr)`;
  const totalHeight = (HOUR_END - HOUR_START) * HOUR_PX;

  return (
    <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <DayHeader columnTemplate={columnTemplate} />

      <div
        className="grid"
        style={{ gridTemplateColumns: columnTemplate, height: `${totalHeight}px` }}
      >
        <HourAxis />
        {DAYS.map((_, dow) => {
          const daySessions = SESSIONS.filter((s) => s.dow === dow);
          // Group sessions by start hour to detect overlaps
          const groupsByStart = {};
          daySessions.forEach((s) => {
            if (!groupsByStart[s.start]) groupsByStart[s.start] = [];
            groupsByStart[s.start].push(s);
          });

          return (
            <div
              key={dow}
              className="relative border-r border-[#EAEAEA] last:border-r-0"
              style={{
                backgroundImage: 'linear-gradient(to bottom, rgba(0,0,0,0.04) 1px, transparent 1px)',
                backgroundSize: `100% ${HOUR_PX}px`,
              }}
            >
              {Object.entries(groupsByStart).map(([startHour, group]) => {
                const start = Number(startHour);
                const top = (start - HOUR_START) * HOUR_PX;
                const maxDur = Math.max(...group.map((g) => g.dur));
                const height = maxDur * HOUR_PX;
                const count = group.length;

                return (
                  <div
                    key={`${dow}-${start}`}
                    className="absolute left-0.5 right-0.5 flex gap-0.5"
                    style={{ top: `${top}px`, height: `${height}px` }}
                  >
                    {group.map((s) => {
                      const account = accountById(s.account);
                      return (
                        <div
                          key={s.id}
                          className="relative flex-1 overflow-hidden rounded-lg border p-1.5"
                          style={{
                            backgroundColor: account.soft,
                            borderColor: account.border,
                            height: `${s.dur * HOUR_PX}px`,
                          }}
                        >
                          <div
                            className="absolute bottom-0 left-0 top-0 w-[3px]"
                            style={{ backgroundColor: account.color }}
                          ></div>
                          <div className="pl-1.5">
                            <div className="font-mono text-[10px] font-semibold tabular-nums" style={{ color: account.text }}>
                              {formatTime(s.start)}
                            </div>
                            {count <= 2 && (
                              <div className="mt-0.5 truncate text-[10px] font-medium text-[#0A0A0A]">
                                {s.host ?? 'Unassigned'}
                              </div>
                            )}
                            <div className="mt-0.5 truncate font-mono text-[9px]" style={{ color: account.text }}>
                              {account.name.split(' ')[0]}
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                );
              })}
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Option 4: Single grid, color-coded by account with legend
// ─────────────────────────────────────────────────────────────────────────────

function Option4ColorCoded() {
  const columnTemplate = `56px repeat(7, 1fr)`;
  const totalHeight = (HOUR_END - HOUR_START) * HOUR_PX;

  return (
    <div className="space-y-3">
      {/* Legend */}
      <div className="flex flex-wrap items-center gap-4 rounded-[12px] border border-[#EAEAEA] bg-white px-4 py-2.5">
        <span className="font-mono text-[10px] uppercase tracking-wide text-[#A3A3A3]">Legend</span>
        {ACCOUNTS.map((a) => (
          <div key={a.id} className="flex items-center gap-1.5">
            <span className="inline-block h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: a.color }}></span>
            <span className="text-[12px] font-medium text-[#0A0A0A]">{a.name}</span>
          </div>
        ))}
      </div>

      <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <DayHeader columnTemplate={columnTemplate} />

        <div
          className="grid"
          style={{ gridTemplateColumns: columnTemplate, height: `${totalHeight}px` }}
        >
          <HourAxis />
          {DAYS.map((_, dow) => {
            // When multiple sessions overlap, lay side by side within the same time cell
            const daySessions = SESSIONS.filter((s) => s.dow === dow);
            const groups = {};
            daySessions.forEach((s) => {
              const key = s.start;
              if (!groups[key]) groups[key] = [];
              groups[key].push(s);
            });

            return (
              <div
                key={dow}
                className="relative border-r border-[#EAEAEA] last:border-r-0"
                style={{
                  backgroundImage: 'linear-gradient(to bottom, rgba(0,0,0,0.04) 1px, transparent 1px)',
                  backgroundSize: `100% ${HOUR_PX}px`,
                }}
              >
                {Object.entries(groups).map(([startHour, group]) => {
                  const start = Number(startHour);
                  const top = (start - HOUR_START) * HOUR_PX;
                  const widthPct = 100 / group.length;

                  return group.map((s, idx) => {
                    const account = accountById(s.account);
                    const height = s.dur * HOUR_PX;
                    return (
                      <div
                        key={s.id}
                        className="absolute overflow-hidden rounded-lg border p-1.5 transition-shadow hover:shadow-md"
                        style={{
                          top: `${top}px`,
                          height: `${height}px`,
                          left: `calc(${widthPct * idx}% + 2px)`,
                          width: `calc(${widthPct}% - 4px)`,
                          backgroundColor: account.soft,
                          borderColor: account.border,
                        }}
                      >
                        <div
                          className="absolute bottom-0 left-0 top-0 w-[3px] rounded-l-lg"
                          style={{ backgroundColor: account.color }}
                        ></div>
                        <div className="pl-2">
                          <div className="font-mono text-[10px] font-semibold tabular-nums" style={{ color: account.text }}>
                            {formatTime(s.start)}
                          </div>
                          {height >= 56 && (
                            <>
                              <div className="mt-0.5 font-mono text-[9px] text-[#A3A3A3]">
                                {s.dur * 60}min
                              </div>
                              <div className="mt-1 truncate text-[10px] font-medium text-[#0A0A0A]">
                                {s.host ?? 'Unassigned'}
                              </div>
                            </>
                          )}
                        </div>
                      </div>
                    );
                  });
                })}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Option card wrapper
// ─────────────────────────────────────────────────────────────────────────────

function OptionCard({ number, title, pros, cons, best, children }) {
  return (
    <section className="space-y-3">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-3">
            <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-ink font-mono text-[11px] font-bold text-white">
              {number}
            </span>
            <h2 className="text-[20px] font-semibold text-[#0A0A0A]">{title}</h2>
          </div>
        </div>
        <div className="flex flex-wrap gap-4 text-[12px]">
          <div>
            <span className="font-mono text-[10px] uppercase tracking-wide text-[#10B981]">Pros</span>
            <p className="mt-0.5 max-w-md text-[#525252]">{pros}</p>
          </div>
          <div>
            <span className="font-mono text-[10px] uppercase tracking-wide text-[#F43F5E]">Cons</span>
            <p className="mt-0.5 max-w-md text-[#525252]">{cons}</p>
          </div>
          <div>
            <span className="font-mono text-[10px] uppercase tracking-wide text-[#6366F1]">Best for</span>
            <p className="mt-0.5 max-w-md text-[#525252]">{best}</p>
          </div>
        </div>
      </div>
      {children}
    </section>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Page
// ─────────────────────────────────────────────────────────────────────────────

export default function CalendarPreview() {
  return (
    <>
      <Head title="Calendar Layout Preview" />
      <TopBar breadcrumb={['Live Host Desk', 'Session Slots', 'Preview']} />

      <div className="space-y-10 p-8">
        <header>
          <h1 className="text-3xl font-semibold tracking-[-0.03em] text-[#0A0A0A]">
            Multi-Account Calendar Preview
          </h1>
          <p className="mt-1.5 max-w-3xl text-sm text-[#737373]">
            Four approaches to displaying sessions from multiple TikTok Shop accounts on the same
            weekly calendar. Using the same mock data (3 accounts · 19 sessions) for all four.
          </p>
        </header>

        <OptionCard
          number={1}
          title="Swim lanes per account"
          pros="See every account at once, no overlap ambiguity."
          cons="Narrow cells, cramped beyond 3–4 accounts."
          best="2–4 accounts; ops teams scanning whole week."
        >
          <Option1SwimLanes />
        </OptionCard>

        <OptionCard
          number={2}
          title="Grouped sections (one grid per account)"
          pros="Maximum clarity, each account gets full width."
          cons="Lots of vertical scrolling, hard to compare cross-account."
          best="10+ accounts; focused single-account review."
        >
          <Option2GroupedSections />
        </OptionCard>

        <OptionCard
          number={3}
          title="Stacked chips (side-by-side in same cell)"
          pros="Compact, preserves single-grid layout."
          cons="Text truncates when 3+ overlap in same slot."
          best="Occasional overlaps; dense schedules."
        >
          <Option3StackedChips />
        </OptionCard>

        <OptionCard
          number={4}
          title="Color-coded single grid (recommended)"
          pros="Clean single grid, auto side-by-side on overlap, scales well."
          cons="Need a legend; color-blind users need labels."
          best="Most teams — balances clarity and density."
        >
          <Option4ColorCoded />
        </OptionCard>

        <footer className="rounded-[16px] border border-dashed border-[#EAEAEA] bg-[#FAFAFA] p-6 text-sm text-[#525252]">
          <p className="font-semibold text-[#0A0A0A]">After you pick one</p>
          <p className="mt-1">
            Tell me which option to ship and I'll replace the current calendar at{' '}
            <code className="rounded bg-white px-1.5 py-0.5 font-mono text-[12px]">
              /livehost/session-slots
            </code>{' '}
            with the chosen layout — wired to real data, filters, and the existing modals.
          </p>
        </footer>
      </div>
    </>
  );
}

CalendarPreview.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
