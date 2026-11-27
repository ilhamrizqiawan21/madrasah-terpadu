import { Head, Link } from '@inertiajs/react';
import { home } from '@/routes';

type Props = { status: number };

const MESSAGES: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Akses tidak diizinkan',
        description:
            'Akun Anda tidak memiliki izin untuk membuka halaman ini. Hubungi admin sekolah bila Anda memerlukannya.',
    },
    404: {
        title: 'Halaman tidak ditemukan',
        description:
            'Alamat yang Anda buka tidak ada, sudah dipindahkan, atau modulnya sedang dimatikan.',
    },
    419: {
        title: 'Sesi berakhir',
        description:
            'Halaman terlalu lama dibiarkan terbuka. Muat ulang halaman lalu coba lagi.',
    },
    429: {
        title: 'Terlalu banyak percobaan',
        description: 'Tunggu sebentar sebelum mencoba lagi.',
    },
    500: {
        title: 'Terjadi kesalahan di server',
        description:
            'Permintaan Anda belum berhasil diproses. Coba lagi beberapa saat; bila berulang, hubungi admin sekolah.',
    },
    503: {
        title: 'Sedang dalam pemeliharaan',
        description:
            'Aplikasi sedang diperbarui dan akan kembali sebentar lagi.',
    },
};

/** Halaman galat berbahasa Indonesia; berdiri sendiri karena prop bersama bisa tidak tersedia saat galat. */
export default function ErrorPage({ status }: Props) {
    const message = MESSAGES[status] ?? MESSAGES[500];

    return (
        <main className="bg-islamic-pattern-subtle flex min-h-screen items-center justify-center bg-background px-6">
            <Head title={message.title} />
            <div className="w-full max-w-md rounded-xl border border-border bg-card p-8 text-center shadow-xs">
                <p className="text-sm font-semibold text-primary tabular-nums">
                    {status}
                </p>
                <h1 className="mt-2 text-xl font-semibold tracking-tight">
                    {message.title}
                </h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    {message.description}
                </p>
                <div className="mt-6 flex justify-center gap-2">
                    <button
                        type="button"
                        onClick={() => window.history.back()}
                        className="inline-flex h-9 items-center rounded-md border border-border bg-card px-4 text-sm font-medium transition-colors duration-150 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        Kembali
                    </button>
                    <Link
                        href={home()}
                        className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors duration-150 hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        Ke beranda
                    </Link>
                </div>
            </div>
        </main>
    );
}
