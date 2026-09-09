import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { csrfToken } from '@/cekbot-admin/lib/utils';

window.Pusher = Pusher;

let echo = null;

/**
 * Lazily create the Laravel Echo (Reverb) client. Returns null when broadcasting
 * is not configured (no VITE_REVERB_APP_KEY), so callers can fall back to polling.
 */
export function getEcho() {
  if (echo) return echo;

  const key = import.meta.env.VITE_REVERB_APP_KEY;
  if (!key) return null;

  echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    auth: { headers: { 'X-CSRF-TOKEN': csrfToken() } },
  });

  return echo;
}
