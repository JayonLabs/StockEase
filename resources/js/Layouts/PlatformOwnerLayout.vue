<script setup>
import { Toaster } from '@/Components/ui/sonner';
import { Head } from '@inertiajs/vue3';
import { LogOut } from 'lucide-vue-next';
import { router, usePage } from '@inertiajs/vue3';
import UpgradePromptProvider from '@/Components/Subscription/UpgradePromptProvider.vue';
import 'vue-sonner/style.css';

import { SidebarProvider, SidebarTrigger } from '@/Components/ui/sidebar';
import { Separator } from '@/Components/ui/separator';
import AppSidebarOwner from '@/Components/AppSidebarOwner.vue';

const page = usePage();
const user = page.props.auth.user;

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head title="Platform Owner" />

    <SidebarProvider class="dark">
        <div class="flex min-h-screen w-full bg-zinc-950">
            <AppSidebarOwner />

            <div class="flex flex-1 flex-col overflow-hidden">
                <header class="border-b border-zinc-800 bg-zinc-900">
                    <div class="flex h-16 items-center justify-between px-4">
                        <div class="flex items-center gap-3">
                            <SidebarTrigger
                                class="text-zinc-400 hover:text-zinc-100"
                            />
                            <Separator
                                orientation="vertical"
                                class="h-4 bg-zinc-700"
                            />
                            <slot name="breadcrumb" />
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-zinc-400">{{
                                user.name
                            }}</span>
                            <button
                                @click="logout"
                                class="cursor-pointer inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-zinc-100"
                            >
                                <LogOut class="h-4 w-4" />
                                Logout
                            </button>
                        </div>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto">
                    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                        <slot />
                    </div>
                </main>
            </div>
        </div>

        <Toaster rich-colors position="top-right" />
        <UpgradePromptProvider />
    </SidebarProvider>
</template>
