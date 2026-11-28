import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import GraduationController from '@/actions/App/Http/Controllers/Admin/GraduationController';
import { Button } from '@/components/ui/button';

type Props = {
    school: {
        foundation: string;
        unit: string;
        address: string | null;
        phone: string | null;
        head: string | null;
    };
    letter: { number: string; year: string; date: string };
    student: {
        name: string;
        nis: string;
        nisn: string | null;
        birth: string;
        classroom: string | null;
    };
};

/** Lembar SKL siap cetak (A4). Halaman berdiri sendiri tanpa sidebar agar hasil cetak bersih. */
export default function GraduationLetter({ school, letter, student }: Props) {
    const rows: [string, string][] = [
        ['Nama', student.name],
        ['NIS / NISN', `${student.nis} / ${student.nisn ?? '—'}`],
        ['Tempat, tanggal lahir', student.birth || '—'],
        ['Rombel', student.classroom ?? '—'],
        ['Tahun ajaran', letter.year],
    ];

    return (
        <div className="min-h-svh bg-muted/40 py-8 print:bg-white print:py-0">
            <Head title={`SKL ${student.name}`} />

            <div className="mx-auto mb-4 flex max-w-[210mm] justify-between gap-2 px-4 print:hidden">
                <Button asChild variant="outline">
                    <Link href={GraduationController.index.url()}>
                        <ArrowLeft className="size-4" strokeWidth={1.75} />
                        Kembali
                    </Link>
                </Button>
                <Button type="button" onClick={() => window.print()}>
                    <Printer className="size-4" strokeWidth={1.75} />
                    Cetak SKL
                </Button>
            </div>

            <article className="mx-auto min-h-[297mm] max-w-[210mm] bg-white p-[20mm] text-[11pt] leading-relaxed text-black shadow-sm print:shadow-none">
                <header className="border-b-2 border-black pb-3 text-center">
                    <p className="text-sm uppercase">{school.foundation}</p>
                    <h1 className="text-xl font-bold uppercase">
                        {school.unit}
                    </h1>
                    {(school.address || school.phone) && (
                        <p className="text-xs">
                            {[
                                school.address,
                                school.phone && `Telp. ${school.phone}`,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    )}
                </header>

                <h2 className="mt-8 text-center font-bold underline">
                    SURAT KETERANGAN LULUS
                </h2>
                <p className="text-center">Nomor: {letter.number}</p>

                <p className="mt-8">
                    Yang bertanda tangan di bawah ini, Kepala {school.unit},
                    menerangkan bahwa:
                </p>

                <table className="my-4 ml-6">
                    <tbody>
                        {rows.map(([k, v]) => (
                            <tr key={k}>
                                <td className="w-48 py-0.5 align-top">{k}</td>
                                <td className="px-2 py-0.5 align-top">:</td>
                                <td className="py-0.5 font-medium">{v}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <p>
                    dinyatakan <strong>LULUS</strong> dari {school.unit} pada
                    tahun ajaran {letter.year}. Surat keterangan ini berlaku
                    sementara sampai ijazah diterbitkan.
                </p>

                <div className="mt-16 ml-auto w-64 text-center">
                    <p>Ditetapkan, {letter.date}</p>
                    <p>Kepala {school.unit}</p>
                    <p className="mt-20 font-bold underline">
                        {school.head ?? '................................'}
                    </p>
                </div>
            </article>
        </div>
    );
}

GraduationLetter.layout = (page: ReactNode) => page;
