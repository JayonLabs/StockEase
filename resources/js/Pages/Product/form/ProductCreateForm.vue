<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { nextTick, onUnmounted, ref, watch } from 'vue';
import { cn } from '@/lib/utils';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import VueCropper from 'vue-cropperjs/VueCropper.js';
import 'cropperjs/dist/cropper.css';
import { Html5QrcodeScanner } from 'html5-qrcode';
import DatePicker from '@/Components/DatePicker.vue';
import { toast } from 'vue-sonner';
import InputError from '@/Components/InputError.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

import {
    ArrowLeft,
    Check,
    ChevronsUpDown,
    Loader2,
    Search,
    ScanBarcode,
    ImageIcon,
    Package,
    Coins,
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
    ComboboxTrigger,
} from '@/Components/ui/combobox';

import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

const props = defineProps({
    units: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
});

const category = ref();
const unit = ref();
const expiryDate = ref();
const showCropperModal = ref(false);

watch(expiryDate, (newValue) => {
    form.expiry_date = newValue ? newValue.toISOString().split('T')[0] : '';
});

watch(category, (newValue) => {
    form.category_id = newValue?.value || '';
});

watch(unit, (newValue) => {
    form.unit_id = newValue?.value || '';
});

const form = useForm({
    category_id: '',
    name: '',
    sku: '',
    barcode: '',
    unit_id: '',
    stock: 0,
    purchase_price: 0,
    selling_price: 0,
    alert_stock: 0,
    expiry_date: '',
    image: null,
});

const imgSrc = ref(null);
const cropImg = ref(null);
const cropper = ref(null);

const setImage = (e) => {
    const file = e.target.files[0];
    if (!file?.type?.includes('image/')) {
        toast.error('Format file tidak didukung', {
            description: 'Harap pilih file gambar (JPG, PNG, WEBP).',
        });
        return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
        imgSrc.value = event.target.result;
        showCropperModal.value = true;
        nextTick(() => {
            cropper.value?.replace(event.target.result);
        });
    };
    reader.readAsDataURL(file);
};

const handleCrop = () => {
    const canvas = cropper.value?.getCroppedCanvas();

    if (canvas) {
        cropImg.value = canvas.toDataURL();

        canvas.toBlob((blob) => {
            if (blob) {
                const file = new File([blob], 'cropped.jpg', {
                    type: 'image/jpeg',
                });

                form.image = file;
            }
        }, 'image/jpeg');
    }

    showCropperModal.value = false;
};

const showScannerModal = ref(false);

let html5QrcodeScanner = null;

const onScanSuccess = (decodedText, decodedResult) => {
    toast.success('Barcode terdeteksi', {
        description: `Barcode ${decodedText} berhasil terbaca.`,
    });

    form.barcode = decodedText;
    showScannerModal.value = false;
};

const onScanFailure = (error) => {
    // console.warn(`Code scan error = ${error}`);
};

watch(showScannerModal, (newVal) => {
    if (newVal) {
        nextTick(() => {
            html5QrcodeScanner = new Html5QrcodeScanner(
                'reader',
                { fps: 10, qrbox: { width: 250, height: 250 } },
                false,
            );
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        });
    } else {
        if (html5QrcodeScanner) {
            html5QrcodeScanner
                .clear()
                .then(() => {
                    html5QrcodeScanner = null;
                })
                .catch((err) => {
                    console.error('Failed to clear scanner', err);
                });
        }
    }
});

onUnmounted(() => {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear().catch(() => {});
        html5QrcodeScanner = null;
    }
});

const user = usePage().props.auth.user.name;

const submit = () => {
    const payload = {
        ...form.data(),
        purchase_price: parseFloat(form.purchase_price) || 0,
        selling_price: parseFloat(form.selling_price) || 0,
    };

    form.transform((data) => payload).post(route('product.store'), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toast.success('Produk ditambahkan', {
                description: `Produk ${payload.name} berhasil ditambahkan oleh ${user}`,
            });

            showCropperModal.value = false;
        },
        onError: () => {
            toast.error('Gagal menambahkan produk', {
                description: 'Silakan periksa kembali formulir Anda.',
            });
        },
    });
};

const formatInput = (val) => {
    if (val === null || val === undefined || val === '') return '';
    let str = val.toString().replace('.', ',');
    let parts = str.split(',');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return parts.join(',');
};

const parseInput = (val) => {
    if (val === null || val === undefined) return '';
    let clean = val.toString().replace(/[^\d,]/g, '');
    const commaIndex = clean.indexOf(',');
    if (commaIndex !== -1) {
        clean =
            clean.slice(0, commaIndex + 1) +
            clean.slice(commaIndex + 1).replace(/,/g, '');
    }
    return clean.replace(',', '.');
};
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Tambah Produk</title>
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
                        <Link :href="route('product.index')">
                            <BreadcrumbLink> Produk </BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage> Tambah Produk </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <div class="flex flex-1 flex-col gap-6 p-6 w-full">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">
                        Tambah Produk Baru
                    </h2>
                    <p class="text-muted-foreground">
                        Lengkapi informasi produk untuk menambahkannya ke
                        inventaris.
                    </p>
                </div>
                <Link :href="route('product.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Batal
                    </Button>
                </Link>
            </div>

            <form class="grid gap-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column: General Info & Pricing -->
                    <div class="lg:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <div class="flex items-center gap-2">
                                    <Package class="h-5 w-5 text-primary" />
                                    <CardTitle>Informasi Dasar</CardTitle>
                                </div>
                                <CardDescription>
                                    Detail utama produk Anda.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="name">Nama Produk</Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        placeholder="Contoh: Indomie Goreng"
                                        autofocus
                                    />
                                    <InputError :message="form.errors.name" />
                                </div>

                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                >
                                    <div class="grid gap-2">
                                        <Label for="category">Kategori</Label>
                                        <Combobox v-model="category" by="label">
                                            <ComboboxAnchor class="w-full">
                                                <ComboboxTrigger
                                                    as-child
                                                    class="w-full"
                                                >
                                                    <Button
                                                        variant="outline"
                                                        class="w-full justify-between font-normal"
                                                    >
                                                        {{
                                                            category?.label ??
                                                            'Pilih kategori'
                                                        }}
                                                        <ChevronsUpDown
                                                            class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                                        />
                                                    </Button>
                                                </ComboboxTrigger>
                                            </ComboboxAnchor>
                                            <ComboboxList>
                                                <div
                                                    class="relative w-full items-center"
                                                >
                                                    <ComboboxInput
                                                        class="pl-9 h-10 w-full border-0 border-b rounded-none focus-visible:ring-0"
                                                        placeholder="Cari kategori..."
                                                    />
                                                    <Search
                                                        class="absolute left-3 top-3 size-4 text-muted-foreground"
                                                    />
                                                </div>
                                                <ComboboxEmpty>
                                                    Tidak ada kategori.
                                                </ComboboxEmpty>
                                                <ComboboxGroup>
                                                    <ComboboxItem
                                                        v-for="cat in categories"
                                                        :key="cat.value"
                                                        :value="cat"
                                                        class="cursor-pointer"
                                                    >
                                                        {{ cat.label }}
                                                        <ComboboxItemIndicator>
                                                            <Check
                                                                class="ml-auto h-4 w-4"
                                                            />
                                                        </ComboboxItemIndicator>
                                                    </ComboboxItem>
                                                </ComboboxGroup>
                                            </ComboboxList>
                                        </Combobox>
                                        <InputError
                                            :message="form.errors.category_id"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="unit">Satuan</Label>
                                        <Combobox v-model="unit" by="label">
                                            <ComboboxAnchor class="w-full">
                                                <ComboboxTrigger
                                                    as-child
                                                    class="w-full"
                                                >
                                                    <Button
                                                        variant="outline"
                                                        class="w-full justify-between font-normal"
                                                    >
                                                        {{
                                                            unit?.label ??
                                                            'Pilih satuan'
                                                        }}
                                                        <ChevronsUpDown
                                                            class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                                        />
                                                    </Button>
                                                </ComboboxTrigger>
                                            </ComboboxAnchor>
                                            <ComboboxList>
                                                <div
                                                    class="relative w-full items-center"
                                                >
                                                    <ComboboxInput
                                                        class="pl-9 h-10 w-full border-0 border-b rounded-none focus-visible:ring-0"
                                                        placeholder="Cari satuan..."
                                                    />
                                                    <Search
                                                        class="absolute left-3 top-3 size-4 text-muted-foreground"
                                                    />
                                                </div>
                                                <ComboboxEmpty>
                                                    Tidak ada satuan.
                                                </ComboboxEmpty>
                                                <ComboboxGroup>
                                                    <ComboboxItem
                                                        v-for="u in units"
                                                        :key="u.value"
                                                        :value="u"
                                                        class="cursor-pointer"
                                                    >
                                                        {{ u.label }}
                                                        <ComboboxItemIndicator>
                                                            <Check
                                                                class="ml-auto h-4 w-4"
                                                            />
                                                        </ComboboxItemIndicator>
                                                    </ComboboxItem>
                                                </ComboboxGroup>
                                            </ComboboxList>
                                        </Combobox>
                                        <InputError
                                            :message="form.errors.unit_id"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                >
                                    <div class="grid gap-2">
                                        <Label for="sku">SKU</Label>
                                        <Input
                                            id="sku"
                                            v-model="form.sku"
                                            type="text"
                                            placeholder="Contoh: SKU001"
                                        />
                                        <InputError
                                            :message="form.errors.sku"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="barcode">Barcode</Label>
                                        <div class="flex gap-2">
                                            <Input
                                                id="barcode"
                                                v-model="form.barcode"
                                                type="text"
                                                placeholder="Opsional"
                                                class="flex-1"
                                            />
                                            <Button
                                                aria-label="Scan barcode"
                                                type="button"
                                                variant="secondary"
                                                size="icon"
                                                title="Scan Barcode"
                                                @click="showScannerModal = true"
                                            >
                                                <ScanBarcode class="h-4 w-4" />
                                            </Button>
                                        </div>
                                        <InputError
                                            :message="form.errors.barcode"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div class="flex items-center gap-2">
                                    <Coins class="h-5 w-5 text-primary" />
                                    <CardTitle>Harga & Stok</CardTitle>
                                </div>
                                <CardDescription>
                                    Atur harga dan batas inventaris.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="grid gap-4">
                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                >
                                    <div class="grid gap-2">
                                        <Label for="purchase_price"
                                            >Harga Beli</Label
                                        >
                                        <div class="relative">
                                            <span
                                                class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"
                                                >Rp</span
                                            >
                                            <Input
                                                id="purchase_price"
                                                :model-value="
                                                    formatInput(
                                                        form.purchase_price,
                                                    )
                                                "
                                                type="text"
                                                class="pl-9 font-mono"
                                                @update:model-value="
                                                    (v) =>
                                                        (form.purchase_price =
                                                            parseInput(v))
                                                "
                                            />
                                        </div>
                                        <InputError
                                            :message="
                                                form.errors.purchase_price
                                            "
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="selling_price"
                                            >Harga Jual</Label
                                        >
                                        <div class="relative">
                                            <span
                                                class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"
                                                >Rp</span
                                            >
                                            <Input
                                                id="selling_price"
                                                :model-value="
                                                    formatInput(
                                                        form.selling_price,
                                                    )
                                                "
                                                type="text"
                                                class="pl-9 font-mono text-blue-600 dark:text-blue-400 font-bold"
                                                @update:model-value="
                                                    (v) =>
                                                        (form.selling_price =
                                                            parseInput(v))
                                                "
                                            />
                                        </div>
                                        <InputError
                                            :message="form.errors.selling_price"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="grid grid-cols-1 md:grid-cols-3 gap-4"
                                >
                                    <div class="grid gap-2">
                                        <Label for="stock">Stok Awal</Label>
                                        <Input
                                            id="stock"
                                            v-model="form.stock"
                                            type="number"
                                            min="0"
                                        />
                                        <InputError
                                            :message="form.errors.stock"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="alert_stock"
                                            >Stok Minimum</Label
                                        >
                                        <Input
                                            id="alert_stock"
                                            v-model="form.alert_stock"
                                            type="number"
                                            min="0"
                                        />
                                        <InputError
                                            :message="form.errors.alert_stock"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="expiry_date"
                                            >Tgl Kedaluwarsa</Label
                                        >
                                        <DatePicker
                                            id="expiry_date"
                                            v-model="expiryDate"
                                            placeholder="Opsional"
                                        />
                                        <InputError
                                            :message="form.errors.expiry_date"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Right Column: Image -->
                    <div class="space-y-6">
                        <Card class="overflow-hidden">
                            <CardHeader>
                                <div class="flex items-center gap-2">
                                    <ImageIcon class="h-5 w-5 text-primary" />
                                    <CardTitle>Gambar Produk</CardTitle>
                                </div>
                                <CardDescription>
                                    Gunakan gambar yang jelas.
                                </CardDescription>
                            </CardHeader>
                            <CardContent
                                class="flex flex-col items-center gap-4"
                            >
                                <div
                                    class="relative w-full aspect-square rounded-lg border-2 border-dashed border-muted-foreground/25 flex items-center justify-center overflow-hidden bg-muted/50 group"
                                >
                                    <img
                                        v-if="cropImg"
                                        :src="cropImg"
                                        class="w-full h-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex flex-col items-center gap-2 text-muted-foreground"
                                    >
                                        <ImageIcon
                                            class="h-10 w-10 opacity-50"
                                        />
                                        <span class="text-xs"
                                            >Belum ada gambar</span
                                        >
                                    </div>

                                    <div
                                        class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <Label
                                            for="image"
                                            class="cursor-pointer bg-white dark:bg-zinc-900 px-3 py-1.5 rounded-md text-xs font-medium"
                                        >
                                            Pilih Gambar
                                        </Label>
                                    </div>
                                    <input
                                        id="image"
                                        type="file"
                                        class="hidden"
                                        accept="image/*"
                                        @change="setImage"
                                    />
                                </div>
                                <p
                                    class="text-[10px] text-muted-foreground text-center"
                                >
                                    Rekomendasi: 1:1 (Kotak), Max 2MB.
                                </p>
                                <InputError :message="form.errors.image" />
                            </CardContent>
                        </Card>

                        <div class="flex flex-col gap-3">
                            <Button
                                type="submit"
                                class="w-full h-11 text-base font-semibold"
                                :disabled="form.processing"
                            >
                                <Loader2
                                    v-if="form.processing"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                Simpan Produk
                            </Button>
                            <Link :href="route('product.index')" class="w-full">
                                <Button
                                    variant="ghost"
                                    class="w-full"
                                    type="button"
                                >
                                    Kembali ke Daftar
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Modals -->
            <Dialog v-model:open="showCropperModal">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Sesuaikan Gambar</DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-4">
                        <div class="overflow-hidden rounded-md border">
                            <vue-cropper
                                ref="cropper"
                                :src="imgSrc"
                                :guides="true"
                                :view-mode="2"
                                drag-mode="crop"
                                :auto-crop-area="0.5"
                                :background="true"
                                :rotatable="true"
                                :aspect-ratio="1 / 1"
                                :img-style="{
                                    width: '100%',
                                    maxHeight: '400px',
                                }"
                            />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                variant="outline"
                                @click="showCropperModal = false"
                            >
                                Batal
                            </Button>
                            <Button @click="handleCrop">
                                Simpan Potongan
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="showScannerModal">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Scan Barcode</DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-4">
                        <div
                            id="reader"
                            class="overflow-hidden rounded-md border"
                        />
                        <Button
                            variant="outline"
                            @click="showScannerModal = false"
                        >
                            Tutup
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AuthenticatedLayout>
</template>
