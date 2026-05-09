import { DataTableColumnHeader } from '@/Components/ui/data-table';
import { h } from 'vue';
import { formatPrice, formatDateTime, formatDate } from '@/lib/utils';
import SaleReturnActionRow from './SaleReturnActionRow.vue';

const centerClass =
    'capitalize flex items-center justify-center text-center w-full';

export const saleReturnColumns = [
    {
        accessorKey: 'nomor',
        header: () => h('div', { class: 'text-center w-full' }, 'No.'),
        cell: ({ row }) => h('span', { class: centerClass }, row.index + 1),
    },
    {
        accessorKey: 'id',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'ID Retur',
                class: 'justify-center',
            }),
        cell: ({ row }) =>
            h('span', { class: centerClass }, `#${row.original.id}`),
    },
    {
        accessorKey: 'sale.id',
        header: () => h('div', { class: 'text-center w-full' }, 'ID Transaksi'),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                `#${row.original.sale?.id ?? '-'}`,
            ),
    },
    {
        accessorKey: 'sale.customer_name',
        header: () => h('div', { class: 'text-center w-full' }, 'Pelanggan'),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                row.original.sale?.customer_name ?? '-',
            ),
    },
    {
        accessorKey: 'return_type',
        header: () => h('div', { class: 'text-center w-full' }, 'Tipe Retur'),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                row.original.return_type === 'refund'
                    ? 'Pengembalian Uang'
                    : 'Tukar Barang',
            ),
    },
    {
        accessorKey: 'total_refund',
        header: () => h('div', { class: 'text-center w-full' }, 'Total Refund'),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                formatPrice(row.original.total_refund),
            ),
    },
    {
        accessorKey: 'return_date',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Tanggal Retur',
                class: 'justify-center',
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                formatDate(row.original.return_date),
            ),
    },
    {
        accessorKey: 'user.name',
        header: () =>
            h('div', { class: 'text-center w-full' }, 'Diproses Oleh'),
        cell: ({ row }) =>
            h('span', { class: centerClass }, row.original.user?.name ?? '-'),
    },
    {
        accessorKey: 'status',
        header: () => h('div', { class: 'text-center w-full' }, 'Status'),
        cell: ({ row }) =>
            h(
                'span',
                { class: centerClass },
                row.original.status === 'completed' ? 'Selesai' : 'Dibatalkan',
            ),
    },
    {
        accessorKey: 'action',
        header: () => h('div', { class: 'text-center w-full' }, 'Aksi'),
        cell: ({ row }) => h(SaleReturnActionRow, { row: row.original }),
    },
];
