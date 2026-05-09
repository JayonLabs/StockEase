<script setup>
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth.user ?? null);

function getColorFromName(name) {
    const colors = [
        'bg-red-500',
        'bg-orange-500',
        'bg-amber-500',
        'bg-yellow-500',
        'bg-lime-500',
        'bg-green-500',
        'bg-emerald-500',
        'bg-teal-500',
        'bg-cyan-500',
        'bg-sky-500',
        'bg-blue-500',
        'bg-indigo-500',
        'bg-violet-500',
        'bg-purple-500',
        'bg-pink-500',
        'bg-rose-500',
    ];

    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }

    const index = Math.abs(hash) % colors.length;
    return colors[index];
}

const avatarColor = computed(() => {
    if (!user.value) return 'bg-blue-500';
    return getColorFromName(user.value.name);
});

const initials = computed(() => {
    if (!user.value) return '?';
    return user.value.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .substring(0, 2);
});
</script>

<template>
    <Avatar class="h-full w-full">
        <AvatarImage
            v-if="user?.photo_profile"
            :src="`/${user.photo_profile}`"
        />
        <AvatarFallback
            :class="[
                avatarColor,
                'text-white font-bold w-full h-full flex items-center justify-center rounded-full',
            ]"
        >
            {{ initials }}
        </AvatarFallback>
    </Avatar>
</template>
