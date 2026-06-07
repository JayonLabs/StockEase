<script setup>
import { Button } from '@/Components/ui/button';
import {
    Eye,
    Loader2,
    Pencil,
    Trash2,
    Banknote,
    MoreHorizontal,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { toast } from 'vue-sonner';

const props = defineProps({
    row: { type: Object, required: true },
});

const isDialogOpen = ref(false);
const isLoading = ref(false);

const user = usePage().props.auth.user.name;

// Fixed destroy function parameters to match actual use
const handleDelete = () => {
    isLoading.value = true;

    router.delete(route('product.destroy', props.row.slug), {
        preserveScroll: true,
        showProgress: false,
        onSuccess: () => {
            toast.success('Produk dihapus', {
                description: `Produk ${props.row.name} berhasil dihapus oleh ${user}`,
            });
        },
        onError: () => {
            toast.error('Gagal menghapus produk');
        },
        onFinish: () => {
            isLoading.value = false;
            isDialogOpen.value = false;
        },
    });
};
</script>

<template>
    <div class="flex items-center justify-center gap-1">
        <Link :href="route('product.show', row.slug)">
            <Button
                aria-label="Lihat detail"
                variant="ghost"
                size="icon"
                class="h-8 w-8 text-green-600 hover:text-green-700 hover:bg-green-50 dark:hover:bg-green-900/20"
                title="Detail"
            >
                <Eye class="h-4 w-4" />
            </Button>
        </Link>

        <Link :href="route('product.edit', row.slug)">
            <Button
                aria-label="Ubah"
                variant="ghost"
                size="icon"
                class="h-8 w-8 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                title="Edit"
            >
                <Pencil class="h-4 w-4" />
            </Button>
        </Link>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    aria-label="Opsi lanjutan"
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8"
                >
                    <MoreHorizontal class="h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-48">
                <DropdownMenuLabel>Opsi Lanjutan</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <Link :href="route('product.price.edit', row.slug)">
                    <DropdownMenuItem class="cursor-pointer gap-2">
                        <Banknote class="h-4 w-4 text-orange-500" />
                        Update Harga
                    </DropdownMenuItem>
                </Link>
                <DropdownMenuSeparator />
                <AlertDialog v-model:open="isDialogOpen">
                    <AlertDialogTrigger as-child>
                        <DropdownMenuItem
                            class="cursor-pointer gap-2 text-red-600 focus:text-red-600 focus:bg-red-50 dark:focus:bg-red-900/20"
                            @select.prevent
                        >
                            <Trash2 class="h-4 w-4" />
                            Hapus Produk
                        </DropdownMenuItem>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Hapus Produk?</AlertDialogTitle>
                            <AlertDialogDescription>
                                Apakah Anda yakin ingin menghapus product
                                <span class="font-bold text-foreground">{{
                                    row.name
                                }}</span
                                >? Data akan dipindahkan ke Sampah dan dapat
                                dipulihkan kembali.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Batal</AlertDialogCancel>
                            <AlertDialogAction
                                class="bg-red-600 hover:bg-red-700 text-white"
                                :disabled="isLoading"
                                @click="handleDelete"
                            >
                                <Loader2
                                    v-if="isLoading"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                Hapus
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
