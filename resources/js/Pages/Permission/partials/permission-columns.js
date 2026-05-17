import { DataTableColumnHeader } from '@/Components/ui/data-table';
import { h } from 'vue';
import PermissionActionRow from './PermissionActionRow.vue';

export const permissionColumns = [
    {
        accessorKey: 'name',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Nama',
            }),
    },
    {
        accessorKey: 'guard_name',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Guard',
            }),
    },
    {
        accessorKey: 'action',
        header: () => h('div', { class: 'text-center w-full' }, 'Aksi'),
        cell: ({ row }) =>
            h(PermissionActionRow, {
                row: row.original,
            }),
    },
];
