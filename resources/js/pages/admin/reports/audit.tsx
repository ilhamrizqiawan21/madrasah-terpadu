import { Head, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import AuditLogController from '@/actions/App/Http/Controllers/Admin/AuditLogController';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';

type AuditRow = {
    id: number;
    created_at: string;
    user_name: string;
    action: string;
    subject_type: string;
    subject_id: number | null;
    ip: string;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
};

type Props = {
    filters: {
        user_id: number | null;
        action: string;
        subject_type: string;
        date_from: string;
        date_to: string;
    };
    logs: {
        data: AuditRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    users: { id: number; name: string }[];
    actions: string[];
    subjectTypes: { value: string; label: string }[];
};

export default function AuditLogPage({
    filters,
    logs,
    users,
    actions,
    subjectTypes,
}: Props) {
    const [selected, setSelected] = useState<AuditRow | null>(null);

    function visit(next: Partial<Props['filters']> & { page?: number }) {
        const merged = { ...filters, ...next };
        router.get(
            AuditLogController.index.url({
                query: {
                    user_id: merged.user_id ?? undefined,
                    action: merged.action || undefined,
                    subject_type: merged.subject_type || undefined,
                    date_from: merged.date_from || undefined,
                    date_to: merged.date_to || undefined,
                    page: next.page ?? 1,
                },
            }),
            {},
            { preserveState: false, replace: true },
        );
    }

    return (
        <>
            <Head title="Jejak audit" />
            <div className="space-y-6">
                <PageHeader
                    title="Jejak audit"
                    description="Riwayat perubahan data penting: pelaku, waktu (WITA), aksi, subjek, dan detail sebelum/sesudah."
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={
                            filters.user_id ? String(filters.user_id) : 'semua'
                        }
                        onValueChange={(v) =>
                            visit({ user_id: v === 'semua' ? null : Number(v) })
                        }
                    >
                        <SelectTrigger
                            className="w-[190px]"
                            aria-label="Filter pengguna"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">
                                Semua pengguna
                            </SelectItem>
                            {users.map((u) => (
                                <SelectItem key={u.id} value={String(u.id)}>
                                    {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.action || 'semua'}
                        onValueChange={(v) =>
                            visit({ action: v === 'semua' ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-[210px]"
                            aria-label="Filter aksi"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua aksi</SelectItem>
                            {actions.map((act) => (
                                <SelectItem key={act} value={act}>
                                    {act}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.subject_type || 'semua'}
                        onValueChange={(v) =>
                            visit({ subject_type: v === 'semua' ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-[180px]"
                            aria-label="Filter subjek"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua subjek</SelectItem>
                            {subjectTypes.map((st) => (
                                <SelectItem key={st.value} value={st.value}>
                                    {st.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Input
                        type="date"
                        value={filters.date_from}
                        onChange={(e) => visit({ date_from: e.target.value })}
                        className="w-[160px]"
                        aria-label="Dari tanggal"
                    />
                    <Input
                        type="date"
                        value={filters.date_to}
                        onChange={(e) => visit({ date_to: e.target.value })}
                        className="w-[160px]"
                        aria-label="Sampai tanggal"
                    />
                </div>

                {logs.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada catatan audit"
                        description="Tidak ditemukan jejak audit untuk filter yang dipilih."
                    />
                ) : (
                    <div className="space-y-4">
                        <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2.5">
                                            Waktu (WITA)
                                        </th>
                                        <th className="px-4 py-2.5">
                                            Pengguna
                                        </th>
                                        <th className="px-4 py-2.5">Aksi</th>
                                        <th className="px-4 py-2.5">Subjek</th>
                                        <th className="px-4 py-2.5">IP</th>
                                        <th className="px-4 py-2.5 text-right">
                                            Detail
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {logs.data.map((log) => (
                                        <tr key={log.id}>
                                            <td className="px-4 py-2.5 whitespace-nowrap tabular-nums">
                                                {log.created_at}
                                            </td>
                                            <td className="px-4 py-2.5 font-medium">
                                                {log.user_name}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <StatusBadge variant="muted">
                                                    {log.action}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-4 py-2.5 text-muted-foreground">
                                                {log.subject_type}
                                                {log.subject_id !== null
                                                    ? ` #${log.subject_id}`
                                                    : ''}
                                            </td>
                                            <td className="px-4 py-2.5 font-mono text-xs text-muted-foreground">
                                                {log.ip}
                                            </td>
                                            <td className="px-4 py-2.5 text-right">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label="Lihat detail before dan after"
                                                            onClick={() =>
                                                                setSelected(log)
                                                            }
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        Detail before / after
                                                    </TooltipContent>
                                                </Tooltip>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {logs.meta.last_page > 1 ? (
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>
                                    Menampilkan {logs.meta.from ?? 0}–
                                    {logs.meta.to ?? 0} dari {logs.meta.total}
                                </span>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={logs.meta.current_page <= 1}
                                        onClick={() =>
                                            visit({
                                                page:
                                                    logs.meta.current_page - 1,
                                            })
                                        }
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            logs.meta.current_page >=
                                            logs.meta.last_page
                                        }
                                        onClick={() =>
                                            visit({
                                                page:
                                                    logs.meta.current_page + 1,
                                            })
                                        }
                                    >
                                        Berikutnya
                                    </Button>
                                </div>
                            </div>
                        ) : null}
                    </div>
                )}
            </div>

            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) setSelected(null);
                }}
            >
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            Detail audit: {selected?.action}
                        </DialogTitle>
                    </DialogHeader>
                    {selected ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <div className="text-xs font-medium text-muted-foreground">
                                    Sebelum (before)
                                </div>
                                <pre className="max-h-72 overflow-auto rounded-md border border-border bg-muted/40 p-3 font-mono text-xs">
                                    {selected.before
                                        ? JSON.stringify(
                                              selected.before,
                                              null,
                                              2,
                                          )
                                        : '—'}
                                </pre>
                            </div>
                            <div className="space-y-1.5">
                                <div className="text-xs font-medium text-muted-foreground">
                                    Sesudah (after)
                                </div>
                                <pre className="max-h-72 overflow-auto rounded-md border border-border bg-muted/40 p-3 font-mono text-xs">
                                    {selected.after
                                        ? JSON.stringify(
                                              selected.after,
                                              null,
                                              2,
                                          )
                                        : '—'}
                                </pre>
                            </div>
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}

AuditLogPage.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Laporan', href: '#' },
            {
                title: 'Jejak audit',
                href: AuditLogController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
