<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, RotateCcw, Trash2, Eye } from 'lucide-vue-next';
import { Link, router, usePage } from '@inertiajs/vue3';
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

const user = usePage().props.auth.user.name;
const isRestoring = ref(false);
const isForceDeleting = ref(false);
const isRestoreOpen = ref(false);
const isForceDeleteOpen = ref(false);

const handleRestore = () => {
    isRestoring.value = true;

    router.post(
        route('trash.restore'),
        {
            type: props.row.type,
            id: props.row.id,
        },
        {
            preserveScroll: true,
            showProgress: false,
            onSuccess: () => {
                toast.success(`${props.row.type_label} berhasil dipulihkan`, {
                    description: `${props.row.name} berhasil dipulihkan oleh ${user}`,
                });
            },
            onError: () => {
                toast.error(`${props.row.type_label} gagal dipulihkan`);
            },
            onFinish: () => {
                isRestoring.value = false;
                isRestoreOpen.value = false;
            },
        },
    );
};

const handleForceDelete = () => {
    isForceDeleting.value = true;

    router.delete(route('trash.force-destroy'), {
        data: {
            type: props.row.type,
            id: props.row.id,
        },
        preserveScroll: true,
        showProgress: false,
        onSuccess: () => {
            toast.success(`${props.row.type_label} berhasil dihapus permanen`, {
                description: `${props.row.name} berhasil dihapus permanen oleh ${user}`,
            });
        },
        onError: () => {
            toast.error(`${props.row.type_label} gagal dihapus`);
        },
        onFinish: () => {
            isForceDeleting.value = false;
            isForceDeleteOpen.value = false;
        },
    });
};
</script>

<template>
    <div class="flex items-center justify-center gap-1">
        <!-- Show Button -->
        <Link :href="route('trash.show', { type: row.type, id: row.id })">
            <Button
                variant="ghost"
                size="icon"
                class="dark:hover:bg-blue-900 hover:bg-blue-500 group"
            >
                <Eye
                    class="w-4 h-4 text-blue-500 dark:group-hover:text-white group-hover:text-black"
                />
            </Button>
        </Link>

        <!-- Restore Button + Dialog -->
        <AlertDialog v-model:open="isRestoreOpen">
            <AlertDialogTrigger>
                <Button
                    variant="ghost"
                    size="icon"
                    class="dark:hover:bg-green-900 hover:bg-green-500 group"
                >
                    <RotateCcw
                        class="w-4 h-4 text-green-500 dark:group-hover:text-white group-hover:text-black"
                    />
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle> Pulihkan data ini? </AlertDialogTitle>
                    <AlertDialogDescription>
                        Data <strong>{{ row.name }}</strong> ({{
                            row.type_label
                        }}) akan dikembalikan ke tempat semula.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-green-500 hover:bg-green-600 text-white"
                        @click="handleRestore"
                    >
                        <Loader2
                            v-if="isRestoring"
                            class="w-4 h-4 animate-spin"
                        />
                        {{ isRestoring ? 'Memulihkan...' : 'Pulihkan' }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Force Delete Button + Dialog -->
        <AlertDialog v-model:open="isForceDeleteOpen">
            <AlertDialogTrigger>
                <Button
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
                    <AlertDialogTitle> Hapus permanen? </AlertDialogTitle>
                    <AlertDialogDescription>
                        Data <strong>{{ row.name }}</strong> ({{
                            row.type_label
                        }}) akan dihapus secara permanen. Tindakan ini
                        <strong>tidak dapat dibatalkan</strong>!
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-red-500 hover:bg-red-600 text-white"
                        @click="handleForceDelete"
                    >
                        <Loader2
                            v-if="isForceDeleting"
                            class="w-4 h-4 animate-spin"
                        />
                        {{
                            isForceDeleting ? 'Menghapus...' : 'Hapus Permanen'
                        }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
