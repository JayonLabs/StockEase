<script setup>
import AppSidebar from '@/Components/AppSidebar.vue';
import { SidebarProvider } from '@/Components/ui/sidebar';
import { Toaster } from '@/Components/ui/sonner';
import UpgradePromptProvider from '@/Components/Subscription/UpgradePromptProvider.vue';
import 'vue-sonner/style.css';
</script>

<template>
    <div class="flex h-screen bg-background">
        <SidebarProvider>
            <AppSidebar />
            <slot />
        </SidebarProvider>
        <Toaster rich-colors position="top-right" />
        <UpgradePromptProvider />
    </div>
</template>
