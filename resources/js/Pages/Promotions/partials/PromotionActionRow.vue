<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, Trash2 } from 'lucide-vue-next';
import PromotionUpdateForm from '../form/PromotionUpdateForm.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

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

const props = defineProps({
    row: { type: Object, required: true },
    categories: { type: Array, required: true },
    products: { type: Array, required: true },
});

const isLoading = ref(false);
const isAlertOpen = ref(false);
const user = usePage().props.auth.user.name;

const destroy = () => {
    isLoading.value = true;

    router.delete(route('promotions.destroy', props.row.id), {
        preserveScroll: true,
        showProgress: false,
        onSuccess: () => {
            toast.success('Promo berhasil dihapus', {
                description: `Promo ${props.row.name} berhasil dihapus oleh ${user}`,
            });
        },
        onError: () => {
            toast.error('Promo gagal dihapus');
        },
        onFinish: () => {
            isLoading.value = false;
            isAlertOpen.value = false;
        },
    });
};
</script>

<template>
    <div class="flex items-center justify-center gap-1">
        <PromotionUpdateForm
            :row="row"
            :categories="categories"
            :products="products"
        />

        <AlertDialog v-model:open="isAlertOpen">
            <AlertDialogTrigger as-child>
                <Button
                    aria-label="Hapus"
                    variant="ghost"
                    size="icon"
                    class="group dark:hover:bg-red-900 hover:bg-red-100"
                >
                    <Trash2
                        class="h-4 w-4 text-red-500 dark:group-hover:text-white group-hover:text-red-700"
                    />
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Hapus Promo?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Apakah Anda yakin ingin menghapus promo
                        <strong>{{ row.name }}</strong
                        >? Data akan dipindahkan ke Sampah dan dapat dipulihkan
                        kembali.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-red-500 hover:bg-red-600 text-white"
                        @click="destroy"
                    >
                        <Loader2
                            v-if="isLoading"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        {{ isLoading ? 'Menghapus...' : 'Hapus' }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
