<script setup>
import { computed } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';

const props = defineProps({
    permissions: {
        type: Array,
        required: true,
    },
});

const MAX_VISIBLE = 3;

const visible = computed(() => props.permissions.slice(0, MAX_VISIBLE));
const remaining = computed(() => props.permissions.slice(MAX_VISIBLE));
const hasMore = computed(() => remaining.value.length > 0);
</script>

<template>
    <div v-if="!permissions.length" class="text-muted-foreground text-sm">
        Tidak ada direct permission
    </div>
    <div v-else class="flex flex-wrap gap-1 items-center">
        <span
            v-for="perm in visible"
            :key="perm.id"
            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-orange-500/10 text-orange-500 border-orange-500/20"
        >
            {{ perm.name }}
        </span>
        <TooltipProvider v-if="hasMore" :delayDuration="100">
            <Tooltip>
                <TooltipTrigger as-child>
                    <span
                        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-orange-500/20 text-orange-500 border-orange-500/30 cursor-default"
                    >
                        +{{ remaining.length }} more
                    </span>
                </TooltipTrigger>
                <TooltipContent
                    side="bottom"
                    class="max-w-xs max-h-60 overflow-y-auto p-3"
                >
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-for="perm in remaining"
                            :key="perm.id"
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-orange-500/10 text-orange-500 border-orange-500/20"
                        >
                            {{ perm.name }}
                        </span>
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
</template>
