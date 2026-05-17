<script setup>
import { Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Pencil } from 'lucide-vue-next';

const props = defineProps({
    row: { type: Object, required: true },
    permissions: { type: Array, required: true },
});
</script>

<template>
    <div class="flex items-center justify-center gap-2">
        <Link :href="route('user-permissions.edit', row.id)">
            <Button variant="ghost" size="icon" class="group">
                <Pencil
                    class="w-4 h-4 text-blue-500 dark:group-hover:text-white"
                />
            </Button>
        </Link>
    </div>
</template>
