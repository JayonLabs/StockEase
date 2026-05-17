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

const searchSupplier = ref('');
const searchSupplierData = ref([]);

watchDebounced(
    searchSupplier,
    (newSearchSupplier) => {
        axios
            .get(
                route('reports.purchase.search-supplier', {
                    search: newSearchSupplier,
                }),
            )
            .then((response) => {
                searchSupplierData.value = response.data.data;
            })
            .catch((error) => {
                searchSupplierData.value = [];
            });
    },
    300,
);

const searchUser = ref('');
const searchUserData = ref([]);

watchDebounced(
    searchUser,
    (newSearchUser) => {
        axios
            .get(
                route('reports.purchase.search-user', {
                    search: newSearchUser,
                }),
            )
            .then((response) => {
                searchUserData.value = response.data.data;
            })
            .catch((error) => {
                searchUserData.value = [];
            });
    },
    300,
);

const urlParams = new URLSearchParams(window.location.search);

const supplierParam = urlParams.get('supplier') || null;
const userParam = urlParams.get('user') || null;
const allUserParam = urlParams.get('user') === 'semua-user' || !userParam;
const allSupplierParam =
    urlParams.get('supplier') === 'semua-supplier' || !supplierParam;

const startDate = ref(urlParams.get('start_date') || null);
const endDate = ref(urlParams.get('end_date') || null);
const supplier = ref(null);
const user = ref(null);
const allUser = ref(allUserParam);
const allSupplier = ref(allSupplierParam);

// Jika user pilih supplier manual → allSupplier = false
watch(supplier, (newVal) => {
    if (newVal) {
        allSupplier.value = false;
    }
});

// Jika user centang Semua Supplier → supplier = null
watch(allSupplier, (newVal) => {
    if (newVal) {
        supplier.value = null;
    }
});

// Jika user pilih user manual → allUser = false
watch(user, (newVal) => {
    if (newVal) {
        allUser.value = false;
    }
});

// Jika user centang Semua User → user = null
watch(allUser, (newVal) => {
    if (newVal) {
        user.value = null;
    }
});

if (supplierParam && supplierParam !== 'semua-supplier') {
    axios
        .get(
            route('reports.purchase.search-supplier', {
                search: supplierParam,
            }),
        )
        .then((response) => {
            const foundSupplier = response.data.data.find(
                (item) => String(item.value) === String(supplierParam),
            );

            if (foundSupplier) {
                supplier.value = foundSupplier;
            }
        })
        .catch(() => {
            supplier.value = null;
        });
}

if (userParam && userParam !== 'semua-user') {
    axios
        .get(
            route('reports.purchase.search-user', {
                search: userParam,
            }),
        )
        .then((response) => {
            const foundUser = response.data.data.find(
                (item) => String(item.value) === String(userParam),
            );
            if (foundUser) {
                user.value = foundUser;
            }
        })
        .catch(() => {
            user.value = null;
        });
}

const checkFilter = () => {
    if (!startDate.value || !endDate.value) {
        return false;
    }

    if (!allSupplier.value && !supplier.value) {
        return false;
    }

    if (!allUser.value && !user.value) {
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

    if (!allSupplier.value && !supplier.value) {
        toast.error('Silahkan pilih supplier atau centang Semua Supplier!');
        return;
    }

    if (!allUser.value && !user.value) {
        toast.error('Silahkan pilih user atau centang Semua User!');
        return;
    }

    isFilterLoading.value = true;

    let supplierId = 'semua-supplier';
    if (!allSupplier.value && supplier.value) {
        supplierId = supplier.value.value;
    }

    let userId = 'semua-user';
    if (!allUser.value && user.value) {
        userId = user.value.value;
    }

    router.get(
        route('reports.purchase.index'),
        {
            start_date: startDate.value,
            end_date: endDate.value,
            supplier: supplierId,
            user: userId,
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

    let supplierId = 'semua-supplier';
    if (!allSupplier.value && supplier.value) {
        supplierId = supplier.value.value;
    }

    let userId = 'semua-user';
    if (!allUser.value && user.value) {
        userId = user.value.value;
    }

    window.open(
        route('reports.purchase.export-to-pdf', {
            start_date: startDate.value,
            end_date: endDate.value,
            supplier: supplierId,
            user: userId,
        }),
        '_blank',
    );
};

const handleExportExcel = () => {
    if (!checkFilter()) {
        toast.error('Lengkapi filter terlebih dahulu!');
        return;
    }

    let supplierId = 'semua-supplier';
    if (!allSupplier.value && supplier.value) {
        supplierId = supplier.value.value;
    }

    let userId = 'semua-user';
    if (!allUser.value && user.value) {
        userId = user.value.value;
    }

    window.open(
        route('reports.purchase.export-to-excel', {
            start_date: startDate.value,
            end_date: endDate.value,
            supplier: supplierId,
            user: userId,
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
                <Label html-for="supplier" class="font-medium">Supplier</Label>
                <Combobox v-model="supplier" by="label" :disabled="allSupplier">
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
                            Tidak ada supplier ditemukan.
                        </ComboboxEmpty>

                        <ComboboxGroup>
                            <ComboboxItem
                                v-for="s in searchSupplierData"
                                :key="s.value"
                                :value="s"
                                class="cursor-pointer"
                            >
                                {{ s.label }}
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
                <Label html-for="user" class="font-medium">User</Label>
                <Combobox
                    v-model="user"
                    by="label"
                    html-id="user"
                    :disabled="allUser"
                >
                    <ComboboxAnchor class="w-full">
                        <div class="relative w-full items-center">
                            <ComboboxInput
                                v-model="searchUser"
                                class="pl-9 h-10 bg-background"
                                :display-value="(val) => val?.label ?? ''"
                                placeholder="Cari User..."
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
                            Tidak ada user ditemukan.
                        </ComboboxEmpty>

                        <ComboboxGroup>
                            <ComboboxItem
                                v-for="u in searchUserData"
                                :key="u.value"
                                :value="u"
                                class="cursor-pointer"
                            >
                                {{ u.label }}
                                <ComboboxItemIndicator>
                                    <Check :class="cn('ml-auto h-4 w-4')" />
                                </ComboboxItemIndicator>
                            </ComboboxItem>
                        </ComboboxGroup>
                    </ComboboxList>
                </Combobox>
                <div class="flex items-center space-x-2 pt-1">
                    <Checkbox id="all-user" v-model="allUser" />
                    <label
                        for="all-user"
                        class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                    >
                        Semua User
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
