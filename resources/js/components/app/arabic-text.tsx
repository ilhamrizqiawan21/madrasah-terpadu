import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ArabicTextProps = {
    text: string;
    className?: string;
    showControls?: boolean;
};

const STORAGE_KEY = 'cbt.arabic_font_scale';
const SIZES = ['text-lg', 'text-xl', 'text-2xl', 'text-3xl'] as const;

export function ArabicText({
    text,
    className,
    showControls = false,
}: ArabicTextProps) {
    const [scaleIndex, setScaleIndex] = useState<number>(() => {
        if (typeof window === 'undefined') {
            return 1;
        }

        const saved = Number(window.localStorage.getItem(STORAGE_KEY));

        return Number.isInteger(saved) && saved >= 0 && saved < SIZES.length
            ? saved
            : 1;
    });

    const updateScale = (next: number): void => {
        const clamped = Math.max(0, Math.min(SIZES.length - 1, next));
        setScaleIndex(clamped);
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, String(clamped));
        }
    };

    return (
        <div className="space-y-2">
            {showControls && (
                <div className="flex items-center justify-end gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-7 px-2 text-xs"
                        aria-label="Perkecil teks Arab"
                        disabled={scaleIndex === 0}
                        onClick={() => updateScale(scaleIndex - 1)}
                    >
                        A−
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-7 px-2 text-xs"
                        aria-label="Perbesar teks Arab"
                        disabled={scaleIndex === SIZES.length - 1}
                        onClick={() => updateScale(scaleIndex + 1)}
                    >
                        A+
                    </Button>
                </div>
            )}
            <p
                dir="rtl"
                lang="ar"
                className={cn(
                    'leading-loose font-normal tracking-normal',
                    SIZES[scaleIndex],
                    className,
                )}
            >
                {text}
            </p>
        </div>
    );
}
