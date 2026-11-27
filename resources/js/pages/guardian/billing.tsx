import { Head } from '@inertiajs/react';
import { EmptyState } from '@/components/app/empty-state';
import GuardianLayout from '@/layouts/guardian-layout';

export default function GuardianBilling() {
    return (
        <GuardianLayout>
            <Head title="Tagihan" />

            <EmptyState
                title="Belum ada tagihan"
                description="Daftar tagihan sekolah anak Anda akan tampil di sini."
            />
        </GuardianLayout>
    );
}
