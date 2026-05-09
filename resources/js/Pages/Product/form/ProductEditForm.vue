<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { nextTick, ref, watch, computed } from 'vue';
import { cn } from '@/lib/utils';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import VueCropper from 'vue-cropperjs/VueCropper.js';
import 'cropperjs/dist/cropper.css';
import { Html5QrcodeScanner } from 'html5-qrcode';
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
    Warehouse,
    TrendingUp,
    AlertCircle,
    ExternalLink,
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
    product: {
        type: Object,
        required: true,
    },
});

const findOption = (options, value) => {
    return options.find((opt) => opt.value === value);
};

const category = ref(findOption(props.categories, props.product.category_id));
const unit = ref(findOption(props.units, props.product.unit_id));
const showCropperModal = ref(false);

watch(category, (newValue) => {
    form.category_id = newValue?.value || null;
});

watch(unit, (newValue) => {
    form.unit_id = newValue?.value || null;
});

const form = useForm({
    category_id: props.product.category_id,
    name: props.product.name,
    sku: props.product.sku,
    barcode: props.product.barcode,
    unit_id: props.product.unit_id,
    purchase_price: props.product.purchase_price,
    selling_price: props.product.selling_price,
    alert_stock: props.product.alert_stock,
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

const user = usePage().props.auth.user.name;
const isLoading = ref(false);

const submit = () => {
    isLoading.value = true;

    if (!(form.image instanceof File)) {
        form.image = null;
    }

    router.post(
        route('product.update', props.product.slug),
        {
            _method: 'patch',
            ...form.data(),
        },
        {
            preserveScroll: true,
            showProgress: false,
            onSuccess: () => {
                toast.success('Produk diperbarui', {
                    description: `Produk ${form.name} berhasil diperbarui oleh ${user}`,
                });
                showCropperModal.value = false;
                isLoading.value = false;
            },
            onError: (e) => {
                toast.error('Gagal memperbarui produk', {
                    description: 'Silakan periksa kembali formulir Anda.',
                });
                isLoading.value = false;
                console.error(e);
            },
        },
    );
};

const imagePreview = computed(() => {
    if (cropImg.value) return cropImg.value;
    if (form.image && form.image instanceof File) {
        return URL.createObjectURL(form.image);
    }
    return props.product.image_path ? `/${props.product.image_path}` : null;
});

const expiryDateFormatted = computed(() => {
    if (!props.product.expiry_date) return null;
    return new Date(props.product.expiry_date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
});
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Edit Produk</title>
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
                        <BreadcrumbPage> Edit Produk </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <div class="flex flex-1 flex-col gap-6 p-6 w-full">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">
                        Edit Produk
                    </h2>
                    <p class="text-muted-foreground">
                        Perbarui informasi untuk produk
                        <span class="font-medium text-foreground">{{
                            product.name
                        }}</span
                        >.
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
                    <!-- Left Column: General Info & Inventory -->
                    <div class="lg:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <div class="flex items-center gap-2">
                                    <Package class="h-5 w-5 text-primary" />
                                    <CardTitle>Informasi Dasar</CardTitle>
                                </div>
                                <CardDescription>
                                    Detail utama identitas produk.
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
                                    <Warehouse class="h-5 w-5 text-primary" />
                                    <CardTitle>Inventaris</CardTitle>
                                </div>
                                <CardDescription>
                                    Pantau stok dan atur peringatan.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="grid gap-6">
                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-6"
                                >
                                    <div
                                        class="p-4 rounded-lg bg-muted/50 border flex flex-col gap-1"
                                    >
                                        <Label
                                            class="text-xs text-muted-foreground uppercase tracking-wider"
                                            >Stok Saat Ini</Label
                                        >
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-3xl font-bold">{{
                                                product.stock
                                            }}</span>
                                            <span
                                                class="text-muted-foreground"
                                                >{{ unit?.label }}</span
                                            >
                                        </div>
                                        <p
                                            class="text-[10px] text-muted-foreground mt-1"
                                        >
                                            Ubah stok melalui menu
                                            <strong>Stock Opname</strong>.
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="alert_stock"
                                            >Stok Minimum (Peringatan)</Label
                                        >
                                        <Input
                                            id="alert_stock"
                                            v-model="form.alert_stock"
                                            type="number"
                                            min="0"
                                            class="h-12"
                                        />
                                        <p
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            Sistem akan memberi peringatan jika
                                            stok di bawah angka ini.
                                        </p>
                                        <InputError
                                            :message="form.errors.alert_stock"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="p-4 rounded-lg border flex items-start gap-3"
                                    :class="
                                        product.expiry_date
                                            ? 'bg-amber-50 dark:bg-amber-900/10 border-amber-100 dark:border-amber-900/30'
                                            : 'bg-muted/30 border-dashed'
                                    "
                                >
                                    <AlertCircle
                                        class="h-5 w-5 text-amber-600 dark:text-amber-500 mt-0.5 shrink-0"
                                    />
                                    <div class="grid gap-1">
                                        <span
                                            class="text-sm font-semibold text-amber-900 dark:text-amber-100"
                                            >Tanggal Kedaluwarsa Terdekat</span
                                        >
                                        <p
                                            class="text-xs text-amber-800 dark:text-amber-200 leading-relaxed"
                                        >
                                            <span
                                                v-if="expiryDateFormatted"
                                                class="font-mono font-bold"
                                                >{{ expiryDateFormatted }}</span
                                            >
                                            <span
                                                v-else
                                                class="italic opacity-70"
                                                >Belum ada data
                                                kedaluwarsa.</span
                                            >
                                            <br />
                                            Diperbarui otomatis berdasarkan
                                            batch pembelian (FEFO). Ubah tanggal
                                            di menu <strong>Pembelian</strong>.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Right Column: Image & Pricing Link -->
                    <div class="space-y-6">
                        <Card class="overflow-hidden border-primary/20">
                            <CardHeader>
                                <div class="flex items-center gap-2">
                                    <TrendingUp class="h-5 w-5 text-primary" />
                                    <CardTitle>Manajemen Harga</CardTitle>
                                </div>
                                <CardDescription>
                                    Harga beli & jual dikelola secara terpisah.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="pt-6 grid gap-4 border-t">
                                <div class="grid grid-cols-2 gap-4 text-center">
                                    <div class="grid gap-1">
                                        <span
                                            class="text-[10px] text-muted-foreground uppercase"
                                        >
                                            Beli
                                        </span>
                                        <span
                                            class="font-mono font-medium truncate"
                                            >{{
                                                new Intl.NumberFormat('id-ID', {
                                                    style: 'currency',
                                                    currency: 'IDR',
                                                    maximumFractionDigits: 0,
                                                }).format(
                                                    product.purchase_price,
                                                )
                                            }}</span
                                        >
                                    </div>
                                    <div class="grid gap-1 border-l">
                                        <span
                                            class="text-[10px] text-muted-foreground uppercase"
                                            >Jual</span
                                        >
                                        <span
                                            class="font-mono font-bold text-blue-600 dark:text-blue-400 truncate"
                                            >{{
                                                new Intl.NumberFormat('id-ID', {
                                                    style: 'currency',
                                                    currency: 'IDR',
                                                    maximumFractionDigits: 0,
                                                }).format(product.selling_price)
                                            }}</span
                                        >
                                    </div>
                                </div>
                                <Link
                                    :href="
                                        route(
                                            'product.price.edit',
                                            product.slug,
                                        )
                                    "
                                    class="w-full"
                                >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="w-full gap-2"
                                    >
                                        Update Harga
                                        <ExternalLink class="h-3 w-3" />
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>

                        <Card class="overflow-hidden">
                            <CardHeader>
                                <div class="flex items-center gap-2">
                                    <ImageIcon class="h-5 w-5 text-primary" />
                                    <CardTitle>Gambar Produk</CardTitle>
                                </div>
                                <CardDescription>
                                    Foto produk saat ini.
                                </CardDescription>
                            </CardHeader>
                            <CardContent
                                class="flex flex-col items-center gap-4"
                            >
                                <div
                                    class="relative w-full aspect-square rounded-lg border-2 border-dashed border-muted-foreground/25 flex items-center justify-center overflow-hidden bg-muted/50 group"
                                >
                                    <img
                                        v-if="imagePreview"
                                        :src="imagePreview"
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
                                            >Tidak ada gambar</span
                                        >
                                    </div>

                                    <div
                                        class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <Label
                                            for="image"
                                            class="cursor-pointer bg-white dark:bg-zinc-900 px-3 py-1.5 rounded-md text-xs font-medium"
                                        >
                                            Ganti Gambar
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

                        <div class="flex flex-col gap-3 pt-2">
                            <Button
                                type="submit"
                                class="w-full h-11 text-base font-semibold"
                                :disabled="isLoading"
                            >
                                <Loader2
                                    v-if="isLoading"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                Simpan Perubahan
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
