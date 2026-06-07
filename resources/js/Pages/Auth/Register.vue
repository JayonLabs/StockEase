<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Eye, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_name: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head>
            <title>Daftar</title>
        </Head>
        <div class="flex flex-col gap-6">
            <Card>
                <CardHeader class="text-center">
                    <CardTitle class="text-xl">Daftar Akun</CardTitle>
                    <CardDescription>
                        Buat akun untuk mulai menggunakan StockEase
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="company_name">Nama Toko / Usaha</Label>
                            <Input
                                id="company_name"
                                v-model="form.company_name"
                                type="text"
                                placeholder="Toko Makmur Jaya"
                                required
                                autofocus
                                autocomplete="organization"
                            />
                            <InputError :message="form.errors.company_name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="name">Nama Lengkap</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="Budi Santoso"
                                required
                                autocomplete="name"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="budi@tokomakmur.com"
                                required
                                autocomplete="email"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password">Kata Sandi</Label>
                            <div class="relative">
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Minimal 8 karakter"
                                    required
                                    autocomplete="new-password"
                                />
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    @click="showPassword = !showPassword"
                                >
                                    <Eye class="w-4 h-4" />
                                </button>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation">Konfirmasi Kata Sandi</Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Ulangi kata sandi"
                                required
                                autocomplete="new-password"
                            />
                            <InputError :message="form.errors.password_confirmation" />
                        </div>

                        <Button type="submit" class="w-full" :disabled="form.processing">
                            <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin mr-2" />
                            Daftar
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <div class="text-center text-sm text-muted-foreground">
                Sudah punya akun?
                <Link :href="route('login')" class="underline hover:text-foreground">
                    Login
                </Link>
            </div>
        </div>
    </GuestLayout>
</template>
