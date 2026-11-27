import { Head, useForm } from '@inertiajs/react';
import { ClipboardList, Play } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ExamSessionController from '@/actions/App/Http/Controllers/Exam/ExamSessionController';
import { EmptyState } from '@/components/app/empty-state';
import { FormDialog } from '@/components/app/form-dialog';
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
import ExamLayout from '@/layouts/exam-layout';

type ExamItem = {
    id: number;
    title: string;
    subject: string;
    duration_minutes: number;
    requires_token: boolean;
    device_modes: string[];
    session_status: string | null;
    session_status_label: string | null;
};

type Props = {
    student: {
        id: number;
        nis: string;
        name: string;
        unit: string;
        classroom: string | null;
    };
    exams: ExamItem[];
};

const deviceModeLabels: Record<string, string> = {
    lab: 'Lab komputer',
    tablet: 'Tablet sekolah',
    phone: 'HP orang tua',
};

export default function ExamIndex({ student, exams }: Props) {
    const [startingExam, setStartingExam] = useState<ExamItem | null>(null);

    const form = useForm({
        device_type: 'lab',
        access_token: '',
    });

    const openStart = (exam: ExamItem): void => {
        setStartingExam(exam);
        form.clearErrors();
        form.setData({
            device_type: exam.device_modes[0] ?? 'lab',
            access_token: '',
        });
    };

    const submitStart = (event: FormEvent): void => {
        event.preventDefault();
        if (startingExam === null) {
            return;
        }

        form.post(ExamSessionController.start.url(startingExam.id), {
            preserveScroll: true,
        });
    };

    return (
        <ExamLayout
            studentName={student.name}
            studentNis={student.nis}
            classroomName={student.classroom}
        >
            <Head title="Daftar Ujian" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Ujian aktif
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Pilih ujian yang sedang berlangsung untuk mulai
                        mengerjakan.
                    </p>
                </div>

                {exams.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Belum ada ujian aktif saat ini"
                        description="Ujian yang dijadwalkan untuk rombel Anda akan muncul di halaman ini saat jendela waktu dibuka."
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {exams.map((exam) => {
                            const finished =
                                exam.session_status === 'submitted' ||
                                exam.session_status === 'auto_closed' ||
                                exam.session_status === 'force_closed';

                            return (
                                <div
                                    key={exam.id}
                                    className="flex flex-col justify-between gap-4 rounded-lg border border-border bg-card p-5 shadow-xs"
                                >
                                    <div className="space-y-2">
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <h2 className="text-base font-semibold">
                                                    {exam.title}
                                                </h2>
                                                <p className="text-sm text-muted-foreground">
                                                    {exam.subject}
                                                </p>
                                            </div>
                                            {exam.session_status_label && (
                                                <StatusBadge
                                                    variant={
                                                        finished
                                                            ? 'success'
                                                            : 'default'
                                                    }
                                                >
                                                    {exam.session_status_label}
                                                </StatusBadge>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground tabular-nums">
                                            Durasi {exam.duration_minutes} menit
                                        </p>
                                    </div>

                                    {!finished && (
                                        <Button
                                            type="button"
                                            className="h-11 w-full"
                                            onClick={() => openStart(exam)}
                                        >
                                            <Play
                                                className="size-4"
                                                strokeWidth={1.75}
                                            />
                                            {exam.session_status ===
                                            'in_progress'
                                                ? 'Lanjutkan ujian'
                                                : 'Mulai ujian'}
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            <FormDialog
                open={startingExam !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setStartingExam(null);
                    }
                }}
                title={startingExam?.title ?? 'Mulai ujian'}
                description="Pastikan perangkat dan koneksi siap sebelum memulai."
                processing={form.processing}
                submitLabel="Mulai kerjakan"
                onSubmit={submitStart}
            >
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="start-device">
                            Perangkat yang dipakai
                        </Label>
                        <Select
                            value={form.data.device_type}
                            onValueChange={(value) =>
                                form.setData('device_type', value)
                            }
                        >
                            <SelectTrigger id="start-device">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(startingExam?.device_modes ?? []).map(
                                    (mode) => (
                                        <SelectItem key={mode} value={mode}>
                                            {deviceModeLabels[mode] ?? mode}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.device_type} />
                    </div>

                    {startingExam?.requires_token && (
                        <div className="space-y-1.5">
                            <Label htmlFor="start-token">Token ujian</Label>
                            <Input
                                id="start-token"
                                value={form.data.access_token}
                                onChange={(event) =>
                                    form.setData(
                                        'access_token',
                                        event.target.value,
                                    )
                                }
                                placeholder="Masukkan token dari pengawas"
                            />
                            <InputError message={form.errors.access_token} />
                        </div>
                    )}

                    <InputError
                        message={
                            (form.errors as Record<string, string | undefined>)
                                .exam
                        }
                    />
                </div>
            </FormDialog>
        </ExamLayout>
    );
}
