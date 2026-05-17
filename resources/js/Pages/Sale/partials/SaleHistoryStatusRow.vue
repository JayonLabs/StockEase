<script setup>
import { Badge } from '@/Components/ui/badge';

const props = defineProps({
    row: {
        required: true,
        type: String,
    },
});
</script>

<template>
    <div class="capitalize flex items-center justify-center">
        <Badge variant="outline" class="border border-green-500">
            {{ props.row }}
        </Badge>
    </div>
</template>
