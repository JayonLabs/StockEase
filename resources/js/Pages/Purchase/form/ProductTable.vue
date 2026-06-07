<script setup>
import { Input } from '@/Components/ui/input';
import { cn, formatPrice, formatNumber } from '@/lib/utils';
import { Button } from '@/Components/ui/button';
import { Check, Plus, Search, Trash } from 'lucide-vue-next';
import { ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import axios from 'axios';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
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
import DatePicker from '@/Components/DatePicker.vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    form: {
        type: Object,
        default: () => ({}),
    },
});

const formatDateInput = (date) => {
    if (!date) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const parseExpiryDate = (dateStr) => {
    if (!dateStr) return null;
    const datePart = dateStr.split('T')[0];
    const [year, month, day] = datePart.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const searchProduct = ref({});
const productOptions = ref({});

watchDebounced(
    searchProduct,
    (newSearchProduct) => {
        for (const index in newSearchProduct) {
            const search = newSearchProduct[index];
            axios
                .get(route('purchase.search-product', { search }))
                .then((response) => {
                    productOptions.value[index] = response.data.data;
                });
        }
    },
    { debounce: 300, deep: true },
);

props.modelValue.forEach((item, index) => {
    if (!productOptions.value[index]) {
        productOptions.value[index] = [
            {
                id: item.product_id,
                label: item.product?.name ?? '-',
                purchase_price: parseFloat(item.price),
                selling_price: parseFloat(item.selling_price),
                unit: item.product?.unit?.name ?? item.unit,
            },
        ];
    }

    if (!item.product_name && item.product?.name) {
        item.product_name = item.product.name;
    }
});

const emit = defineEmits(['update:modelValue']);

function addItem() {
    emit('update:modelValue', [
        ...props.modelValue,
        {
            product_id: '',
            qty: 1,
            price: 0,
            selling_price: 0,
            unit: '',
            expiry_date: null,
        },
    ]);
}

function remove(index) {
    const updated = [...props.modelValue];
    updated.splice(index, 1);
    emit('update:modelValue', updated);
}

function formatCurrency(value) {
    if (!value && value !== 0) return '';
    return new Intl.NumberFormat('id-ID').format(Number(value));
}

function parseCurrency(value) {
    const raw = String(value).replace(/[^0-9]/g, '');
    return raw ? Number(raw) : 0;
}
</script>

<template>
    <div class="rounded-md border overflow-x-auto">
        <Table class="min-w-175">
            <TableCaption> Daftar Produk Pembelian </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-45"> Produk </TableHead>
                    <TableHead class="w-15 text-center"> QTY </TableHead>
                    <TableHead class="w-15 text-center"> Unit </TableHead>
                    <TableHead class="w-27.5"> Harga Beli </TableHead>
                    <TableHead class="w-27.5"> Harga Jual </TableHead>
                    <TableHead class="w-25"> Kadaluwarsa </TableHead>
                    <TableHead class="w-22.5 text-right px-4">
                        Subtotal
                    </TableHead>
                    <TableHead class="w-7.5" />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="(item, index) in modelValue" :key="index">
                    <TableCell>
                        <Combobox
                            :model-value="item.product_id"
                            @update:model-value="
                                (val) => {
                                    item.product_id = val.id;
                                    item.product_name = val.label;
                                    item.price = parseFloat(val.purchase_price);
                                    item.selling_price = parseFloat(
                                        val.selling_price,
                                    );
                                    item.unit = val.unit?.name ?? val.unit;
                                }
                            "
                        >
                            <ComboboxAnchor>
                                <div class="relative w-full items-center">
                                    <ComboboxInput
                                        v-model="searchProduct[index]"
                                        class="pl-9"
                                        :display-value="
                                            () => {
                                                const found = (
                                                    productOptions[index] || []
                                                ).find(
                                                    (p) =>
                                                        p.id ===
                                                        item.product_id,
                                                );
                                                return (
                                                    found?.label ??
                                                    item.product_name ??
                                                    ''
                                                );
                                            }
                                        "
                                        placeholder="Cari Produk..."
                                    />
                                    <span
                                        class="absolute inset-start-0 inset-y-0 flex items-center justify-center px-3"
                                    >
                                        <Search
                                            class="size-4 text-muted-foreground"
                                        />
                                    </span>
                                </div>
                            </ComboboxAnchor>

                            <ComboboxList>
                                <ComboboxEmpty>
                                    Tidak ada produk ditemukan.
                                </ComboboxEmpty>
                                <ComboboxGroup>
                                    <ComboboxItem
                                        v-for="product in productOptions[
                                            index
                                        ] || []"
                                        :key="product.id"
                                        class="cursor-pointer"
                                        :value="product"
                                    >
                                        {{ product.label }}

                                        <ComboboxItemIndicator>
                                            <Check
                                                :class="cn('ml-auto h-4 w-4')"
                                            />
                                        </ComboboxItemIndicator>
                                    </ComboboxItem>
                                </ComboboxGroup>
                            </ComboboxList>
                        </Combobox>
                        <p
                            v-if="
                                form?.errors?.[
                                    `product_items.${index}.product_id`
                                ]
                            "
                            class="text-red-500 text-[10px] mt-1"
                        >
                            {{
                                form.errors[`product_items.${index}.product_id`]
                            }}
                        </p>
                    </TableCell>
                    <TableCell>
                        <Input
                            v-model.number="item.qty"
                            type="number"
                            placeholder="0"
                            min="1"
                            class="text-center [&::-webkit-inner-spin-button]:appearance-none"
                        />
                        <p
                            v-if="form?.errors?.[`product_items.${index}.qty`]"
                            class="text-red-500 text-[10px] mt-1"
                        >
                            {{ form.errors[`product_items.${index}.qty`] }}
                        </p>
                    </TableCell>

                    <TableCell
                        class="text-center text-sm text-muted-foreground"
                    >
                        {{ item.unit ?? '-' }}
                    </TableCell>

                    <TableCell>
                        <div class="relative group">
                            <span
                                class="absolute inset-s-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground"
                                >Rp</span
                            >
                            <Input
                                :model-value="formatCurrency(item.price)"
                                type="text"
                                inputmode="numeric"
                                placeholder="0"
                                class="pl-8 font-mono"
                                @update:model-value="
                                    (val) => (item.price = parseCurrency(val))
                                "
                            />
                            <div
                                class="hidden group-focus-within:block absolute top-full inset-s-0 mt-1 bg-zinc-900 text-white text-[10px] px-2 py-1 rounded shadow-lg z-10 whitespace-nowrap"
                            >
                                {{ formatNumber(item.price || 0) }}
                            </div>
                        </div>
                        <p
                            v-if="
                                form?.errors?.[`product_items.${index}.price`]
                            "
                            class="text-red-500 text-[10px] mt-1"
                        >
                            {{ form.errors[`product_items.${index}.price`] }}
                        </p>
                    </TableCell>
                    <TableCell>
                        <div class="relative group">
                            <span
                                class="absolute inset-s-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground"
                                >Rp</span
                            >
                            <Input
                                :model-value="
                                    formatCurrency(item.selling_price)
                                "
                                type="text"
                                inputmode="numeric"
                                placeholder="0"
                                class="pl-8 font-mono text-blue-600 dark:text-blue-400"
                                @update:model-value="
                                    (val) =>
                                        (item.selling_price =
                                            parseCurrency(val))
                                "
                            />
                            <div
                                class="hidden group-focus-within:block absolute top-full inset-s-0 mt-1 bg-zinc-900 text-white text-[10px] px-2 py-1 rounded shadow-lg z-10 whitespace-nowrap"
                            >
                                {{ formatNumber(item.selling_price || 0) }}
                            </div>
                        </div>
                    </TableCell>
                    <TableCell>
                        <DatePicker
                            :model-value="parseExpiryDate(item.expiry_date)"
                            placeholder="Pilih"
                            class="w-full"
                            @update:model-value="
                                (dateValue) => {
                                    if (dateValue) {
                                        item.expiry_date =
                                            formatDateInput(dateValue);
                                    } else {
                                        item.expiry_date = null;
                                    }
                                }
                            "
                        />
                    </TableCell>
                    <TableCell class="text-right font-medium px-4">
                        {{ formatPrice(item.qty * (item.price || 0)) }}
                    </TableCell>
                    <TableCell>
                        <Button
                            aria-label="Hapus"
                            size="icon"
                            variant="ghost"
                            class="text-destructive hover:text-destructive hover:bg-destructive/10"
                            @click.prevent="remove(index)"
                        >
                            <Trash class="w-4 h-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>

    <div
        class="mt-4 flex justify-between items-center bg-muted/30 p-4 rounded-lg border border-dashed"
    >
        <Button variant="outline" size="sm" @click.prevent="addItem">
            <Plus class="w-4 h-4 mr-2" />
            Tambah Baris Produk
        </Button>

        <div class="flex flex-col items-end">
            <span
                class="text-xs text-muted-foreground uppercase tracking-wider font-semibold"
            >
                Total Estimasi Pembelian
            </span>
            <span class="text-2xl font-black text-primary">
                {{
                    formatPrice(
                        modelValue.reduce(
                            (acc, item) => acc + item.qty * (item.price || 0),
                            0,
                        ),
                    )
                }}
            </span>
        </div>
    </div>
</template>
