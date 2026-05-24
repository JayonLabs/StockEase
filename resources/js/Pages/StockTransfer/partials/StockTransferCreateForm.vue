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
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectItemText,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

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
        required: true,
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
    from_warehouse_id: '',
    to_warehouse_id: '',
    product_id: '',
    qty: 0,
    note: '',
    date: date.value.toString(),
});

watchDebounced(
    searchProduct,
    (newSearch) => {
        if (newSearch.length < 2) return;
        const params = { search: newSearch };
        if (form.from_warehouse_id) {
            params.warehouse_id = form.from_warehouse_id;
        }

        axios
            .get(route('stock-transfer.search-product'), {
                params,
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
    }
});

watch(date, (val) => {
    form.date = val.toString();
});

const resetForm = () => {
    form.reset();
    selectedProduct.value = null;
    searchProduct.value = '';
    products.value = [];
};

const user = usePage().props.auth.user.name;

const submit = () => {
    form.post(route('stock-transfer.store'), {
        preserveScroll: true,
        showProgress: false,
        onSuccess: () => {
            resetForm();
            toast.success('Pemindahan stok berhasil', {
                description: `Berhasil diproses oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Gagal menyimpan pemindahan stok');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button variant="outline" class="dark:border-white border-zinc-600">
                <Plus class="w-4 h-4 mr-2" />
                Pindah Stok
            </Button>
        </DialogTrigger>
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Form Pemindahan Stok</DialogTitle>
                <DialogDescription>
                    Silahkan pilih gudang asal, gudang tujuan, dan produk untuk
                    melakukan pemindahan stok.
                </DialogDescription>
            </DialogHeader>
            <form
                id="stock-transfer-form"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="from_warehouse"> Gudang Asal </Label>
                        <Select v-model="form.from_warehouse_id">
                            <SelectTrigger id="from_warehouse">
                                <SelectValue placeholder="Pilih gudang asal" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="warehouse in warehouses"
                                        :key="warehouse.id"
                                        :value="String(warehouse.id)"
                                    >
                                        <SelectItemText>
                                            {{ warehouse.name }}
                                        </SelectItemText>
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.from_warehouse_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="to_warehouse"> Gudang Tujuan </Label>
                        <Select v-model="form.to_warehouse_id">
                            <SelectTrigger id="to_warehouse">
                                <SelectValue
                                    placeholder="Pilih gudang tujuan"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="warehouse in warehouses"
                                        :key="warehouse.id"
                                        :value="String(warehouse.id)"
                                    >
                                        <SelectItemText>
                                            {{ warehouse.name }}
                                        </SelectItemText>
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.to_warehouse_id" />
                    </div>
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
                                            Stok gudang:
                                            {{
                                                form.from_warehouse_id
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
                        <Label for="qty"> Jumlah Stok </Label>
                        <Input
                            id="qty"
                            v-model="form.qty"
                            type="number"
                            min="1"
                            placeholder="Masukkan jumlah"
                        />
                        <InputError :message="form.errors.qty" />
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
                    <Label for="note"> Catatan </Label>
                    <Textarea
                        id="note"
                        v-model="form.note"
                        placeholder="Alasan pemindahan (opsional)"
                    />
                    <InputError :message="form.errors.note" />
                </div>
            </form>
            <DialogFooter class="flex justify-between">
                <DialogClose as-child>
                    <Button type="button" variant="secondary"> Batal </Button>
                </DialogClose>

                <Button
                    type="submit"
                    form="stock-transfer-form"
                    :disabled="form.processing"
                >
                    <Loader2
                        v-if="form.processing"
                        class="w-4 h-4 mr-2 animate-spin"
                    />
                    {{ form.processing ? 'Menyimpan...' : 'Pindahkan Stok' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
