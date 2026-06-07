<script setup>
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Loader2, Pencil } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import DatePicker from '@/Components/DatePicker.vue';

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

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

const props = defineProps({
    row: { type: Object, required: true },
    categories: { type: Array, required: true },
    products: { type: Array, required: true },
});

const user = usePage().props.auth.user.name;
const isDialogOpen = ref(false);

const resetForm = () => {
    form.name = props.row.name;
    form.type = props.row.type;
    form.discount_value = props.row.discount_value
        ? Number(props.row.discount_value)
        : null;
    form.buy_qty = props.row.buy_qty;
    form.get_qty = props.row.get_qty;
    form.category_id = props.row.category_id;
    form.product_id = props.row.product_id;
    form.start_date = props.row.start_date ? props.row.start_date : '';
    form.end_date = props.row.end_date ? props.row.end_date : '';
    form.is_active = Boolean(props.row.is_active);
    startDate.value = props.row.start_date
        ? new Date(props.row.start_date)
        : null;
    endDate.value = props.row.end_date ? new Date(props.row.end_date) : null;
};

const startDate = ref(
    props.row.start_date ? new Date(props.row.start_date) : null,
);
const endDate = ref(props.row.end_date ? new Date(props.row.end_date) : null);

const form = useForm({
    name: props.row.name,
    type: props.row.type,
    discount_value: props.row.discount_value
        ? Number(props.row.discount_value)
        : null,
    buy_qty: props.row.buy_qty,
    get_qty: props.row.get_qty,
    category_id: props.row.category_id,
    product_id: props.row.product_id,
    start_date: props.row.start_date ? props.row.start_date : '',
    end_date: props.row.end_date ? props.row.end_date : '',
    is_active: Boolean(props.row.is_active),
});

const formattedDiscountValue = computed({
    get: () => {
        if (!form.discount_value) return '';
        if (form.type === 'percentage') return form.discount_value.toString();

        return new Intl.NumberFormat('id-ID').format(form.discount_value);
    },
    set: (value) => {
        const numericValue = value.replace(/[^0-9]/g, '');
        form.discount_value = numericValue ? parseInt(numericValue, 10) : null;
    },
});

watch(startDate, (newValue) => {
    form.start_date = newValue ? newValue.toISOString().split('T')[0] : '';
});

watch(endDate, (newValue) => {
    form.end_date = newValue ? newValue.toISOString().split('T')[0] : '';
});

watch(
    () => props.row,
    (newRow) => {
        form.name = newRow.name;
        form.type = newRow.type;
        form.discount_value = newRow.discount_value
            ? Number(newRow.discount_value)
            : null;
        form.buy_qty = newRow.buy_qty;
        form.get_qty = newRow.get_qty;
        form.category_id = newRow.category_id;
        form.product_id = newRow.product_id;
        startDate.value = newRow.start_date
            ? new Date(newRow.start_date)
            : null;
        endDate.value = newRow.end_date ? new Date(newRow.end_date) : null;
        form.is_active = Boolean(newRow.is_active);
    },
);

watch(isDialogOpen, (isOpen) => {
    if (isOpen) {
        resetForm();
    }
});

const submit = () => {
    form.put(route('promotions.update', props.row.id), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Promo berhasil diubah', {
                description: `Promo ${form.name} berhasil diubah oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Gagal mengubah promo', {
                description: 'Periksa kembali isian form.',
            });
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button aria-label="Ubah" variant="ghost" size="icon" class="group">
                <Pencil
                    class="h-4 w-4 text-blue-500 dark:group-hover:text-white"
                />
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Edit Promo</DialogTitle>
                <DialogDescription>
                    Ubah data promo <strong>{{ row.name }}</strong
                    >.
                </DialogDescription>
            </DialogHeader>

            <form
                id="promotion-update-form"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="edit-name">Nama Promo</Label>
                    <Input
                        id="edit-name"
                        v-model="form.name"
                        placeholder="Contoh: Flash Sale Akhir Bulan"
                        autocomplete="off"
                        required
                    />
                    <span v-if="form.errors.name" class="text-sm text-red-500">
                        {{ form.errors.name }}
                    </span>
                </div>

                <div class="grid gap-2">
                    <Label for="edit-type">Tipe Promo</Label>
                    <Select v-model="form.type" required>
                        <SelectTrigger id="edit-type">
                            <SelectValue placeholder="Pilih Tipe Promo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="percentage">
                                Persentase (%)
                            </SelectItem>
                            <SelectItem value="nominal">
                                Nominal (Rp)
                            </SelectItem>
                            <SelectItem value="bogo">
                                Beli X Gratis Y
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <span v-if="form.errors.type" class="text-sm text-red-500">
                        {{ form.errors.type }}
                    </span>
                </div>

                <div
                    v-if="form.type === 'percentage' || form.type === 'nominal'"
                    class="grid gap-2"
                >
                    <Label for="edit-discount_value">
                        Nilai Diskon
                        <span class="text-muted-foreground">
                            {{
                                form.type === 'percentage'
                                    ? '(maks. 100%)'
                                    : '(Rp)'
                            }}
                        </span>
                    </Label>
                    <Input
                        id="edit-discount_value"
                        v-model="formattedDiscountValue"
                        type="text"
                        placeholder="Contoh: 10"
                        required
                    />
                    <span
                        v-if="form.errors.discount_value"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.discount_value }}
                    </span>
                </div>

                <div v-if="form.type === 'bogo'" class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="edit-buy_qty">Syarat Beli (Qty)</Label>
                        <Input
                            id="edit-buy_qty"
                            v-model="form.buy_qty"
                            type="number"
                            min="1"
                            placeholder="Contoh: 2"
                            required
                        />
                        <span
                            v-if="form.errors.buy_qty"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.buy_qty }}
                        </span>
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-get_qty">Gratis (Qty)</Label>
                        <Input
                            id="edit-get_qty"
                            v-model="form.get_qty"
                            type="number"
                            min="1"
                            placeholder="Contoh: 1"
                            required
                        />
                        <span
                            v-if="form.errors.get_qty"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.get_qty }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="edit-category_id"
                            >Kategori (Opsional)</Label
                        >
                        <Select v-model="form.category_id">
                            <SelectTrigger id="edit-category_id">
                                <SelectValue placeholder="Semua Kategori" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">
                                    Semua Kategori
                                </SelectItem>
                                <SelectItem
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-product_id">Produk (Opsional)</Label>
                        <Select v-model="form.product_id">
                            <SelectTrigger id="edit-product_id">
                                <SelectValue placeholder="Semua Produk" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">
                                    Semua Produk
                                </SelectItem>
                                <SelectItem
                                    v-for="prod in products"
                                    :key="prod.id"
                                    :value="prod.id"
                                >
                                    {{ prod.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="edit-start_date">Tanggal Mulai</Label>
                        <DatePicker
                            id="edit-start_date"
                            v-model="startDate"
                            placeholder="Pilih tanggal mulai"
                        />
                        <span
                            v-if="form.errors.start_date"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.start_date }}
                        </span>
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-end_date">Tanggal Berakhir</Label>
                        <DatePicker
                            id="edit-end_date"
                            v-model="endDate"
                            placeholder="Pilih tanggal berakhir"
                        />
                        <span
                            v-if="form.errors.end_date"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.end_date }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <Checkbox id="edit-is_active" v-model="form.is_active" />
                    <Label for="edit-is_active">Aktifkan Promo</Label>
                </div>
            </form>

            <DialogFooter class="flex justify-between">
                <DialogClose as-child>
                    <Button type="button" variant="secondary"> Batal </Button>
                </DialogClose>
                <Button
                    type="submit"
                    form="promotion-update-form"
                    :disabled="form.processing"
                    class="disabled:cursor-not-allowed"
                >
                    <Loader2
                        v-if="form.processing"
                        class="mr-2 h-4 w-4 animate-spin"
                    />
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
