import PushSubscriptionController from '@/actions/App/Http/Controllers/PushSubscriptionController';

export type PushPermissionState = NotificationPermission | 'unsupported';

export function isPushSupported(): boolean {
    return (
        typeof window !== 'undefined' &&
        'Notification' in window &&
        'serviceWorker' in navigator &&
        'PushManager' in window
    );
}

export function getPushPermission(): PushPermissionState {
    if (!isPushSupported()) {
        return 'unsupported';
    }

    return Notification.permission;
}

export async function getCurrentPushSubscription(): Promise<PushSubscription | null> {
    if (!isPushSupported()) {
        return null;
    }

    const registration = await navigator.serviceWorker.ready;

    return registration.pushManager.getSubscription();
}

function urlBase64ToUint8Array(base64String: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const buffer = new ArrayBuffer(rawData.length);
    const output = new Uint8Array(buffer);

    for (let i = 0; i < rawData.length; i++) {
        output[i] = rawData.charCodeAt(i);
    }

    return output;
}

function csrfToken(): string {
    const meta = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    );

    if (meta?.content) {
        return meta.content;
    }

    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

/**
 * Meminta izin notifikasi HANYA ketika dipanggil dari klik tombol pengguna (T-401).
 */
export async function subscribeToPush(
    vapidPublicKey: string,
): Promise<boolean> {
    if (!isPushSupported() || !vapidPublicKey) {
        return false;
    }

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        return false;
    }

    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    const json = subscription.toJSON();
    const token = csrfToken();

    const response = await fetch(PushSubscriptionController.store.url(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': token,
        },
        body: JSON.stringify({
            endpoint: subscription.endpoint,
            keys: json.keys ?? {},
            content_encoding: 'aes128gcm',
        }),
    });

    return response.ok;
}

export async function unsubscribeFromPush(): Promise<boolean> {
    const subscription = await getCurrentPushSubscription();

    if (!subscription) {
        return true;
    }

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();

    const response = await fetch(PushSubscriptionController.destroy.url(), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ endpoint }),
    });

    return response.ok;
}
