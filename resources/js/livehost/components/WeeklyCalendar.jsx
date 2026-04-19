import { Link } from '@inertiajs/react';
import { useMemo } from 'react';

const DAY_LABELS = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];

const PLATFORM_STYLES = {
  shopee: {
    bg: 'bg-[#F43F5E]/12',
    bgInactive: 'bg-[#F43F5E]/6',
    border: 'border-[#F43F5E]',
    text: 'text-[#9F1239]',
    dot: 'bg-[#F43F5E]',
  },
  tiktok: {
    bg: 'bg-[#0A0A0A]/8',
    bgInactive: 'bg-[#0A0A0A]/4',
    border: 'border-[#0A0A0A]',
    text: 'text-[#0A0A0A]',
    dot: 'bg-[#0A0A0A]',
  },
  facebook: {
    bg: 'bg-[#0EA5E9]/12',
    bgInactive: 'bg-[#0EA5E9]/6',
    border: 'border-[#0EA5E9]',
    text: 'text-[#075985]',
    dot: 'bg-[#0EA5E9]',
  },
  instagram: {
    bg: 'bg-[#8B5CF6]/12',
    bgInactive: 'bg-[#8B5CF6]/6',
    border: 'border-[#8B5CF6]',
    text: 'text-[#5B21B6]',
    dot: 'bg-[#8B5CF6]',
  },
  youtube: {
    bg: 'bg-[#EF4444]/12',
    bgInactive: 'bg-[#EF4444]/6',
    border: 'border-[#EF4444]',
    text: 'text-[#991B1B]',
    dot: 'bg-[#EF4444]',
  },
  default: {
    bg: 'bg-[#10B981]/12',
    bgInactive: 'bg-[#10B981]/6',
    border: 'border-[#10B981]',
    text: 'text-[#065F46]',
    dot: 'bg-[#10B981]',
  },
};

function styleFor(platformType) {
  if (!platformType) {
    return PLATFORM_STYLES.default;
  }
  const key = String(platformType).toLowerCase();
  return PLATFORM_STYLES[key] ?? PLATFORM_STYLES.default;
}

function timeToMinutes(hhmm) {
  if (!hhmm) {
    return 0;
  }
  const [h, m] = String(hhmm).split(':').map((n) => parseInt(n, 10) || 0);
  return h * 60 + m;
}

function hourLabel(hour) {
  return `${String(hour).padStart(2, '0')}:00`;
}

/**
 * For a day's sorted schedules, compute overlap group layout:
 * returns { groupSize, groupIndex } per schedule id.
 */
function computeOverlapLayout(daySchedules) {
  const layouts = {};
  const sorted = [...daySchedules].sort(
    (a, b) => timeToMinutes(a.startTime) - timeToMinutes(b.startTime)
  );

  let group = [];
  let groupEnd = 0;

  const flush = () => {
    group.forEach((s, idx) => {
      layouts[s.id] = { groupSize: group.length, groupIndex: idx };
    });
  };

  sorted.forEach((schedule) => {
    const start = timeToMinutes(schedule.startTime);
    const end = timeToMinutes(schedule.endTime);

    if (group.length === 0 || start >= groupEnd) {
      flush();
      group = [schedule];
      groupEnd = end;
    } else {
      group.push(schedule);
      groupEnd = Math.max(groupEnd, end);
    }
  });

  flush();

  return layouts;
}

export default function WeeklyCalendar({ schedules = [], startHour = 6, endHour = 23 }) {
  const { effectiveStartHour, effectiveEndHour } = useMemo(() => {
    let s = startHour;
    let e = endHour;
    schedules.forEach((schedule) => {
      const startMin = timeToMinutes(schedule.startTime);
      const endMin = timeToMinutes(schedule.endTime);
      const startH = Math.floor(startMin / 60);
      const endH = Math.ceil(endMin / 60);
      if (startH < s) {
        s = startH;
      }
      if (endH > e) {
        e = endH;
      }
    });
    return {
      effectiveStartHour: Math.max(0, s),
      effectiveEndHour: Math.min(24, Math.max(e, s + 1)),
    };
  }, [schedules, startHour, endHour]);

  const totalHours = effectiveEndHour - effectiveStartHour;
  const hourRows = Array.from({ length: totalHours }, (_, i) => effectiveStartHour + i);
  const rowHeight = 60; // px per hour
  const totalHeight = totalHours * rowHeight;

  const todayIndex = new Date().getDay();

  const schedulesByDay = useMemo(() => {
    const buckets = Array.from({ length: 7 }, () => []);
    schedules.forEach((schedule) => {
      const idx = Number(schedule.dayOfWeek);
      if (idx >= 0 && idx <= 6) {
        buckets[idx].push(schedule);
      }
    });
    return buckets;
  }, [schedules]);

  const layoutsByDay = useMemo(
    () => schedulesByDay.map((list) => computeOverlapLayout(list)),
    [schedulesByDay]
  );

  return (
    <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="overflow-x-auto">
        <div className="min-w-[900px]">
          {/* Header row */}
          <div className="sticky top-0 z-10 grid grid-cols-[64px_repeat(7,1fr)] border-b border-[#EAEAEA] bg-[#FAFAFA]">
            <div className="px-2 py-3 text-[10px] font-semibold uppercase tracking-wider text-[#A3A3A3]">
              Time
            </div>
            {DAY_LABELS.map((label, idx) => (
              <div
                key={label}
                className={[
                  'border-l border-[#F0F0F0] px-3 py-3 text-center text-[11px] font-semibold uppercase tracking-wider',
                  idx === todayIndex ? 'bg-[#ECFDF5] text-[#065F46]' : 'text-[#525252]',
                ].join(' ')}
              >
                {label}
              </div>
            ))}
          </div>

          {/* Grid body */}
          <div
            className="relative grid grid-cols-[64px_repeat(7,1fr)]"
            style={{ height: `${totalHeight}px` }}
          >
            {/* Time column */}
            <div className="relative">
              {hourRows.map((hour, i) => (
                <div
                  key={hour}
                  className="absolute left-0 right-0 flex items-start justify-end pr-2 pt-1 text-[10px] font-medium tabular-nums text-[#A3A3A3]"
                  style={{ top: `${i * rowHeight}px`, height: `${rowHeight}px` }}
                >
                  {hourLabel(hour)}
                </div>
              ))}
            </div>

            {/* Day columns */}
            {schedulesByDay.map((daySchedules, dayIdx) => {
              const layouts = layoutsByDay[dayIdx];
              const isToday = dayIdx === todayIndex;
              return (
                <div
                  key={dayIdx}
                  className={[
                    'relative border-l border-[#F0F0F0]',
                    isToday ? 'bg-[#ECFDF5]/40' : '',
                  ].join(' ')}
                >
                  {/* Hour grid lines */}
                  {hourRows.map((hour, i) => (
                    <div
                      key={hour}
                      className="absolute left-0 right-0 border-t border-[#F0F0F0]"
                      style={{ top: `${i * rowHeight}px` }}
                    />
                  ))}

                  {/* Schedule blocks */}
                  {daySchedules.map((schedule) => {
                    const startMin = timeToMinutes(schedule.startTime);
                    const endMin = timeToMinutes(schedule.endTime);
                    const offsetMin = startMin - effectiveStartHour * 60;
                    const durationMin = Math.max(15, endMin - startMin);
                    const top = (offsetMin / 60) * rowHeight;
                    const height = (durationMin / 60) * rowHeight;

                    const layout = layouts[schedule.id] ?? { groupSize: 1, groupIndex: 0 };
                    const widthPct = 100 / layout.groupSize;
                    const leftPct = layout.groupIndex * widthPct;

                    const palette = styleFor(schedule.platformType);
                    const inactive = !schedule.isActive;

                    return (
                      <Link
                        key={schedule.id}
                        href={`/livehost/schedules/${schedule.id}`}
                        className={[
                          'absolute overflow-hidden rounded-md border-l-[3px] px-2 py-1.5 text-[11px] leading-tight',
                          'transition-all duration-150 hover:shadow-md hover:z-20',
                          inactive ? 'border-dashed opacity-60' : 'border-solid',
                          inactive ? palette.bgInactive : palette.bg,
                          palette.border,
                          palette.text,
                        ].join(' ')}
                        style={{
                          top: `${top}px`,
                          height: `${height}px`,
                          left: `calc(${leftPct}% + 2px)`,
                          width: `calc(${widthPct}% - 4px)`,
                        }}
                        title={`${schedule.startTime}–${schedule.endTime} · ${schedule.platformAccount ?? ''} · ${schedule.hostName ?? 'Unassigned'}`}
                      >
                        <div className="flex items-center gap-1 font-semibold tabular-nums">
                          <span className={`inline-block h-1.5 w-1.5 rounded-full ${palette.dot}`} />
                          <span>
                            {schedule.startTime}–{schedule.endTime}
                          </span>
                        </div>
                        {height >= 36 && (
                          <div className="mt-0.5 truncate font-medium">
                            {schedule.platformAccount ?? '—'}
                            {schedule.platformType ? (
                              <span className="ml-1 text-[9px] uppercase opacity-70">
                                · {schedule.platformType}
                              </span>
                            ) : null}
                          </div>
                        )}
                        {height >= 54 && (
                          <div className="mt-0.5 truncate text-[10px] opacity-80">
                            {schedule.hostName ?? (
                              <span className="italic">Unassigned</span>
                            )}
                          </div>
                        )}
                      </Link>
                    );
                  })}
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {/* Legend */}
      <div className="flex flex-wrap items-center gap-4 border-t border-[#EAEAEA] bg-[#FAFAFA] px-4 py-3 text-[11px] text-[#737373]">
        <span className="font-semibold uppercase tracking-wider text-[#A3A3A3]">Platforms</span>
        {Object.entries(PLATFORM_STYLES)
          .filter(([key]) => key !== 'default')
          .map(([key, palette]) => (
            <div key={key} className="flex items-center gap-1.5">
              <span className={`inline-block h-2 w-2 rounded-full ${palette.dot}`} />
              <span className="capitalize">{key}</span>
            </div>
          ))}
        <span className="ml-auto text-[10px] text-[#A3A3A3]">
          Dashed border = inactive
        </span>
      </div>
    </div>
  );
}
