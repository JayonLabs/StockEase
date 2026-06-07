<script setup>
import { Button } from '@/Components/ui/button';
import { CalendarIcon, Check, Loader2, Plus, Search } from 'lucide-vue-next';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

import {
    DateFormatter,
    getLocalTimeZone,
    today,
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
    warehouses: {
        type: Array,
        default: () => [],
    },
});

const isDialogOpen = ref(false);
const searchSupplier = ref('');
const suppliers = ref([]);
const selectedSupplier = ref(null);

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
    form.supplier_id = newSelectedSupplier.value;
});

const df = new DateFormatter('id-ID', {
    dateStyle: 'long',
});

const formatDate = (date) => {
    return df.format(date.toDate(getLocalTimeZone()));
};

const date = ref(today(getLocalTimeZone()));

const form = useForm({
    supplier_id: '',
    warehouse_id: '',
    date: formatDate(date.value),
    product_items: [],
});

const user = usePage().props.auth.user.name;

const submit = () => {
    form.post(route('purchase.store'), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toast.success('Pembelian berhasil ditambahkan', {
                description: `Pembelian berhasil ditambahkan oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Pembelian gagal ditambahkan');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button variant="outline" class="dark:border-white border-zinc-600">
                <Plus />
                Tambah Pembelian Produk
            </Button>
        </DialogTrigger>
        <DialogContent
            class="max-w-[95vw] lg:max-w-6xl max-h-[90vh] overflow-y-auto"
        >
            <DialogHeader>
                <DialogTitle>Form tambah pembelian produk</DialogTitle>
                <DialogDescription>
                    Silahkan isi form dibawah ini untuk menambahkan pembelian
                    produk.
                    <br />
                    Jika harga produk dan harga jual produk berubah makan akan
                    mengubah harga jual produk dan harga beli produk.
                </DialogDescription>
            </DialogHeader>
            <form id="form" @submit.prevent="submit">
                <div class="grid gap-2 mb-4">
                    <Label for="warehouse">Gudang Tujuan</Label>
                    <Select v-model="form.warehouse_id">
                        <SelectTrigger id="warehouse">
                            <SelectValue placeholder="Pilih gudang..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="warehouse in props.warehouses"
                                :key="warehouse.id"
                                :value="String(warehouse.id)"
                            >
                                {{ warehouse.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.warehouse_id" />
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
                    {{ form.processing ? 'Loading...' : 'Simpan' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
