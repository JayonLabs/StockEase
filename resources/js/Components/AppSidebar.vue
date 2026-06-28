<script setup>
import { usePage } from '@inertiajs/vue3';
import { menuSections } from '@/Constants/menu';
import SidebarGroupMenu from '@/Components/SidebarGroupMenu.vue';
import { computed } from 'vue';

import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/Components/ui/sidebar';

const page = usePage();
const user = page.props.auth.user;
const userPermissions = user?.permissions || [];
const planFeatures = page.props.auth.subscription?.plan?.features ?? {};
const isSubscriptionActive = computed(() => !!page.props.auth.subscription);
</script>

<template>
    <Sidebar>
        <SidebarHeader>
            <div class="flex items-center gap-2 justify-center mt-2">
                <img
                    class="h-8 w-8"
                    src="/img/StockEase-Logo.png"
                    alt="Stock Ease"
                />
                <span class="font-bold dark:text-white">Stock Ease</span>
            </div>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroupMenu
                v-for="section in menuSections"
                :key="section.label"
                :title="section.label"
                :items="section.items"
                :user-role="user.role"
                :user-permissions="userPermissions"
                :plan-features="planFeatures"
                :collapsible="section.collapsible"
                :is-subscription-active="isSubscriptionActive"
            />
        </SidebarContent>

        <SidebarFooter />
    </Sidebar>
</template>
