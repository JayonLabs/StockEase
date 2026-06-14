<script setup>
import { ownerMenuSections } from '@/Constants/menuOwner';
import SidebarGroupMenu from '@/Components/SidebarGroupMenu.vue';

import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/Components/ui/sidebar';
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
                <span class="font-bold text-white">Platform Owner</span>
            </div>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroupMenu
                v-for="section in ownerMenuSections"
                :key="section.label"
                :title="section.label"
                :items="section.items"
                user-role="platform_owner"
                :collapsible="section.collapsible"
            />
        </SidebarContent>

        <SidebarFooter />
    </Sidebar>
</template>
