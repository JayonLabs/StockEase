<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, Trash2 } from 'lucide-vue-next';
import WarehouseUpdateForm from '../form/WarehouseUpdateForm.vue';
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
});

const isLoading = ref(false);
const isDialogOpen = ref(false);
const user = usePage().props.auth.user.name;

const destroy = (slug) => {
    isLoading.value = true;
    isDialogOpen.value = true;

    router.delete(route('warehouse.destroy', slug), {
        preserveScroll: true,
        showProgress: false,
        onSuccess: () => {
            toast.success('Gudang berhasil dihapus', {
                description: `Gudang ${props.row.name} berhasil dihapus oleh ${user}`,
            });
        },
        onError: () => {
            toast.error('Gudang gagal dihapus');
        },
        onFinish: () => {
            isLoading.value = false;
            isDialogOpen.value = false;
        },
    });
};
</script>

<template>
    <div class="flex items-center justify-center">
        <WarehouseUpdateForm :row="row" />

        <AlertDialog v-model:open="isDialogOpen">
            <AlertDialogTrigger>
                <Button
                    aria-label="Hapus"
                    variant="ghost"
                    size="icon"
                    class="dark:hover:bg-red-900 hover:bg-red-500 group"
                >
                    <Trash2
                        class="w-4 h-4 text-red-500 dark:group-hover:text-white group-hover:text-black"
                    />
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Apakah anda yakin ingin menghapus?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        Data akan dipindahkan ke <strong>Sampah</strong> dan
                        dapat dipulihkan kembali.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-red-500 hover:bg-red-600 text-white"
                        @click="destroy(row.slug)"
                    >
                        <Loader2
                            v-if="isLoading"
                            class="w-4 h-4 animate-spin"
                        />
                        {{ isLoading ? 'Loading...' : 'Hapus' }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
