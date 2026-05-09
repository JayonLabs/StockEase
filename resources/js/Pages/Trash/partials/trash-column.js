import { DataTableColumnHeader } from '@/Components/ui/data-table';
import { h } from 'vue';
import TrashActionRow from './TrashActionRow.vue';

export const trashColumns = [
    {
        accessorKey: 'name',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Nama Item',
            }),
    },
    {
        accessorKey: 'type_label',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Tipe',
            }),
    },
    {
        accessorKey: 'deleted_at',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Dihapus Pada',
            }),
    },
    {
        accessorKey: 'action',
        header: () => h('div', { class: 'text-center w-full' }, 'Aksi'),
        cell: ({ row }) =>
            h(TrashActionRow, {
                row: row.original,
            }),
    },
];
