import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, CircleAlert, Trash2, Upload } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import AppearanceSettingController from '@/actions/App/Http/Controllers/Admin/AppearanceSettingController';
import { SettingsShell } from '@/components/app/settings-shell';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Appearance = {
    logo_url: string | null;
    favicon_url: string | null;
    primary_color: string | null;
    default_color: string;
};

type AppearanceForm = {
    logo: File | null;
    favicon: File | null;
    remove_logo: boolean;
    remove_favicon: boolean;
    primary_color: string;
    reset_color: boolean;
};

type AssetKey = 'logo' | 'favicon';

const PRESET_COLORS = ['#135B42', '#1E40AF', '#4C2C72', '#6E3F19'];

const ASSETS: Record<
    AssetKey,
    {
        label: string;
        hint: string;
        accept: string;
        removeField: 'remove_logo' | 'remove_favicon';
        imageClass: string;
    }
> = {
    logo: {
        label: 'Logo',
        hint: 'PNG, JPG, atau WebP persegi, maksimal 1 MB.',
        accept: 'image/png,image/jpeg,image/webp',
        removeField: 'remove_logo',
        imageClass: 'size-16',
    },
    favicon: {
        label: 'Favicon',
        hint: 'PNG atau ICO persegi, maksimal 256 KB.',
        accept: 'image/png,image/x-icon,.ico',
        removeField: 'remove_favicon',
        imageClass: 'size-8',
    },
};

const ASSET_KEYS: AssetKey[] = ['logo', 'favicon'];

function relativeLuminance(hex: string): number {
    const clean = hex.replace('#', '');
    const channels = [0, 2, 4].map((offset) => {
        const c = Number.parseInt(clean.slice(offset, offset + 2), 16) / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

function contrastRatio(hexA: string, hexB: string): number {
    const l1 = relativeLuminance(hexA);
    const l2 = relativeLuminance(hexB);

    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
}

function isValidHex(hex: string): boolean {
    return /^#[0-9A-Fa-f]{6}$/.test(hex.trim());
}

function passesAa(hex: string): boolean {
    if (!isValidHex(hex)) {
        return false;
    }

    return (
        contrastRatio(hex, '#FAF8F5') >= 4.5 &&
        contrastRatio(hex, '#FBFAF7') >= 4.5
    );
}

function useObjectUrl(file: File | null): string | null {
    const [url, setUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            setUrl(null);

            return;
        }

        const objectUrl = URL.createObjectURL(file);
        setUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    return url;
}

export default function AppearanceSettings({
    appearance,
}: {
    appearance: Appearance;
}) {
    const initialColor = appearance.primary_color ?? appearance.default_color;

    const form = useForm<AppearanceForm>({
        logo: null,
        favicon: null,
        remove_logo: false,
        remove_favicon: false,
        primary_color: initialColor,
        reset_color: false,
    });

    const logoInput = useRef<HTMLInputElement>(null);
    const faviconInput = useRef<HTMLInputElement>(null);
    const inputs = { logo: logoInput, favicon: faviconInput };
    const previews: Record<AssetKey, string | null> = {
        logo: useObjectUrl(form.data.logo),
        favicon: useObjectUrl(form.data.favicon),
    };
    const savedUrl = (asset: AssetKey): string | null =>
        asset === 'logo' ? appearance.logo_url : appearance.favicon_url;

    const shownUrl = (asset: AssetKey): string | null => {
        if (previews[asset]) {
            return previews[asset];
        }

        return form.data[ASSETS[asset].removeField] ? null : savedUrl(asset);
    };

    const colorValid = useMemo(
        () => isValidHex(form.data.primary_color),
        [form.data.primary_color],
    );
    const colorPassesAa = useMemo(
        () => passesAa(form.data.primary_color),
        [form.data.primary_color],
    );

    const previewHex = colorValid
        ? form.data.primary_color.trim().toUpperCase()
        : initialColor;

    const clearInputs = (): void => {
        ASSET_KEYS.forEach((asset) => {
            const input = inputs[asset].current;

            if (input) {
                input.value = '';
            }
        });
    };

    const chooseFile = (
        asset: AssetKey,
        event: ChangeEvent<HTMLInputElement>,
    ): void => {
        const file = event.target.files?.[0] ?? null;

        form.setData((data) => ({
            ...data,
            [asset]: file,
            [ASSETS[asset].removeField]: false,
        }));
    };

    const removeAsset = (asset: AssetKey): void => {
        const input = inputs[asset].current;

        if (input) {
            input.value = '';
        }

        form.setData((data) => ({
            ...data,
            [asset]: null,
            // Tandai hapus hanya bila memang ada berkas tersimpan di server.
            [ASSETS[asset].removeField]: savedUrl(asset) !== null,
        }));
    };

    const resetToDefault = (): void => {
        router.post(
            AppearanceSettingController.update.url(),
            { reset_color: true },
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.setData('primary_color', appearance.default_color);
                },
            },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(AppearanceSettingController.update.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                clearInputs();
                form.setData((data) => ({
                    ...data,
                    logo: null,
                    favicon: null,
                    remove_logo: false,
                    remove_favicon: false,
                    reset_color: false,
                }));
            },
            onError: () =>
                toast.error(
                    'Tampilan belum tersimpan. Periksa isian yang ditandai.',
                ),
            onNetworkError: () => {
                toast.error(
                    'Koneksi terputus. Perubahan belum tersimpan, coba lagi.',
                );

                return false;
            },
        });
    };

    return (
        <SettingsShell
            title="Tampilan"
            description="Logo, favicon, dan warna utama. Turunan hover dan fokus dihitung otomatis."
        >
            <Head title="Tampilan" />

            <form onSubmit={submit} className="space-y-8" noValidate>
                {ASSET_KEYS.map((asset) => {
                    const config = ASSETS[asset];
                    const url = shownUrl(asset);
                    const inputId = `appearance-${asset}`;
                    const lowerLabel = config.label.toLowerCase();

                    return (
                        <div key={asset} className="grid gap-3">
                            <Label htmlFor={inputId}>{config.label}</Label>
                            <div className="flex flex-wrap items-start gap-4">
                                <div className="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted">
                                    {url ? (
                                        <img
                                            src={url}
                                            alt={`${config.label} saat ini`}
                                            className={`${config.imageClass} object-contain`}
                                        />
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            Belum ada
                                        </span>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                inputs[asset].current?.click()
                                            }
                                        >
                                            <Upload
                                                className="size-4"
                                                strokeWidth={1.75}
                                            />
                                            {url
                                                ? `Ganti ${lowerLabel}`
                                                : `Unggah ${lowerLabel}`}
                                        </Button>
                                        {url && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                onClick={() =>
                                                    removeAsset(asset)
                                                }
                                            >
                                                <Trash2
                                                    className="size-4"
                                                    strokeWidth={1.75}
                                                />
                                                Hapus
                                            </Button>
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {config.hint}
                                    </p>
                                </div>
                            </div>
                            <input
                                ref={inputs[asset]}
                                id={inputId}
                                type="file"
                                accept={config.accept}
                                className="sr-only"
                                tabIndex={-1}
                                onChange={(event) => chooseFile(asset, event)}
                            />
                            <InputError message={form.errors[asset]} />
                        </div>
                    );
                })}

                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="appearance-primary-color">
                            Warna utama
                        </Label>
                        <div className="flex flex-wrap items-center gap-3">
                            {PRESET_COLORS.map((preset) => {
                                const selected =
                                    form.data.primary_color.toUpperCase() ===
                                    preset;

                                return (
                                    <button
                                        key={preset}
                                        type="button"
                                        aria-label={`Pilih warna ${preset}`}
                                        aria-pressed={selected}
                                        onClick={() =>
                                            form.setData(
                                                'primary_color',
                                                preset,
                                            )
                                        }
                                        style={{ backgroundColor: preset }}
                                        className={cn(
                                            'size-10 rounded-lg border transition-transform focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            selected
                                                ? 'ring-2 ring-ring ring-offset-2 ring-offset-background'
                                                : 'border-border hover:scale-105',
                                        )}
                                    />
                                );
                            })}

                            <Input
                                id="appearance-primary-color"
                                value={form.data.primary_color}
                                onChange={(event) =>
                                    form.setData(
                                        'primary_color',
                                        event.target.value,
                                    )
                                }
                                placeholder="#135B42"
                                className="w-36 font-mono text-sm uppercase"
                            />
                        </div>
                        <InputError message={form.errors.primary_color} />
                    </div>

                    <div
                        className={cn(
                            'flex items-center gap-2.5 rounded-lg border px-3.5 py-2.5 text-sm',
                            colorPassesAa
                                ? 'border-border bg-muted text-foreground'
                                : 'border-destructive/40 bg-destructive/10 text-destructive',
                        )}
                    >
                        {colorPassesAa ? (
                            <CheckCircle2
                                className="size-4 shrink-0 text-success"
                                strokeWidth={1.75}
                            />
                        ) : (
                            <CircleAlert
                                className="size-4 shrink-0"
                                strokeWidth={1.75}
                            />
                        )}
                        <span>
                            {colorPassesAa
                                ? 'Kontras teks di atas warna ini memenuhi AA (terang dan gelap).'
                                : 'Kontras warna ini terlalu rendah untuk teks WCAG AA (minimal 4,5:1).'}
                        </span>
                    </div>

                    <div className="space-y-2">
                        <Label>Pratinjau</Label>
                        <div className="space-y-3 rounded-lg border border-border bg-card p-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <span
                                    style={{
                                        backgroundColor: previewHex,
                                        color: '#FAF8F5',
                                    }}
                                    className="inline-flex h-9 items-center rounded-md px-4 text-sm font-medium"
                                >
                                    Terbitkan tagihan
                                </span>
                                <span className="inline-flex h-9 items-center rounded-md border border-border bg-background px-4 text-sm font-medium">
                                    Batal
                                </span>
                            </div>
                            <div
                                style={{ color: previewHex }}
                                className="text-sm font-medium"
                            >
                                Lihat kuitansi
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-end gap-2">
                    {appearance.primary_color !== null && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={resetToDefault}
                            disabled={form.processing}
                        >
                            Kembalikan bawaan
                        </Button>
                    )}
                    <Button
                        type="submit"
                        disabled={
                            form.processing || !form.isDirty || !colorPassesAa
                        }
                    >
                        {form.processing && <Spinner aria-label="Menyimpan" />}
                        Simpan
                    </Button>
                </div>
            </form>
        </SettingsShell>
    );
}
