import { Form, Head } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

export default function NoAccess() {
    return (
        <>
            <Head title="Belum memiliki akses" />

            <main className="flex min-h-screen items-center justify-center bg-background p-6 text-foreground">
                <div className="w-full max-w-md rounded-lg border border-border bg-card p-6 text-center">
                    <div className="mx-auto mb-4 flex size-11 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                        <ShieldAlert className="size-5" strokeWidth={1.75} />
                    </div>
                    <h1 className="text-lg font-semibold">
                        Akun belum memiliki akses
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Akun Anda belum memiliki peran di sistem. Hubungi
                        operator yayasan atau administrator untuk menetapkan
                        peran yang sesuai.
                    </p>
                    <Form action={logout()} method="post" className="mt-6">
                        <Button type="submit" variant="outline">
                            Keluar
                        </Button>
                    </Form>
                </div>
            </main>
        </>
    );
}
