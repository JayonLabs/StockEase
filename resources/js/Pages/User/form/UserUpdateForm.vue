<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, Pencil } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useForm, usePage } from '@inertiajs/vue3';
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

import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import UserResetPasswordForm from './UserResetPasswordForm.vue';

const props = defineProps({
    row: { type: Object, required: true },
});

watch(
    () => props.row,
    () => {
        form.name = props.row.name;
        form.email = props.row.email;
        form.role = props.row.role;
    },
);

const form = useForm({
    name: props.row.name,
    email: props.row.email,
    role: props.row.role,
});

const user = usePage().props.auth.user.name;
const isDialogOpen = ref(false);

const roles = [
    { value: 'super_admin', label: 'Super Admin' },
    { value: 'admin', label: 'Admin' },
    { value: 'cashier', label: 'Kasir' },
    { value: 'warehouse', label: 'Gudang' },
];

const submit = (id) => {
    form.put(route('users.update', id), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            toast.success('User berhasil diperbarui', {
                description: `User ${form.name} berhasil diperbarui oleh ${user}`,
            });
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('User gagal diperbarui');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button variant="ghost" size="icon" class="group">
                <Pencil
                    class="w-4 h-4 text-blue-500 dark:group-hover:text-white"
                />
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Form tambah user</DialogTitle>
                <DialogDescription>
                    Silahkan isi form dibawah ini untuk menambahkan user
                </DialogDescription>
            </DialogHeader>
            <form id="form" class="space-y-4" @submit.prevent="submit(row.id)">
                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="name"> Nama user </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Masukkan nama user"
                            type="text"
                            required
                            autocomplete="off"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="email"> Email </Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            placeholder="Masukkan email user"
                            type="email"
                            disabled
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <div class="grid flex-1 gap-2">
                        <Label for="role"> Role </Label>
                        <Select v-model="form.role">
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Role</SelectLabel>
                                    <SelectItem
                                        v-for="role in roles"
                                        :key="role.value"
                                        class="capitalize cursor-pointer"
                                        :value="role.value"
                                    >
                                        {{ role.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError class="mt-2" :message="form.errors.role" />
                    </div>
                </div>
            </form>
            <DialogFooter class="flex">
                <div class="flex items-start justify-start grow">
                    <UserResetPasswordForm :row="row" />
                </div>

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
