import { Head, useForm } from '@inertiajs/react';
import { Trash2, Upload } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import AppearanceSettingController from '@/actions/App/Http/Controllers/Admin/AppearanceSettingController';
import { SettingsShell } from '@/components/app/settings-shell';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Appearance = {
    logo_url: string | null;
    favicon_url: string | null;
};

type AppearanceForm = {
    logo: File | null;
    favicon: File | null;
    remove_logo: boolean;
    remove_favicon: boolean;
};

type AssetKey = 'logo' | 'favicon';

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
    const form = useForm<AppearanceForm>({
        logo: null,
        favicon: null,
        remove_logo: false,
        remove_favicon: false,
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

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(AppearanceSettingController.update.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                clearInputs();
                form.reset();
            },
            onError: () =>
                toast.error(
                    'Tampilan belum tersimpan. Periksa berkas yang ditandai.',
                ),
            onNetworkError: () => {
                toast.error(
                    'Koneksi terputus. Berkas belum terunggah, coba lagi.',
                );

                return false;
            },
        });
    };

    return (
        <SettingsShell
            title="Tampilan"
            description="Logo dan favicon. Tampil di halaman login dan tab browser."
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

                <div className="flex justify-end">
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
