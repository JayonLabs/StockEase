<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { ArrowLeftToLine, Loader2, RotateCcw } from 'lucide-vue-next';
import { Separator } from '@/Components/ui/separator';
import { formatPrice, formatDateTime } from '@/lib/utils';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import InputError from '@/Components/InputError.vue';
import {
    NumberField,
    NumberFieldContent,
    NumberFieldDecrement,
    NumberFieldIncrement,
    NumberFieldInput,
} from '@/Components/ui/number-field';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { computed } from 'vue';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

const props = defineProps({
    sale: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    return_type: 'refund',
    reason: '',
    notes: '',
    items: [],
});

props.sale.sale_items.forEach((item) => {
    form.items.push({
        sale_item_id: item.id,
        qty: 0,
    });
});

const selectedItems = computed(() => form.items.filter((item) => item.qty > 0));

const refundTotal = computed(() => {
    if (form.return_type !== 'refund') return 0;

    return form.items.reduce((total, item) => {
        const saleItem = props.sale.sale_items.find(
            (si) => si.id === item.sale_item_id,
        );
        if (saleItem && item.qty > 0) {
            return (
                total +
                item.qty * Number(saleItem.price) -
                Number(saleItem.discount_amount || 0) *
                    (item.qty / saleItem.qty)
            );
        }
        return total;
    }, 0);
});

const canSubmit = computed(() => selectedItems.value.length > 0);

const submit = () => {
    form.post(route('sale-return.store', props.sale.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};

const getItemSubtotal = (item) => {
    const saleItem = props.sale.sale_items.find(
        (si) => si.id === item.sale_item_id,
    );
    if (!saleItem || item.qty <= 0) return 0;

    const discountPerUnit =
        Number(saleItem.discount_amount || 0) / saleItem.qty;
    return item.qty * (Number(saleItem.price) - discountPerUnit);
};
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Form Retur Penjualan</title>
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
                        <Link :href="route('sale-return.index')">
                            <BreadcrumbLink> Retur Penjualan </BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>
                            Form Retur #{{ props.sale.id }}
                        </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <h4 class="font-semibold">Form Retur Penjualan</h4>
                    <Link :href="route('sale-return.index')">
                        <Button
                            variant="outline"
                            class="dark:border-white border-zinc-600"
                        >
                            <ArrowLeftToLine />
                            Kembali
                        </Button>
                    </Link>
                </div>
                <Separator class="my-4" />

                <form @submit.prevent="submit">
                    <!-- Sale Info -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3 w-full mb-6"
                    >
                        <div
                            class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800"
                        >
                            <h3
                                class="font-medium text-gray-800 text-theme-xl dark:text-white/90"
                            >
                                Detail Transaksi
                            </h3>
                            <h4
                                class="text-base font-medium text-gray-700 dark:text-gray-400"
                            >
                                ID : #{{ props.sale.id }}
                            </h4>
                        </div>

                        <div class="p-5 xl:p-8">
                            <div
                                class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6"
                            >
                                <div>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        Tanggal
                                    </p>
                                    <p
                                        class="text-base font-medium text-gray-800 dark:text-white/90"
                                    >
                                        {{ formatDateTime(props.sale.date) }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        Pelanggan
                                    </p>
                                    <p
                                        class="text-base font-medium text-gray-800 dark:text-white/90"
                                    >
                                        {{ props.sale.customer_name ?? '-' }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        Kasir
                                    </p>
                                    <p
                                        class="text-base font-medium text-gray-800 dark:text-white/90"
                                    >
                                        {{ props.sale.user?.name ?? '-' }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        Metode Pembayaran
                                    </p>
                                    <p
                                        class="text-base font-medium text-gray-800 dark:text-white/90"
                                    >
                                        {{ props.sale.payment_method }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        Total Transaksi
                                    </p>
                                    <p
                                        class="text-base font-medium text-gray-800 dark:text-white/90"
                                    >
                                        {{ formatPrice(props.sale.total) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Items Table -->
                            <div class="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="hover:bg-transparent">
                                            <TableHead>Produk</TableHead>
                                            <TableHead class="text-center">
                                                Harga
                                            </TableHead>
                                            <TableHead class="text-center">
                                                Qty Terjual
                                            </TableHead>
                                            <TableHead class="text-center">
                                                Subtotal
                                            </TableHead>
                                            <TableHead class="text-center">
                                                Qty Retur
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="(saleItem, index) in props
                                                .sale.sale_items"
                                            :key="saleItem.id"
                                        >
                                            <TableCell>
                                                <p class="font-medium">
                                                    {{ saleItem.product?.name }}
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    SKU:
                                                    {{ saleItem.product?.sku }}
                                                </p>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                {{
                                                    formatPrice(saleItem.price)
                                                }}
                                            </TableCell>
                                            <TableCell class="text-center">
                                                {{ saleItem.qty }}
                                            </TableCell>
                                            <TableCell class="text-center">
                                                {{
                                                    formatPrice(
                                                        saleItem.qty *
                                                            Number(
                                                                saleItem.price,
                                                            ) -
                                                            Number(
                                                                saleItem.discount_amount ||
                                                                    0,
                                                            ),
                                                    )
                                                }}
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <NumberField
                                                    :model-value="
                                                        form.items[index].qty
                                                    "
                                                    :min="0"
                                                    :max="saleItem.qty"
                                                    :step="1"
                                                    class="justify-center"
                                                    @update:model-value="
                                                        form.items[index].qty =
                                                            $event
                                                    "
                                                >
                                                    <NumberFieldContent
                                                        class="w-24 mx-auto"
                                                    >
                                                        <NumberFieldDecrement
                                                            class="p-1.5 cursor-pointer"
                                                        />
                                                        <NumberFieldInput
                                                            class="h-7 text-sm"
                                                        />
                                                        <NumberFieldIncrement
                                                            class="p-1.5 cursor-pointer"
                                                        />
                                                    </NumberFieldContent>
                                                </NumberField>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </div>

                    <!-- Return Options -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3 w-full mb-6"
                    >
                        <div
                            class="flex items-center px-6 py-4 border-b border-gray-200 dark:border-gray-800"
                        >
                            <h3
                                class="font-medium text-gray-800 text-theme-xl dark:text-white/90"
                            >
                                Opsi Retur
                            </h3>
                        </div>

                        <div class="p-5 xl:p-8 space-y-6">
                            <!-- Return Type -->
                            <div class="grid gap-2">
                                <Label for="return_type">Tipe Retur</Label>
                                <Select
                                    v-model="form.return_type"
                                    name="return_type"
                                >
                                    <SelectTrigger class="w-full">
                                        <SelectValue
                                            placeholder="Pilih tipe retur"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="refund">
                                            Pengembalian Uang (Refund)
                                        </SelectItem>
                                        <SelectItem value="exchange">
                                            Tukar Barang (Exchange)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.return_type"
                                />
                            </div>

                            <!-- Reason -->
                            <div class="grid gap-2">
                                <Label for="reason">Alasan Retur</Label>
                                <Input
                                    id="reason"
                                    v-model="form.reason"
                                    placeholder="Masukkan alasan retur (opsional)"
                                    type="text"
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.reason" />
                            </div>

                            <!-- Notes -->
                            <div class="grid gap-2">
                                <Label for="notes">Catatan</Label>
                                <Textarea
                                    id="notes"
                                    v-model="form.notes"
                                    placeholder="Catatan tambahan (opsional)"
                                />
                                <InputError :message="form.errors.notes" />
                            </div>

                            <!-- Refund Total -->
                            <div
                                v-if="form.return_type === 'refund'"
                                class="p-4 rounded-lg bg-orange-50 dark:bg-orange-950/30 border border-orange-200 dark:border-orange-800"
                            >
                                <p
                                    class="text-sm text-orange-700 dark:text-orange-300"
                                >
                                    Total Pengembalian Dana
                                </p>
                                <p
                                    class="text-2xl font-bold text-orange-800 dark:text-orange-200"
                                >
                                    {{ formatPrice(refundTotal) }}
                                </p>
                            </div>

                            <!-- Selected items summary -->
                            <div
                                v-if="selectedItems.length > 0"
                                class="space-y-2"
                            >
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Produk yang diretur:
                                </p>
                                <ul
                                    class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1"
                                >
                                    <li
                                        v-for="item in selectedItems"
                                        :key="item.sale_item_id"
                                    >
                                        {{
                                            props.sale.sale_items.find(
                                                (si) =>
                                                    si.id === item.sale_item_id,
                                            )?.product?.name
                                        }}
                                        - {{ item.qty }} pcs ({{
                                            formatPrice(getItemSubtotal(item))
                                        }})
                                    </li>
                                </ul>
                            </div>

                            <InputError :message="form.errors.error" />
                            <InputError :message="form.errors.items" />
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex justify-end gap-3">
                        <Link :href="route('sale-return.index')">
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            :disabled="!canSubmit || form.processing"
                            class="disabled:cursor-not-allowed"
                        >
                            <Loader2
                                v-if="form.processing"
                                class="w-4 h-4 mr-2 animate-spin"
                            />
                            <RotateCcw v-else class="w-4 h-4 mr-2" />
                            {{
                                form.processing
                                    ? 'Memproses...'
                                    : 'Proses Retur'
                            }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
