import { Star } from 'lucide-react';
import { isLayoutField } from '../lib/fields';

const inputClass =
  'w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20';

/**
 * Renders the interactive input for a single field. Shared between the public
 * fill page and the builder's live preview. Layout fields (section/paragraph)
 * render as static content.
 */
export default function FieldRenderer({ field, value, onChange, error, disabled = false }) {
  const { type, options = [], settings = {} } = field;

  if (type === 'section') {
    return <h3 className="border-b border-line pb-2 text-lg font-semibold text-ink">{field.label || 'Seksyen'}</h3>;
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

  return (
    <div>
      <label className="mb-1.5 block text-sm font-medium text-ink">
        {field.label || 'Soalan tanpa tajuk'}
        {field.required && <span className="ml-1 text-rose-500">*</span>}
      </label>
      {field.help && <p className="mb-2 text-xs text-muted">{field.help}</p>}

      {renderControl({ type, field, options, settings, value, onChange, disabled })}

      {error && <p className="mt-1.5 text-xs text-rose-500">{error}</p>}
    </div>
  );
}

function renderControl({ type, field, options, settings, value, onChange, disabled }) {
  const set = (v) => onChange?.(v);

  switch (type) {
    case 'long_text':
      return (
        <textarea
          className={inputClass}
          rows={4}
          value={value ?? ''}
          disabled={disabled}
          onChange={(e) => set(e.target.value)}
        />
      );

    case 'number':
      return (
        <input type="number" className={inputClass} value={value ?? ''} disabled={disabled} onChange={(e) => set(e.target.value)} />
      );

    case 'email':
      return (
        <input type="email" className={inputClass} value={value ?? ''} disabled={disabled} onChange={(e) => set(e.target.value)} placeholder="nama@contoh.com" />
      );

    case 'date':
      return (
        <input type="date" className={inputClass} value={value ?? ''} disabled={disabled} onChange={(e) => set(e.target.value)} />
      );

    case 'phone':
      return (
        <input type="tel" className={inputClass} value={value ?? ''} disabled={disabled} onChange={(e) => set(e.target.value)} placeholder="012-3456789" />
      );

    case 'dropdown':
      return (
        <select className={inputClass} value={value ?? ''} disabled={disabled} onChange={(e) => set(e.target.value)}>
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
        <div className="space-y-2">
          {options.map((opt, i) => (
            <label key={i} className="flex items-center gap-2.5 text-sm text-ink">
              <input
                type="radio"
                name={field.id}
                className="h-4 w-4 accent-brand"
                checked={value === opt}
                disabled={disabled}
                onChange={() => set(opt)}
              />
              {opt}
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
        <div className="space-y-2">
          {options.map((opt, i) => (
            <label key={i} className="flex items-center gap-2.5 text-sm text-ink">
              <input
                type="checkbox"
                className="h-4 w-4 rounded accent-brand"
                checked={arr.includes(opt)}
                disabled={disabled}
                onChange={() => toggle(opt)}
              />
              {opt}
            </label>
          ))}
        </div>
      );
    }

    case 'file':
      return (
        <input
          type="file"
          className="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-brand-soft file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-ink hover:file:bg-brand/10"
          disabled={disabled}
          onChange={(e) => set(e.target.files?.[0] ?? null)}
        />
      );

    case 'rating': {
      const max = Number(settings.max) || 5;
      const current = Number(value) || 0;
      return (
        <div className="flex gap-1.5">
          {Array.from({ length: max }, (_, i) => i + 1).map((n) => (
            <button
              key={n}
              type="button"
              disabled={disabled}
              onClick={() => set(n)}
              className="transition hover:scale-110 disabled:cursor-not-allowed"
              aria-label={`${n} bintang`}
            >
              <Star className={`h-7 w-7 ${n <= current ? 'fill-amber-400 text-amber-400' : 'text-slate-300'}`} />
            </button>
          ))}
        </div>
      );
    }

    default:
      return (
        <input type="text" className={inputClass} value={value ?? ''} disabled={disabled} onChange={(e) => set(e.target.value)} />
      );
  }
}

export { isLayoutField };
