<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowUp, CreditCard } from 'lucide-vue-next';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

defineProps({
    resource: { type: String, default: 'user' },
    featureName: { type: String, default: '' },
    planName: { type: String, default: '' },
    open: { type: Boolean, default: false },
    lockType: { type: String, default: 'feature' }, // 'feature' | 'subscription'
});

const emit = defineEmits(['close']);

const labels = {
    product: 'produk',
    user: 'pengguna',
    warehouse: 'gudang',
};
</script>

<template>
    <AlertDialog :open="open" @update:open="(v) => !v && emit('close')">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    <template v-if="lockType === 'subscription'">
                        Langganan Tidak Aktif
                    </template>
                    <template v-else-if="featureName">
                        Fitur Tidak Tersedia
                    </template>
                    <template v-else> Limit Plan Tercapai </template>
                </AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="lockType === 'subscription'">
                        Langganan Anda tidak aktif. Pilih plan untuk melanjutkan
                        menggunakan aplikasi.
                    </template>
                    <template v-else-if="featureName">
                        Fitur
                        <span class="font-medium text-foreground">
                            {{ featureName }}
                        </span>
                        belum tersedia di plan
                        <span class="font-medium text-foreground">
                            {{ planName }} </span
                        >.
                        <span class="block mt-2">
                            Upgrade ke plan yang lebih tinggi untuk mengakses
                            fitur ini.
                        </span>
                    </template>
                    <template v-else>
                        Batas maksimal
                        <span class="font-medium text-foreground">
                            {{ labels[resource] ?? resource }}
                        </span>
                        untuk plan
                        <span class="font-medium text-foreground">
                            {{ planName }}
                        </span>
                        telah tercapai.
                        <span class="block mt-2">
                            Tingkatkan ke plan yang lebih tinggi untuk menambah
                            kapasitas.
                        </span>
                    </template>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="emit('close')">
                    Kembali
                </AlertDialogCancel>
                <AlertDialogAction as-child>
                    <Link
                        :href="route('subscription.index')"
                        class="inline-flex items-center gap-2"
                    >
                        <template v-if="lockType === 'subscription'">
                            <CreditCard class="size-4" />
                            Pilih Plan
                        </template>
                        <template v-else>
                            <ArrowUp class="size-4" />
                            Upgrade Plan
                        </template>
                    </Link>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
