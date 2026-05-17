import { DataTableColumnHeader } from '@/Components/ui/data-table';
import { h } from 'vue';
import UserPermissionActionRow from './UserPermissionActionRow.vue';
import UserPermissionBadgeList from './UserPermissionBadgeList.vue';

export const getUserPermissionColumns = (permissions) => [
    {
        accessorKey: 'name',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Nama',
            }),
    },
    {
        accessorKey: 'email',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Email',
            }),
    },
    {
        accessorKey: 'roles',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Role',
            }),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'flex flex-wrap gap-1' },
                row.original.roles.length
                    ? row.original.roles.map((r) =>
                          h(
                              'span',
                              {
                                  class: 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-purple-500/10 text-purple-500 border-purple-500/20',
                              },
                              r.name,
                          ),
                      )
                    : [
                          h(
                              'span',
                              { class: 'text-muted-foreground text-sm' },
                              'Tidak ada role',
                          ),
                      ],
            ),
    },
    {
        accessorKey: 'permissions',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column: column,
                title: 'Direct Permissions',
            }),
        cell: ({ row }) =>
            h(UserPermissionBadgeList, {
                permissions: row.original.permissions,
            }),
    },
    {
        accessorKey: 'action',
        header: () => h('div', { class: 'text-center w-full' }, 'Aksi'),
        cell: ({ row }) =>
            h(UserPermissionActionRow, {
                row: row.original,
                permissions: permissions,
            }),
    },
];
