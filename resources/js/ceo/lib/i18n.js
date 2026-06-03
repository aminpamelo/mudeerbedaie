import { usePage } from '@inertiajs/react';

/**
 * Frontend translation hook for the CEO bundle. Reads the `i18n` dictionary
 * shared by HandleCeoInertiaRequests (the current-locale `ceo.ui` strings) and
 * returns a `t(key, replacements)` function. Backend-generated strings (metric
 * labels, alerts, section titles) arrive already translated in the payload.
 */
export function useT() {
  const { props } = usePage();
  const dict = props.i18n ?? {};

  return (key, replacements = {}) => {
    let str = dict[key] ?? key;
    for (const [k, v] of Object.entries(replacements)) {
      str = str.replaceAll(`:${k}`, String(v));
    }
    return str;
  };
}

export function useLocale() {
  const { props } = usePage();
  return {
    locale: props.ceoLocale ?? 'ms',
    locales: props.ceoLocales ?? [],
  };
}

/** Map a traffic-light status to its translated label via the `t` function. */
export function statusLabel(t, status) {
  if (status === 'red') return t('attention');
  if (status === 'amber') return t('watch');
  return t('healthy');
}
