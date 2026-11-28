import AcademicMasterController from '@/actions/App/Http/Controllers/Admin/AcademicMasterController';
import type {
    MasterField,
    MasterOption,
} from '@/components/app/master-crud-page';
import {
    MasterCrudPage,
    masterLayout,
} from '@/components/app/master-crud-page';
import type { PaginatedResource } from '@/types';

type Row = {
    id: number;
    unit_id: number;
    unit_name: string;
    name: string;
    sort_order: number | null;
};

type Props = {
    kind: 'tingkat' | 'rombel' | 'jurusan';
    meta: { title: string; noun: string; description: string };
    rows: PaginatedResource<Row>;
    filters: { search: string; unit: number | null };
    unitOptions: MasterOption[];
};

const placeholders: Record<Props['kind'], string> = {
    tingkat: 'Mis. Kelas 1 atau A',
    rombel: 'Mis. A, B, Reguler',
    jurusan: 'Mis. Tahfidz',
};

export default function AcademicMaster({
    kind,
    meta,
    rows,
    filters,
    unitOptions,
}: Props) {
    const fields: MasterField[] = [
        {
            key: 'unit_id',
            label: 'Unit',
            type: 'select',
            options: unitOptions,
            wide: kind !== 'tingkat',
        },
        ...(kind === 'tingkat'
            ? [
                  {
                      key: 'sort_order',
                      label: 'Urutan',
                      type: 'number' as const,
                      required: true,
                  },
              ]
            : []),
        {
            key: 'name',
            label: `Nama ${meta.noun}`,
            required: true,
            placeholder: placeholders[kind],
            wide: true,
        },
    ];

    return (
        <MasterCrudPage<Row>
            key={kind}
            title={meta.title}
            description={meta.description}
            group="Akademik"
            itemNoun={meta.noun}
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
            searchPlaceholder={`Cari ${meta.noun}`}
            urls={{
                index: AcademicMasterController.index.url(kind),
                store: AcademicMasterController.store.url(kind),
                update: (id) =>
                    AcademicMasterController.update.url({ kind, id }),
                destroy: (id) =>
                    AcademicMasterController.destroy.url({ kind, id }),
            }}
            columns={[
                ...(kind === 'tingkat'
                    ? [
                          {
                              key: 'sort_order',
                              label: 'Urutan',
                              align: 'center' as const,
                              render: (r: Row) => r.sort_order,
                          },
                      ]
                    : []),
                {
                    key: 'name',
                    label: `Nama ${meta.noun}`,
                    render: (r) => (
                        <span className="font-medium">{r.name}</span>
                    ),
                },
                { key: 'unit', label: 'Unit', render: (r) => r.unit_name },
            ]}
            fields={fields}
            defaults={{
                unit_id: unitOptions[0]?.value ?? '',
                name: '',
                sort_order: '1',
            }}
            toForm={(r) => ({
                unit_id: String(r.unit_id),
                name: r.name,
                sort_order: String(r.sort_order ?? 0),
            })}
            emptyDescription={`Tambahkan ${meta.noun} per unit.`}
        />
    );
}

AcademicMaster.layout = masterLayout;
