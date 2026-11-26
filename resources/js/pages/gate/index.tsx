import { Head } from '@inertiajs/react';

export default function GateIndex() {
    return (
        <>
            <Head title="Scan Gerbang" />
            <main className="mx-auto max-w-3xl p-6">
                <h1 className="text-xl font-semibold">Presensi Gerbang</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Layar scan kartu QR gerbang akan dilengkapi pada Fase 5.
                </p>
            </main>
        </>
    );
}
