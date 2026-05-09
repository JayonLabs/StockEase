<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Badge } from '@/Components/ui/badge';
import { ArrowLeftToLine, Lock, Wallet } from 'lucide-vue-next';
import { formatPrice, formatDateTime } from '@/lib/utils';
import { computed, ref } from 'vue';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

const props = defineProps({
    shift: {
        type: Object,
        required: true,
    },
});

const closeDialog = ref(false);

const form = useForm({
    actual_cash: '',
    notes: '',
});

const formattedActualCash = computed({
    get: () => {
        if (!form.actual_cash) return '';
        return new Intl.NumberFormat('id-ID').format(Number(form.actual_cash));
    },
    set: (value) => {
        const numericValue = value.replace(/[^0-9]/g, '');
        form.actual_cash = numericValue || '';
    },
});

function submitClose() {
    form.post(route('shift.close', props.shift.id), {
        onSuccess: () => {
            closeDialog.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Detail Shift #{{ shift.id }}</title>
        </Head>
        <template #breadcrumb>
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <Link :href="route('dashboard')">
                            <BreadcrumbLink> Dashboard </BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <Link :href="route('shift.index')">
                            <BreadcrumbLink> Manajemen Shift </BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>
                            Detail Shift #{{ shift.id }}
                        </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <h4 class="font-semibold">
                            Detail Shift #{{ shift.id }}
                        </h4>
                        <Badge
                            :variant="
                                shift.status === 'open'
                                    ? 'default'
                                    : 'secondary'
                            "
                        >
                            {{
                                shift.status === 'open' ? 'Terbuka' : 'Tertutup'
                            }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-2">
                        <Dialog
                            v-if="shift.status === 'open'"
                            v-model:open="closeDialog"
                        >
                            <DialogTrigger>
                                <Button variant="destructive">
                                    <Lock class="w-4 h-4 mr-1" />
                                    Tutup Shift
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Tutup Shift</DialogTitle>
                                    <DialogDescription>
                                        Hitung dan masukkan jumlah uang fisik
                                        yang ada di laci kasir.
                                    </DialogDescription>
                                </DialogHeader>
                                <form
                                    class="space-y-4"
                                    @submit.prevent="submitClose"
                                >
                                    <div class="space-y-2">
                                        <Label for="actual_cash"
                                            >Uang Fisik Dihitung (Rp)</Label
                                        >
                                        <div class="relative">
                                            <span
                                                class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"
                                            >
                                                Rp
                                            </span>
                                            <Input
                                                id="actual_cash"
                                                v-model="formattedActualCash"
                                                type="text"
                                                inputmode="numeric"
                                                placeholder="500.000"
                                                class="pl-10"
                                                :disabled="form.processing"
                                                required
                                            />
                                        </div>
                                        <p
                                            v-if="form.errors.actual_cash"
                                            class="text-sm text-red-500"
                                        >
                                            {{ form.errors.actual_cash }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="notes">Catatan</Label>
                                        <Textarea
                                            id="notes"
                                            v-model="form.notes"
                                            placeholder="Catatan tambahan (opsional)"
                                            :disabled="form.processing"
                                        />
                                        <p
                                            v-if="form.errors.notes"
                                            class="text-sm text-red-500"
                                        >
                                            {{ form.errors.notes }}
                                        </p>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            :disabled="form.processing"
                                        >
                                            <Wallet class="w-4 h-4 mr-1" />
                                            {{
                                                form.processing
                                                    ? 'Menutup...'
                                                    : 'Konfirmasi Tutup Shift'
                                            }}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                        <Link :href="route('shift.index')">
                            <Button variant="outline">
                                <ArrowLeftToLine class="w-4 h-4 mr-1" />
                                Kembali
                            </Button>
                        </Link>
                    </div>
                </div>
                <Separator class="my-4" />

                <!-- Shift Info -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">Kasir</p>
                        <p class="text-sm font-semibold mt-0.5">
                            {{ shift.user?.name ?? '-' }}
                        </p>
                    </div>
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">Dibuka</p>
                        <p class="text-sm font-semibold mt-0.5">
                            {{ formatDateTime(shift.opened_at) }}
                        </p>
                    </div>
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">Ditutup</p>
                        <p class="text-sm font-semibold mt-0.5">
                            {{
                                shift.closed_at
                                    ? formatDateTime(shift.closed_at)
                                    : '-'
                            }}
                        </p>
                    </div>
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">Modal Awal</p>
                        <p class="text-sm font-semibold mt-0.5">
                            {{ formatPrice(shift.starting_cash) }}
                        </p>
                    </div>
                </div>

                <!-- Cash Summary (only for closed shifts) -->
                <div
                    v-if="shift.status === 'closed'"
                    class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4"
                >
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">
                            Kas Diharapkan
                        </p>
                        <p class="text-base font-semibold mt-0.5">
                            {{ formatPrice(shift.expected_cash) }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Modal awal + total penjualan tunai
                        </p>
                    </div>
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">Kas Aktual</p>
                        <p class="text-base font-semibold mt-0.5">
                            {{ formatPrice(shift.actual_cash) }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Uang fisik yang dihitung
                        </p>
                    </div>
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">Selisih Kas</p>
                        <p
                            class="text-base font-semibold mt-0.5"
                            :class="{
                                'text-green-600': shift.cash_difference >= 0,
                                'text-red-600': shift.cash_difference < 0,
                            }"
                        >
                            {{ formatPrice(shift.cash_difference) }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{
                                shift.cash_difference > 0
                                    ? 'Kelebihan'
                                    : shift.cash_difference < 0
                                      ? 'Kekurangan'
                                      : 'Sesuai'
                            }}
                        </p>
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="shift.notes" class="mb-4">
                    <h5 class="text-xs font-medium text-muted-foreground mb-1">
                        Catatan
                    </h5>
                    <p class="text-sm">
                        {{ shift.notes }}
                    </p>
                </div>

                <Separator class="my-4" />

                <!-- Sales List -->
                <div class="mt-4">
                    <h4 class="font-semibold mb-4">
                        Daftar Penjualan dalam Shift
                    </h4>
                    <div
                        v-if="shift.sales && shift.sales.length > 0"
                        class="rounded-lg border"
                    >
                        <table class="w-full">
                            <thead>
                                <tr
                                    class="border-b bg-muted/50 text-sm text-muted-foreground"
                                >
                                    <th class="text-left p-3">#ID</th>
                                    <th class="text-left p-3">Pelanggan</th>
                                    <th class="text-right p-3">Total</th>
                                    <th class="text-center p-3">Metode</th>
                                    <th class="text-center p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="sale in shift.sales"
                                    :key="sale.id"
                                    class="border-b text-sm hover:bg-muted/30"
                                >
                                    <td class="p-3">
                                        <Link
                                            :href="route('sale.show', sale.id)"
                                            class="text-blue-600 hover:underline"
                                        >
                                            #{{ sale.id }}
                                        </Link>
                                    </td>
                                    <td class="p-3">
                                        {{ sale.customer_name ?? '-' }}
                                    </td>
                                    <td class="p-3 text-right">
                                        {{ formatPrice(sale.total) }}
                                    </td>
                                    <td class="p-3 text-center capitalize">
                                        {{ sale.payment_method }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <Badge
                                            :variant="
                                                sale.status === 'completed'
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                        >
                                            {{
                                                sale.status === 'completed'
                                                    ? 'Selesai'
                                                    : sale.status
                                            }}
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-else
                        class="text-center py-8 text-muted-foreground text-sm"
                    >
                        Belum ada penjualan dalam shift ini.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
