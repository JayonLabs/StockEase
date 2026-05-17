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
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
} from '@/Components/ui/combobox';

const urlParams = new URLSearchParams(window.location.search);

const selectedCategoryParam = urlParams.get('category') || null;
const selectedSupplierParam = urlParams.get('supplier') || null;
const allCategoryParam =
    urlParams.get('category') === 'semua-kategori' || !selectedCategoryParam;
const allSupplierParam =
    urlParams.get('supplier') === 'semua-supplier' || !selectedSupplierParam;

const startDate = ref(urlParams.get('start_date') || null);
const endDate = ref(urlParams.get('end_date') || null);
const selectedCategory = ref(null);
const selectedSupplier = ref(null);
const allCategory = ref(allCategoryParam);
const allSupplier = ref(allSupplierParam);

if (selectedCategoryParam && selectedCategoryParam !== 'semua-kategori') {
    axios
        .get(
            route('reports.stock.searchCategory', {
                search: selectedCategoryParam,
            }),
        )
        .then((response) => {
            const categoriesData = response.data.data.map((item) => ({
                label: item.name,
                value: item.id,
            }));

            const foundCategory = categoriesData.find(
                (item) => String(item.value) === String(selectedCategoryParam),
            );

            if (foundCategory) {
                selectedCategory.value = foundCategory;
            }
        })
        .catch(() => {
            selectedCategory.value = null;
        });
}

if (selectedSupplierParam && selectedSupplierParam !== 'semua-supplier') {
    axios
        .get(
            route('reports.stock.searchSupplier', {
                search: selectedSupplierParam,
            }),
        )
        .then((response) => {
            const suppliersData = response.data.data.map((item) => ({
                label: item.name,
                value: item.id,
            }));

            const foundSupplier = suppliersData.find(
                (item) => String(item.value) === String(selectedSupplierParam),
            );

            if (foundSupplier) {
                selectedSupplier.value = foundSupplier;
            }
        })
        .catch(() => {
            selectedSupplier.value = null;
        });
}

watch(selectedCategory, (newVal) => {
    if (newVal) {
        allCategory.value = false;
    }
});
watch(allCategory, (newVal) => {
    if (newVal) {
        selectedCategory.value = null;
    }
});
watch(selectedSupplier, (newVal) => {
    if (newVal) {
        allSupplier.value = false;
    }
});
watch(allSupplier, (newVal) => {
    if (newVal) {
        selectedSupplier.value = null;
    }
});

const categories = ref([]);
const searchCategory = ref('');

watchDebounced(
    searchCategory,
    (newSearchCategory) => {
        axios
            .get(
                route('reports.stock.searchCategory', {
                    search: newSearchCategory,
                }),
            )
            .then((response) => {
                categories.value = response.data.data.map((item) => {
                    return {
                        label: item.name,
                        value: item.id,
                    };
                });
            })
            .catch((error) => {
                categories.value = [];
            });
    },
    { debounce: 300 },
);

const searchSupplier = ref('');
const suppliers = ref([]);

watchDebounced(
    searchSupplier,
    (newSearchSupplier) => {
        axios
            .get(
                route('reports.stock.searchSupplier', {
                    search: newSearchSupplier,
                }),
            )
            .then((response) => {
                suppliers.value = response.data.data.map((item) => {
                    return {
                        label: item.name,
                        value: item.id,
                    };
                });
            })
            .catch((error) => {
                suppliers.value = [];
            });
    },
    { debounce: 300 },
);

const checkFilter = () => {
    if (!startDate.value || !endDate.value) {
        return false;
    }

    if (!allSupplier.value && !selectedSupplier.value) {
        return false;
    }

    if (!allCategory.value && !selectedCategory.value) {
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

    if (!allSupplier.value && !selectedSupplier.value) {
        toast.error('Silahkan pilih supplier atau centang Semua Supplier!');
        return;
    }

    if (!allCategory.value && !selectedCategory.value) {
        toast.error('Silahkan pilih kategori atau centang Semua Kategori!');
        return;
    }

    isFilterLoading.value = true;

    let supplierParam = allSupplier.value
        ? 'semua-supplier'
        : selectedSupplier.value.value;
    let categoryParam = allCategory.value
        ? 'semua-kategori'
        : selectedCategory.value.value;

    router.get(
        route('reports.stock.index'),
        {
            start_date: startDate.value,
            end_date: endDate.value,
            supplier: supplierParam,
            category: categoryParam,
            page: 1,
        },
        {
            preserveState: true,
            onFinish: () => {
                isFilterLoading.value = false;
            },
        },
    );
};

const handleExportPdf = () => {
    if (!checkFilter()) {
        toast.error('Lengkapi filter terlebih dahulu!');
        return;
    }

    let supplierParam = allSupplier.value
        ? 'semua-supplier'
        : selectedSupplier.value.value;
    let categoryParam = allCategory.value
        ? 'semua-kategori'
        : selectedCategory.value.value;

    window.open(
        route('reports.stock.export-to-pdf', {
            start_date: startDate.value,
            end_date: endDate.value,
            supplier: supplierParam,
            category: categoryParam,
        }),
        '_blank',
    );
};

const handleExportExcel = () => {
    if (!checkFilter()) {
        toast.error('Lengkapi filter terlebih dahulu!');
        return;
    }

    let supplierParam = allSupplier.value
        ? 'semua-supplier'
        : selectedSupplier.value.value;
    let categoryParam = allCategory.value
        ? 'semua-kategori'
        : selectedCategory.value.value;

    window.open(
        route('reports.stock.export-to-excel', {
            start_date: startDate.value,
            end_date: endDate.value,
            supplier: supplierParam,
            category: categoryParam,
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
                <Label html-for="category" class="font-medium">Kategori</Label>
                <Combobox
                    v-model="selectedCategory"
                    by="label"
                    html-id="category"
                    :disabled="allCategory"
                >
                    <ComboboxAnchor class="w-full">
                        <div class="relative w-full items-center">
                            <ComboboxInput
                                v-model="searchCategory"
                                class="pl-9 h-10 bg-background"
                                :display-value="(val) => val?.label ?? ''"
                                placeholder="Cari Kategori..."
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
                            Tidak ada Kategori ditemukan.
                        </ComboboxEmpty>

                        <ComboboxGroup>
                            <ComboboxItem
                                v-for="category in categories"
                                :key="category.value"
                                :value="category"
                                class="cursor-pointer"
                            >
                                {{ category.label }}

                                <ComboboxItemIndicator>
                                    <Check :class="cn('ml-auto h-4 w-4')" />
                                </ComboboxItemIndicator>
                            </ComboboxItem>
                        </ComboboxGroup>
                    </ComboboxList>
                </Combobox>
                <div class="flex items-center space-x-2 pt-1">
                    <Checkbox id="all-category" v-model="allCategory" />
                    <label
                        for="all-category"
                        class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                    >
                        Semua Kategori
                    </label>
                </div>
            </div>

            <div class="space-y-2">
                <Label html-for="supplier" class="font-medium">Supplier</Label>
                <Combobox
                    v-model="selectedSupplier"
                    by="label"
                    html-id="supplier"
                    :disabled="allSupplier"
                >
                    <ComboboxAnchor class="w-full">
                        <div class="relative w-full items-center">
                            <ComboboxInput
                                v-model="searchSupplier"
                                class="pl-9 h-10 bg-background"
                                :display-value="(val) => val?.label ?? ''"
                                placeholder="Cari Supplier..."
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
                            Tidak ada Supplier ditemukan.
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
                                    <Check :class="cn('ml-auto h-4 w-4')" />
                                </ComboboxItemIndicator>
                            </ComboboxItem>
                        </ComboboxGroup>
                    </ComboboxList>
                </Combobox>
                <div class="flex items-center space-x-2 pt-1">
                    <Checkbox id="all-supplier" v-model="allSupplier" />
                    <label
                        for="all-supplier"
                        class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                    >
                        Semua Supplier
                    </label>
                </div>
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
                    @click="handleExportPdf()"
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
