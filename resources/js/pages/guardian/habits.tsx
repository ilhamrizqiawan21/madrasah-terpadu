import { Head, router } from '@inertiajs/react';
import { Check, Lock } from 'lucide-react';
import { useEffect, useState } from 'react';
import HabitController from '@/actions/App/Http/Controllers/Guardian/HabitController';
import { EmptyState } from '@/components/app/empty-state';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import GuardianLayout from '@/layouts/guardian-layout';
import { cn } from '@/lib/utils';

type HabitRow = {
    habit_item_id: number;
    name: string;
    aspect: 'spiritual' | 'social';
    aspect_label: string;
    done: boolean;
    verification: 'pending' | 'verified' | 'rejected';
    note: string | null;
};

type DateOption = { date: string; label: string };

type Props = {
    student: { id: number; name: string; nis: string };
    selectedDate: string;
    dateOptions: DateOption[];
    items: HabitRow[];
};

export default function GuardianHabits({
    student,
    selectedDate,
    dateOptions,
    items,
}: Props) {
    const [localItems, setLocalItems] = useState<HabitRow[]>(items);

    useEffect(() => {
        setLocalItems(items);
    }, [items]);

    function selectDate(date: string) {
        router.get(
            HabitController.index.url(student.id, { query: { date } }),
            {},
            { preserveState: true, replace: true },
        );
    }

    function toggle(item: HabitRow) {
        if (item.verification === 'verified') {
            return;
        }

        const previous = localItems;
        const nextDone = !item.done;

        setLocalItems((curr) =>
            curr.map((row) =>
                row.habit_item_id === item.habit_item_id
                    ? { ...row, done: nextDone, verification: 'pending' }
                    : row,
            ),
        );

        router.post(
            HabitController.toggle.url(student.id),
            {
                habit_item_id: item.habit_item_id,
                log_date: selectedDate,
                done: nextDone,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => setLocalItems(previous),
            },
        );
    }

    return (
        <GuardianLayout title="Mutabaah">
            <Head title={`Mutabaah — ${student.name}`} />

            <div className="space-y-5">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Pembiasaan {student.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Centang kegiatan harian ananda (maksimal 3 hari ke
                        belakang).
                    </p>
                </div>

                <div className="grid grid-cols-4 gap-2">
                    {dateOptions.map((opt) => (
                        <Button
                            key={opt.date}
                            type="button"
                            variant={
                                opt.date === selectedDate
                                    ? 'default'
                                    : 'outline'
                            }
                            className="min-h-11"
                            onClick={() => selectDate(opt.date)}
                        >
                            {opt.label}
                        </Button>
                    ))}
                </div>

                {localItems.length === 0 ? (
                    <EmptyState
                        title="Belum ada item pembiasaan"
                        description="Sekolah belum mengaktifkan daftar kegiatan pembiasaan untuk unit ini."
                    />
                ) : (
                    <div className="space-y-2.5">
                        {localItems.map((item) => {
                            const isVerified = item.verification === 'verified';

                            return (
                                <button
                                    key={item.habit_item_id}
                                    type="button"
                                    disabled={isVerified}
                                    onClick={() => toggle(item)}
                                    className={cn(
                                        'flex min-h-14 w-full items-center justify-between gap-3 rounded-lg border bg-card p-3.5 text-left shadow-xs transition-colors',
                                        item.done
                                            ? 'border-primary/40 bg-primary/5'
                                            : 'border-border',
                                        isVerified &&
                                            'cursor-not-allowed opacity-85',
                                    )}
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <span
                                            className={cn(
                                                'flex h-7 w-7 shrink-0 items-center justify-center rounded-md border',
                                                item.done
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-input bg-background',
                                            )}
                                        >
                                            {item.done ? (
                                                <Check className="h-4 w-4" />
                                            ) : null}
                                        </span>

                                        <div className="min-w-0">
                                            <p className="font-medium text-foreground">
                                                {item.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.aspect_label}
                                                {item.note
                                                    ? ` · ${item.note}`
                                                    : ''}
                                            </p>
                                        </div>
                                    </div>

                                    {isVerified ? (
                                        <StatusBadge variant="success">
                                            <Lock className="mr-1 h-3 w-3" />
                                            Terverifikasi
                                        </StatusBadge>
                                    ) : item.verification === 'rejected' ? (
                                        <StatusBadge variant="destructive">
                                            Ditolak
                                        </StatusBadge>
                                    ) : null}
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>
        </GuardianLayout>
    );
}
