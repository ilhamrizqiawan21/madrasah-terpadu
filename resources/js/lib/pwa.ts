export type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

const DISMISS_KEY = 'mt_pwa_install_dismissed';

export function registerServiceWorker(): void {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Abaikan bila browser menolak SW (mis. mode privat).
        });
    });
}

export function isStandaloneMode(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (window.navigator as Navigator & { standalone?: boolean })
            .standalone === true
    );
}

export function isIosSafari(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const ua = window.navigator.userAgent;

    return (
        /iPad|iPhone|iPod/.test(ua) &&
        /Safari/.test(ua) &&
        !/CriOS|FxiOS/.test(ua)
    );
}

export function isInstallBannerDismissed(): boolean {
    try {
        return window.localStorage.getItem(DISMISS_KEY) === '1';
    } catch {
        return false;
    }
}

export function dismissInstallBanner(): void {
    try {
        window.localStorage.setItem(DISMISS_KEY, '1');
    } catch {
        // Abaikan bila storage diblokir.
    }
}
