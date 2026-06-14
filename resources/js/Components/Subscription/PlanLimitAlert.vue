<script setup>
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Link } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';

defineProps({
    resource: { type: String, required: true },
    planName: { type: String, default: '' },
});

const labels = {
    product: 'produk',
    user: 'pengguna',
    warehouse: 'gudang',
};
</script>

<template>
    <Alert variant="destructive" class="mb-4">
        <AlertTriangle class="w-4 h-4" />
        <AlertTitle>Limit Plan Tercapai</AlertTitle>
        <AlertDescription>
            Batas maksimal {{ labels[resource] ?? resource }} untuk plan
            {{ planName }} telah tercapai.
            <Link
                :href="route('subscription.index')"
                class="underline font-medium"
            >
                Upgrade plan Anda </Link
            >.
        </AlertDescription>
    </Alert>
</template>
