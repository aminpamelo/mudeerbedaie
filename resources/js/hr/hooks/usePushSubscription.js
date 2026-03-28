import { useEffect, useState } from 'react';
import api from '../lib/api';

const VAPID_PUBLIC_KEY = document.querySelector('meta[name="vapid-public-key"]')?.content;

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

export default function usePushSubscription() {
    const [isSubscribed, setIsSubscribed] = useState(false);
    const [isSupported, setIsSupported] = useState(false);

    useEffect(() => {
        const supported = 'serviceWorker' in navigator && 'PushManager' in window && !!VAPID_PUBLIC_KEY;
        setIsSupported(supported);
        if (!supported) return;

        navigator.serviceWorker.ready.then(async (registration) => {
            const subscription = await registration.pushManager.getSubscription();
            setIsSubscribed(!!subscription);
        });
    }, []);

    const subscribe = async () => {
        if (!isSupported) return;

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            });

            const key = subscription.getKey('p256dh');
            const auth = subscription.getKey('auth');

            await api.post('/push-subscriptions', {
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(key))),
                    auth: btoa(String.fromCharCode.apply(null, new Uint8Array(auth))),
                },
            });

            setIsSubscribed(true);
        } catch (err) {
            console.error('Push subscription failed:', err);
        }
    };

    const unsubscribe = async () => {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await api.delete('/push-subscriptions', {
                    data: { endpoint: subscription.endpoint },
                });
                await subscription.unsubscribe();
            }
            setIsSubscribed(false);
        } catch (err) {
            console.error('Push unsubscribe failed:', err);
        }
    };

    return { isSubscribed, isSupported, subscribe, unsubscribe };
}
