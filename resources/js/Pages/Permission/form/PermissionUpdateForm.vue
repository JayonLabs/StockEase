<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, Pencil } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import { toast } from 'vue-sonner';

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

const props = defineProps({
    row: { type: Object, required: true },
});

watch(
    () => props.row,
    () => {
        form.name = props.row.name;
    },
);

const form = useForm({
    name: props.row.name,
});

const isDialogOpen = ref(false);

const submit = (id) => {
    form.put(route('permissions.update', id), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Permission berhasil diperbarui');
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Permission gagal diperbarui');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button aria-label="Ubah" variant="ghost" size="icon" class="group">
                <Pencil
                    class="w-4 h-4 text-blue-500 dark:group-hover:text-white"
                />
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Form edit permission</DialogTitle>
                <DialogDescription>
                    Silahkan ubah nama permission
                </DialogDescription>
            </DialogHeader>
            <form
                id="perm-edit-form"
                class="space-y-4"
                @submit.prevent="submit(row.id)"
            >
                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="name"> Nama permission </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Masukkan nama permission"
                            type="text"
                            required
                            autocomplete="off"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                </div>
            </form>
            <DialogFooter class="flex justify-between">
                <DialogClose as-child>
                    <Button type="button" variant="secondary"> Batal </Button>
                </DialogClose>

                <Button
                    type="submit"
                    form="perm-edit-form"
                    :class="{ 'opacity-25 ': form.processing }"
                    :disabled="form.processing"
                    class="disabled:cursor-not-allowed"
                >
                    <Loader2
                        v-if="form.processing"
                        class="w-4 h-4 animate-spin"
                    />
                    {{ form.processing ? 'Loading...' : 'Simpan' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
