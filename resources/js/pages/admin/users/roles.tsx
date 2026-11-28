import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import RolePermissionController from '@/actions/App/Http/Controllers/Admin/RolePermissionController';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/app/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import StaffLayout from '@/layouts/staff-layout';
import { cn } from '@/lib/utils';

type Row = { label: string; view: string | null; manage: string | null };
type Group = { label: string; rows: Row[] };

type Props = {
    roles: { value: string; label: string }[];
    selectedRole: string;
    granted: string[];
    groups: Group[];
};

function rowPermissions(rows: Row[]): string[] {
    return rows.flatMap((row) =>
        [row.view, row.manage].filter((p): p is string => p !== null),
    );
}

export default function RolePermissions({
    roles,
    selectedRole,
    granted,
    groups,
}: Props) {
    const form = useForm<{ permissions: string[] }>({ permissions: granted });
    const selected = new Set(form.data.permissions);
    const selectedLabel =
        roles.find((r) => r.value === selectedRole)?.label ?? selectedRole;

    const setMany = (permissions: string[], on: boolean) => {
        const next = new Set(form.data.permissions);
        permissions.forEach((p) => (on ? next.add(p) : next.delete(p)));
        form.setData('permissions', [...next]);
    };

    // "Kelola" selalu menyertakan "Lihat"; mencabut "Lihat" ikut mencabut "Kelola".
    const toggle = (row: Row, column: 'view' | 'manage', on: boolean) => {
        const permission = row[column];
        if (permission === null) {
            return;
        }

        if (column === 'manage' && on && row.view) {
            setMany([permission, row.view], true);
        } else if (column === 'view' && !on && row.manage) {
            setMany([permission, row.manage], false);
        } else {
            setMany([permission], on);
        }
    };

    const switchRole = (role: string) => {
        if (role === selectedRole) {
            return;
        }

        router.get(
            RolePermissionController.index.url({ query: { peran: role } }),
            {},
            { preserveScroll: true },
        );
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(RolePermissionController.update.url(selectedRole), {
            preserveScroll: true,
        });
    };

    const all = groups.flatMap((g) => rowPermissions(g.rows));

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Pengguna', href: '#' },
                {
                    title: 'Peran & Hak Akses',
                    href: RolePermissionController.index.url(),
                },
            ]}
        >
            <Head title="Peran & Hak Akses" />

            <form onSubmit={submit} className="space-y-6">
                <PageHeader
                    title="Peran & Hak Akses"
                    description="Pilih peran, lalu tentukan fitur yang boleh dilihat dan dikelola. Admin selalu memegang semua izin."
                />

                <div
                    role="group"
                    aria-label="Pilih peran"
                    className="flex flex-wrap gap-2"
                >
                    {roles.map((role) => {
                        const active = role.value === selectedRole;

                        return (
                            <Button
                                key={role.value}
                                type="button"
                                variant={active ? 'default' : 'outline'}
                                aria-pressed={active}
                                onClick={() => switchRole(role.value)}
                            >
                                {role.label}
                            </Button>
                        );
                    })}
                </div>

                <div className="rounded-xl border border-border bg-card shadow-xs">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-4">
                        <h2 className="text-sm font-semibold">
                            Izin peran {selectedLabel}
                        </h2>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setMany(all, true)}
                            >
                                Centang semua
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setMany(all, false)}
                            >
                                Kosongkan semua
                            </Button>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-border bg-muted/50 text-left text-xs text-muted-foreground">
                                    <th className="px-4 py-2 font-medium">
                                        Fitur
                                    </th>
                                    <th className="w-24 px-4 py-2 text-center font-medium">
                                        Lihat
                                    </th>
                                    <th className="w-24 px-4 py-2 text-center font-medium">
                                        Kelola
                                    </th>
                                </tr>
                            </thead>
                            {groups.map((group) => {
                                const groupPermissions = rowPermissions(
                                    group.rows,
                                );

                                return (
                                    <tbody key={group.label}>
                                        <tr className="border-b border-border bg-muted/20">
                                            <th
                                                colSpan={3}
                                                className="px-4 py-2 text-left"
                                            >
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <span className="font-semibold">
                                                        {group.label}
                                                    </span>
                                                    <span className="flex gap-3 text-xs font-normal">
                                                        <button
                                                            type="button"
                                                            className="text-primary hover:underline"
                                                            onClick={() =>
                                                                setMany(
                                                                    groupPermissions,
                                                                    true,
                                                                )
                                                            }
                                                        >
                                                            Centang kategori
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="text-muted-foreground hover:underline"
                                                            onClick={() =>
                                                                setMany(
                                                                    groupPermissions,
                                                                    false,
                                                                )
                                                            }
                                                        >
                                                            Kosongkan
                                                        </button>
                                                    </span>
                                                </div>
                                            </th>
                                        </tr>
                                        {group.rows.map((row) => (
                                            <tr
                                                key={row.label}
                                                className="border-b border-border last:border-0"
                                            >
                                                <td className="px-4 py-2.5">
                                                    {row.label}
                                                </td>
                                                {(
                                                    ['view', 'manage'] as const
                                                ).map((column) => {
                                                    const permission =
                                                        row[column];

                                                    return (
                                                        <td
                                                            key={column}
                                                            className={cn(
                                                                'px-4 py-2.5 text-center',
                                                            )}
                                                        >
                                                            {permission ? (
                                                                <Checkbox
                                                                    className="size-5"
                                                                    checked={selected.has(
                                                                        permission,
                                                                    )}
                                                                    onCheckedChange={(
                                                                        v,
                                                                    ) =>
                                                                        toggle(
                                                                            row,
                                                                            column,
                                                                            v ===
                                                                                true,
                                                                        )
                                                                    }
                                                                    aria-label={`${column === 'view' ? 'Lihat' : 'Kelola'} ${row.label}`}
                                                                />
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    —
                                                                </span>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                );
                            })}
                        </table>
                    </div>
                </div>

                <InputError message={form.errors.permissions} />

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan pengaturan izin
                    </Button>
                </div>
            </form>
        </StaffLayout>
    );
}

RolePermissions.layout = (page: ReactNode) => page;
