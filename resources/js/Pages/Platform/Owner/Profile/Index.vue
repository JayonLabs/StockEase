<script setup>
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Separator } from '@/Components/ui/separator';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff, KeyRound, Loader2, User } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const user = usePage().props.auth.user;

const profileForm = useForm({
    name: user.name,
    email: user.email,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref({
    current_password: false,
    password: false,
    password_confirmation: false,
});

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

function initials(name) {
    return name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}

function toggleVisibility(field) {
    showPassword.value[field] = !showPassword.value[field];
}

function submitProfile() {
    profileForm.patch(route('platform.owner.profile.update'), {
        preserveScroll: true,
        onSuccess: () => toast.success('Profile updated successfully.'),
    });
}

function submitPassword() {
    passwordForm.put(route('platform.owner.profile.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => {
            if (passwordForm.errors.password) {
                passwordForm.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (passwordForm.errors.current_password) {
                passwordForm.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
}
</script>

<template>
    <Head title="Profile - Platform Owner" />

    <div class="mb-8 flex items-center gap-4">
        <div
            class="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-lg font-bold text-zinc-950"
        >
            {{ initials(user.name) }}
        </div>
        <div>
            <h1 class="text-xl font-semibold text-zinc-100">
                {{ user.name }}
            </h1>
            <span
                class="mt-1 inline-flex items-center rounded-full border border-emerald-800 bg-emerald-950 px-2.5 py-0.5 text-xs font-medium text-emerald-400"
            >
                Platform Owner
            </span>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <Card class="border-zinc-800 bg-zinc-900">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-zinc-100">
                    <User class="h-5 w-5 text-emerald-400" />
                    Profile Information
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p class="mb-6 text-sm text-zinc-500">
                    Update your name and email address.
                </p>

                <form id="profile-form" @submit.prevent="submitProfile">
                    <div class="space-y-4">
                        <div>
                            <Label for="name" class="text-zinc-300">Name</Label>
                            <Input
                                id="name"
                                v-model="profileForm.name"
                                type="text"
                                required
                                autocomplete="name"
                                class="mt-1 border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-500 focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="Your name"
                            />
                            <InputError :message="profileForm.errors.name" />
                        </div>

                        <Separator class="bg-zinc-800" />

                        <div>
                            <Label for="email" class="text-zinc-300"
                                >Email</Label
                            >
                            <Input
                                id="email"
                                v-model="profileForm.email"
                                type="email"
                                required
                                autocomplete="email"
                                class="mt-1 border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-500 focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="your@email.com"
                            />
                            <InputError :message="profileForm.errors.email" />
                        </div>
                    </div>
                </form>

                <div class="mt-6 flex justify-end">
                    <Button
                        form="profile-form"
                        type="submit"
                        :disabled="profileForm.processing"
                        class="bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <Loader2
                            v-if="profileForm.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        Save Changes
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card class="border-zinc-800 bg-zinc-900">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-zinc-100">
                    <KeyRound class="h-5 w-5 text-emerald-400" />
                    Change Password
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p class="mb-6 text-sm text-zinc-500">
                    Use a long, random password to keep your account secure.
                </p>

                <form id="password-form" @submit.prevent="submitPassword">
                    <div class="space-y-4">
                        <div>
                            <Label for="current_password" class="text-zinc-300"
                                >Current Password</Label
                            >
                            <div class="relative mt-1">
                                <Input
                                    id="current_password"
                                    ref="currentPasswordInput"
                                    v-model="passwordForm.current_password"
                                    :type="
                                        showPassword.current_password
                                            ? 'text'
                                            : 'password'
                                    "
                                    autocomplete="current-password"
                                    class="border-zinc-700 bg-zinc-800 pr-10 text-zinc-100 placeholder:text-zinc-500 focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-500 hover:text-zinc-300"
                                    @click="toggleVisibility('current_password')"
                                >
                                    <EyeOff
                                        v-if="showPassword.current_password"
                                        class="h-4 w-4"
                                    />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <InputError
                                :message="passwordForm.errors.current_password"
                            />
                        </div>

                        <Separator class="bg-zinc-800" />

                        <div>
                            <Label for="password" class="text-zinc-300"
                                >New Password</Label
                            >
                            <div class="relative mt-1">
                                <Input
                                    id="password"
                                    ref="passwordInput"
                                    v-model="passwordForm.password"
                                    :type="
                                        showPassword.password ? 'text' : 'password'
                                    "
                                    autocomplete="new-password"
                                    class="border-zinc-700 bg-zinc-800 pr-10 text-zinc-100 placeholder:text-zinc-500 focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-500 hover:text-zinc-300"
                                    @click="toggleVisibility('password')"
                                >
                                    <EyeOff
                                        v-if="showPassword.password"
                                        class="h-4 w-4"
                                    />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <InputError :message="passwordForm.errors.password" />
                        </div>

                        <div>
                            <Label
                                for="password_confirmation"
                                class="text-zinc-300"
                                >Confirm New Password</Label
                            >
                            <div class="relative mt-1">
                                <Input
                                    id="password_confirmation"
                                    v-model="passwordForm.password_confirmation"
                                    :type="
                                        showPassword.password_confirmation
                                            ? 'text'
                                            : 'password'
                                    "
                                    autocomplete="new-password"
                                    class="border-zinc-700 bg-zinc-800 pr-10 text-zinc-100 placeholder:text-zinc-500 focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-500 hover:text-zinc-300"
                                    @click="
                                        toggleVisibility('password_confirmation')
                                    "
                                >
                                    <EyeOff
                                        v-if="showPassword.password_confirmation"
                                        class="h-4 w-4"
                                    />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <InputError
                                :message="
                                    passwordForm.errors.password_confirmation
                                "
                            />
                        </div>
                    </div>
                </form>

                <div class="mt-6 flex justify-end">
                    <Button
                        form="password-form"
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <Loader2
                            v-if="passwordForm.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        Update Password
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
