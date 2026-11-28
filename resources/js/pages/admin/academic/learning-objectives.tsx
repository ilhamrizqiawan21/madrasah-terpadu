import LearningObjectiveController from '@/actions/App/Http/Controllers/Admin/LearningObjectiveController';
import type { MasterOption } from '@/components/app/master-crud-page';
import {
    MasterCrudPage,
    masterLayout,
} from '@/components/app/master-crud-page';
import type { PaginatedResource } from '@/types';

type Row = {
    id: number;
    subject_id: number;
    subject_name: string;
    grade: string;
    code: string;
    description: string;
};

type Props = {
    rows: PaginatedResource<Row>;
    filters: {
        search: string;
        unit: number | null;
        subject: number | null;
        grade: string | null;
    };
    unitOptions: MasterOption[];
    subjectOptions: MasterOption[];
    gradeOptions: MasterOption[];
};

export default function LearningObjectives({
    rows,
    filters,
    unitOptions,
    subjectOptions,
    gradeOptions,
}: Props) {
    return (
        <MasterCrudPage<Row>
            title="Tujuan Pembelajaran"
            description="Target capaian belajar (TP) per mata pelajaran dan tingkat kelas. Berlaku lintas tahun ajaran."
            group="Akademik"
            itemNoun="tujuan pembelajaran"
            rows={rows}
            filters={filters}
            filterSelects={[
                {
                    key: 'unit',
                    label: 'Saring unit',
                    allLabel: 'Semua unit',
                    options: unitOptions,
                },
                {
                    key: 'subject',
                    label: 'Saring mapel',
                    allLabel: 'Semua mata pelajaran',
                    options: subjectOptions,
                },
                {
                    key: 'grade',
                    label: 'Saring kelas',
                    allLabel: 'Semua kelas',
                    options: gradeOptions,
                },
            ]}
            searchPlaceholder="Cari kode atau deskripsi"
            urls={{
                index: LearningObjectiveController.index.url(),
                store: LearningObjectiveController.store.url(),
                update: (id) => LearningObjectiveController.update.url(id),
                destroy: (id) => LearningObjectiveController.destroy.url(id),
            }}
            columns={[
                {
                    key: 'subject',
                    label: 'Mata pelajaran',
                    render: (r) => (
                        <span className="font-medium">{r.subject_name}</span>
                    ),
                },
                {
                    key: 'grade',
                    label: 'Kelas',
                    align: 'center',
                    render: (r) => r.grade,
                },
                {
                    key: 'code',
                    label: 'Kode',
                    className: 'whitespace-nowrap tabular-nums',
                    render: (r) => r.code,
                },
                {
                    key: 'description',
                    label: 'Deskripsi target capaian',
                    className: 'hidden @3xl:table-cell',
                    render: (r) => (
                        <span className="line-clamp-2 max-w-xl">
                            {r.description}
                        </span>
                    ),
                },
            ]}
            fields={[
                {
                    key: 'subject_id',
                    label: 'Mata pelajaran',
                    type: 'select',
                    options: subjectOptions,
                },
                {
                    key: 'grade',
                    label: 'Tingkat kelas',
                    type: 'select',
                    options: gradeOptions,
                },
                {
                    key: 'code',
                    label: 'Kode TP',
                    required: true,
                    placeholder: 'Mis. TP 1.1',
                    wide: true,
                },
                {
                    key: 'description',
                    label: 'Deskripsi ringkas target',
                    type: 'textarea',
                    required: true,
                    wide: true,
                },
            ]}
            defaults={{ subject_id: '', grade: '', code: '', description: '' }}
            toForm={(r) => ({
                subject_id: String(r.subject_id),
                grade: r.grade,
                code: r.code,
                description: r.description,
            })}
            emptyDescription="Tambahkan target capaian belajar per mapel dan kelas, mis. TP 1.1."
        />
    );
}

LearningObjectives.layout = masterLayout;
