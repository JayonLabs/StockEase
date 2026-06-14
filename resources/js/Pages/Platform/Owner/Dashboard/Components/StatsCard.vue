<script setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    title: { type: String, required: true },
    value: { type: [Number, String], required: true },
    icon: { type: Object, default: null },
    description: { type: String, default: '' },
    trend: { type: String, default: '' },
});
</script>

<template>
    <Card class="border-zinc-800 bg-zinc-900">
        <CardHeader class="flex flex-row items-center justify-between pb-2">
            <CardTitle class="text-sm font-medium text-zinc-400">
                {{ title }}
            </CardTitle>
            <component :is="icon" v-if="icon" class="h-4 w-4 text-zinc-500" />
        </CardHeader>
        <CardContent>
            <div class="text-2xl font-bold text-zinc-100">{{ value }}</div>
            <p v-if="description" class="mt-1 text-xs text-zinc-500">
                {{ description }}
            </p>
        </CardContent>
    </Card>
</template>
