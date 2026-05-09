<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { DataTable } from '@/Components/ui/data-table';
import { shiftColumns } from './partials/shift-columns';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import DateRangePicker from '@/Components/DateRangePicker.vue';
import { Plus, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
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
    shifts: {
        type: Object,
        required: true,
    },
    hasActiveShift: {
        type: Boolean,
        default: false,
    },
    filters: {
        type: Object,
        default: () => ({ status: 'open', start: '', end: '', search: '' }),
    },
});

const openDialog = ref(false);
const dateStart = ref(props.filters.start || '');
const dateEnd = ref(props.filters.end || '');
const statusFilter = ref(props.filters.status || 'open');

const form = useForm({
    starting_cash: '',
});

const formattedCash = computed({
    get: () => {
        if (!form.starting_cash) return '';
        return new Intl.NumberFormat('id-ID').format(
            Number(form.starting_cash),
        );
    },
    set: (value) => {
        const numericValue = value.replace(/[^0-9]/g, '');
        form.starting_cash = numericValue || '';
    },
});

function applyFilters() {
    router.get(
        route('shift.index'),
        {
            search: props.filters.search || undefined,
            status: statusFilter.value,
            start: dateStart.value || undefined,
            end: dateEnd.value || undefined,
            page: 1,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function resetFilters() {
    dateStart.value = '';
    dateEnd.value = '';
    statusFilter.value = 'open';
    router.get(
        route('shift.index'),
        {
            search: props.filters.search || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch(statusFilter, () => {
    applyFilters();
});

function submit() {
    form.post(route('shift.store'), {
        onSuccess: () => {
            openDialog.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Manajemen Shift</title>
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
                        <BreadcrumbPage> Manajemen Shift </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <h4 class="font-semibold">Manajemen Shift</h4>
                        <Badge v-if="hasActiveShift" variant="default">
                            Shift Aktif
                        </Badge>
                    </div>
                    <Button
                        :disabled="hasActiveShift"
                        @click="openDialog = true"
                    >
                        <Plus class="w-4 h-4 mr-1" />
                        Buka Shift
                    </Button>
                    <Dialog v-model:open="openDialog">
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Buka Shift Baru</DialogTitle>
                                <DialogDescription>
                                    Masukkan jumlah uang modal awal (starting
                                    cash) untuk memulai shift.
                                </DialogDescription>
                            </DialogHeader>
                            <form class="space-y-4" @submit.prevent="submit">
                                <div class="space-y-2">
                                    <Label for="starting_cash"
                                        >Modal Awal (Rp)</Label
                                    >
                                    <div class="relative">
                                        <span
                                            class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"
                                        >
                                            Rp
                                        </span>
                                        <Input
                                            id="starting_cash"
                                            v-model="formattedCash"
                                            type="text"
                                            inputmode="numeric"
                                            placeholder="100.000"
                                            class="pl-10"
                                            :disabled="form.processing"
                                            required
                                        />
                                    </div>
                                    <p
                                        v-if="form.errors.starting_cash"
                                        class="text-sm text-red-500"
                                    >
                                        {{ form.errors.starting_cash }}
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
                                                ? 'Membuka...'
                                                : 'Buka Shift'
                                        }}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
                <Separator class="my-4" />

                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <Select v-model="statusFilter">
                        <SelectTrigger class="w-36">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="open"> Terbuka </SelectItem>
                            <SelectItem value="closed"> Tertutup </SelectItem>
                            <SelectItem value="all"> Semua </SelectItem>
                        </SelectContent>
                    </Select>

                    <DateRangePicker
                        :start="dateStart"
                        :end="dateEnd"
                        placeholder="Filter tanggal"
                        @update:start="
                            (val) => {
                                dateStart = val || '';
                            }
                        "
                        @update:end="
                            (val) => {
                                dateEnd = val || '';
                            }
                        "
                    />

                    <Button size="sm" variant="outline" @click="applyFilters">
                        Terapkan
                    </Button>

                    <Button
                        v-if="dateStart || dateEnd || statusFilter !== 'open'"
                        size="sm"
                        variant="ghost"
                        @click="resetFilters"
                    >
                        Reset
                    </Button>
                </div>

                <div class="mt-4">
                    <DataTable
                        :data="shifts.data"
                        :columns="shiftColumns"
                        :route-name="'shift.index'"
                        :pagination="shifts"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
