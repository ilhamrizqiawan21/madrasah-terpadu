import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type FormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    submitLabel?: string;
    cancelLabel?: string;
    processing?: boolean;
    children: ReactNode;
    contentClassName?: string;
};

export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    onSubmit,
    submitLabel = 'Simpan',
    cancelLabel = 'Batal',
    processing = false,
    children,
    contentClassName,
}: FormDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={cn('sm:max-w-lg', contentClassName)}>
                {/* noValidate: pesan validasi berbahasa Indonesia dari server tampil inline, bukan tooltip browser berbahasa Inggris. */}
                <form onSubmit={onSubmit} noValidate className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description && (
                            <DialogDescription>{description}</DialogDescription>
                        )}
                    </DialogHeader>

                    <div className="space-y-4">{children}</div>

                    <DialogFooter className="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => onOpenChange(false)}
                        >
                            {cancelLabel}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
