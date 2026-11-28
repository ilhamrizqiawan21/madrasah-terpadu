import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import IdentitySettingController from '@/actions/App/Http/Controllers/Admin/IdentitySettingController';
import { SettingsShell } from '@/components/app/settings-shell';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Identity = {
    app_name: string;
    foundation_name: string;
    address: string | null;
    city: string | null;
    phone: string | null;
    email: string | null;
};

type IdentityForm = {
    app_name: string;
    foundation_name: string;
    address: string;
    city: string;
    phone: string;
    email: string;
};

function toForm(identity: Identity): IdentityForm {
    return {
        app_name: identity.app_name,
        foundation_name: identity.foundation_name,
        address: identity.address ?? '',
        city: identity.city ?? '',
        phone: identity.phone ?? '',
        email: identity.email ?? '',
    };
}

export default function IdentitySettings({ identity }: { identity: Identity }) {
    const form = useForm<IdentityForm>(toForm(identity));

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.put(IdentitySettingController.update.url(), {
            preserveScroll: true,
            // Tampilkan nilai yang benar-benar tersimpan (mis. nomor HP yang sudah dinormalisasi).
            onSuccess: (page) => {
                const saved = toForm(page.props.identity as Identity);
                form.setDefaults(saved);
                form.setData(saved);
            },
            onError: () =>
                toast.error(
                    'Identitas belum tersimpan. Periksa isian yang ditandai.',
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
            title="Identitas sekolah"
            description="Nama dan kontak sekolah. Tampil di halaman login dan kop dokumen."
        >
            <Head title="Identitas sekolah" />

            <form onSubmit={submit} className="space-y-6" noValidate>
                <div className="grid gap-2">
                    <Label htmlFor="identity-app-name">Nama aplikasi</Label>
                    <Input
                        id="identity-app-name"
                        value={form.data.app_name}
                        onChange={(event) =>
                            form.setData('app_name', event.target.value)
                        }
                        maxLength={60}
                        required
                        aria-invalid={Boolean(form.errors.app_name)}
                    />
                    <InputError message={form.errors.app_name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="identity-foundation">Nama yayasan</Label>
                    <Input
                        id="identity-foundation"
                        value={form.data.foundation_name}
                        onChange={(event) =>
                            form.setData('foundation_name', event.target.value)
                        }
                        maxLength={120}
                        required
                        aria-invalid={Boolean(form.errors.foundation_name)}
                    />
                    <InputError message={form.errors.foundation_name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="identity-address">Alamat (opsional)</Label>
                    <Input
                        id="identity-address"
                        value={form.data.address}
                        onChange={(event) =>
                            form.setData('address', event.target.value)
                        }
                        maxLength={500}
                        autoComplete="off"
                        aria-invalid={Boolean(form.errors.address)}
                    />
                    <InputError message={form.errors.address} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="identity-city">
                        Kota/kabupaten (opsional)
                    </Label>
                    <Input
                        id="identity-city"
                        value={form.data.city}
                        onChange={(event) =>
                            form.setData('city', event.target.value)
                        }
                        maxLength={100}
                        placeholder="Mis. Kolaka"
                        autoComplete="off"
                        aria-invalid={Boolean(form.errors.city)}
                    />
                    <p className="text-xs text-muted-foreground">
                        Dipakai sebagai tempat penetapan surat, mis. di SKL.
                    </p>
                    <InputError message={form.errors.city} />
                </div>

                <div className="grid items-start gap-6 sm:grid-cols-2 sm:gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="identity-phone">
                            Telepon (opsional)
                        </Label>
                        <Input
                            id="identity-phone"
                            type="tel"
                            value={form.data.phone}
                            onChange={(event) =>
                                form.setData('phone', event.target.value)
                            }
                            maxLength={20}
                            autoComplete="off"
                            className="tabular-nums"
                            aria-invalid={Boolean(form.errors.phone)}
                        />
                        <InputError message={form.errors.phone} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="identity-email">Email (opsional)</Label>
                        <Input
                            id="identity-email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            maxLength={120}
                            autoComplete="off"
                            aria-invalid={Boolean(form.errors.email)}
                        />
                        <InputError message={form.errors.email} />
                    </div>
                </div>

                <div className="flex justify-end pt-2">
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
