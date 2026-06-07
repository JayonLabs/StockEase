<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, Pencil } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
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
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

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

const user = usePage().props.auth.user.name;

const isDialogOpen = ref(false);

const submit = () => {
    form.put(route('category.update', props.row.slug), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toast.success('Kategori berhasil diupdate', {
                description: `Kategori ${form.name} berhasil diupdate oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Kategori gagal diupdate');
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
                <DialogTitle>Form update kategori</DialogTitle>
                <DialogDescription>
                    Silahkan isi form dibawah ini untuk mengupdate kategori
                </DialogDescription>
            </DialogHeader>
            <form id="form" @submit.prevent="submit">
                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="name"> Nama Kategori </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Masukkan nama kategori"
                            type="text"
                            required
                            autocomplete="off"
                        />
                    </div>
                </div>
            </form>
            <DialogFooter class="flex justify-between">
                <DialogClose as-child>
                    <Button type="button" variant="secondary"> Batal </Button>
                </DialogClose>

                <Button
                    type="submit"
                    form="form"
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
