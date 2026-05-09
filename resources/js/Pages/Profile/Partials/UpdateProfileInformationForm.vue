<script setup>
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

import { useForm, usePage } from '@inertiajs/vue3';
import { Loader2, Pencil } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

defineProps({
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        showProgress: false,
        onSuccess: () => {
            toast.success('Profil berhasil diperbarui', {
                description: `Profil ${form.name} berhasil diperbarui oleh ${user}`,
            });
        },
    });
};
</script>

<template>
    <div
        class="mb-6 rounded-2xl border border-gray-200 p-5 lg:p-6 dark:border-gray-800"
    >
        <div
            class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between"
        >
            <div class="w-full">
                <div class="mb-6">
                    <h2 class="text-lg font-medium">Informasi Pribadi</h2>

                    <p class="mt-1 text-muted-foreground text-sm">
                        Perbarui informasi profil akun dan alamat email Anda.
                    </p>
                </div>

                <form
                    id="update-profile-information-form"
                    class="w-full"
                    @submit.prevent="submit"
                >
                    <div
                        class="grid grid-cols-1 gap-4 lg:grid-cols-2 w-full mt-4"
                    >
                        <div class="w-full">
                            <Label for="name">Nama</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="Nama"
                                required
                                autocomplete="off"
                                class="w-full h-11 py-3 rounded-lg border"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="w-full">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="text"
                                placeholder="Email"
                                required
                                autocomplete="off"
                                class="w-full h-11 py-3 rounded-lg border"
                            />
                            <InputError :message="form.errors.email" />
                        </div>
                    </div>
                </form>
            </div>

            <Button
                variant="secondary"
                :disable="form.processing"
                form="update-profile-information-form"
                class="shadow-theme-xs flex w-full items-center justify-center gap-2 rounded-full border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 lg:inline-flex lg:w-auto dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/3 dark:hover:text-gray-200 disabled:pointer-events-none disabled:opacity-50"
            >
                <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                <Pencil v-else class="w-4 h-4" />
                Edit
            </Button>
        </div>
    </div>
</template>
