<script setup>
import DateRangePicker from '@/Components/DateRangePicker.vue';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import { watchDebounced } from '@vueuse/core';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Checkbox } from '@/Components/ui/checkbox';

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardFooter,
} from '@/Components/ui/card';

import {
    Check,
    FileSpreadsheet,
    Loader2,
    Printer,
    Search,
    Filter as FilterIcon,
} from 'lucide-vue-next';

import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

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

const searchCashier = ref('');
const cashierData = ref([]);

watchDebounced(
    searchCashier,
    (newsearchCashier) => {
        axios
            .get(
                route('reports.sale.search-cashier', {
                    search: newsearchCashier,
                }),
            )
            .then((response) => {
                cashierData.value = response.data.data;
            })
            .catch((error) => {
                console.log(error);
                cashierData.value = [];
            });
    },
    300,
);

const urlParams = new URLSearchParams(window.location.search);

const paymentParam = urlParams.get('payment') || 'semua-metode';
const cashierParam = urlParams.get('cashier') || null;
const allCashierParam =
    urlParams.get('cashier') === 'semua-cashier' || !cashierParam;

const startDate = ref(urlParams.get('start') || null);
const endDate = ref(urlParams.get('end') || null);
const cashier = ref(null);
const payment = ref(paymentParam);
const allCashier = ref(allCashierParam);

watch(allCashier, (newVal) => {
    if (newVal) {
        cashier.value = null;
    }
});

watch(cashier, (newVal) => {
    if (newVal) {
        allCashier.value = false;
    }
});

if (cashierParam && cashierParam !== 'semua-cashier') {
    axios
        .get(route('reports.sale.search-cashier', { search: cashierParam }))
        .then((response) => {
            const foundCashier = response.data.data.find(
                (item) => String(item.value) === String(cashierParam),
            );
            if (foundCashier) {
                cashier.value = foundCashier;
            }
        })
        .catch(() => {
            cashier.value = null;
        });
}

const checkFilter = () => {
    if (!startDate.value || !endDate.value) {
        return false;
    }

    if (!allCashier.value && !cashier.value) {
        return false;
    }

    return true;
};

const isFilterLoading = ref(false);

const handleFilter = () => {
    if (!startDate.value || !endDate.value) {
        toast.error('Tanggal mulai dan tanggal selesai wajib diisi!');
        return;
    }

    if (!allCashier.value && !cashier.value) {
        toast.error('Silahkan pilih kasir atau centang Semua Kasir!');
        return;
    }

    isFilterLoading.value = true;

    let cashierId = 'semua-cashier';
    if (!allCashier.value && cashier.value) {
        cashierId = cashier.value.value;
    }

    router.get(
        route('reports.sale.index'),
        {
            start: startDate.value,
            end: endDate.value,
            cashier: cashierId,
            payment: payment.value,
        },
        {
            preserveState: true,
            onFinish: () => {
                isFilterLoading.value = false;
            },
        },
    );
};

const handlePrintPdf = () => {
    if (!checkFilter()) {
        toast.error('Lengkapi filter terlebih dahulu!');
        return;
    }

    let cashierId = 'semua-cashier';
    if (!allCashier.value && cashier.value) {
        cashierId = cashier.value.value;
    }

    window.open(
        route('reports.sale.export-to-pdf', {
            start: startDate.value,
            end: endDate.value,
            cashier: cashierId,
            payment: payment.value,
        }),
        '_blank',
    );
};

const handleExportExcel = () => {
    if (!checkFilter()) {
        toast.error('Lengkapi filter terlebih dahulu!');
        return;
    }

    let cashierId = 'semua-cashier';
    if (!allCashier.value && cashier.value) {
        cashierId = cashier.value.value;
    }

    window.open(
        route('reports.sale.export-to-excel', {
            start: startDate.value,
            end: endDate.value,
            cashier: cashierId,
            payment: payment.value,
        }),
        '_blank',
    );
};
</script>

<template>
    <Card class="shadow-sm">
        <CardHeader class="pb-4 border-b">
            <CardTitle class="flex items-center gap-2 text-lg font-semibold">
                <FilterIcon class="w-5 h-5 text-primary" />
                Filter Laporan
            </CardTitle>
        </CardHeader>
        <CardContent
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-6 pb-2"
        >
            <div class="space-y-2">
                <Label html-for="cashier" class="font-medium">Kasir</Label>
                <Combobox v-model="cashier" by="label" :disabled="allCashier">
                    <ComboboxAnchor class="w-full">
                        <div class="relative w-full items-center">
                            <ComboboxInput
                                v-model="searchCashier"
                                class="pl-9 h-10 bg-background"
                                :display-value="(val) => val?.label ?? ''"
                                placeholder="Cari Kasir..."
                            />
                            <span
                                class="absolute inset-s-0 inset-y-0 flex items-center justify-center px-3"
                            >
                                <Search class="size-4 text-muted-foreground" />
                            </span>
                        </div>
                    </ComboboxAnchor>

                    <ComboboxList>
                        <ComboboxEmpty>
                            Tidak ada kasir ditemukan.
                        </ComboboxEmpty>

                        <ComboboxGroup>
                            <ComboboxItem
                                v-for="c in cashierData"
                                :key="c.value"
                                :value="c"
                                class="cursor-pointer"
                            >
                                {{ c.label }}
                                <ComboboxItemIndicator>
                                    <Check :class="cn('ml-auto h-4 w-4')" />
                                </ComboboxItemIndicator>
                            </ComboboxItem>
                        </ComboboxGroup>
                    </ComboboxList>
                </Combobox>
                <div class="flex items-center space-x-2 pt-1">
                    <Checkbox id="all-cashier" v-model="allCashier" />
                    <label
                        for="all-cashier"
                        class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                    >
                        Semua Kasir
                    </label>
                </div>
            </div>

            <div class="space-y-2">
                <Label html-for="payment" class="font-medium">
                    Metode Pembayaran
                </Label>
                <Select id="payment" v-model="payment">
                    <SelectTrigger class="w-full h-10 bg-background">
                        <SelectValue placeholder="Pilih Metode" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>Metode Pembayaran</SelectLabel>
                            <SelectItem
                                value="semua-metode"
                                class="cursor-pointer"
                            >
                                Semua Metode
                            </SelectItem>
                            <SelectItem value="cash" class="cursor-pointer">
                                Cash
                            </SelectItem>
                            <SelectItem value="qris" class="cursor-pointer">
                                Midtrans (QRIS)
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-2">
                <Label class="font-medium">Rentang Tanggal</Label>
                <div class="space-y-2">
                    <DateRangePicker
                        v-model:start="startDate"
                        v-model:end="endDate"
                        placeholder="Pilih rentang tanggal laporan"
                    />
                </div>
            </div>
        </CardContent>

        <CardFooter
            class="flex flex-col sm:flex-row justify-end items-center gap-3 pt-4 border-t mt-4"
        >
            <div
                class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto"
            >
                <Button
                    variant="outline"
                    :disabled="!checkFilter()"
                    class="w-full sm:w-auto disabled:cursor-not-allowed hover:bg-accent"
                    @click="handleExportExcel()"
                >
                    <FileSpreadsheet class="h-4 w-4 mr-2 text-green-600" />
                    Excel
                </Button>
                <Button
                    variant="outline"
                    :disabled="!checkFilter()"
                    class="w-full sm:w-auto disabled:cursor-not-allowed hover:bg-accent"
                    @click="handlePrintPdf()"
                >
                    <Printer class="h-4 w-4 mr-2 text-red-500" />
                    PDF
                </Button>
                <Button
                    :disabled="isFilterLoading || !checkFilter()"
                    class="w-full sm:w-auto disabled:cursor-not-allowed"
                    @click="handleFilter"
                >
                    <Loader2
                        v-if="isFilterLoading"
                        class="w-4 h-4 animate-spin mr-2"
                    />
                    <Search v-else class="h-4 w-4 mr-2" />
                    Terapkan Filter
                </Button>
            </div>
        </CardFooter>
    </Card>
</template>
