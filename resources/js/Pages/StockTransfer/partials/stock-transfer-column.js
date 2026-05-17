import { h } from 'vue';
import dayjs from 'dayjs';
import 'dayjs/locale/id';

export const stockTransferColumns = [
    {
        accessorKey: 'date',
        header: 'Tanggal',
        cell: ({ row }) => {
            return h(
                'div',
                null,
                dayjs(row.getValue('date')).locale('id').format('DD MMM YYYY'),
            );
        },
    },
    {
        accessorKey: 'product.name',
        header: 'Produk',
        cell: ({ row }) => {
            return h(
                'div',
                { class: 'font-medium' },
                row.original.product.name,
            );
        },
    },
    {
        accessorKey: 'from_warehouse.name',
        header: 'Gudang Asal',
        cell: ({ row }) => {
            return h('div', null, row.original.from_warehouse?.name ?? '-');
        },
    },
    {
        accessorKey: 'to_warehouse.name',
        header: 'Gudang Tujuan',
        cell: ({ row }) => {
            return h('div', null, row.original.to_warehouse?.name ?? '-');
        },
    },
    {
        accessorKey: 'qty',
        header: 'Jumlah',
        cell: ({ row }) => {
            return h(
                'div',
                { class: 'font-medium text-center' },
                row.getValue('qty'),
            );
        },
    },
    {
        accessorKey: 'note',
        header: 'Catatan',
        cell: ({ row }) => {
            return h('div', null, row.getValue('note') || '-');
        },
    },
    {
        accessorKey: 'user.name',
        header: 'Petugas',
        cell: ({ row }) => {
            return h('div', null, row.original.user?.name ?? '-');
        },
    },
];
