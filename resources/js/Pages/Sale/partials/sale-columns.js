import { DataTableColumnHeader } from '@/Components/ui/data-table';
import { h } from 'vue';
import { formatPrice, formatDateTime } from '@/lib/utils';
import SaleActionRow from './SaleActionRow.vue';
import SaleHistoryStatusRow from './SaleHistoryStatusRow.vue';

const centerClass =
    'capitalize flex items-center justify-center text-center w-full';

export const saleColumns = [
    {
        accessorKey: 'nomor',
        header: () => h('div', { class: 'text-center w-full' }, 'No.'),
        cell: ({ row }) => h('span', { class: centerClass }, row.index + 1),
    },
    {
        accessorKey: 'created_at',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Tanggal',
                class: 'justify-center',
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                formatDateTime(row.original.updated_at),
            ),
    },
    {
        accessorKey: 'user.name',
        header: () => h('div', 'Kasir'),
        cell: ({ row }) => h('span', row.original.user.name),
    },
    {
        accessorKey: 'total',
        header: () => h('div', 'Total'),
        cell: ({ row }) => h('span', formatPrice(row.original.total)),
    },
    {
        accessorKey: 'payment_method',
        header: () =>
            h('div', { class: 'text-center w-full' }, 'Metode Pembayaran'),
        cell: ({ row }) =>
            h('span', { class: centerClass }, row.original.payment_method),
    },
    {
        accessorKey: 'status',
        header: () => h('div', { class: 'text-center w-full' }, 'Status'),
        cell: ({ row }) =>
            h(SaleHistoryStatusRow, { row: row.original.status }),
    },
    {
        accessorKey: 'action',
        header: () => h('div', { class: 'text-center w-full' }, 'Aksi'),
        cell: ({ row }) => h(SaleActionRow, { row: row.original }),
    },
];
