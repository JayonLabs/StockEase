<script setup>
import { Head, router, usePoll } from '@inertiajs/vue3';
import { ref, watch, nextTick, computed } from 'vue';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

const props = defineProps({
    stats: {
        type: Object,
        default: null,
    },
    lines: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const logContainer = ref(null);
const autoScroll = ref(true);

const searchQuery = ref(props.filters.search || '');
const activeLevel = ref(props.filters.level || '');

usePoll(15000);

function applyFilter() {
    const params = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (activeLevel.value) params.level = activeLevel.value;

    router.reload({
        only: ['lines', 'stats', 'filters'],
        data: params,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => scrollToBottom(),
    });
}

function clearFilters() {
    searchQuery.value = '';
    activeLevel.value = '';
    router.reload({
        only: ['lines', 'stats', 'filters'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => scrollToBottom(),
    });
}

function refreshLogs() {
    router.reload({
        only: ['lines', 'stats', 'filters'],
        preserveScroll: false,
        preserveState: true,
        onSuccess: () => scrollToBottom(),
    });
}

function scrollToBottom() {
    nextTick(() => {
        if (logContainer.value && autoScroll.value) {
            logContainer.value.scrollTop = logContainer.value.scrollHeight;
        }
    });
}

function onScroll() {
    if (!logContainer.value) return;
    const { scrollTop, scrollHeight, clientHeight } = logContainer.value;
    autoScroll.value = scrollHeight - scrollTop - clientHeight < 100;
}

watch(
    () => props.lines,
    () => scrollToBottom(),
    { immediate: true },
);

const lineColorClass = (level) => {
    switch (level) {
        case 'error':
            return 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30';
        case 'warning':
            return 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30';
        default:
            return 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50';
    }
};

const encodedStats = computed(() => props.stats ?? null);
</script>

<template>
    <Head title="Queue Worker Logs" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="rounded-xl bg-muted/50 h-full flex flex-col overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 pb-0">
                <div>
                    <h4 class="font-semibold text-lg">Queue Worker Logs</h4>
                    <p class="text-sm text-muted-foreground">
                        Output dari scheduled queue worker
                    </p>
                </div>
                <Button variant="outline" size="sm" @click="refreshLogs">
                    Refresh
                </Button>
            </div>

            <Separator class="my-3" />

            <template v-if="encodedStats">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-1 px-4 text-sm text-muted-foreground">
                    <span>File: <span class="font-mono text-xs">{{ encodedStats.file }}</span></span>
                    <span>Size: {{ encodedStats.size }}</span>
                    <span>Lines: {{ encodedStats.lines }}</span>
                    <span>Modified: {{ encodedStats.modified }}</span>
                </div>
            </template>

            <Separator class="my-3" />

            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 px-4">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <Label for="search-log" class="sr-only">Search</Label>
                    <Input
                        id="search-log"
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search log entries..."
                        class="h-9 w-full sm:w-64"
                        @keyup.enter="applyFilter"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Select v-model="activeLevel" @update:model-value="applyFilter">
                        <SelectTrigger class="h-9 w-36">
                            <SelectValue placeholder="All Levels" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="error">Error</SelectItem>
                            <SelectItem value="warning">Warning</SelectItem>
                            <SelectItem value="info">Info</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <Button
                    v-if="searchQuery || activeLevel"
                    variant="ghost"
                    size="sm"
                    class="h-9"
                    @click="clearFilters"
                >
                    Clear
                </Button>
                <div class="flex items-center gap-1 ml-auto text-xs text-muted-foreground">
                    <Badge variant="outline" class="text-xs h-5">
                        Showing {{ lines.length }} of {{ stats?.lines || 0 }}
                    </Badge>
                </div>
            </div>

            <Separator class="mt-3" />

            <!-- Log content area -->
            <div
                ref="logContainer"
                class="flex-1 overflow-auto bg-gray-950 dark:bg-gray-950 p-4 font-mono text-xs leading-relaxed"
                @scroll="onScroll"
            >
                <template v-if="lines.length">
                    <div
                        v-for="(line, index) in lines"
                        :key="index"
                        class="flex items-start py-0.5 rounded px-1"
                        :class="lineColorClass(line.level)"
                    >
                        <span class="text-gray-500 dark:text-gray-600 select-none shrink-0 w-12 text-right mr-3">
                            {{ index + 1 }}
                        </span>
                        <span class="break-all whitespace-pre-wrap">{{ line.text }}</span>
                    </div>
                </template>

                <template v-else>
                    <div class="flex flex-col items-center justify-center h-full text-gray-500 py-12">
                        <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                        <p class="text-sm">Tidak ada log yang cocok</p>
                    </div>
                </template>
            </div>

            <div
                v-if="!autoScroll && lines.length"
                class="flex justify-center py-2 bg-gray-900 border-t border-gray-800"
            >
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-xs text-gray-400 hover:text-white h-7"
                    @click="scrollToBottom"
                >
                    Scroll to bottom
                </Button>
            </div>
        </div>
    </div>
</template>
