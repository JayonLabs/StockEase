<script setup>
import Header from '@/Components/Header.vue';
</script>

<template>
    <div class="flex-1 flex flex-col overflow-hidden">
        <Header>
            <template #breadcrumb>
                <slot name="breadcrumb" />
            </template>
        </Header>
        <main class="flex-1 overflow-x-hidden overflow-y-auto pt-2">
            <slot />
        </main>
    </div>
</template>
