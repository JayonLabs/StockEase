import { DataTableColumnHeader } from '@/Components/ui/data-table';
import { h } from 'vue';
import WarehouseActionRow from './WarehouseActionRow.vue';

export const warehouseColumns = [
    {
        accessorKey: 'name',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Nama Gudang',
            }),
    },
    {
        accessorKey: 'phone',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Nomor Telepon',
            }),
    },
    {
        accessorKey: 'address',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Alamat',
            }),
    },
    {
        accessorKey: 'action',
        header: () => h('div', { class: 'text-center w-full' }, 'Aksi'),
        cell: ({ row }) =>
            h(WarehouseActionRow, {
                row: row.original,
            }),
    },
];
