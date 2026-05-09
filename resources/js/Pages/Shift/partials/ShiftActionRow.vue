<script setup>
import { Button } from '@/Components/ui/button';
import { Link } from '@inertiajs/vue3';
import { Eye, Lock } from 'lucide-vue-next';

defineProps({
    row: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="flex items-center justify-center gap-1">
        <Link :href="route('shift.show', row.id)">
            <Button variant="ghost" size="icon">
                <Eye class="w-4 h-4" />
            </Button>
        </Link>
        <Link v-if="row.status === 'open'" :href="route('shift.show', row.id)">
            <Button variant="ghost" size="icon">
                <Lock class="w-4 h-4" />
            </Button>
        </Link>
    </div>
</template>
