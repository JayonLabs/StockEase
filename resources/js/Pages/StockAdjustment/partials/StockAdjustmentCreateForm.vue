<script setup>
import { Button } from '@/Components/ui/button';
import { CalendarIcon, Check, Loader2, Plus, Search } from 'lucide-vue-next';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { toast } from 'vue-sonner';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { cn } from '@/lib/utils';
import { watchDebounced } from '@vueuse/core';
import axios from 'axios';
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
const searchProduct = ref('');
const products = ref([]);
const selectedProduct = ref(null);

const df = new DateFormatter('id-ID', {
    dateStyle: 'long',
});

const date = ref(today(getLocalTimeZone()));

const form = useForm({
    warehouse_id: '',
    product_id: '',
    new_stock: 0,
    reason: '',
    date: date.value.toString(),
});

watchDebounced(
    searchProduct,
    (newSearch) => {
        if (newSearch.length < 2) return;
        axios
            .get(route('stock-adjustment.search-product'), {
                params: {
                    search: newSearch,
                    warehouse_id: form.warehouse_id || undefined,
                },
            })
            .then((response) => {
                products.value = response.data;
            })
            .catch(() => {
                products.value = [];
            });
    },
    { debounce: 300 },
);

watch(selectedProduct, (val) => {
    if (val) {
        form.product_id = val.value;
        form.new_stock = form.warehouse_id
            ? (val.warehouse_stock ?? 0)
            : val.stock;
    }
});

watch(date, (val) => {
    form.date = val.toString();
});

const user = usePage().props.auth.user.name;

const submit = () => {
    form.post(route('stock-adjustment.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            selectedProduct.value = null;
            searchProduct.value = '';
            products.value = [];
            toast.success('Penyesuaian stok berhasil disimpan', {
                description: `Berhasil diproses oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Gagal menyimpan penyesuaian stok');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button variant="outline" class="dark:border-white border-zinc-600">
                <Plus class="w-4 h-4 mr-2" />
                Tambah Penyesuaian
            </Button>
        </DialogTrigger>
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Form Stock Opname</DialogTitle>
                <DialogDescription>
                    Silahkan pilih produk dan masukkan jumlah stok fisik terbaru
                    untuk melakukan penyesuaian.
                </DialogDescription>
            </DialogHeader>
            <form
                id="stock-adjustment-form"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="warehouse">Gudang</Label>
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
                <div class="grid gap-2">
                    <Label for="product"> Produk </Label>
                    <Combobox v-model="selectedProduct" by="label">
                        <ComboboxAnchor class="w-full">
                            <div class="relative w-full items-center">
                                <ComboboxInput
                                    v-model="searchProduct"
                                    class="pl-9"
                                    :display-value="(val) => val?.label ?? ''"
                                    placeholder="Cari Produk (Nama/SKU/Barcode)..."
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
                                Produk tidak ditemukan.
                            </ComboboxEmpty>
                            <ComboboxGroup>
                                <ComboboxItem
                                    v-for="product in products"
                                    :key="product.value"
                                    :value="product"
                                    class="cursor-pointer"
                                >
                                    <div class="flex flex-col">
                                        <span>{{ product.label }}</span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            Stok
                                            {{
                                                form.warehouse_id
                                                    ? 'gudang'
                                                    : 'global'
                                            }}:
                                            {{
                                                form.warehouse_id
                                                    ? (product.warehouse_stock ??
                                                      0)
                                                    : product.stock
                                            }}
                                        </span>
                                    </div>
                                    <ComboboxItemIndicator>
                                        <Check :class="cn('ml-auto h-4 w-4')" />
                                    </ComboboxItemIndicator>
                                </ComboboxItem>
                            </ComboboxGroup>
                        </ComboboxList>
                    </Combobox>
                    <InputError :message="form.errors.product_id" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="new_stock"> Stok Fisik Baru </Label>
                        <Input
                            id="new_stock"
                            v-model="form.new_stock"
                            type="number"
                            min="0"
                        />
                        <InputError :message="form.errors.new_stock" />
                    </div>

                    <div class="grid gap-2">
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
                            <PopoverContent class="w-auto p-0">
                                <Calendar v-model="date" initial-focus />
                            </PopoverContent>
                        </Popover>
                        <InputError :message="form.errors.date" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="reason"> Alasan / Keterangan </Label>
                    <Textarea
                        id="reason"
                        v-model="form.reason"
                        placeholder="Contoh: Barang rusak, selisih hitung, dll."
                    />
                    <InputError :message="form.errors.reason" />
                </div>
            </form>
            <DialogFooter class="flex justify-between">
                <DialogClose as-child>
                    <Button type="button" variant="secondary"> Batal </Button>
                </DialogClose>

                <Button
                    type="submit"
                    form="stock-adjustment-form"
                    :disabled="form.processing"
                >
                    <Loader2
                        v-if="form.processing"
                        class="w-4 h-4 mr-2 animate-spin"
                    />
                    {{
                        form.processing ? 'Menyimpan...' : 'Simpan Penyesuaian'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
