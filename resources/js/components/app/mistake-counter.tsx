import { Minus, Plus } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type MistakeCounterItem = {
    id: number;
    label: string;
    value: number;
    max?: number;
    subtitle?: string;
};

type MistakeCounterGroupProps = {
    items: MistakeCounterItem[];
    activeId: number | null;
    onSelectActive: (id: number) => void;
    onChange: (id: number, nextValue: number) => void;
    className?: string;
};

function clampValue(next: number, max?: number): number {
    if (next < 0) {
        return 0;
    }
    if (max !== undefined && next > max) {
        return max;
    }

    return next;
}

function isTextInputFocused(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    const tag = target.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        return true;
    }

    return target.isContentEditable;
}

export function MistakeCounterGroup({
    items,
    activeId,
    onSelectActive,
    onChange,
    className,
}: MistakeCounterGroupProps) {
    useEffect(() => {
        if (items.length === 0) {
            return;
        }

        function handleKeyDown(e: KeyboardEvent) {
            if (isTextInputFocused(e.target)) {
                return;
            }

            const currentIndex = items.findIndex((i) => i.id === activeId);
            const resolvedIndex = currentIndex >= 0 ? currentIndex : 0;
            const current = items[resolvedIndex];
            if (!current) {
                return;
            }

            if (e.key === 'PageDown') {
                e.preventDefault();
                onChange(
                    current.id,
                    clampValue(current.value + 1, current.max),
                );
            } else if (e.key === 'PageUp') {
                e.preventDefault();
                onChange(
                    current.id,
                    clampValue(current.value - 1, current.max),
                );
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                const next = items[(resolvedIndex + 1) % items.length];
                if (next) {
                    onSelectActive(next.id);
                }
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                const prev =
                    items[(resolvedIndex - 1 + items.length) % items.length];
                if (prev) {
                    onSelectActive(prev.id);
                }
            }
        }

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [items, activeId, onChange, onSelectActive]);

    return (
        <div className={cn('space-y-3', className)}>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                {items.map((item) => {
                    const isActive = item.id === activeId;

                    return (
                        <div
                            key={item.id}
                            onClick={() => onSelectActive(item.id)}
                            className={cn(
                                'cursor-pointer rounded-lg border bg-card p-3.5 shadow-xs transition-colors',
                                isActive
                                    ? 'border-primary ring-2 ring-primary/30'
                                    : 'border-border hover:border-border/80',
                            )}
                        >
                            <div className="flex items-center justify-between gap-2">
                                <span className="font-medium text-foreground">
                                    {item.label}
                                </span>
                                {isActive ? (
                                    <span className="rounded bg-primary/10 px-1.5 py-0.5 text-xs font-medium text-primary">
                                        aktif
                                    </span>
                                ) : null}
                            </div>
                            {item.subtitle ? (
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {item.subtitle}
                                </p>
                            ) : null}

                            <div className="mt-3 flex items-center justify-between gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="h-11 w-11 shrink-0"
                                    aria-label={`Kurangi kesalahan ${item.label}`}
                                    disabled={item.value <= 0}
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onSelectActive(item.id);
                                        onChange(
                                            item.id,
                                            clampValue(
                                                item.value - 1,
                                                item.max,
                                            ),
                                        );
                                    }}
                                >
                                    <Minus className="h-4 w-4" />
                                </Button>

                                <span
                                    aria-live="polite"
                                    className="min-w-12 text-center text-2xl font-semibold text-foreground tabular-nums"
                                >
                                    {item.value}
                                </span>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="h-11 w-11 shrink-0"
                                    aria-label={`Tambah kesalahan ${item.label}`}
                                    disabled={
                                        item.max !== undefined &&
                                        item.value >= item.max
                                    }
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onSelectActive(item.id);
                                        onChange(
                                            item.id,
                                            clampValue(
                                                item.value + 1,
                                                item.max,
                                            ),
                                        );
                                    }}
                                >
                                    <Plus className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    );
                })}
            </div>
            <p className="text-xs text-muted-foreground">
                Pintasan keyboard: PageDown (+1 salah), PageUp (−1 salah), panah
                kiri/kanan pindah kriteria aktif.
            </p>
        </div>
    );
}
