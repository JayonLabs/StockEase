import { DataTableColumnHeader } from '@/Components/ui/data-table';
import { h } from 'vue';
import { formatPrice, formatDateTime } from '@/lib/utils';
import ShiftActionRow from './ShiftActionRow.vue';
import ShiftStatusBadge from './ShiftStatusBadge.vue';

const centerClass =
    'capitalize flex items-center justify-center text-center w-full';

export const shiftColumns = [
    {
        accessorKey: 'nomor',
        header: () => h('div', { class: 'text-center w-full' }, 'No.'),
        cell: ({ row }) => h('span', { class: centerClass }, row.index + 1),
    },
    {
        accessorKey: 'user.name',
        header: () => h('div', 'Kasir'),
        cell: ({ row }) => h('span', row.original.user?.name ?? '-'),
    },
    {
        accessorKey: 'opened_at',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Dibuka',
                class: 'justify-center',
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                formatDateTime(row.original.opened_at),
            ),
    },
    {
        accessorKey: 'closed_at',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Ditutup',
                class: 'justify-center',
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                row.original.closed_at
                    ? formatDateTime(row.original.closed_at)
                    : '-',
            ),
    },
    {
        accessorKey: 'starting_cash',
        header: () => h('div', { class: 'text-center w-full' }, 'Modal Awal'),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                formatPrice(row.original.starting_cash),
            ),
    },
    {
        accessorKey: 'expected_cash',
        header: () =>
            h('div', { class: 'text-center w-full' }, 'Kas Diharapkan'),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                row.original.expected_cash
                    ? formatPrice(row.original.expected_cash)
                    : '-',
            ),
    },
    {
        accessorKey: 'actual_cash',
        header: () => h('div', { class: 'text-center w-full' }, 'Kas Aktual'),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                row.original.actual_cash
                    ? formatPrice(row.original.actual_cash)
                    : '-',
            ),
    },
    {
        accessorKey: 'cash_difference',
        header: () => h('div', { class: 'text-center w-full' }, 'Selisih'),
        cell: ({ row }) =>
            h(
                'span',
                {
                    class: `${centerClass} font-semibold ${
                        row.original.cash_difference < 0
                            ? 'text-red-600'
                            : row.original.cash_difference > 0
                              ? 'text-green-600'
                              : ''
                    }`,
                },
                row.original.cash_difference !== null
                    ? formatPrice(row.original.cash_difference)
                    : '-',
            ),
    },
    {
        accessorKey: 'status',
        header: () => h('div', { class: 'text-center w-full' }, 'Status'),
        cell: ({ row }) => h(ShiftStatusBadge, { status: row.original.status }),
    },
    {
        accessorKey: 'action',
        header: () => h('div', { class: 'text-center w-full' }, 'Aksi'),
        cell: ({ row }) => h(ShiftActionRow, { row: row.original }),
    },
];
