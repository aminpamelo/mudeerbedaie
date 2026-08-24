import { Star } from 'lucide-react';
import { isLayoutField } from '../lib/fields';

const inputClass =
  'w-full rounded-xl border border-line bg-white px-3.5 py-3 text-sm text-ink outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-brand focus:ring-2 focus:ring-brand/20';

/** Full-width selectable row shared by radio and checkbox options. */
function optionRowClass(active) {
  return `flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 text-sm text-ink transition select-none ${
    active
      ? 'border-brand bg-brand-soft ring-1 ring-brand/30'
      : 'border-line bg-white hover:border-brand/40 hover:bg-brand-soft/40'
  }`;
}

/**
 * Renders the interactive input for a single field. Shared between the public
 * fill page and the builder's live preview. Layout fields (section/paragraph)
 * render as static content.
 */
export default function FieldRenderer({ field, value, onChange, error, disabled = false }) {
  const { type, options = [], settings = {} } = field;

  if (type === 'section') {
    return (
      <h3 className="border-b border-line pb-2 text-[15px] font-semibold tracking-[-0.01em] text-ink">
        {field.label || 'Seksyen'}
      </h3>
    );
  }

  if (type === 'paragraph') {
    return <p className="text-sm leading-relaxed text-muted">{field.label}</p>;
  }

  if (type === 'image') {
    const src = settings.url || settings.path;
    if (!src) {
      return null;
    }
    const widths = { small: 'max-w-[200px]', medium: 'max-w-sm', large: 'max-w-xl', full: 'w-full' };
    const aligns = { left: 'mr-auto', center: 'mx-auto', right: 'ml-auto' };
    return (
      <figure>
        <img
          src={src}
          alt={field.label || ''}
          className={`block h-auto rounded-xl border border-line ${widths[settings.width] || 'max-w-sm'} ${aligns[settings.align] || 'mx-auto'}`}
        />
        {field.label && <figcaption className="mt-2 text-center text-xs text-muted">{field.label}</figcaption>}
      </figure>
    );
  }

  const helpId = field.help ? `help-${field.id}` : undefined;
  const errorId = error ? `err-${field.id}` : undefined;
  const describedBy = [helpId, errorId].filter(Boolean).join(' ') || undefined;

  return (
    <div>
      <label className="mb-1.5 block text-sm font-semibold text-ink">
        {field.label || 'Soalan tanpa tajuk'}
        {field.required && (
          <>
            <span aria-hidden="true" className="ml-1 text-rose-500">
              *
            </span>
            <span className="sr-only"> (diperlukan)</span>
          </>
        )}
      </label>
      {field.help && (
        <p id={helpId} className="mb-2 text-xs leading-relaxed text-muted">
          {field.help}
        </p>
      )}

      {renderControl({ type, field, options, settings, value, onChange, disabled, describedBy, invalid: !!error })}

      {error && (
        <p id={errorId} role="alert" className="mt-1.5 text-xs font-medium text-rose-500">
          {error}
        </p>
      )}
    </div>
  );
}

function renderControl({ type, field, options, settings, value, onChange, disabled, describedBy, invalid }) {
  const set = (v) => onChange?.(v);
  const common = {
    disabled,
    'aria-describedby': describedBy,
    'aria-invalid': invalid || undefined,
  };

  switch (type) {
    case 'long_text':
      return (
        <textarea
          {...common}
          className={inputClass}
          rows={4}
          value={value ?? ''}
          onChange={(e) => set(e.target.value)}
        />
      );

    case 'number':
      return (
        <input
          {...common}
          type="number"
          inputMode="numeric"
          className={inputClass}
          value={value ?? ''}
          onChange={(e) => set(e.target.value)}
        />
      );

    case 'email':
      return (
        <input
          {...common}
          type="email"
          autoComplete="email"
          className={inputClass}
          value={value ?? ''}
          onChange={(e) => set(e.target.value)}
          placeholder="nama@contoh.com"
        />
      );

    case 'date':
      return (
        <input {...common} type="date" className={inputClass} value={value ?? ''} onChange={(e) => set(e.target.value)} />
      );

    case 'phone':
      return (
        <input
          {...common}
          type="tel"
          inputMode="tel"
          autoComplete="tel"
          className={inputClass}
          value={value ?? ''}
          onChange={(e) => set(e.target.value)}
          placeholder="012-3456789"
        />
      );

    case 'dropdown':
      return (
        <select {...common} className={inputClass} value={value ?? ''} onChange={(e) => set(e.target.value)}>
          <option value="">— Pilih —</option>
          {options.map((opt, i) => (
            <option key={i} value={opt}>
              {opt}
            </option>
          ))}
        </select>
      );

    case 'radio':
      return (
        <div className="space-y-2.5" role="radiogroup" aria-describedby={describedBy}>
          {options.map((opt, i) => (
            <label key={i} className={optionRowClass(value === opt)}>
              <input
                type="radio"
                name={field.id}
                className="h-4 w-4 shrink-0 accent-brand"
                checked={value === opt}
                disabled={disabled}
                onChange={() => set(opt)}
              />
              <span>{opt}</span>
            </label>
          ))}
        </div>
      );

    case 'checkbox': {
      const arr = Array.isArray(value) ? value : [];
      const toggle = (opt) => {
        if (arr.includes(opt)) {
          set(arr.filter((v) => v !== opt));
        } else {
          set([...arr, opt]);
        }
      };
      return (
        <div className="space-y-2.5">
          {options.map((opt, i) => (
            <label key={i} className={optionRowClass(arr.includes(opt))}>
              <input
                type="checkbox"
                className="h-4 w-4 shrink-0 rounded accent-brand"
                checked={arr.includes(opt)}
                disabled={disabled}
                onChange={() => toggle(opt)}
              />
              <span>{opt}</span>
            </label>
          ))}
        </div>
      );
    }

    case 'file':
      return (
        <input
          {...common}
          type="file"
          className="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-brand-soft file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-ink hover:file:bg-brand/10 file:cursor-pointer"
          onChange={(e) => set(e.target.files?.[0] ?? null)}
        />
      );

    case 'rating': {
      const max = Number(settings.max) || 5;
      const current = Number(value) || 0;
      return (
        <div className="flex gap-1" role="radiogroup" aria-describedby={describedBy}>
          {Array.from({ length: max }, (_, i) => i + 1).map((n) => (
            <button
              key={n}
              type="button"
              disabled={disabled}
              onClick={() => set(n)}
              className="rounded-md p-1 transition hover:scale-110 active:scale-95 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30"
              aria-label={`${n} bintang`}
              aria-pressed={n <= current}
            >
              <Star
                className={`h-7 w-7 transition-colors ${n <= current ? 'fill-amber-400 text-amber-400' : 'text-slate-300'}`}
              />
            </button>
          ))}
        </div>
      );
    }

    default:
      return (
        <input {...common} type="text" className={inputClass} value={value ?? ''} onChange={(e) => set(e.target.value)} />
      );
  }
}

export { isLayoutField };
