<script setup>
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, Loader2, KeyRound } from 'lucide-vue-next';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref({
    current_password: false,
    password: false,
    password_confirmation: false,
});

const togglePasswordVisibility = (field) => {
    showPassword.value[field] = !showPassword.value[field];
    const input = document.getElementById(field);
    if (input) {
        input.type = showPassword.value[field] ? 'text' : 'password';
    }
};

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
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
                    <h2 class="text-lg font-medium">Update Password</h2>

                    <p class="mt-1 text-muted-foreground text-sm">
                        Pastikan akun Anda menggunakan kata sandi yang panjang
                        dan acak agar tetap aman.
                    </p>
                </div>

                <form
                    id="passwordForm"
                    class="w-full"
                    @submit.prevent="updatePassword"
                >
                    <div
                        class="grid grid-cols-1 gap-4 lg:grid-cols-2 w-full mt-4"
                    >
                        <div class="w-full">
                            <Label for="current_password">
                                Kata Sandi Lama
                            </Label>
                            <div class="relative">
                                <Input
                                    id="current_password"
                                    ref="currentPasswordInput"
                                    v-model="form.current_password"
                                    type="password"
                                    class="w-full h-11 py-3 rounded-lg border pr-10"
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 hover:text-foreground"
                                    :aria-label="
                                        showPassword.current_password
                                            ? 'Sembunyikan kata sandi'
                                            : 'Tampilkan kata sandi'
                                    "
                                    @click="
                                        togglePasswordVisibility(
                                            'current_password',
                                        )
                                    "
                                >
                                    <EyeOff
                                        v-if="showPassword.current_password"
                                        class="size-4 text-muted-foreground"
                                    />
                                    <Eye
                                        v-else
                                        class="size-4 text-muted-foreground"
                                    />
                                </button>
                            </div>
                            <InputError
                                :message="form.errors.current_password"
                            />
                        </div>

                        <div class="w-full">
                            <Label for="password">Kata Sandi Baru</Label>
                            <div class="relative">
                                <Input
                                    id="password"
                                    ref="passwordInput"
                                    v-model="form.password"
                                    type="password"
                                    class="w-full h-11 py-3 rounded-lg border pr-10"
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 hover:text-foreground"
                                    :aria-label="
                                        showPassword.password
                                            ? 'Sembunyikan kata sandi'
                                            : 'Tampilkan kata sandi'
                                    "
                                    @click="
                                        togglePasswordVisibility('password')
                                    "
                                >
                                    <EyeOff
                                        v-if="showPassword.password"
                                        class="size-4 text-muted-foreground"
                                    />
                                    <Eye
                                        v-else
                                        class="size-4 text-muted-foreground"
                                    />
                                </button>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="w-full lg:col-span-2">
                            <Label for="password_confirmation">
                                Konfirmasi Kata Sandi Baru
                            </Label>
                            <div class="relative">
                                <Input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    class="w-full h-11 py-3 rounded-lg border pr-10"
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 hover:text-foreground"
                                    :aria-label="
                                        showPassword.password_confirmation
                                            ? 'Sembunyikan kata sandi'
                                            : 'Tampilkan kata sandi'
                                    "
                                    @click="
                                        togglePasswordVisibility(
                                            'password_confirmation',
                                        )
                                    "
                                >
                                    <EyeOff
                                        v-if="
                                            showPassword.password_confirmation
                                        "
                                        class="size-4 text-muted-foreground"
                                    />
                                    <Eye
                                        v-else
                                        class="size-4 text-muted-foreground"
                                    />
                                </button>
                            </div>
                            <InputError
                                :message="form.errors.password_confirmation"
                            />
                        </div>
                    </div>
                </form>
            </div>

            <Button
                type="submit"
                form="passwordForm"
                variant="secondary"
                class="shadow-theme-xs flex w-full items-center justify-center gap-2 rounded-full border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 lg:inline-flex lg:w-auto dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/3 dark:hover:text-gray-200"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                <KeyRound v-else class="w-4 h-4" />
                Simpan
            </Button>
        </div>
    </div>
</template>
