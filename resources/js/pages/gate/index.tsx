import { Head } from '@inertiajs/react';
import { Camera, CameraOff, Clock, QrCode, ScanLine } from 'lucide-react';
import type { FormEvent } from 'react';
import { useCallback, useEffect, useRef, useState } from 'react';
import GateController from '@/actions/App/Http/Controllers/Gate/GateController';
import { EmptyState } from '@/components/app/empty-state';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import KioskLayout from '@/layouts/kiosk-layout';
import type { CameraScannerHandle, ScanTone } from '@/lib/scanner';
import { playScanTone, startQrCameraScanner } from '@/lib/scanner';

type RecentScan = {
    id: number;
    student_name: string;
    nis: string;
    unit: string;
    classroom: string;
    result: string;
    result_label: string;
    time_label: string;
};

type ScanOutcomeResponse = {
    accepted: boolean;
    code: string;
    message: string;
    tone: ScanTone;
    time_label: string;
    scan_id: number | null;
    student: {
        id: number;
        name: string;
        nis: string;
        unit: string;
        classroom: string;
        photo_url: string | null;
    } | null;
};

type Props = {
    schoolName: string;
    operatorName: string;
    hasAdminAccess: boolean;
    recentScans: RecentScan[];
};

function readXsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function badgeVariantForResult(
    result: string,
): 'success' | 'warning' | 'destructive' | 'muted' {
    switch (result) {
        case 'on_time':
        case 'check_out':
            return 'success';
        case 'late':
            return 'warning';
        case 'duplicate':
        case 'outside_hours':
        case 'holiday':
            return 'muted';
        default:
            return 'destructive';
    }
}

function initialsOf(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export default function GateIndex({
    schoolName,
    operatorName,
    hasAdminAccess,
    recentScans: initialRecentScans,
}: Props) {
    const [tokenInput, setTokenInput] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [lastOutcome, setLastOutcome] = useState<ScanOutcomeResponse | null>(
        null,
    );
    const [recentScans, setRecentScans] =
        useState<RecentScan[]>(initialRecentScans);
    const [cameraOpen, setCameraOpen] = useState(false);
    const [cameraError, setCameraError] = useState<string | null>(null);

    const inputRef = useRef<HTMLInputElement>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const scannerRef = useRef<CameraScannerHandle | null>(null);
    const lastCameraValueRef = useRef<{ value: string; at: number }>({
        value: '',
        at: 0,
    });

    const focusInput = useCallback((): void => {
        if (!cameraOpen) {
            inputRef.current?.focus();
        }
    }, [cameraOpen]);

    useEffect(() => {
        focusInput();
    }, [focusInput]);

    const submitToken = useCallback(async (rawToken: string): Promise<void> => {
        const cleaned = rawToken.trim();

        if (!cleaned) {
            return;
        }

        setSubmitting(true);

        try {
            const response = await fetch(GateController.scan.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
                body: JSON.stringify({
                    token: cleaned,
                    device_label: 'Gerbang',
                }),
            });

            if (!response.ok) {
                playScanTone('error');
                setLastOutcome({
                    accepted: false,
                    code: 'request_failed',
                    message:
                        response.status === 429
                            ? 'Terlalu banyak scan. Tunggu beberapa detik.'
                            : 'Gagal memproses scan. Periksa koneksi.',
                    tone: 'error',
                    time_label: '-',
                    scan_id: null,
                    student: null,
                });

                return;
            }

            const payload = (await response.json()) as ScanOutcomeResponse;
            setLastOutcome(payload);
            playScanTone(payload.tone);

            if (payload.student && payload.scan_id !== null) {
                const entry: RecentScan = {
                    id: payload.scan_id,
                    student_name: payload.student.name,
                    nis: payload.student.nis,
                    unit: payload.student.unit,
                    classroom: payload.student.classroom,
                    result: payload.code,
                    result_label: payload.message,
                    time_label: payload.time_label,
                };

                setRecentScans((prev) => [entry, ...prev].slice(0, 10));
            }
        } catch {
            playScanTone('error');
            setLastOutcome({
                accepted: false,
                code: 'network_error',
                message: 'Koneksi terputus saat mengirim scan.',
                tone: 'error',
                time_label: '-',
                scan_id: null,
                student: null,
            });
        } finally {
            setSubmitting(false);
            setTokenInput('');
            inputRef.current?.focus();
        }
    }, []);

    useEffect(() => {
        if (!cameraOpen || !videoRef.current) {
            return;
        }

        let cancelled = false;
        setCameraError(null);

        void startQrCameraScanner(
            videoRef.current,
            (detected) => {
                const now = Date.now();

                if (
                    lastCameraValueRef.current.value === detected &&
                    now - lastCameraValueRef.current.at < 2500
                ) {
                    return;
                }

                lastCameraValueRef.current = { value: detected, at: now };
                void submitToken(detected);
            },
            (err) => {
                if (!cancelled) {
                    setCameraError(err);
                }
            },
        ).then((handle) => {
            if (cancelled) {
                handle.stop();
            } else {
                scannerRef.current = handle;
            }
        });

        return () => {
            cancelled = true;
            scannerRef.current?.stop();
            scannerRef.current = null;
        };
    }, [cameraOpen, submitToken]);

    const handleFormSubmit = (event: FormEvent): void => {
        event.preventDefault();
        void submitToken(tokenInput);
    };

    const outcomeColors =
        lastOutcome?.tone === 'success'
            ? 'border-emerald-300 bg-emerald-50/70'
            : lastOutcome?.tone === 'warning'
              ? 'border-amber-300 bg-amber-50/70'
              : lastOutcome
                ? 'border-destructive/40 bg-destructive/5'
                : 'border-border bg-card';

    return (
        <KioskLayout
            schoolName={schoolName}
            kioskTitle="Scan Presensi Gerbang"
            operatorName={operatorName}
            hasAdminAccess={hasAdminAccess}
        >
            <Head title="Scan Presensi Gerbang" />

            <div className="grid gap-6 lg:grid-cols-12">
                <section className="space-y-4 lg:col-span-7">
                    <Card className="shadow-xs">
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center justify-between text-base">
                                <span className="flex items-center gap-2">
                                    <ScanLine
                                        className="size-5 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span>Pemindai Kartu QR / RFID</span>
                                </span>
                                <Button
                                    type="button"
                                    variant={
                                        cameraOpen ? 'secondary' : 'outline'
                                    }
                                    className="min-h-11 gap-2"
                                    onClick={() =>
                                        setCameraOpen((open) => !open)
                                    }
                                >
                                    {cameraOpen ? (
                                        <>
                                            <CameraOff className="size-4" />
                                            <span>Tutup kamera</span>
                                        </>
                                    ) : (
                                        <>
                                            <Camera className="size-4" />
                                            <span>Kamera QR</span>
                                        </>
                                    )}
                                </Button>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <form
                                onSubmit={handleFormSubmit}
                                className="flex gap-2"
                            >
                                <Input
                                    ref={inputRef}
                                    type="text"
                                    value={tokenInput}
                                    onChange={(e) =>
                                        setTokenInput(e.target.value)
                                    }
                                    onBlur={() => {
                                        window.setTimeout(focusInput, 120);
                                    }}
                                    placeholder="Arahkan scanner barcode/RFID ke sini..."
                                    aria-label="Token kartu QR atau UID RFID"
                                    autoComplete="off"
                                    disabled={submitting}
                                    className="h-12 text-base"
                                />
                                <Button
                                    type="submit"
                                    disabled={submitting || !tokenInput.trim()}
                                    className="min-h-12 px-5"
                                >
                                    Proses
                                </Button>
                            </form>

                            {cameraOpen && (
                                <div className="space-y-2 rounded-lg border border-border bg-muted/40 p-3">
                                    <video
                                        ref={videoRef}
                                        muted
                                        playsInline
                                        className="aspect-video w-full rounded-md bg-black object-cover"
                                    />
                                    {cameraError && (
                                        <p className="text-sm text-destructive">
                                            {cameraError}
                                        </p>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card
                        className={`border-2 shadow-xs transition-colors ${outcomeColors}`}
                        aria-live="polite"
                    >
                        <CardContent className="p-6">
                            {lastOutcome === null ? (
                                <EmptyState
                                    icon={QrCode}
                                    title="Siap memindai kartu siswa"
                                    description="Tempelkan kartu RFID atau arahkan kartu QR ke scanner untuk mencatat kehadiran."
                                />
                            ) : (
                                <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
                                    <div className="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-muted text-2xl font-semibold text-muted-foreground">
                                        {lastOutcome.student?.photo_url ? (
                                            <img
                                                src={
                                                    lastOutcome.student
                                                        .photo_url
                                                }
                                                alt={lastOutcome.student.name}
                                                className="size-full object-cover"
                                            />
                                        ) : lastOutcome.student ? (
                                            initialsOf(lastOutcome.student.name)
                                        ) : (
                                            <QrCode className="size-10" />
                                        )}
                                    </div>

                                    <div className="min-w-0 flex-1 space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <StatusBadge
                                                variant={badgeVariantForResult(
                                                    lastOutcome.code,
                                                )}
                                            >
                                                {lastOutcome.message}
                                            </StatusBadge>
                                            <span className="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground">
                                                <Clock
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                {lastOutcome.time_label} WITA
                                            </span>
                                        </div>

                                        {lastOutcome.student ? (
                                            <>
                                                <h2 className="truncate text-2xl font-bold tracking-tight">
                                                    {lastOutcome.student.name}
                                                </h2>
                                                <p className="text-sm text-muted-foreground">
                                                    NIS{' '}
                                                    {lastOutcome.student.nis} ·{' '}
                                                    {
                                                        lastOutcome.student
                                                            .classroom
                                                    }{' '}
                                                    · Unit{' '}
                                                    {lastOutcome.student.unit}
                                                </p>
                                            </>
                                        ) : (
                                            <h2 className="text-xl font-semibold text-destructive">
                                                {lastOutcome.message}
                                            </h2>
                                        )}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <aside className="lg:col-span-5">
                    <Card className="shadow-xs">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">
                                10 Scan Terakhir Hari Ini
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {recentScans.length === 0 ? (
                                <EmptyState
                                    icon={Clock}
                                    title="Belum ada scan hari ini"
                                    description="Riwayat 10 scan terakhir akan muncul otomatis di sini."
                                />
                            ) : (
                                <ul className="divide-y divide-border">
                                    {recentScans.map((item) => (
                                        <li
                                            key={item.id}
                                            className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {item.student_name}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {item.classroom} ·{' '}
                                                    {item.unit} ·{' '}
                                                    {item.time_label} WITA
                                                </p>
                                            </div>
                                            <StatusBadge
                                                variant={badgeVariantForResult(
                                                    item.result,
                                                )}
                                            >
                                                {item.result_label}
                                            </StatusBadge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </KioskLayout>
    );
}
