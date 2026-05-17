<script setup>
import { Badge } from '@/Components/ui/badge';
import { computed } from 'vue';

const props = defineProps({
    row: { type: Object, required: true },
});

const borderColor = computed(() => {
    switch (props.row.role) {
        case 'super_admin':
            return 'border-purple-500';
        case 'admin':
            return 'border-red-500';
        case 'warehouse':
            return 'border-green-500';
        default:
            return 'border-gray-500';
    }
});

const roleLabel = computed(() => {
    if (!props.row.role) {
        return 'Belum diatur';
    }

    return props.row.role
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (l) => l.toUpperCase());
});
</script>

<template>
    <Badge
        v-if="props.row.role"
        class="capitalize"
        :class="borderColor"
        variant="outline"
    >
        {{ roleLabel }}
    </Badge>
    <span v-else class="text-muted-foreground text-xs italic"
        >Belum diatur</span
    >
</template>
