import { DateInput } from '@/components/app/date-input';
import { Head, useForm } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import LeaveRequestController from '@/actions/App/Http/Controllers/Guardian/LeaveRequestController';
import { EmptyState } from '@/components/app/empty-state';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import GuardianLayout from '@/layouts/guardian-layout';
import { formatDate } from '@/lib/format';

type LeaveItem = {
    id: number;
    type_label: string;
    starts_on: string;
    ends_on: string;
    reason: string;
    status: string;
    status_label: string;
    decision_note: string | null;
};

type Props = {
    student: {
        id: number;
        name: string;
    };
    leaveTypes: { value: string; label: string }[];
    backdateDays: number;
    requests: LeaveItem[];
};

function badgeForStatus(status: string): 'warning' | 'success' | 'destructive' {
    switch (status) {
        case 'approved':
            return 'success';
        case 'rejected':
            return 'destructive';
        default:
            return 'warning';
    }
}

export default function GuardianLeaveCreate({
    student,
    leaveTypes,
    backdateDays,
    requests,
}: Props) {
    const form = useForm<{
        type: string;
        starts_on: string;
        ends_on: string;
        reason: string;
        attachment: File | null;
    }>({
        type: leaveTypes[0]?.value ?? 'sick',
        starts_on: '',
        ends_on: '',
        reason: '',
        attachment: null,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        form.post(LeaveRequestController.store.url(student.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('starts_on', 'ends_on', 'reason', 'attachment');
            },
        });
    };

    return (
        <GuardianLayout title="Ajukan Izin / Sakit">
            <Head title={`Izin ${student.name}`} />

            <div className="space-y-4">
                <Card className="shadow-xs">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">
                            Ajukan izin untuk {student.name}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="leave-type">Jenis</Label>
                                <Select
                                    value={form.data.type}
                                    onValueChange={(value) =>
                                        form.setData('type', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="leave-type"
                                        className="min-h-11"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {leaveTypes.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.type} />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="leave-start">
                                        Tanggal mulai
                                    </Label>
                                    <DateInput
                                        id="leave-start"
                                        className="min-h-11"
                                        value={form.data.starts_on}
                                        onChange={(e) =>
                                            form.setData(
                                                'starts_on',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Maksimal {backdateDays} hari ke
                                        belakang.
                                    </p>
                                    <InputError
                                        message={form.errors.starts_on}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="leave-end">
                                        Tanggal selesai
                                    </Label>
                                    <DateInput
                                        id="leave-end"
                                        className="min-h-11"
                                        value={form.data.ends_on}
                                        onChange={(e) =>
                                            form.setData(
                                                'ends_on',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.ends_on} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="leave-reason">Alasan</Label>
                                <textarea
                                    id="leave-reason"
                                    rows={3}
                                    value={form.data.reason}
                                    onChange={(e) =>
                                        form.setData('reason', e.target.value)
                                    }
                                    placeholder="Jelaskan alasan sakit atau izin..."
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={form.errors.reason} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="leave-attachment">
                                    Foto surat dokter / bukti (opsional, maks 2
                                    MB)
                                </Label>
                                <Input
                                    id="leave-attachment"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(e) =>
                                        form.setData(
                                            'attachment',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={form.errors.attachment} />
                            </div>

                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="min-h-11 w-full"
                            >
                                Kirim pengajuan
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="shadow-xs">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">
                            Riwayat pengajuan izin
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {requests.length === 0 ? (
                            <EmptyState
                                icon={CalendarPlus}
                                title="Belum ada pengajuan izin"
                                description="Pengajuan izin atau sakit yang dikirim akan muncul di sini."
                            />
                        ) : (
                            <ul className="divide-y divide-border">
                                {requests.map((item) => (
                                    <li
                                        key={item.id}
                                        className="space-y-1 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-medium">
                                                {item.type_label} ·{' '}
                                                {formatDate(item.starts_on)}
                                                {item.ends_on !== item.starts_on
                                                    ? ` – ${formatDate(item.ends_on)}`
                                                    : ''}
                                            </span>
                                            <StatusBadge
                                                variant={badgeForStatus(
                                                    item.status,
                                                )}
                                            >
                                                {item.status_label}
                                            </StatusBadge>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {item.reason}
                                        </p>
                                        {item.decision_note && (
                                            <p className="text-xs text-muted-foreground">
                                                Catatan sekolah:{' '}
                                                {item.decision_note}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </GuardianLayout>
    );
}
