import QrScanner from 'qr-scanner';

export type ScanTone = 'success' | 'warning' | 'error';

export function playScanTone(tone: ScanTone): void {
    if (typeof window === 'undefined') {
        return;
    }

    const AudioCtx = window.AudioContext;

    if (!AudioCtx) {
        return;
    }

    try {
        const ctx = new AudioCtx();
        const now = ctx.currentTime;

        const playBeep = (
            frequency: number,
            startOffset: number,
            duration: number,
        ): void => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = tone === 'error' ? 'triangle' : 'sine';
            osc.frequency.setValueAtTime(frequency, now + startOffset);
            gain.gain.setValueAtTime(0.14, now + startOffset);
            gain.gain.exponentialRampToValueAtTime(
                0.001,
                now + startOffset + duration,
            );
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now + startOffset);
            osc.stop(now + startOffset + duration);
        };

        if (tone === 'success') {
            playBeep(880, 0, 0.12);
            playBeep(1174, 0.13, 0.16);
        } else if (tone === 'warning') {
            playBeep(660, 0, 0.16);
            playBeep(520, 0.18, 0.18);
        } else {
            playBeep(310, 0, 0.24);
        }

        window.setTimeout(() => {
            void ctx.close();
        }, 600);
    } catch {
        // Audio diblokir browser sebelum interaksi pengguna; abaikan.
    }
}

type BarcodeDetectorHit = {
    rawValue?: string;
};

type BarcodeDetectorInstance = {
    detect: (source: HTMLVideoElement) => Promise<BarcodeDetectorHit[]>;
};

type BarcodeDetectorConstructor = new (options?: {
    formats?: string[];
}) => BarcodeDetectorInstance;

export type CameraScannerHandle = {
    stop: () => void;
};

export async function startQrCameraScanner(
    video: HTMLVideoElement,
    onDetected: (value: string) => void,
    onError: (message: string) => void,
): Promise<CameraScannerHandle> {
    const nativeDetector = (
        window as unknown as { BarcodeDetector?: BarcodeDetectorConstructor }
    ).BarcodeDetector;

    if (nativeDetector && navigator.mediaDevices?.getUserMedia) {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false,
            });
            video.srcObject = stream;
            await video.play();

            const detector = new nativeDetector({ formats: ['qr_code'] });
            let active = true;
            let rafId = 0;

            const tick = async (): Promise<void> => {
                if (!active) {
                    return;
                }

                if (video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
                    try {
                        const hits = await detector.detect(video);
                        const code = hits[0]?.rawValue?.trim();

                        if (code) {
                            onDetected(code);
                        }
                    } catch {
                        // Frame gagal didekode; lanjut frame berikutnya.
                    }
                }

                rafId = window.requestAnimationFrame(() => {
                    void tick();
                });
            };

            void tick();

            return {
                stop: () => {
                    active = false;
                    window.cancelAnimationFrame(rafId);
                    stream.getTracks().forEach((track) => track.stop());
                    video.srcObject = null;
                },
            };
        } catch {
            // Jatuh ke fallback qr-scanner bila BarcodeDetector atau izin gagal.
        }
    }

    const fallbackScanner = new QrScanner(
        video,
        (result) => {
            const code = result.data?.trim();

            if (code) {
                onDetected(code);
            }
        },
        {
            preferredCamera: 'environment',
            highlightScanRegion: true,
        },
    );

    try {
        await fallbackScanner.start();
    } catch {
        onError(
            'Kamera tidak dapat diakses. Pastikan izin kamera diaktifkan atau gunakan scanner barcode.',
        );
    }

    return {
        stop: () => {
            fallbackScanner.stop();
            fallbackScanner.destroy();
        },
    };
}
