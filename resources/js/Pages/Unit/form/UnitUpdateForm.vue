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
        form.short_name = props.row.short_name;
    },
);

const form = useForm({
    name: props.row.name,
    short_name: props.row.short_name,
});

const user = usePage().props.auth.user.name;

const isDialogOpen = ref(false);

const submit = () => {
    form.put(route('unit.update', props.row.slug), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toast.success('Satuan berhasil diupdate', {
                description: `Satuan ${form.name} berhasil diupdate oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Satuan gagal diupdate');
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
                <DialogTitle>Form update satuan</DialogTitle>
                <DialogDescription>
                    Silahkan isi form dibawah ini untuk mengupdate satuan
                </DialogDescription>
            </DialogHeader>
            <form id="form" class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name"> Nama Satuan </Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Contoh: Kilogram"
                        type="text"
                        required
                        autocomplete="off"
                    />
                    <span
                        v-if="form.errors.name"
                        class="text-sm text-red-500"
                        >{{ form.errors.name }}</span
                    >
                </div>
                <div class="grid gap-2">
                    <Label for="short_name"> Singkatan </Label>
                    <Input
                        id="short_name"
                        v-model="form.short_name"
                        placeholder="Contoh: kg"
                        type="text"
                        required
                        autocomplete="off"
                    />
                    <span
                        v-if="form.errors.short_name"
                        class="text-sm text-red-500"
                        >{{ form.errors.short_name }}</span
                    >
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
