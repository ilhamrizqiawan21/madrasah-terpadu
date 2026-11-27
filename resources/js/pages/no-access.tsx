import { Form, Head } from '@inertiajs/react';
import { NoAccessState } from '@/components/app/no-access-state';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

export default function NoAccess() {
    return (
        <>
            <Head title="Belum memiliki akses" />

            <main className="flex min-h-screen items-center justify-center bg-background p-6 text-foreground">
                <NoAccessState
                    description="Akun Anda belum memiliki peran di sistem. Hubungi operator yayasan atau administrator untuk menetapkan peran yang sesuai."
                    action={
                        <Form action={logout()} method="post">
                            <Button type="submit" variant="outline">
                                Keluar
                            </Button>
                        </Form>
                    }
                />
            </main>
        </>
    );
}
