<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, Pencil } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { toast } from 'vue-sonner';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/Components/InputError.vue';

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
    form.put(route('warehouse.update', props.row.slug), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Gudang berhasil diperbarui', {
                description: `Gudang ${form.name} berhasil diperbarui oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Gudang gagal diperbarui');
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
                <DialogTitle>Form update gudang</DialogTitle>
                <DialogDescription>
                    Silahkan isi form dibawah ini untuk mengupdate gudang
                </DialogDescription>
            </DialogHeader>
            <form id="form" class="space-y-4" @submit.prevent="submit">
                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="name"> Nama Gudang </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Masukkan nama gudang"
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
                            placeholder="Contoh: 021-1234567"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            class="appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                        />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="address"> Alamat Gudang </Label>
                        <Textarea
                            id="address"
                            v-model="form.address"
                            placeholder="Masukkan alamat gudang"
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
