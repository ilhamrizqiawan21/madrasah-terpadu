import { Head, useForm, usePage } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ExamAuthController from '@/actions/App/Http/Controllers/Exam/ExamAuthController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type BrandingProps = {
    appName: string;
    foundationName: string;
    logoUrl: string | null;
};

export default function ExamLogin() {
    const { branding } = usePage<{ branding: BrandingProps }>().props;
    const [showPin, setShowPin] = useState(false);

    const form = useForm({
        nis: '',
        pin: '',
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(ExamAuthController.store.url(), {
            onFinish: () => form.reset('pin'),
        });
    };

    return (
        <div className="bg-islamic-pattern-subtle flex min-h-svh flex-col items-center justify-center bg-background p-6">
            <Head title="Masuk Ujian CBT" />

            <div className="w-full max-w-sm rounded-lg border border-border bg-card p-6 shadow-xs">
                <div className="mb-6 flex flex-col items-center gap-2 text-center">
                    {branding.logoUrl ? (
                        <img
                            src={branding.logoUrl}
                            alt={branding.appName}
                            className="size-12 rounded-md object-contain"
                        />
                    ) : (
                        <div className="flex size-12 items-center justify-center rounded-md bg-primary text-base font-semibold text-primary-foreground">
                            MT
                        </div>
                    )}
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Masuk Ujian CBT
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {branding.foundationName}
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="exam-nis">NIS</Label>
                        <Input
                            id="exam-nis"
                            type="text"
                            inputMode="numeric"
                            autoComplete="username"
                            autoFocus
                            required
                            value={form.data.nis}
                            onChange={(event) =>
                                form.setData('nis', event.target.value)
                            }
                            placeholder="Masukkan NIS siswa"
                        />
                        <InputError message={form.errors.nis} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="exam-pin">PIN ujian</Label>
                        <div className="relative">
                            <Input
                                id="exam-pin"
                                type={showPin ? 'text' : 'password'}
                                inputMode="numeric"
                                autoComplete="current-password"
                                required
                                value={form.data.pin}
                                onChange={(event) =>
                                    form.setData('pin', event.target.value)
                                }
                                placeholder="6 digit PIN ujian"
                                className="pr-10"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="absolute top-0 right-0 h-full px-3 text-muted-foreground"
                                aria-label={
                                    showPin
                                        ? 'Sembunyikan PIN ujian'
                                        : 'Tampilkan PIN ujian'
                                }
                                onClick={() => setShowPin((prev) => !prev)}
                            >
                                {showPin ? (
                                    <EyeOff
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                ) : (
                                    <Eye
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                )}
                            </Button>
                        </div>
                        <InputError message={form.errors.pin} />
                    </div>

                    <Button
                        type="submit"
                        className="h-11 w-full"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        Masuk ruang ujian
                    </Button>
                </form>
            </div>
        </div>
    );
}
