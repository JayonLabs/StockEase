<script setup>
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Loader2, Plus } from 'lucide-vue-next';
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
    categories: { type: Array, required: true },
    products: { type: Array, required: true },
});

const user = usePage().props.auth.user.name;
const isDialogOpen = ref(false);

const startDate = ref();
const endDate = ref();

const form = useForm({
    name: '',
    type: 'percentage',
    discount_value: null,
    buy_qty: null,
    get_qty: null,
    category_id: null,
    product_id: null,
    start_date: '',
    end_date: '',
    is_active: true,
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

const submit = () => {
    form.post(route('promotions.store'), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            startDate.value = null;
            endDate.value = null;
            toast.success('Promo berhasil ditambahkan', {
                description: `Promo berhasil ditambahkan oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Gagal menyimpan promo', {
                description: 'Periksa kembali isian form.',
            });
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button>
                <Plus class="mr-2 h-4 w-4" />
                Tambah Promo
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Tambah Promo Baru</DialogTitle>
                <DialogDescription>
                    Isi form berikut untuk menambahkan promo atau diskon.
                </DialogDescription>
            </DialogHeader>

            <form
                id="promotion-create-form"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="create-name">Nama Promo</Label>
                    <Input
                        id="create-name"
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
                    <Label for="create-type">Tipe Promo</Label>
                    <Select v-model="form.type" required>
                        <SelectTrigger id="create-type">
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
                    <Label for="create-discount_value">
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
                        id="create-discount_value"
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
                        <Label for="create-buy_qty">Syarat Beli (Qty)</Label>
                        <Input
                            id="create-buy_qty"
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
                        <Label for="create-get_qty">Gratis (Qty)</Label>
                        <Input
                            id="create-get_qty"
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
                        <Label for="create-category_id"
                            >Kategori (Opsional)</Label
                        >
                        <Select v-model="form.category_id">
                            <SelectTrigger id="create-category_id">
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
                        <Label for="create-product_id">Produk (Opsional)</Label>
                        <Select v-model="form.product_id">
                            <SelectTrigger id="create-product_id">
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
                        <Label for="create-start_date">Tanggal Mulai</Label>
                        <DatePicker
                            id="create-start_date"
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
                        <Label for="create-end_date">Tanggal Berakhir</Label>
                        <DatePicker
                            id="create-end_date"
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
                    <Checkbox id="create-is_active" v-model="form.is_active" />
                    <Label for="create-is_active">Aktifkan Promo</Label>
                </div>
            </form>

            <DialogFooter class="flex justify-between">
                <DialogClose as-child>
                    <Button type="button" variant="secondary"> Batal </Button>
                </DialogClose>
                <Button
                    type="submit"
                    form="promotion-create-form"
                    :disabled="form.processing"
                    class="disabled:cursor-not-allowed"
                >
                    <Loader2
                        v-if="form.processing"
                        class="mr-2 h-4 w-4 animate-spin"
                    />
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Promo' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
