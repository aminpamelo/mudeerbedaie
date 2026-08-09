/** Join truthy class fragments. */
export function cn(...parts) {
  return parts.filter(Boolean).join(' ');
}

/** Up-to-two-letter initials from a name. */
export function initialsFrom(name) {
  if (!name) return '?';
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w.charAt(0).toUpperCase())
    .join('');
}

/**
 * Translate a dot-notation key using the shared translations object.
 * Falls back to the key itself if not found.
 * Supports :param replacements, e.g. t('student.courses.students', { count: 5 })
 */
export function t(key, params = {}, translations = null) {
  // Allow global access via window.__translations (set once per page)
  const trans = translations || window.__studentTranslations || {};
  const parts = key.split('.');
  let value = trans;
  for (const part of parts) {
    if (value && typeof value === 'object' && part in value) {
      value = value[part];
    } else {
      return key; // fallback
    }
  }
  if (typeof value !== 'string') return key;
  // Replace :param placeholders
  return Object.entries(params).reduce(
    (str, [k, v]) => str.replace(new RegExp(`:${k}`, 'g'), v),
    value
  );
}

/** Format a number as Malaysian Ringgit. */
export function formatMoney(value, { withSymbol = true } = {}) {
  const num = Number(value || 0);
  const formatted = num.toLocaleString('en-MY', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return withSymbol ? `RM ${formatted}` : formatted;
}
