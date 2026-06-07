<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, Pencil } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { toast } from 'vue-sonner';
import { Textarea } from '@/Components/ui/textarea';
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
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    row: { type: Object, required: true },
});

watch(
    () => props.row,
    () => {
        form.name = props.row.name;
        form.phone = props.row.phone;
        form.address = props.row.address;
    },
);

const form = useForm({
    name: props.row.name,
    phone: props.row.phone,
    address: props.row.address,
});

const user = usePage().props.auth.user.name;
const isDialogOpen = ref(false);

const submit = () => {
    form.put(route('supplier.update', props.row.slug), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Supplier berhasil diperbarui', {
                description: `Supplier ${form.name} berhasil diperbarui oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Supplier gagal diperbarui');
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
                <DialogTitle>Form update supplier</DialogTitle>
                <DialogDescription>
                    Silahkan isi form dibawah ini untuk mengupdate supplier
                </DialogDescription>
            </DialogHeader>
            <form id="form" class="space-y-4" @submit.prevent="submit">
                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="name"> Nama supplier </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Masukkan nama supplier"
                            type="text"
                            required
                            autocomplete="off"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="phone"> Nomor Telepon </Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            placeholder="Masukkan nomor telepon"
                            type="text"
                            inputmode="numeric"
                            required
                            autocomplete="off"
                            class="appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                        />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="address"> Alamat supplier </Label>
                        <Textarea
                            id="address"
                            v-model="form.address"
                            placeholder="Masukkan alamat supplier"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.address"
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
