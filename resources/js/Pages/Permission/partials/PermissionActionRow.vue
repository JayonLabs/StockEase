<script setup>
import { Button } from '@/Components/ui/button';
import { Trash } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import PermissionUpdateForm from '../form/PermissionUpdateForm.vue';

const props = defineProps({
    row: { type: Object, required: true },
});
</script>

<template>
    <div class="flex items-center justify-center gap-2">
        <PermissionUpdateForm :row="row" />
        <Button
            aria-label="Hapus"
            variant="ghost"
            size="icon"
            class="group"
            as-child
        >
            <Link
                :href="route('permissions.destroy', row.id)"
                method="delete"
                as="button"
                onclick="
                    return confirm('Yakin ingin menghapus permission ini?');
                "
            >
                <Trash
                    class="w-4 h-4 text-red-500 dark:group-hover:text-white"
                />
            </Link>
        </Button>
    </div>
</template>
