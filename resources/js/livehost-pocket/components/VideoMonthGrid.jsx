import { Video } from 'lucide-react';

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

/**
 * A Sunday-first month grid of video-logging activity, shared by the My Path
 * "Day by day" calendar and the Today "Video content" summary. Days with a
 * video read green (daily compliance); logged-range days with none stay
 * neutral; future days sit faded.
 *
 * When `onSelectDay` is passed, cells are tappable buttons (My Path → day
 * detail). Without it, cells are static <div>s so the grid can live inside a
 * link (the Today card). `dense` shrinks everything for the summary card.
 *
 * @param {{year:number, month:number, days:Array<{day:number, videos:number, date?:string}>, onSelectDay?:(date:string)=>void, dense?:boolean}} props
 */
export default function VideoMonthGrid({ year, month, days, onSelectDay = null, dense = false }) {
  const byDay = new Map(days.map((d) => [d.day, d]));
  const firstWeekday = new Date(year, month - 1, 1).getDay();
  const daysInMonth = new Date(year, month, 0).getDate();
  const now = new Date();
  const todayDay = now.getFullYear() === year && now.getMonth() + 1 === month ? now.getDate() : null;

  const cells = [];
  for (let i = 0; i < firstWeekday; i += 1) {
    cells.push(null);
  }
  for (let d = 1; d <= daysInMonth; d += 1) {
    cells.push(d);
  }

  const gap = dense ? 'gap-0.5' : 'gap-1';
  const cellBase = dense
    ? 'flex min-h-[34px] flex-col items-center justify-center gap-0 rounded-[7px] border px-0.5 py-0.5'
    : 'flex min-h-[46px] flex-col items-center justify-center gap-0.5 rounded-[9px] border px-0.5 py-1';
  const dayText = dense ? 'text-[9px]' : 'text-[10px]';
  const countText = dense ? 'text-[8.5px]' : 'text-[10px]';
  const iconSize = dense ? 'h-2 w-2' : 'h-2.5 w-2.5';

  return (
    <div>
      <div className={`mb-1 grid grid-cols-7 ${gap}`}>
        {WEEKDAYS.map((w) => (
          <div key={w} className={`text-center font-mono font-bold uppercase tracking-wide text-[var(--fg-3)] ${dense ? 'text-[8px]' : 'text-[9px]'}`}>{w}</div>
        ))}
      </div>
      <div className={`grid grid-cols-7 ${gap}`}>
        {cells.map((dnum, i) => {
          if (dnum === null) {
            return <div key={`b-${i}`} />;
          }
          const d = byDay.get(dnum);
          const isFuture = !d;
          const isToday = dnum === todayDay;
          const logged = Boolean(d && d.videos > 0);
          const cls = `${cellBase} ${isToday ? 'border-[var(--accent)] ring-1 ring-[var(--accent)]' : 'border-[var(--hair)]'} ${isFuture ? 'bg-[var(--app-bg)] opacity-40' : logged ? 'bg-[#ECFDF5]' : 'bg-[var(--app-bg)]'} ${onSelectDay && d ? 'transition active:scale-95' : ''}`;
          const body = (
            <>
              <span className={`${dayText} font-semibold leading-none ${logged ? 'text-[#047857]' : 'text-[var(--fg-3)]'}`}>{dnum}</span>
              {!isFuture && (
                <span className={`flex items-center gap-0.5 ${countText} font-bold leading-none tabular-nums ${logged ? 'text-[#047857]' : 'text-[var(--fg-3)]'}`}>
                  {logged ? (<><Video className={iconSize} strokeWidth={2.4} />{d.videos}</>) : '·'}
                </span>
              )}
            </>
          );

          return onSelectDay ? (
            <button type="button" key={dnum} disabled={isFuture} onClick={() => d && onSelectDay(d.date)} className={cls}>{body}</button>
          ) : (
            <div key={dnum} className={cls}>{body}</div>
          );
        })}
      </div>
    </div>
  );
}
