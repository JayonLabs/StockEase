import { DataTableColumnHeader } from '@/Components/ui/data-table';
import { formatPrice } from '@/lib/utils';
import { h } from 'vue';
import PurchaseActionRow from './PurchaseActionRow.vue';

export const purchaseColumns = [
    {
        accessorKey: 'date',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Tanggal Pembelian',
            }),
        cell: ({ row }) =>
            h('span', { class: 'capitalize' }, row.original.date),
    },
    {
        accessorKey: 'supplier.name',
        header: 'Supplier',
        cell: ({ row }) => {
            return h(
                'span',
                { class: 'capitalize' },
                row.original.supplier.name,
            );
        },
    },
    {
        accessorKey: 'user.name',
        header: 'User Input',
        cell: ({ row }) => {
            return h('span', { class: 'capitalize' }, row.original.user.name);
        },
    },
    {
        accessorKey: 'total',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Total Pembelian',
            }),
        cell: ({ row }) => {
            return h('span', null, formatPrice(row.original.total));
        },
    },
    {
        id: 'items_count',
        header: 'Jumlah Item',
        cell: ({ row }) => {
            const items = row.original.purchase_items || [];
            const totalQty = items.reduce(
                (sum, item) => sum + Number(item.qty),
                0,
            );

            return h('span', null, totalQty);
        },
    },
    {
        accessorKey: 'action',
        header: () => h('div', { class: 'text-center w-full' }, 'Aksi'),
        cell: ({ row }) =>
            h(PurchaseActionRow, {
                row: row.original,
            }),
    },
];
