import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import FeatureFlagController from '@/actions/App/Http/Controllers/Admin/FeatureFlagController';
import { SettingsShell } from '@/components/app/settings-shell';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';

type FeatureItem = {
    key: string;
    label: string;
    description: string;
    enabled: boolean;
    held_until_cutover: boolean;
};

type FeatureForm = {
    features: Record<string, boolean>;
};

function toForm(features: FeatureItem[]): FeatureForm {
    return {
        features: Object.fromEntries(
            features.map((feature) => [feature.key, feature.enabled]),
        ),
    };
}

export default function FeatureSettings({
    features,
}: {
    features: FeatureItem[];
}) {
    const form = useForm<FeatureForm>(toForm(features));
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.put(FeatureFlagController.update.url(), {
            preserveScroll: true,
            onSuccess: (page) => {
                const saved = toForm(page.props.features as FeatureItem[]);
                form.setDefaults(saved);
                form.setData(saved);
            },
            onError: () =>
                toast.error('Kunci fitur belum tersimpan. Muat ulang halaman.'),
            onNetworkError: () => {
                toast.error(
                    'Koneksi terputus. Perubahan belum tersimpan, coba lagi.',
                );

                return false;
            },
        });
    };

    const toggle = (key: string, enabled: boolean): void => {
        form.setData('features', { ...form.data.features, [key]: enabled });
    };

    const groups = [
        {
            key: 'modules',
            title: 'Modul sekolah',
            note: null,
            items: features.filter((feature) => !feature.held_until_cutover),
        },
        {
            key: 'finance',
            title: 'Keuangan & kelayakan ujian',
            note: 'Biarkan nonaktif selama masa paralel; tagihan dan pembayaran masih di aplikasi lama.',
            items: features.filter((feature) => feature.held_until_cutover),
        },
    ];

    return (
        <SettingsShell
            title="Kunci fitur"
            description="Modul yang dimatikan hilang dari menu dan halamannya tidak dapat dibuka."
        >
            <Head title="Kunci fitur" />

            <form onSubmit={submit} className="space-y-8" noValidate>
                {groups.map((group) => (
                    <fieldset
                        key={group.key}
                        aria-describedby={
                            group.note ? `${group.key}-note` : undefined
                        }
                    >
                        <legend className="text-sm font-semibold text-foreground">
                            {group.title}
                        </legend>
                        {group.note && (
                            <p
                                id={`${group.key}-note`}
                                className="mt-0.5 text-sm text-muted-foreground"
                            >
                                {group.note}
                            </p>
                        )}
                        <ul className="mt-3 divide-y divide-border rounded-lg border border-border bg-card">
                            {group.items.map((feature) => {
                                const id = `feature-${feature.key}`;
                                const error = errors[`features.${feature.key}`];

                                return (
                                    <li
                                        key={feature.key}
                                        className="flex items-start gap-3 px-4 py-3"
                                    >
                                        <Checkbox
                                            id={id}
                                            checked={
                                                form.data.features[
                                                    feature.key
                                                ] ?? false
                                            }
                                            onCheckedChange={(checked) =>
                                                toggle(
                                                    feature.key,
                                                    checked === true,
                                                )
                                            }
                                            aria-describedby={`${id}-description`}
                                            aria-invalid={Boolean(error)}
                                            className="mt-0.5"
                                        />
                                        <div className="min-w-0 space-y-0.5">
                                            <label
                                                htmlFor={id}
                                                className="block cursor-pointer text-sm font-medium text-foreground"
                                            >
                                                {feature.label}
                                            </label>
                                            <p
                                                id={`${id}-description`}
                                                className="text-sm text-muted-foreground"
                                            >
                                                {feature.description}
                                            </p>
                                            <InputError message={error} />
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </fieldset>
                ))}

                <InputError message={errors.features} />

                <div className="flex justify-end border-t border-border pt-4">
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        {form.processing && <Spinner aria-label="Menyimpan" />}
                        Simpan
                    </Button>
                </div>
            </form>
        </SettingsShell>
    );
}
