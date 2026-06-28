<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import UpgradePromptDialog from '@/Components/Subscription/UpgradePromptDialog.vue';

const showDialog = ref(false);
const hasBeenShown = ref(false);

const resource = computed(() => {
    const error = usePage().props.flash?.error ?? '';
    if (error.includes('user')) return 'user';
    if (error.includes('produk')) return 'product';
    if (error.includes('gudang')) return 'warehouse';
    return 'user';
});

const planName = computed(
    () => usePage().props.auth.subscription?.plan?.name ?? '',
);

watch(
    () => usePage().props.flash?.error,
    (error) => {
        if (error?.includes('Batas maksimal') && !hasBeenShown.value) {
            showDialog.value = true;
            hasBeenShown.value = true;
        }
        if (!error) {
            hasBeenShown.value = false;
        }
    },
    { immediate: true },
);

const close = () => {
    showDialog.value = false;
};
</script>

<template>
    <UpgradePromptDialog
        :open="showDialog"
        :resource="resource"
        :plan-name="planName"
        @close="close"
    />
</template>
