import { Link } from '@inertiajs/react';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type RowActionsProps = {
    viewHref?: string;
    onView?: () => void;
    onEdit?: () => void;
    onDelete?: () => void;
    deleteTitle?: string;
    deleteDescription?: string;
    deleting?: boolean;
    itemName?: string;
    /** Aksi icon-only tambahan (dengan tooltip), ditempatkan sebelum Hapus. */
    children?: ReactNode;
};

export function RowActions({
    viewHref,
    onView,
    onEdit,
    onDelete,
    deleteTitle = 'Hapus data?',
    deleteDescription,
    deleting = false,
    itemName,
    children,
}: RowActionsProps) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    const resolvedDeleteDescription =
        deleteDescription ??
        (itemName
            ? `Data "${itemName}" akan dihapus dan tindakan ini tidak dapat dibatalkan.`
            : 'Data yang dihapus tidak dapat dikembalikan.');

    return (
        <>
            <div className="inline-flex items-center justify-center gap-1">
                {viewHref && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                asChild
                                aria-label="Lihat"
                            >
                                <Link href={viewHref}>
                                    <Eye
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Lihat</TooltipContent>
                    </Tooltip>
                )}

                {!viewHref && onView && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={onView}
                                aria-label="Lihat"
                            >
                                <Eye className="size-4" strokeWidth={1.75} />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Lihat</TooltipContent>
                    </Tooltip>
                )}

                {onEdit && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={onEdit}
                                aria-label="Ubah"
                            >
                                <Pencil className="size-4" strokeWidth={1.75} />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Ubah</TooltipContent>
                    </Tooltip>
                )}

                {children}

                {onDelete && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => setConfirmOpen(true)}
                                aria-label="Hapus"
                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            >
                                <Trash2 className="size-4" strokeWidth={1.75} />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Hapus</TooltipContent>
                    </Tooltip>
                )}
            </div>

            {onDelete && (
                <ConfirmDialog
                    open={confirmOpen}
                    onOpenChange={setConfirmOpen}
                    title={deleteTitle}
                    description={resolvedDeleteDescription}
                    confirmLabel="Hapus"
                    variant="destructive"
                    loading={deleting}
                    onConfirm={() => {
                        onDelete();
                        setConfirmOpen(false);
                    }}
                />
            )}
        </>
    );
}
