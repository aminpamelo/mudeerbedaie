import { useEffect, useState, useCallback } from 'react';
import api from '../lib/api';

function getVapidKey() {
    return document.querySelector('meta[name="vapid-public-key"]')?.content;
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Shared state across all hook instances
let _isSubscribed = false;
let _listeners = new Set();

function notifyListeners() {
    _listeners.forEach(fn => fn(_isSubscribed));
}

export default function usePushSubscription() {
    const [isSubscribed, setIsSubscribed] = useState(_isSubscribed);
    const [isSupported, setIsSupported] = useState(false);
    const [checked, setChecked] = useState(false);

    // Sync shared state to local state
    useEffect(() => {
        const listener = (val) => setIsSubscribed(val);
        _listeners.add(listener);
        return () => _listeners.delete(listener);
    }, []);

    // Check current subscription status
    useEffect(() => {
        const vapidKey = getVapidKey();
        const supported = 'serviceWorker' in navigator && 'PushManager' in window && !!vapidKey;
        setIsSupported(supported);
        if (!supported) {
            setChecked(true);
            return;
        }

        navigator.serviceWorker.ready.then(async (registration) => {
            const subscription = await registration.pushManager.getSubscription();
            _isSubscribed = !!subscription;
            setIsSubscribed(_isSubscribed);
            setChecked(true);
            notifyListeners();
        }).catch(() => {
            setChecked(true);
        });
    }, []);

    const subscribe = useCallback(async () => {
        const vapidKey = getVapidKey();
        if (!vapidKey) return;

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidKey),
            });

            const key = subscription.getKey('p256dh');
            const auth = subscription.getKey('auth');

            await api.post('/push-subscriptions', {
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(key))),
                    auth: btoa(String.fromCharCode.apply(null, new Uint8Array(auth))),
                },
                content_encoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
            });

            _isSubscribed = true;
            setIsSubscribed(true);
            notifyListeners();
        } catch (err) {
            console.error('Push subscription failed:', err);
        }
    }, []);

    const unsubscribe = useCallback(async () => {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await api.delete('/push-subscriptions', {
                    data: { endpoint: subscription.endpoint },
                });
                await subscription.unsubscribe();
            }
            _isSubscribed = false;
            setIsSubscribed(false);
            notifyListeners();
        } catch (err) {
            console.error('Push unsubscribe failed:', err);
        }
    }, []);

    return { isSubscribed, isSupported, subscribe, unsubscribe, checked };
}
