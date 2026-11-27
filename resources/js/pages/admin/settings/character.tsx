import { Head, Link, router, useForm } from '@inertiajs/react';
import { Info, Pencil, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import CharacterSettingController from '@/actions/App/Http/Controllers/Admin/CharacterSettingController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { FormDialog } from '@/components/app/form-dialog';
import { SettingsShell } from '@/components/app/settings-shell';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type Level = { code: string; label: string; min: number };
type Tier = { max: number | null; score: number };
type ComponentParams = {
    aspect?: 'spiritual' | 'social';
    item_ids?: number[];
    tiers?: Tier[];
};
type ComponentRow = { source: string; weight: number; params: ComponentParams };

type Dimension = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
    description_templates: Record<string, string>;
    components: ComponentRow[];
};

type Source = {
    value: string;
    label: string;
    description: string;
    needs_coverage: boolean;
    uses_tiers: boolean;
    default_tiers: Tier[];
};

type CurriculumOption = {
    value: 'merdeka' | 'k13';
    label: string;
    scale: Level[];
    dimensions: string[];
};

type Props = {
    units: { id: number; code: string; name: string }[];
    unit: { id: number; code: string; name: string } | null;
    profile: {
        curriculum: 'merdeka' | 'k13';
        curriculum_label: string;
        scale: Level[];
        coverage_min_pct: number;
    } | null;
    dimensions: Dimension[];
    habitItems: {
        id: number;
        name: string;
        aspect: 'spiritual' | 'social';
        is_active: boolean;
    }[];
    sources: Source[];
    curricula: CurriculumOption[];
};

/** Warna segmen komponen; netral + primary + satu warna status (DESIGN §2). */
const SEGMENT_TONES = [
    'bg-primary',
    'bg-primary/60',
    'bg-primary/35',
    'bg-chart-5/60',
    'bg-chart-5/35',
    'bg-muted-foreground/25',
];

const PLACEHOLDERS = '{nama}, {dimensi}, {kuat}, {lemah}';

export default function CharacterSettings(props: Props) {
    const { units, unit, profile, dimensions, sources } = props;
    const sourceLabel = (value: string): string =>
        sources.find((s) => s.value === value)?.label ?? value;

    const [curriculumOpen, setCurriculumOpen] = useState(false);
    const [scaleOpen, setScaleOpen] = useState(false);
    const [editing, setEditing] = useState<Dimension | 'new' | null>(null);

    return (
        <SettingsShell
            title="Penilaian karakter"
            description="Kurikulum, skala capaian, serta dimensi dan sumber datanya per unit. Dipakai wali kelas saat menghitung nilai karakter semester."
            wide
            actions={
                profile && unit ? (
                    <Button type="button" onClick={() => setEditing('new')}>
                        <Plus className="size-4" strokeWidth={1.75} />
                        Tambah dimensi
                    </Button>
                ) : undefined
            }
        >
            <Head title="Penilaian karakter" />

            {units.length > 1 && (
                <nav
                    aria-label="Pilih unit"
                    className="mb-5 inline-flex rounded-lg border border-border bg-muted/50 p-1"
                >
                    {units.map((u) => (
                        <Link
                            key={u.id}
                            href={CharacterSettingController.edit.url({
                                query: { unit: u.code },
                            })}
                            preserveScroll
                            aria-current={
                                u.id === unit?.id ? 'page' : undefined
                            }
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors duration-150',
                                u.id === unit?.id
                                    ? 'bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {u.code}
                        </Link>
                    ))}
                </nav>
            )}

            {!unit ? (
                <p className="text-sm text-muted-foreground">
                    Belum ada unit aktif. Tambahkan unit di Pengaturan › Unit &
                    jam presensi.
                </p>
            ) : !profile ? (
                <CurriculumPicker
                    unitId={unit.id}
                    unitCode={unit.code}
                    curricula={props.curricula}
                />
            ) : (
                <div className="space-y-6">
                    <ProfileCard
                        profile={profile}
                        onChangeCurriculum={() => setCurriculumOpen(true)}
                        onEditScale={() => setScaleOpen(true)}
                    />

                    <section className="space-y-3">
                        <h3 className="text-sm font-semibold">
                            Dimensi (
                            {dimensions.filter((d) => d.is_active).length}{' '}
                            aktif)
                        </h3>
                        <div className="grid gap-4 lg:grid-cols-2">
                            {dimensions.map((dimension) => (
                                <DimensionCard
                                    key={dimension.id}
                                    dimension={dimension}
                                    sourceLabel={sourceLabel}
                                    onEdit={() => setEditing(dimension)}
                                />
                            ))}
                        </div>
                    </section>

                    <CurriculumDialog
                        open={curriculumOpen}
                        onOpenChange={setCurriculumOpen}
                        unitId={unit.id}
                        current={profile.curriculum}
                        curricula={props.curricula}
                    />
                    <ScaleDialog
                        open={scaleOpen}
                        onOpenChange={setScaleOpen}
                        unitId={unit.id}
                        profile={profile}
                    />
                    {editing !== null && (
                        <DimensionDialog
                            key={editing === 'new' ? 'new' : editing.id}
                            unitId={unit.id}
                            dimension={editing === 'new' ? null : editing}
                            scale={profile.scale}
                            sources={sources}
                            habitItems={props.habitItems}
                            onClose={() => setEditing(null)}
                        />
                    )}
                </div>
            )}
        </SettingsShell>
    );
}

function CurriculumPicker({
    unitId,
    unitCode,
    curricula,
}: {
    unitId: number;
    unitCode: string;
    curricula: CurriculumOption[];
}) {
    const [processing, setProcessing] = useState(false);

    const apply = (value: string): void => {
        router.post(
            CharacterSettingController.applyCurriculum.url(unitId),
            { curriculum: value },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <section className="space-y-4">
            <p className="text-sm text-muted-foreground">
                Unit {unitCode} belum diatur. Pilih kurikulum untuk memasang
                dimensi dan skala bawaan; semuanya bisa diubah setelahnya.
            </p>
            <div className="grid gap-4 md:grid-cols-2">
                {curricula.map((c) => (
                    <div
                        key={c.value}
                        className={cn(
                            'flex flex-col rounded-xl border bg-card p-5 shadow-xs',
                            c.value === 'merdeka'
                                ? 'border-primary/40'
                                : 'border-border',
                        )}
                    >
                        <div className="flex items-center justify-between gap-2">
                            <h3 className="font-semibold">{c.label}</h3>
                            {c.value === 'merdeka' && (
                                <StatusBadge variant="default">
                                    Bawaan
                                </StatusBadge>
                            )}
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Dimensi
                        </p>
                        <ul className="mt-1 space-y-0.5 text-sm">
                            {c.dimensions.map((name) => (
                                <li key={name}>{name}</li>
                            ))}
                        </ul>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Skala capaian
                        </p>
                        <p className="mt-1 text-sm">
                            {c.scale.map((l) => l.label).join(' · ')}
                        </p>
                        <Button
                            type="button"
                            className="mt-5 self-start"
                            variant={
                                c.value === 'merdeka' ? 'default' : 'outline'
                            }
                            disabled={processing}
                            onClick={() => apply(c.value)}
                        >
                            Pakai {c.label}
                        </Button>
                    </div>
                ))}
            </div>
        </section>
    );
}

function ProfileCard({
    profile,
    onChangeCurriculum,
    onEditScale,
}: {
    profile: NonNullable<Props['profile']>;
    onChangeCurriculum: () => void;
    onEditScale: () => void;
}) {
    const levels = profile.scale;

    return (
        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/50 px-5 py-3">
                <div>
                    <p className="text-xs text-muted-foreground">Kurikulum</p>
                    <p className="font-semibold">{profile.curriculum_label}</p>
                </div>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={onEditScale}
                    >
                        Ubah skala
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={onChangeCurriculum}
                    >
                        Ganti kurikulum
                    </Button>
                </div>
            </div>

            <div className="space-y-4 px-5 py-4">
                <div>
                    <div
                        className="flex h-2.5 overflow-hidden rounded-full"
                        aria-hidden
                    >
                        {[...levels].reverse().map((level, index, arr) => {
                            const upper =
                                index === arr.length - 1
                                    ? 100
                                    : arr[index + 1].min;

                            return (
                                <div
                                    key={level.code}
                                    className={cn(
                                        'border-r-2 border-card last:border-r-0',
                                        SEGMENT_TONES[arr.length - 1 - index] ??
                                            'bg-muted',
                                    )}
                                    style={{ width: `${upper - level.min}%` }}
                                />
                            );
                        })}
                    </div>
                    <dl className="mt-3 grid gap-x-6 gap-y-1.5 sm:grid-cols-2">
                        {levels.map((level, index) => (
                            <div
                                key={level.code}
                                className="flex items-center gap-2 text-sm"
                            >
                                <span
                                    className={cn(
                                        'size-2.5 rounded-sm',
                                        SEGMENT_TONES[index] ?? 'bg-muted',
                                    )}
                                    aria-hidden
                                />
                                <dt className="font-medium">{level.label}</dt>
                                <dd className="ml-auto text-muted-foreground tabular-nums">
                                    {index === 0
                                        ? `≥ ${level.min}`
                                        : `${level.min}–${levels[index - 1].min - 1}`}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <p className="text-sm text-muted-foreground">
                    Data pembiasaan dan kehadiran dipakai bila mencakup minimal{' '}
                    <span className="font-medium text-foreground">
                        {profile.coverage_min_pct}%
                    </span>{' '}
                    hari efektif. Kurang dari itu, wali kelas menetapkan capaian
                    dengan alasan.
                </p>

                {profile.curriculum === 'merdeka' && (
                    <p className="flex gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                        <Info
                            className="mt-0.5 size-4 shrink-0"
                            strokeWidth={1.75}
                        />
                        Cocokkan nama dimensi dan label skala dengan panduan
                        kokurikuler KMA 1503 yang dipakai madrasah sebelum
                        penilaian pertama.
                    </p>
                )}
            </div>
        </section>
    );
}

function DimensionCard({
    dimension,
    sourceLabel,
    onEdit,
}: {
    dimension: Dimension;
    sourceLabel: (value: string) => string;
    onEdit: () => void;
}) {
    return (
        <article
            className={cn(
                'rounded-xl border border-border bg-card p-4 shadow-xs',
                !dimension.is_active && 'bg-muted/40',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h4
                        className={cn(
                            'font-semibold',
                            !dimension.is_active && 'text-muted-foreground',
                        )}
                    >
                        {dimension.name}
                    </h4>
                    <p className="text-xs text-muted-foreground">
                        {dimension.components.length} sumber data
                    </p>
                </div>
                <div className="flex items-center gap-1">
                    {!dimension.is_active && (
                        <StatusBadge variant="muted">Nonaktif</StatusBadge>
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Ubah dimensi ${dimension.name}`}
                        onClick={onEdit}
                    >
                        <Pencil className="size-4" strokeWidth={1.75} />
                    </Button>
                </div>
            </div>

            <div
                className="mt-3 flex h-2 overflow-hidden rounded-full bg-muted"
                aria-hidden
            >
                {dimension.components.map((c, index) => (
                    <div
                        key={index}
                        className={cn(
                            'border-r-2 border-card last:border-r-0',
                            SEGMENT_TONES[index] ?? 'bg-muted',
                        )}
                        style={{ width: `${c.weight}%` }}
                    />
                ))}
            </div>
            <ul className="mt-3 space-y-1">
                {dimension.components.map((c, index) => (
                    <li key={index} className="flex items-center gap-2 text-sm">
                        <span
                            className={cn(
                                'size-2.5 shrink-0 rounded-sm',
                                SEGMENT_TONES[index] ?? 'bg-muted',
                            )}
                            aria-hidden
                        />
                        <span className="min-w-0 truncate">
                            {sourceLabel(c.source)}
                            {c.params.aspect &&
                                ` · ${c.params.aspect === 'spiritual' ? 'spiritual' : 'sosial'}`}
                            {c.params.item_ids &&
                                ` · ${c.params.item_ids.length} item`}
                        </span>
                        <span className="ml-auto font-medium tabular-nums">
                            {c.weight}%
                        </span>
                    </li>
                ))}
            </ul>
        </article>
    );
}

function CurriculumDialog({
    open,
    onOpenChange,
    unitId,
    current,
    curricula,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    unitId: number;
    current: string;
    curricula: CurriculumOption[];
}) {
    const target = curricula.find((c) => c.value !== current);
    const [processing, setProcessing] = useState(false);

    if (!target) {
        return null;
    }

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`Ganti ke ${target.label}?`}
            description={`Dimensi yang sekarang dinonaktifkan (tidak dihapus) dan diganti dimensi bawaan ${target.label}. Skala capaian kembali ke bawaan. Hasil penilaian yang sudah dikunci tidak berubah.`}
            confirmLabel={`Ganti ke ${target.label}`}
            loading={processing}
            onConfirm={() =>
                router.post(
                    CharacterSettingController.applyCurriculum.url(unitId),
                    { curriculum: target.value },
                    {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false);
                            onOpenChange(false);
                        },
                    },
                )
            }
        />
    );
}

function ScaleDialog({
    open,
    onOpenChange,
    unitId,
    profile,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    unitId: number;
    profile: NonNullable<Props['profile']>;
}) {
    const form = useForm<{ scale: Level[]; coverage_min_pct: number }>({
        scale: profile.scale,
        coverage_min_pct: profile.coverage_min_pct,
    });

    const setLevel = (index: number, patch: Partial<Level>): void => {
        form.setData(
            'scale',
            form.data.scale.map((level, i) =>
                i === index ? { ...level, ...patch } : level,
            ),
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        form.put(CharacterSettingController.updateScale.url(unitId), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    form.setData({
                        scale: profile.scale,
                        coverage_min_pct: profile.coverage_min_pct,
                    });
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
            title="Ubah skala capaian"
            description="Urutkan dari tingkat tertinggi. Tingkat terendah selalu mulai dari 0."
            onSubmit={submit}
            processing={form.processing}
            contentClassName="sm:max-w-xl"
        >
            <div className="space-y-2">
                <div className="grid grid-cols-[5rem_1fr_5rem_2.25rem] gap-2 text-xs text-muted-foreground">
                    <span>Kode</span>
                    <span>Label</span>
                    <span>Mulai dari</span>
                </div>
                {form.data.scale.map((level, index) => {
                    const isLast = index === form.data.scale.length - 1;

                    return (
                        <div
                            key={index}
                            className="grid grid-cols-[5rem_1fr_5rem_2.25rem] items-center gap-2"
                        >
                            <Input
                                aria-label={`Kode tingkat ${index + 1}`}
                                value={level.code}
                                maxLength={10}
                                onChange={(e) =>
                                    setLevel(index, { code: e.target.value })
                                }
                            />
                            <Input
                                aria-label={`Label tingkat ${index + 1}`}
                                value={level.label}
                                maxLength={50}
                                onChange={(e) =>
                                    setLevel(index, { label: e.target.value })
                                }
                            />
                            <Input
                                aria-label={`Nilai minimum tingkat ${index + 1}`}
                                type="number"
                                min={0}
                                max={100}
                                value={isLast ? 0 : level.min}
                                disabled={isLast}
                                onChange={(e) =>
                                    setLevel(index, {
                                        min: Number(e.target.value),
                                    })
                                }
                                className="tabular-nums"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Hapus tingkat ${level.label || index + 1}`}
                                disabled={form.data.scale.length <= 2}
                                onClick={() =>
                                    form.setData(
                                        'scale',
                                        form.data.scale
                                            .filter((_, i) => i !== index)
                                            .map((l, i, arr) =>
                                                i === arr.length - 1
                                                    ? { ...l, min: 0 }
                                                    : l,
                                            ),
                                    )
                                }
                            >
                                <Trash2 className="size-4" strokeWidth={1.75} />
                            </Button>
                        </div>
                    );
                })}
                <InputError message={form.errors.scale} />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={form.data.scale.length >= 6}
                    onClick={() =>
                        form.setData('scale', [
                            ...form.data.scale,
                            { code: '', label: '', min: 0 },
                        ])
                    }
                >
                    <Plus className="size-4" strokeWidth={1.75} />
                    Tambah tingkat
                </Button>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="coverage">Batas kecukupan data (%)</Label>
                <Input
                    id="coverage"
                    type="number"
                    min={0}
                    max={100}
                    className="w-28 tabular-nums"
                    value={form.data.coverage_min_pct}
                    onChange={(e) =>
                        form.setData('coverage_min_pct', Number(e.target.value))
                    }
                />
                <InputError message={form.errors.coverage_min_pct} />
            </div>
        </FormDialog>
    );
}

function DimensionDialog({
    unitId,
    dimension,
    scale,
    sources,
    habitItems,
    onClose,
}: {
    unitId: number;
    dimension: Dimension | null;
    scale: Level[];
    sources: Source[];
    habitItems: Props['habitItems'];
    onClose: () => void;
}) {
    const form = useForm<{
        name: string;
        is_active: boolean;
        description_templates: Record<string, string>;
        components: ComponentRow[];
    }>({
        name: dimension?.name ?? '',
        is_active: dimension?.is_active ?? true,
        description_templates: Object.fromEntries(
            scale.map((l) => [
                l.code,
                dimension?.description_templates[l.code] ?? '',
            ]),
        ),
        components: dimension?.components ?? [
            { source: 'teacher_observation', weight: 100, params: {} },
        ],
    });

    const errors = form.errors as Record<string, string | undefined>;
    const total = form.data.components.reduce(
        (sum, c) => sum + (Number(c.weight) || 0),
        0,
    );

    const setComponent = (
        index: number,
        patch: Partial<ComponentRow>,
    ): void => {
        form.setData(
            'components',
            form.data.components.map((c, i) =>
                i === index ? { ...c, ...patch } : c,
            ),
        );
    };

    const changeSource = (index: number, value: string): void => {
        const source = sources.find((s) => s.value === value);
        const params: ComponentParams =
            value === 'habits_aspect'
                ? { aspect: 'spiritual' }
                : value === 'habits_items'
                  ? { item_ids: [] }
                  : source?.uses_tiers
                    ? { tiers: source.default_tiers }
                    : {};
        setComponent(index, { source: value, params });
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (dimension) {
            form.put(
                CharacterSettingController.updateDimension.url([
                    unitId,
                    dimension.id,
                ]),
                options,
            );
        } else {
            form.post(
                CharacterSettingController.storeDimension.url(unitId),
                options,
            );
        }
    };

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={
                dimension ? `Ubah dimensi ${dimension.name}` : 'Tambah dimensi'
            }
            onSubmit={submit}
            processing={form.processing}
            contentClassName="sm:max-w-3xl max-h-[90vh] overflow-y-auto"
        >
            <div className="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                <div className="grid gap-2">
                    <Label htmlFor="dimension-name">Nama dimensi</Label>
                    <Input
                        id="dimension-name"
                        value={form.data.name}
                        maxLength={100}
                        required
                        onChange={(e) => form.setData('name', e.target.value)}
                        aria-invalid={Boolean(errors.name)}
                    />
                    <InputError message={errors.name} />
                </div>
                <label className="flex h-9 items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked === true)
                        }
                    />
                    Aktif
                </label>
            </div>

            <fieldset className="space-y-3">
                <div className="flex items-center justify-between">
                    <legend className="text-sm font-medium">
                        Sumber data & bobot
                    </legend>
                    <span
                        className={cn(
                            'text-sm tabular-nums',
                            total === 100
                                ? 'text-muted-foreground'
                                : 'font-semibold text-destructive',
                        )}
                    >
                        Total {total}%
                    </span>
                </div>

                {form.data.components.map((component, index) => {
                    const source = sources.find(
                        (s) => s.value === component.source,
                    );

                    return (
                        <div
                            key={index}
                            className="space-y-3 rounded-lg border border-border bg-background/60 p-3"
                        >
                            <div className="grid gap-2 sm:grid-cols-[1fr_6rem_2.25rem] sm:items-center">
                                <Select
                                    value={component.source}
                                    onValueChange={(v) =>
                                        changeSource(index, v)
                                    }
                                >
                                    <SelectTrigger
                                        aria-label={`Sumber data ${index + 1}`}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {sources.map((s) => (
                                            <SelectItem
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <div className="flex items-center gap-1">
                                    <Input
                                        type="number"
                                        min={1}
                                        max={100}
                                        aria-label={`Bobot sumber ${index + 1}`}
                                        className="tabular-nums"
                                        value={component.weight}
                                        onChange={(e) =>
                                            setComponent(index, {
                                                weight: Number(e.target.value),
                                            })
                                        }
                                    />
                                    <span className="text-sm text-muted-foreground">
                                        %
                                    </span>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Hapus sumber ${source?.label ?? index + 1}`}
                                    disabled={form.data.components.length <= 1}
                                    onClick={() =>
                                        form.setData(
                                            'components',
                                            form.data.components.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                </Button>
                            </div>
                            {source && (
                                <p className="text-xs text-muted-foreground">
                                    {source.description}
                                </p>
                            )}

                            {component.source === 'habits_aspect' && (
                                <Select
                                    value={
                                        component.params.aspect ?? 'spiritual'
                                    }
                                    onValueChange={(v) =>
                                        setComponent(index, {
                                            params: {
                                                aspect: v as
                                                    | 'spiritual'
                                                    | 'social',
                                            },
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        className="sm:w-56"
                                        aria-label="Aspek pembiasaan"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="spiritual">
                                            Aspek spiritual
                                        </SelectItem>
                                        <SelectItem value="social">
                                            Aspek sosial
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            )}

                            {component.source === 'habits_items' && (
                                <div className="grid gap-1.5 sm:grid-cols-2">
                                    {habitItems.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Belum ada item pembiasaan untuk unit
                                            ini.
                                        </p>
                                    )}
                                    {habitItems.map((item) => {
                                        const selected =
                                            component.params.item_ids?.includes(
                                                item.id,
                                            ) ?? false;

                                        return (
                                            <label
                                                key={item.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <Checkbox
                                                    checked={selected}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        const ids =
                                                            component.params
                                                                .item_ids ?? [];
                                                        setComponent(index, {
                                                            params: {
                                                                item_ids:
                                                                    checked
                                                                        ? [
                                                                              ...ids,
                                                                              item.id,
                                                                          ]
                                                                        : ids.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
                                                                                  id !==
                                                                                  item.id,
                                                                          ),
                                                            },
                                                        });
                                                    }}
                                                />
                                                <span
                                                    className={cn(
                                                        !item.is_active &&
                                                            'text-muted-foreground',
                                                    )}
                                                >
                                                    {item.name}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            )}

                            {source?.uses_tiers && (
                                <TierEditor
                                    tiers={
                                        component.params.tiers ??
                                        source.default_tiers
                                    }
                                    onChange={(tiers) =>
                                        setComponent(index, {
                                            params: { tiers },
                                        })
                                    }
                                />
                            )}

                            <InputError
                                message={
                                    errors[
                                        `components.${index}.params.aspect`
                                    ] ??
                                    errors[
                                        `components.${index}.params.item_ids`
                                    ] ??
                                    errors[
                                        `components.${index}.params.tiers`
                                    ] ??
                                    errors[`components.${index}.weight`]
                                }
                            />
                        </div>
                    );
                })}
                <InputError message={errors.components} />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={form.data.components.length >= 6}
                    onClick={() =>
                        form.setData('components', [
                            ...form.data.components,
                            {
                                source: 'teacher_observation',
                                weight: Math.max(0, 100 - total),
                                params: {},
                            },
                        ])
                    }
                >
                    <Plus className="size-4" strokeWidth={1.75} />
                    Tambah sumber data
                </Button>
            </fieldset>

            <fieldset className="space-y-3">
                <legend className="text-sm font-medium">
                    Templat deskripsi per capaian
                </legend>
                <p className="text-xs text-muted-foreground">
                    Penanda yang bisa dipakai: {PLACEHOLDERS}
                </p>
                {scale.map((level) => (
                    <div key={level.code} className="grid gap-1.5">
                        <Label
                            htmlFor={`template-${level.code}`}
                            className="font-normal text-muted-foreground"
                        >
                            {level.label}
                        </Label>
                        <Textarea
                            id={`template-${level.code}`}
                            rows={2}
                            maxLength={300}
                            value={
                                form.data.description_templates[level.code] ??
                                ''
                            }
                            onChange={(e) =>
                                form.setData('description_templates', {
                                    ...form.data.description_templates,
                                    [level.code]: e.target.value,
                                })
                            }
                        />
                        <InputError
                            message={
                                errors[`description_templates.${level.code}`]
                            }
                        />
                    </div>
                ))}
            </fieldset>
        </FormDialog>
    );
}

function TierEditor({
    tiers,
    onChange,
}: {
    tiers: Tier[];
    onChange: (tiers: Tier[]) => void;
}) {
    const update = (index: number, patch: Partial<Tier>): void =>
        onChange(tiers.map((t, i) => (i === index ? { ...t, ...patch } : t)));

    return (
        <div className="space-y-1.5">
            <p className="text-xs text-muted-foreground">
                Tingkatan poin semester → skor
            </p>
            {tiers.map((tier, index) => {
                const isLast = index === tiers.length - 1;
                const previous = index > 0 ? tiers[index - 1].max : null;

                return (
                    <div
                        key={index}
                        className="flex flex-wrap items-center gap-2 text-sm"
                    >
                        <span className="w-32 text-muted-foreground">
                            {isLast
                                ? `Lebih dari ${previous ?? 0}`
                                : index === 0
                                  ? 'Sampai'
                                  : `${(previous ?? 0) + 1} sampai`}
                        </span>
                        {!isLast && (
                            <Input
                                type="number"
                                min={0}
                                aria-label={`Batas poin baris ${index + 1}`}
                                className="h-8 w-20 tabular-nums"
                                value={tier.max ?? 0}
                                onChange={(e) =>
                                    update(index, {
                                        max: Number(e.target.value),
                                    })
                                }
                            />
                        )}
                        <span className="text-muted-foreground">
                            poin → skor
                        </span>
                        <Input
                            type="number"
                            min={0}
                            max={100}
                            aria-label={`Skor baris ${index + 1}`}
                            className="h-8 w-20 tabular-nums"
                            value={tier.score}
                            onChange={(e) =>
                                update(index, { score: Number(e.target.value) })
                            }
                        />
                        {tiers.length > 2 && !isLast && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label={`Hapus baris ${index + 1}`}
                                onClick={() =>
                                    onChange(
                                        tiers.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                <Trash2
                                    className="size-3.5"
                                    strokeWidth={1.75}
                                />
                            </Button>
                        )}
                    </div>
                );
            })}
            {tiers.length < 8 && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        const lastFinite =
                            [...tiers].reverse().find((t) => t.max !== null)
                                ?.max ?? 0;
                        const next = [...tiers];
                        next.splice(tiers.length - 1, 0, {
                            max: lastFinite + 10,
                            score: tiers[tiers.length - 1].score,
                        });
                        onChange(next);
                    }}
                >
                    <Plus className="size-4" strokeWidth={1.75} />
                    Tambah baris
                </Button>
            )}
        </div>
    );
}
