<script setup>
import ProfilePicture from './ProfilePicture.vue';
import PhotoProfileForm from './PhotoProfileForm.vue';
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);

const reloadPage = () => {
    router.reload({
        preserveScroll: true,
        preserveState: true,
        only: ['auth'],
    });
};
</script>

<template>
    <div
        class="mb-6 rounded-2xl border border-gray-200 p-5 lg:p-6 dark:border-gray-800"
    >
        <div
            class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between"
        >
            <div class="flex w-full flex-col items-center gap-6 xl:flex-row">
                <div
                    class="h-20 w-20 overflow-hidden rounded-full border border-gray-200 dark:border-gray-800"
                >
                    <img
                        v-if="user.photo_profile"
                        :src="`/${user.photo_profile}`"
                        :alt="user.name"
                        class="h-full w-full object-cover"
                    />
                    <ProfilePicture v-else />
                </div>
                <div class="order-3 xl:order-2">
                    <h4
                        class="mb-2 text-center text-lg font-semibold text-gray-800 xl:text-left dark:text-white/90"
                    >
                        {{ user.name }}
                    </h4>
                    <div
                        class="flex flex-col items-center gap-1 text-center xl:flex-row xl:gap-3 xl:text-left"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ user.email }}
                        </p>
                        <span
                            class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30"
                        >
                            {{ user.role }}
                        </span>
                    </div>
                </div>
            </div>

            <PhotoProfileForm @photo-updated="reloadPage" />
        </div>
    </div>
</template>
