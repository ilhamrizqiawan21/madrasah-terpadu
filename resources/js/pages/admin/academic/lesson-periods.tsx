import LessonScheduleController from '@/actions/App/Http/Controllers/Admin/LessonScheduleController';
import type { MasterOption } from '@/components/app/master-crud-page';
import {
    MasterCrudPage,
    masterLayout,
} from '@/components/app/master-crud-page';
import { StatusBadge } from '@/components/app/status-badge';
import type { PaginatedResource } from '@/types';

type Row = {
    id: number;
    unit_id: number;
    unit_name: string;
    name: string;
    starts_at: string;
    ends_at: string;
    sort_order: number;
    is_break: boolean;
};

type Props = {
    rows: PaginatedResource<Row>;
    filters: { search: string; unit: number | null };
    unitOptions: MasterOption[];
};

const dot = (time: string) => time.replace(':', '.');

export default function LessonPeriods({ rows, filters, unitOptions }: Props) {
    return (
        <MasterCrudPage<Row>
            title="Jam Pelajaran"
            description="Slot jam pelajaran per unit, termasuk jam istirahat. Dipakai sebagai baris di jadwal mingguan."
            group="Akademik"
            itemNoun="slot jam"
            rows={rows}
            filters={filters}
            filterSelects={[
                {
                    key: 'unit',
                    label: 'Saring unit',
                    allLabel: 'Semua unit',
                    options: unitOptions,
                },
            ]}
            searchPlaceholder="Cari nama slot"
            urls={{
                index: LessonScheduleController.periods.url(),
                store: LessonScheduleController.storePeriod.url(),
                update: (id) => LessonScheduleController.updatePeriod.url(id),
                destroy: (id) => LessonScheduleController.destroyPeriod.url(id),
            }}
            columns={[
                {
                    key: 'sort_order',
                    label: 'No',
                    align: 'center',
                    render: (r) => r.sort_order,
                },
                {
                    key: 'name',
                    label: 'Nama slot',
                    render: (r) => (
                        <span className="font-medium">{r.name}</span>
                    ),
                },
                {
                    key: 'time',
                    label: 'Waktu',
                    className: 'tabular-nums whitespace-nowrap',
                    render: (r) => `${dot(r.starts_at)}–${dot(r.ends_at)}`,
                },
                {
                    key: 'break',
                    label: 'Keterangan',
                    render: (r) =>
                        r.is_break ? (
                            <StatusBadge variant="attention">
                                Istirahat
                            </StatusBadge>
                        ) : (
                            'Pelajaran'
                        ),
                },
                {
                    key: 'unit',
                    label: 'Unit',
                    className: 'hidden @3xl:table-cell',
                    render: (r) => r.unit_name,
                },
            ]}
            fields={[
                {
                    key: 'unit_id',
                    label: 'Unit',
                    type: 'select',
                    options: unitOptions,
                },
                {
                    key: 'sort_order',
                    label: 'Urutan',
                    type: 'number',
                    required: true,
                },
                {
                    key: 'name',
                    label: 'Nama slot',
                    required: true,
                    placeholder: 'Mis. JP 1',
                },
                {
                    key: 'is_break',
                    label: 'Jenis',
                    type: 'select',
                    options: [
                        { value: '0', label: 'Jam pelajaran' },
                        { value: '1', label: 'Jam istirahat' },
                    ],
                },
                {
                    key: 'starts_at',
                    label: 'Mulai',
                    type: 'time',
                    required: true,
                },
                {
                    key: 'ends_at',
                    label: 'Selesai',
                    type: 'time',
                    required: true,
                },
            ]}
            defaults={{
                unit_id: unitOptions[0]?.value ?? '',
                sort_order: '1',
                name: '',
                is_break: '0',
                starts_at: '07:30',
                ends_at: '08:05',
            }}
            toForm={(r) => ({
                unit_id: String(r.unit_id),
                sort_order: String(r.sort_order),
                name: r.name,
                is_break: r.is_break ? '1' : '0',
                starts_at: r.starts_at,
                ends_at: r.ends_at,
            })}
            emptyDescription="Tambahkan slot jam (JP 1, JP 2, istirahat) agar jadwal mingguan bisa disusun."
        />
    );
}

LessonPeriods.layout = masterLayout;
