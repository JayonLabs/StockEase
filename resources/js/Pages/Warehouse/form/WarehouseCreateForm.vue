<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, Plus } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { toast } from 'vue-sonner';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
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

const form = useForm({
    name: '',
    phone: '',
    address: '',
});

const user = usePage().props.auth.user.name;
const isDialogOpen = ref(false);

const submit = () => {
    form.post(route('warehouse.store'), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toast.success('Gudang berhasil ditambahkan', {
                description: `Gudang ${form.name} berhasil ditambahkan oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Gudang gagal ditambahkan');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button variant="outline" class="dark:border-white border-zinc-600">
                <Plus />
                Tambah Gudang
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Form tambah gudang</DialogTitle>
                <DialogDescription>
                    Silahkan isi form dibawah ini untuk menambahkan gudang
                </DialogDescription>
            </DialogHeader>
            <form id="form" class="space-y-4" @submit.prevent="submit">
                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="name"> Nama Gudang </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Contoh: Gudang Pusat"
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
