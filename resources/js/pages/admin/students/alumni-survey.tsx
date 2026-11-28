import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useRef, useState } from 'react';
import AlumniTracerController from '@/actions/App/Http/Controllers/Admin/AlumniTracerController';
import { PageHeader } from '@/components/app/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import StaffLayout from '@/layouts/staff-layout';

type Option = { value: string; label: string };

type Props = {
    alumni: { id: number; label: string }[];
    search: string;
    years: number[];
    activityOptions: Option[];
    incomeOptions: Option[];
    relevanceOptions: Option[];
};

const NONE = 'kosong';

export default function AlumniSurvey({
    alumni,
    search,
    years,
    activityOptions,
    incomeOptions,
    relevanceOptions,
}: Props) {
    const [query, setQuery] = useState(search);
    const timer = useRef<number | undefined>(undefined);
    const form = useForm({
        student_id: '',
        graduation_year: String(years[0] ?? ''),
        activity: activityOptions[0]?.value ?? '',
        institution: '',
        program: '',
        income_range: NONE,
        relevance: relevanceOptions[0]?.value ?? '',
        feedback: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            income_range: d.income_range === NONE ? null : d.income_range,
        }));
        form.post(AlumniTracerController.store.url());
    };

    const select = (
        id: string,
        label: string,
        value: string,
        onChange: (v: string) => void,
        options: Option[],
        error?: string,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id}>
                    <SelectValue placeholder="Pilih" />
                </SelectTrigger>
                <SelectContent>
                    {options.map((o) => (
                        <SelectItem key={o.value} value={o.value}>
                            {o.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Pengguna', href: '#' },
                {
                    title: 'Survei Alumni',
                    href: AlumniTracerController.create.url(),
                },
            ]}
        >
            <Head title="Survei Alumni" />

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="Kuesioner penelusuran lulusan"
                    description="Isi atas nama alumni (siswa berstatus lulus). Jawaban yang sudah ada akan diperbarui."
                />

                <div className="grid gap-4 rounded-xl border border-border bg-card p-4 shadow-xs">
                    <div className="grid gap-2">
                        <Label htmlFor="alumni-search">Cari alumni</Label>
                        <Input
                            id="alumni-search"
                            type="search"
                            value={query}
                            onChange={(e) => {
                                const v = e.target.value;
                                setQuery(v);
                                window.clearTimeout(timer.current);
                                timer.current = window.setTimeout(
                                    () =>
                                        router.get(
                                            AlumniTracerController.create.url(),
                                            v ? { q: v } : {},
                                            {
                                                preserveState: true,
                                                replace: true,
                                                only: ['alumni', 'search'],
                                            },
                                        ),
                                    300,
                                );
                            }}
                            placeholder="Nama atau NIS alumni"
                        />
                    </div>
                    {select(
                        'alumni-student',
                        'Alumni',
                        form.data.student_id,
                        (v) => form.setData('student_id', v),
                        alumni.map((a) => ({
                            value: String(a.id),
                            label: a.label,
                        })),
                        form.errors.student_id,
                    )}
                    {alumni.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Belum ada siswa berstatus lulus yang cocok. Luluskan
                            siswa dari menu Siswa atau Kenaikan Kelas.
                        </p>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        {select(
                            'alumni-year',
                            'Tahun kelulusan (angkatan)',
                            form.data.graduation_year,
                            (v) => form.setData('graduation_year', v),
                            years.map((y) => ({
                                value: String(y),
                                label: String(y),
                            })),
                            form.errors.graduation_year,
                        )}
                        {select(
                            'alumni-activity',
                            'Status aktivitas saat ini',
                            form.data.activity,
                            (v) => form.setData('activity', v),
                            activityOptions,
                            form.errors.activity,
                        )}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="alumni-institution">
                                Nama kampus / perusahaan / usaha
                            </Label>
                            <Input
                                id="alumni-institution"
                                value={form.data.institution}
                                onChange={(e) =>
                                    form.setData('institution', e.target.value)
                                }
                                maxLength={150}
                            />
                            <InputError message={form.errors.institution} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="alumni-program">
                                Program studi / jabatan
                            </Label>
                            <Input
                                id="alumni-program"
                                value={form.data.program}
                                onChange={(e) =>
                                    form.setData('program', e.target.value)
                                }
                                maxLength={120}
                            />
                            <InputError message={form.errors.program} />
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {select(
                            'alumni-income',
                            'Rentang penghasilan (opsional)',
                            form.data.income_range,
                            (v) => form.setData('income_range', v),
                            [
                                { value: NONE, label: 'Tidak diisi' },
                                ...incomeOptions,
                            ],
                            form.errors.income_range,
                        )}
                        {select(
                            'alumni-relevance',
                            'Kesesuaian kurikulum sekolah',
                            form.data.relevance,
                            (v) => form.setData('relevance', v),
                            relevanceOptions,
                            form.errors.relevance,
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="alumni-feedback">
                            Saran, masukan & kritik untuk almamater
                        </Label>
                        <Textarea
                            id="alumni-feedback"
                            value={form.data.feedback}
                            onChange={(e) =>
                                form.setData('feedback', e.target.value)
                            }
                            rows={4}
                            maxLength={2000}
                        />
                        <InputError message={form.errors.feedback} />
                    </div>
                </div>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan kuesioner
                    </Button>
                </div>
            </form>
        </StaffLayout>
    );
}

AlumniSurvey.layout = (page: ReactNode) => page;
