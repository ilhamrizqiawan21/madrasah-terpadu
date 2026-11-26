import { Head } from '@inertiajs/react';

export default function CanteenIndex() {
    return (
        <>
            <Head title="POS Kantin" />
            <main className="mx-auto max-w-3xl p-6">
                <h1 className="text-xl font-semibold">POS Kantin</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Layar POS kantin akan dilengkapi pada Fase 8.
                </p>
            </main>
        </>
    );
}
