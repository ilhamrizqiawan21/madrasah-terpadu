import { Head } from '@inertiajs/react';
import StaffLayout from '@/layouts/staff-layout';
import { dashboard } from '@/routes/admin';

export default function AdminDashboard() {
    return (
        <StaffLayout breadcrumbs={[{ title: 'Beranda', href: dashboard() }]}>
            <Head title="Beranda Staf" />
            <div className="space-y-4">
                <h1 className="text-xl font-semibold">Beranda Staf</h1>
                <p className="text-sm text-muted-foreground">
                    Pilih menu di bagian atas untuk mengelola data sesuai hak
                    akses Anda.
                </p>
            </div>
        </StaffLayout>
    );
}
