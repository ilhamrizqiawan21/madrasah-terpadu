import { Head } from '@inertiajs/react';

export default function GuardianHome() {
    return (
        <>
            <Head title="Beranda Wali" />
            <main className="mx-auto max-w-xl p-6">
                <h1 className="text-xl font-semibold">Beranda Wali Murid</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Portal wali murid akan dilengkapi pada Fase 3.
                </p>
            </main>
        </>
    );
}
