<script setup>
import { ref, computed } from 'vue';
import { CalendarIcon, X } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/button';
import { RangeCalendar } from '@/Components/ui/range-calendar';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { parseDate, today, getLocalTimeZone } from '@internationalized/date';

import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/Components/ui/sheet';

const props = defineProps({
    start: { type: String, default: null },
    end: { type: String, default: null },
    placeholder: { type: String, default: 'Pilih rentang tanggal' },
    displayText: { type: String, required: true },
    hasValue: { type: Boolean, default: false },
});

const emit = defineEmits(['apply']);

const localTimeZone = getLocalTimeZone();

const isOpen = ref(false);
const mobilePreset = ref('all');

const mobilePresets = [
    { label: 'Semua Tanggal Transaksi', value: 'all' },
    { label: '30 Hari Terakhir', value: 'last30' },
    { label: '90 Hari Terakhir', value: 'last90' },
    { label: 'Pilih Tanggal Sendiri', value: 'custom' },
];

const mobileRange = ref({
    start: null,
    end: null,
});

function selectMobilePreset(value) {
    mobilePreset.value = value;
}

function toDateString(daysAgo) {
    const d = new Date();
    d.setDate(d.getDate() - daysAgo);
    return d.toISOString().split('T')[0];
}

const mobileDisabled = computed(() => {
    if (mobilePreset.value === 'custom') {
        return !mobileRange.value.start || !mobileRange.value.end;
    }
    return false;
});

function handleApply() {
    let start = null;
    let end = null;

    if (mobilePreset.value === 'all') {
        start = null;
        end = null;
    } else if (mobilePreset.value === 'last30') {
        start = toDateString(29);
        end = toDateString(0);
    } else if (mobilePreset.value === 'last90') {
        start = toDateString(89);
        end = toDateString(0);
    } else if (mobilePreset.value === 'custom') {
        start = mobileRange.value.start
            ? mobileRange.value.start.toString()
            : null;
        end = mobileRange.value.end ? mobileRange.value.end.toString() : null;
    }

    emit('apply', { start, end });
    isOpen.value = false;
}
</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetTrigger as-child>
            <Button
                variant="outline"
                :class="
                    cn(
                        'w-full justify-start text-left font-normal bg-card h-10 border-muted-foreground/20',
                        !hasValue && 'text-muted-foreground',
                    )
                "
            >
                <CalendarIcon class="mr-2 h-4 w-4 shrink-0" />
                <span class="truncate">{{ displayText }}</span>
            </Button>
        </SheetTrigger>
        <SheetContent
            side="bottom"
            class="rounded-t-3xl px-0 pb-0 pt-6 gap-0 [&>button]:hidden"
        >
            <div class="flex items-center justify-between px-6 pb-4">
                <SheetTitle class="text-lg font-bold text-foreground">
                    Pilih tanggal
                </SheetTitle>
                <Button
                    aria-label="Tutup"
                    variant="ghost"
                    size="icon"
                    class="rounded-full h-9 w-9"
                    @click="isOpen = false"
                >
                    <X class="h-5 w-5" />
                </Button>
            </div>

            <div class="flex flex-col">
                <button
                    v-for="preset in mobilePresets"
                    :key="preset.value"
                    class="flex items-center justify-between w-full px-6 py-4 text-left border-b border-border/60 transition-colors hover:bg-muted/40 active:bg-muted"
                    @click="selectMobilePreset(preset.value)"
                >
                    <span
                        :class="
                            cn(
                                'text-base font-medium',
                                mobilePreset === preset.value
                                    ? 'text-primary'
                                    : 'text-foreground',
                            )
                        "
                    >
                        {{ preset.label }}
                    </span>
                    <div
                        :class="
                            cn(
                                'h-5 w-5 rounded-full border-2 flex items-center justify-center transition-colors',
                                mobilePreset === preset.value
                                    ? 'border-primary bg-primary'
                                    : 'border-muted-foreground/40',
                            )
                        "
                    >
                        <div
                            v-if="mobilePreset === preset.value"
                            class="h-2 w-2 rounded-full bg-primary-foreground"
                        />
                    </div>
                </button>
            </div>

            <div v-if="mobilePreset === 'custom'" class="px-4 pt-4 pb-2">
                <RangeCalendar
                    v-model="mobileRange"
                    initial-focus
                    :number-of-months="1"
                    class="flex justify-center"
                />
            </div>

            <div class="px-6 pt-3 pb-8">
                <Button
                    class="w-full h-12 text-base font-semibold rounded-xl"
                    :disabled="mobileDisabled"
                    @click="handleApply"
                >
                    Terapkan
                </Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
