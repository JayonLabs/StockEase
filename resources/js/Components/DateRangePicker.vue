<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import DateRangePickerDesktop from '@/Components/DateRangePickerDesktop.vue';
import DateRangePickerMobile from '@/Components/DateRangePickerMobile.vue';
import {
    DateFormatter,
    getLocalTimeZone,
    parseDate,
} from '@internationalized/date';

const props = defineProps({
    start: { type: String, default: null },
    end: { type: String, default: null },
    placeholder: { type: String, default: 'Pilih rentang tanggal' },
});

const emit = defineEmits(['update:start', 'update:end']);

const df = new DateFormatter('id-ID', { dateStyle: 'medium' });
const localTimeZone = getLocalTimeZone();

const windowWidth = ref(
    typeof window !== 'undefined' ? window.innerWidth : 1024,
);

function onResize() {
    windowWidth.value = window.innerWidth;
}

onMounted(() => window.addEventListener('resize', onResize));
onBeforeUnmount(() => window.removeEventListener('resize', onResize));

const isMobile = computed(() => windowWidth.value < 768);

const value = ref({
    start: props.start ? parseDate(props.start) : null,
    end: props.end ? parseDate(props.end) : null,
});

watch(
    () => [props.start, props.end],
    ([newStart, newEnd]) => {
        const currentStart = value.value.start
            ? value.value.start.toString()
            : null;
        const currentEnd = value.value.end ? value.value.end.toString() : null;

        if (newStart !== currentStart || newEnd !== currentEnd) {
            value.value = {
                start: newStart ? parseDate(newStart) : null,
                end: newEnd ? parseDate(newEnd) : null,
            };
        }
    },
);

const displayText = computed(() => {
    if (value.value.start && value.value.end) {
        return `${df.format(value.value.start.toDate(localTimeZone))} - ${df.format(value.value.end.toDate(localTimeZone))}`;
    }
    if (value.value.start) {
        return df.format(value.value.start.toDate(localTimeZone));
    }
    return props.placeholder;
});

const hasValue = computed(() => !!value.value.start);

function handleApply({ start, end }) {
    emit('update:start', start);
    emit('update:end', end);
    value.value = {
        start: start ? parseDate(start) : null,
        end: end ? parseDate(end) : null,
    };
}
</script>

<template>
    <DateRangePickerDesktop
        v-if="!isMobile"
        :start="props.start"
        :end="props.end"
        :placeholder="props.placeholder"
        :display-text="displayText"
        :has-value="hasValue"
        @apply="handleApply"
    />
    <DateRangePickerMobile
        v-else
        :start="props.start"
        :end="props.end"
        :placeholder="props.placeholder"
        :display-text="displayText"
        :has-value="hasValue"
        @apply="handleApply"
    />
</template>
