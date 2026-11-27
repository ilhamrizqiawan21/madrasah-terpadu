import { Head } from '@inertiajs/react';
import { EmptyState } from '@/components/app/empty-state';
import GuardianLayout from '@/layouts/guardian-layout';

export default function GuardianHistory() {
    return (
        <GuardianLayout>
            <Head title="Riwayat" />

            <EmptyState
                title="Belum ada riwayat"
                description="Riwayat presensi, catatan perilaku, pembiasaan, dan setoran hafalan anak akan tampil di sini."
            />
        </GuardianLayout>
    );
}
