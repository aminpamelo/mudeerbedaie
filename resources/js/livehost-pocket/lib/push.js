/**
 * Web Push helpers for the Live Host Pocket PWA.
 *
 * Bridges the browser PushManager to the Laravel subscription endpoints
 * (resources: POST/DELETE /live-host/push-subscriptions). The VAPID public key
 * and CSRF token are read from the document <meta> tags injected by
 * resources/views/livehost-pocket/app.blade.php.
 */

function meta(name) {
  return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';
}

/** Convert a base64url VAPID key into the Uint8Array PushManager expects. */
function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = window.atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i += 1) {
    output[i] = raw.charCodeAt(i);
  }
  return output;
}

/** True when this browser can register a SW and receive web push. */
export function pushSupported() {
  return (
    typeof window !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window
  );
}

export function notificationPermission() {
  return pushSupported() ? Notification.permission : 'denied';
}

export function vapidPublicKey() {
  return meta('vapid-public-key');
}

async function sendSubscription(url, method, body) {
  const response = await fetch(url, {
    method,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': meta('csrf-token'),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!response.ok) {
    throw new Error(`push request failed: ${response.status}`);
  }
  return response;
}

/**
 * Ensure the browser is subscribed and the subscription is persisted server-side.
 * Assumes permission has already been granted. Returns the PushSubscription.
 */
export async function subscribeToPush() {
  if (!pushSupported()) {
    throw new Error('push-unsupported');
  }
  const key = vapidPublicKey();
  if (!key) {
    throw new Error('missing-vapid-key');
  }

  const registration = await navigator.serviceWorker.ready;
  let subscription = await registration.pushManager.getSubscription();
  if (!subscription) {
    subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(key),
    });
  }

  const payload = subscription.toJSON();
  const contentEncoding =
    (window.PushManager && PushManager.supportedContentEncodings?.[0]) || 'aesgcm';

  await sendSubscription('/live-host/push-subscriptions', 'POST', {
    endpoint: subscription.endpoint,
    keys: payload.keys,
    content_encoding: contentEncoding,
  });

  return subscription;
}

/** Tear down the local subscription and remove it server-side. Best-effort. */
export async function unsubscribeFromPush() {
  if (!pushSupported()) {
    return;
  }
  const registration = await navigator.serviceWorker.ready;
  const subscription = await registration.pushManager.getSubscription();
  if (!subscription) {
    return;
  }
  await sendSubscription('/live-host/push-subscriptions', 'DELETE', {
    endpoint: subscription.endpoint,
  }).catch(() => {});
  await subscription.unsubscribe().catch(() => {});
}
