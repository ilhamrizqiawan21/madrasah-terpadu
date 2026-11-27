import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import StudentController from '@/actions/App/Http/Controllers/Admin/StudentController';
import StudentMutationController from '@/actions/App/Http/Controllers/Admin/StudentMutationController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
import { ErrorState } from '@/components/app/error-state';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
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
import StaffLayout from '@/layouts/staff-layout';
import type { PaginatedResource } from '@/types';

type MutationType = 'in' | 'out' | 'graduated';

type MutationRow = {
    id: number;
    type: { value: MutationType; label: string };
    mutated_on: string;
    school_name: string | null;
    reason: string;
    student: { id: number; nis: string; name: string; unit: string };
    recorded_by: string;
};

type UnitOption = { id: number; code: string; name: string };
type Option = { value: string; label: string };
type ClassroomOption = { id: number; unit_id: number; name: string };
type ActiveStudentOption = {
    id: number;
    nis: string;
    name: string;
    unit: string;
};

type Props = {
    mutations: PaginatedResource<MutationRow>;
    filters: { unit_id: number | null; type: string; search: string };
    unitOptions: UnitOption[];
    typeOptions: Option[];
    genderOptions: Option[];
    classroomOptions: ClassroomOption[];
    activeStudentOptions: ActiveStudentOption[];
};

const ALL = 'all';

const typeVariant: Record<MutationType, StatusBadgeVariant> = {
    in: 'success',
    out: 'warning',
    graduated: 'default',
};

const todayWita = (): string =>
    new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Makassar' }).format(
        new Date(),
    );

export default function StudentMutations({
    mutations,
    filters,
    unitOptions,
    typeOptions,
    genderOptions,
    classroomOptions,
    activeStudentOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [openIn, setOpenIn] = useState(false);
    const [openOut, setOpenOut] = useState(false);

    const formIn = useForm<{
        unit_id: number | null;
        nis: string;
        nisn: string;
        name: string;
        gender: string;
        classroom_id: number | null;
        mutated_on: string;
        school_name: string;
        reason: string;
    }>({
        unit_id: filters.unit_id ?? unitOptions[0]?.id ?? null,
        nis: '',
        nisn: '',
        name: '',
        gender: '',
        classroom_id: null,
        mutated_on: todayWita(),
        school_name: '',
        reason: '',
    });

    const formOut = useForm<{
        student_id: number | null;
        mutated_on: string;
        school_name: string;
        reason: string;
    }>({
        student_id: null,
        mutated_on: todayWita(),
        school_name: '',
        reason: '',
    });

    const visit = (next: Partial<Props['filters']> & { page?: number }) => {
        setLoading(true);
        setFailed(false);
        router.get(
            StudentMutationController.index.url(),
            { ...filters, ...next },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onFinish: () => setLoading(false),
                onNetworkError: () => {
                    setFailed(true);
                    return false;
                },
                onHttpException: () => {
                    setFailed(true);
                    return false;
                },
            },
        );
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        visit({ search: search.trim(), page: 1 });
    };

    const submitIn = (e: FormEvent) => {
        e.preventDefault();
        formIn.post(StudentMutationController.storeIn.url(), {
            onSuccess: () => {
                setOpenIn(false);
                formIn.reset();
                formIn.setData('mutated_on', todayWita());
            },
        });
    };

    const submitOut = (e: FormEvent) => {
        e.preventDefault();
        formOut.post(StudentMutationController.storeOut.url(), {
            onSuccess: () => {
                setOpenOut(false);
                formOut.reset();
                formOut.setData('mutated_on', todayWita());
            },
        });
    };

    const filteredClassrooms = classroomOptions.filter(
        (c) =>
            formIn.data.unit_id === null || c.unit_id === formIn.data.unit_id,
    );

    const columns: DataTableColumn<MutationRow>[] = [
        {
            key: 'date',
            label: 'Tanggal',
            className: 'w-32 whitespace-nowrap',
            render: (row) => <DateText value={row.mutated_on} />,
        },
        {
            key: 'student',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.student.name}</div>
                    <div className="text-xs text-muted-foreground tabular-nums">
                        NIS {row.student.nis} · {row.student.unit}
                    </div>
                </div>
            ),
        },
        {
            key: 'type',
            label: 'Jenis',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={typeVariant[row.type.value]}>
                    {row.type.label}
                </StatusBadge>
            ),
        },
        {
            key: 'reason',
            label: 'Alasan & sekolah asal/tujuan',
            render: (row) => (
                <div className="text-sm">
                    <div>{row.reason}</div>
                    {row.school_name && (
                        <div className="text-xs text-muted-foreground">
                            {row.school_name}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'recorder',
            label: 'Dicatat oleh',
            render: (row) => (
                <span className="text-muted-foreground">{row.recorded_by}</span>
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Data Induk', href: StudentController.index() },
                { title: 'Siswa', href: StudentController.index() },
                {
                    title: 'Mutasi siswa',
                    href: StudentMutationController.index(),
                },
            ]}
        >
            <Head title="Mutasi siswa" />

            <div className="space-y-4">
                <PageHeader
                    title="Mutasi siswa"
                    description="Catatan siswa pindah masuk, pindah keluar, dan kelulusan."
                    actions={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpenOut(true)}
                            >
                                <ArrowUpRight className="size-4" />
                                Mutasi keluar
                            </Button>
                            <Button
                                type="button"
                                onClick={() => setOpenIn(true)}
                            >
                                <ArrowDownLeft className="size-4" />
                                Mutasi masuk
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <form
                        onSubmit={submitSearch}
                        className="relative flex-1 sm:max-w-xs"
                    >
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari nama atau NIS"
                            aria-label="Cari nama atau NIS"
                            className="pl-8"
                        />
                    </form>

                    <Select
                        value={
                            filters.unit_id !== null
                                ? String(filters.unit_id)
                                : ALL
                        }
                        onValueChange={(v) =>
                            visit({
                                unit_id: v === ALL ? null : Number(v),
                                page: 1,
                            })
                        }
                    >
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="Semua unit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua unit</SelectItem>
                            {unitOptions.map((u) => (
                                <SelectItem key={u.id} value={String(u.id)}>
                                    {u.code} · {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.type || ALL}
                        onValueChange={(v) =>
                            visit({ type: v === ALL ? '' : v, page: 1 })
                        }
                    >
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="Semua jenis" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua jenis</SelectItem>
                            {typeOptions.map((t) => (
                                <SelectItem key={t.value} value={t.value}>
                                    {t.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {failed && (
                    <ErrorState
                        title="Gagal memuat data mutasi"
                        description="Periksa koneksi lalu coba lagi."
                        onRetry={() => visit({})}
                    />
                )}

                <DataTable
                    columns={columns}
                    data={mutations.data}
                    rowKey={(row) => row.id}
                    loading={loading}
                    pagination={{
                        currentPage: mutations.meta.current_page,
                        lastPage: mutations.meta.last_page,
                        perPage: mutations.meta.per_page,
                        total: mutations.meta.total,
                        from: mutations.meta.from,
                        to: mutations.meta.to,
                    }}
                    onPageChange={(page) => visit({ page })}
                    empty={{
                        title: 'Belum ada catatan mutasi',
                        description:
                            'Catat siswa pindah masuk atau pindah keluar menggunakan tombol di atas.',
                    }}
                />
            </div>

            <FormDialog
                open={openIn}
                onOpenChange={setOpenIn}
                title="Mutasi masuk siswa"
                description="Daftarkan siswa pindahan ke unit dan rombel tahun ajaran aktif."
                submitLabel="Simpan"
                processing={formIn.processing}
                onSubmit={submitIn}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <Label htmlFor="in-unit">Unit</Label>
                        <Select
                            value={
                                formIn.data.unit_id
                                    ? String(formIn.data.unit_id)
                                    : ''
                            }
                            onValueChange={(v) => {
                                formIn.setData('unit_id', Number(v));
                                formIn.setData('classroom_id', null);
                            }}
                        >
                            <SelectTrigger id="in-unit">
                                <SelectValue placeholder="Pilih unit" />
                            </SelectTrigger>
                            <SelectContent>
                                {unitOptions.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.code} · {u.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={formIn.errors.unit_id} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="in-date">Tanggal masuk</Label>
                        <Input
                            id="in-date"
                            type="date"
                            value={formIn.data.mutated_on}
                            onChange={(e) =>
                                formIn.setData('mutated_on', e.target.value)
                            }
                        />
                        <InputError message={formIn.errors.mutated_on} />
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <Label htmlFor="in-nis">NIS</Label>
                        <Input
                            id="in-nis"
                            value={formIn.data.nis}
                            onChange={(e) =>
                                formIn.setData('nis', e.target.value)
                            }
                        />
                        <InputError message={formIn.errors.nis} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="in-gender">Jenis kelamin</Label>
                        <Select
                            value={formIn.data.gender}
                            onValueChange={(v) => formIn.setData('gender', v)}
                        >
                            <SelectTrigger id="in-gender">
                                <SelectValue placeholder="Pilih" />
                            </SelectTrigger>
                            <SelectContent>
                                {genderOptions.map((g) => (
                                    <SelectItem key={g.value} value={g.value}>
                                        {g.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={formIn.errors.gender} />
                    </div>
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="in-name">Nama lengkap</Label>
                    <Input
                        id="in-name"
                        value={formIn.data.name}
                        onChange={(e) => formIn.setData('name', e.target.value)}
                    />
                    <InputError message={formIn.errors.name} />
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <Label htmlFor="in-classroom">Rombel (opsional)</Label>
                        <Select
                            value={
                                formIn.data.classroom_id
                                    ? String(formIn.data.classroom_id)
                                    : ALL
                            }
                            onValueChange={(v) =>
                                formIn.setData(
                                    'classroom_id',
                                    v === ALL ? null : Number(v),
                                )
                            }
                        >
                            <SelectTrigger id="in-classroom">
                                <SelectValue placeholder="Belum ditempatkan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Belum ditempatkan
                                </SelectItem>
                                {filteredClassrooms.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={formIn.errors.classroom_id} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="in-school">
                            Asal sekolah (opsional)
                        </Label>
                        <Input
                            id="in-school"
                            value={formIn.data.school_name}
                            onChange={(e) =>
                                formIn.setData('school_name', e.target.value)
                            }
                        />
                        <InputError message={formIn.errors.school_name} />
                    </div>
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="in-reason">Alasan mutasi</Label>
                    <Input
                        id="in-reason"
                        value={formIn.data.reason}
                        onChange={(e) =>
                            formIn.setData('reason', e.target.value)
                        }
                    />
                    <InputError message={formIn.errors.reason} />
                </div>
            </FormDialog>

            <FormDialog
                open={openOut}
                onOpenChange={setOpenOut}
                title="Mutasi keluar siswa"
                description="Status siswa berubah menjadi Pindah. Wali yang tidak lagi memiliki anak aktif akan dinonaktifkan."
                submitLabel="Simpan"
                processing={formOut.processing}
                onSubmit={submitOut}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="out-student">Siswa aktif</Label>
                    <Select
                        value={
                            formOut.data.student_id
                                ? String(formOut.data.student_id)
                                : ''
                        }
                        onValueChange={(v) =>
                            formOut.setData('student_id', Number(v))
                        }
                    >
                        <SelectTrigger id="out-student">
                            <SelectValue placeholder="Pilih siswa" />
                        </SelectTrigger>
                        <SelectContent>
                            {activeStudentOptions.map((s) => (
                                <SelectItem key={s.id} value={String(s.id)}>
                                    {s.name} ({s.nis} · {s.unit})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={formOut.errors.student_id} />
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <Label htmlFor="out-date">Tanggal keluar</Label>
                        <Input
                            id="out-date"
                            type="date"
                            value={formOut.data.mutated_on}
                            onChange={(e) =>
                                formOut.setData('mutated_on', e.target.value)
                            }
                        />
                        <InputError message={formOut.errors.mutated_on} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="out-school">
                            Sekolah tujuan (opsional)
                        </Label>
                        <Input
                            id="out-school"
                            value={formOut.data.school_name}
                            onChange={(e) =>
                                formOut.setData('school_name', e.target.value)
                            }
                        />
                        <InputError message={formOut.errors.school_name} />
                    </div>
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="out-reason">Alasan mutasi</Label>
                    <Input
                        id="out-reason"
                        value={formOut.data.reason}
                        onChange={(e) =>
                            formOut.setData('reason', e.target.value)
                        }
                    />
                    <InputError message={formOut.errors.reason} />
                </div>
            </FormDialog>
        </StaffLayout>
    );
}
