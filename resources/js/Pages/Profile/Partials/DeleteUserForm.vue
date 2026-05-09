<script setup>
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { Loader2, Trash2 } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

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

const isOpen = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const openModal = () => {
    isOpen.value = true;
    nextTick(() => passwordInput.value?.$el?.querySelector('input')?.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            isOpen.value = false;
        },
        onError: () => {
            passwordInput.value?.$el?.querySelector('input')?.focus();
        },
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <div
        class="mb-6 rounded-2xl border border-red-200 p-4 dark:border-red-900/50"
    >
        <div
            class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between"
        >
            <div class="w-full">
                <div>
                    <h2
                        class="text-lg font-medium text-red-700 dark:text-red-400"
                    >
                        Hapus Akun
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Setelah akun Anda dihapus, semua data akan dihapus
                        secara permanen. Sebelum menghapus, pastikan Anda telah
                        menyimpan data yang diperlukan.
                    </p>
                </div>
            </div>

            <AlertDialog v-model:open="isOpen">
                <AlertDialogTrigger as-child>
                    <Button
                        variant="destructive"
                        class="gap-2"
                        @click="openModal"
                    >
                        <Trash2 class="h-4 w-4" />
                        Hapus Akun
                    </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle> Hapus akun Anda? </AlertDialogTitle>
                        <AlertDialogDescription>
                            Setelah akun Anda dihapus, semua data akan dihapus
                            secara permanen. Masukkan kata sandi Anda untuk
                            mengonfirmasi penghapusan akun.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    <div class="mt-4">
                        <Label for="password">Kata Sandi</Label>
                        <Input
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="mt-1"
                            placeholder="Masukkan kata sandi"
                            autocomplete="current-password"
                            @keyup.enter="deleteUser"
                        />
                        <p
                            v-if="form.errors.password"
                            class="text-sm text-red-500 mt-1"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <AlertDialogFooter class="mt-6">
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            class="bg-red-600 hover:bg-red-700 text-white"
                            :disabled="form.processing"
                            @click="deleteUser"
                        >
                            <Loader2
                                v-if="form.processing"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            {{
                                form.processing ? 'Menghapus...' : 'Hapus Akun'
                            }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </div>
</template>
