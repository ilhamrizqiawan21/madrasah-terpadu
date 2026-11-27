import { Head } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import ExamLayout from '@/layouts/exam-layout';

type Props = {
    student: {
        id: number;
        nis: string;
        name: string;
        unit: string;
        classroom: string | null;
    };
};

export default function ExamIndex({ student }: Props) {
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

                <EmptyState
                    icon={ClipboardList}
                    title="Belum ada ujian aktif saat ini"
                    description="Ujian yang dijadwalkan untuk rombel Anda akan muncul di halaman ini saat jendela waktu dibuka."
                />
            </div>
        </ExamLayout>
    );
}
