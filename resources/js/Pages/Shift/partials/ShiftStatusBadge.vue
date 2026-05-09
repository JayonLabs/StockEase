<script setup>
import { Badge } from '@/Components/ui/badge';

defineProps({
    status: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="flex items-center justify-center w-full">
        <Badge :variant="status === 'open' ? 'default' : 'secondary'">
            {{ status === 'open' ? 'Terbuka' : 'Tertutup' }}
        </Badge>
    </div>
</template>
