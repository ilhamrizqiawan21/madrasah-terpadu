import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export type DataTableColumn<TData> = {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
    sortable?: boolean;
    className?: string;
    render: (row: TData) => ReactNode;
};

export type DataTablePagination = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type DataTableSort = {
    key: string;
    direction: 'asc' | 'desc';
};

type DataTableProps<TData> = {
    columns: DataTableColumn<TData>[];
    data: TData[];
    rowKey: (row: TData) => string | number;
    loading?: boolean;
    skeletonRows?: number;
    empty?: {
        title: string;
        description: string;
        action?: ReactNode;
    };
    sort?: DataTableSort;
    onSortChange?: (key: string, direction: 'asc' | 'desc') => void;
    pagination?: DataTablePagination;
    onPageChange?: (page: number) => void;
};

const alignClasses: Record<'left' | 'right' | 'center', string> = {
    left: 'text-left',
    right: 'text-right tabular-nums',
    center: 'text-center',
};

export function DataTable<TData>({
    columns,
    data,
    rowKey,
    loading = false,
    skeletonRows = 5,
    empty = {
        title: 'Belum ada data',
        description: 'Belum ada data yang sesuai untuk ditampilkan.',
    },
    sort,
    onSortChange,
    pagination,
    onPageChange,
}: DataTableProps<TData>) {
    const toggleSort = (key: string): void => {
        if (!onSortChange) {
            return;
        }

        const nextDirection =
            sort?.key === key && sort.direction === 'asc' ? 'desc' : 'asc';
        onSortChange(key, nextDirection);
    };

    if (!loading && data.length === 0) {
        return (
            <EmptyState
                title={empty.title}
                description={empty.description}
                action={empty.action}
            />
        );
    }

    return (
        <div className="space-y-3">
            <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b border-border bg-primary/[0.04]">
                            {columns.map((col) => {
                                const isSorted = sort?.key === col.key;

                                return (
                                    <th
                                        key={col.key}
                                        scope="col"
                                        className={cn(
                                            'px-3 py-2 text-center font-medium whitespace-nowrap text-muted-foreground',
                                            col.className,
                                        )}
                                    >
                                        {col.sortable && onSortChange ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggleSort(col.key)
                                                }
                                                className="inline-flex items-center gap-1 rounded px-1 py-0.5 transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                <span>{col.label}</span>
                                                {isSorted ? (
                                                    sort.direction === 'asc' ? (
                                                        <ArrowUp
                                                            className="size-3.5"
                                                            strokeWidth={1.75}
                                                        />
                                                    ) : (
                                                        <ArrowDown
                                                            className="size-3.5"
                                                            strokeWidth={1.75}
                                                        />
                                                    )
                                                ) : (
                                                    <ArrowUpDown
                                                        className="size-3.5 opacity-60"
                                                        strokeWidth={1.75}
                                                    />
                                                )}
                                            </button>
                                        ) : (
                                            col.label
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {loading
                            ? Array.from({ length: skeletonRows }).map(
                                  (_, rowIndex) => (
                                      <tr key={`skeleton-${rowIndex}`}>
                                          {columns.map((col) => (
                                              <td
                                                  key={col.key}
                                                  className="px-3 py-2"
                                              >
                                                  <Skeleton className="h-4 w-full" />
                                              </td>
                                          ))}
                                      </tr>
                                  ),
                              )
                            : data.map((row) => (
                                  <tr
                                      key={rowKey(row)}
                                      className="transition-colors hover:bg-primary/[0.03]"
                                  >
                                      {columns.map((col) => (
                                          <td
                                              key={col.key}
                                              className={cn(
                                                  'px-3 py-2',
                                                  alignClasses[
                                                      col.align ?? 'left'
                                                  ],
                                                  col.className,
                                              )}
                                          >
                                              {col.render(row)}
                                          </td>
                                      ))}
                                  </tr>
                              ))}
                    </tbody>
                </table>
            </div>

            {pagination && pagination.lastPage > 1 && onPageChange && (
                <div className="flex flex-col items-center justify-between gap-2 text-sm text-muted-foreground sm:flex-row">
                    <div className="tabular-nums">
                        Menampilkan {pagination.from ?? 0}–{pagination.to ?? 0}{' '}
                        dari {pagination.total} data
                    </div>
                    <div className="flex items-center gap-1.5">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={pagination.currentPage <= 1 || loading}
                            onClick={() =>
                                onPageChange(pagination.currentPage - 1)
                            }
                            aria-label="Halaman sebelumnya"
                        >
                            <ChevronLeft
                                className="size-4"
                                strokeWidth={1.75}
                            />
                            Sebelumnya
                        </Button>
                        <span className="px-2 tabular-nums">
                            {pagination.currentPage} / {pagination.lastPage}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={
                                pagination.currentPage >= pagination.lastPage ||
                                loading
                            }
                            onClick={() =>
                                onPageChange(pagination.currentPage + 1)
                            }
                            aria-label="Halaman berikutnya"
                        >
                            Berikutnya
                            <ChevronRight
                                className="size-4"
                                strokeWidth={1.75}
                            />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
