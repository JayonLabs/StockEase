<script setup>
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { toast } from 'vue-sonner';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { cn } from '@/lib/utils';
import { watchDebounced } from '@vueuse/core';
import axios from 'axios';
import ProductTable from './ProductTable.vue';
import { Calendar } from '@/Components/ui/calendar';
import InputError from '@/Components/InputError.vue';

import { CalendarIcon, Check, Eye, Loader2, Search } from 'lucide-vue-next';

import {
    DateFormatter,
    getLocalTimeZone,
    parseDate,
} from '@internationalized/date';

import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';

import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
} from '@/Components/ui/combobox';

import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';

const props = defineProps({
    purchase: {
        type: Object,
        required: true,
    },
});

const isDialogOpen = ref(false);
const searchSupplier = ref('');
const suppliers = ref([]);

const selectedSupplier = ref({
    value: props.purchase.supplier.id,
    label: props.purchase.supplier.name,
});

watchDebounced(
    searchSupplier,
    (newSearchSupplier) => {
        axios
            .get(route('purchase.search-supplier'), {
                params: {
                    search: newSearchSupplier,
                },
            })
            .then((response) => {
                suppliers.value = response.data.data;
            })
            .catch((error) => {
                suppliers.value = [];
            });
    },
    { debounce: 200 },
);

watch(selectedSupplier, (newSelectedSupplier) => {
    form.supplier_id = newSelectedSupplier?.value ?? '';
});

const df = new DateFormatter('id-ID', {
    dateStyle: 'long',
});

const formatDate = (date) => {
    return df.format(date.toDate(getLocalTimeZone()));
};

const date = ref(parseDate(props.purchase.date));

watch(date, (newDate) => {
    form.date = formatDate(newDate);
});

watch(
    () => props.row,
    () => {
        ((form.supplier_id = selectedSupplier.value?.value ?? ''),
            (form.date = formatDate(date.value)),
            (form.product_items = props.purchase.purchase_items.map((item) => ({
                product_id: item.product_id,
                qty: item.qty,
                price: parseFloat(item.price),
                expiry_date: item.expiry_date,
                selling_price: parseFloat(item.product.selling_price),
                unit: item.product.unit,
                product: item.product,
            }))));
    },
);

const form = useForm({
    supplier_id: selectedSupplier.value?.value ?? '',
    date: formatDate(date.value),
    product_items: props.purchase.purchase_items.map((item) => ({
        product_id: item.product_id,
        qty: item.qty,
        price: parseFloat(item.price),
        expiry_date: item.expiry_date,
        selling_price: parseFloat(item.product.selling_price),
        unit: item.product.unit,
        product: item.product,
    })),
});

const user = usePage().props.auth.user.name;

const submit = () => {
    form.put(route('purchase.update', props.purchase.id), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Pembelian berhasil diperbarui', {
                description: `Pembelian berhasil diperbarui oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Pembelian gagal diperbarui');
            console.log(form.errors);
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button variant="ghost" size="icon" class="group">
                <Eye
                    class="w-4 h-4 text-green-500 dark:group-hover:text-white"
                />
            </Button>
        </DialogTrigger>
        <DialogContent
            class="max-w-[95vw] lg:max-w-6xl max-h-[90vh] overflow-y-auto"
        >
            <DialogHeader>
                <DialogTitle>Form edit pembelian produk</DialogTitle>
                <DialogDescription>
                    Silahkan isi form dibawah ini untuk memperbarui pembelian
                    produk.
                    <br />
                    Jika harga produk dan harga jual produk berubah makan akan
                    mengubah harga jual produk dan harga beli produk.
                </DialogDescription>
            </DialogHeader>
            <form id="form" @submit.prevent="submit">
                <div class="grid gap-2 mb-4">
                    <Label>Gudang</Label>
                    <p
                        class="text-sm text-muted-foreground border rounded-md px-3 py-2 bg-muted"
                    >
                        {{ props.purchase.warehouse?.name ?? '—' }}
                        <span class="ml-1 text-xs">(tidak dapat diubah)</span>
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="supplier"> Supplier </Label>
                        <Combobox
                            v-model="selectedSupplier"
                            by="label"
                            html-id="supplier"
                        >
                            <ComboboxAnchor class="w-full">
                                <div
                                    class="relative w-full max-w-sm items-center"
                                >
                                    <ComboboxInput
                                        v-model="searchSupplier"
                                        class="pl-9"
                                        :display-value="
                                            (val) => val?.label ?? ''
                                        "
                                        placeholder="Cari Supplier..."
                                    />
                                    <span
                                        class="absolute inset-s-0 inset-y-0 flex items-center justify-center px-3"
                                    >
                                        <Search
                                            class="size-4 text-muted-foreground"
                                        />
                                    </span>
                                </div>
                            </ComboboxAnchor>

                            <ComboboxList class="w-full">
                                <ComboboxEmpty>
                                    Tidak ada supplier ditemukan.
                                </ComboboxEmpty>

                                <ComboboxGroup>
                                    <ComboboxItem
                                        v-for="supplier in suppliers"
                                        :key="supplier.value"
                                        :value="supplier"
                                        class="cursor-pointer"
                                    >
                                        {{ supplier.label }}

                                        <ComboboxItemIndicator>
                                            <Check
                                                :class="cn('ml-auto h-4 w-4')"
                                            />
                                        </ComboboxItemIndicator>
                                    </ComboboxItem>
                                </ComboboxGroup>
                            </ComboboxList>
                        </Combobox>
                        <InputError :message="form.errors.supplier_id" />
                    </div>
                    <div class="grid flex-1 gap-2">
                        <Label for="date"> Tanggal </Label>
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button
                                    variant="outline"
                                    :class="
                                        cn(
                                            'w-full justify-start text-left font-normal',
                                            !date && 'text-muted-foreground',
                                        )
                                    "
                                >
                                    <CalendarIcon class="mr-2 h-4 w-4" />
                                    {{
                                        date
                                            ? df.format(
                                                  date.toDate(
                                                      getLocalTimeZone(),
                                                  ),
                                              )
                                            : 'Pilih tanggal'
                                    }}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-auto p-0" align="start">
                                <Calendar v-model="date" initial-focus />
                            </PopoverContent>
                        </Popover>
                        <InputError :message="form.errors.date" />
                    </div>
                </div>
                <div class="mt-6">
                    <Label> Produk </Label>
                    <InputError :message="form.errors.product_items" />
                    <ProductTable v-model="form.product_items" :form="form" />
                </div>
            </form>
            <DialogFooter class="flex justify-between">
                <DialogClose as-child>
                    <Button type="button" variant="secondary"> Batal </Button>
                </DialogClose>

                <Button
                    type="submit"
                    form="form"
                    :class="{ 'opacity-25 ': form.processing }"
                    :disabled="form.processing"
                    class="disabled:cursor-not-allowed"
                >
                    <Loader2
                        v-if="form.processing"
                        class="w-4 h-4 animate-spin"
                    />
                    {{ form.processing ? 'Loading...' : 'Update' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
