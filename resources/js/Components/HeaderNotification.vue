<script setup>
import { onMounted } from 'vue';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Link } from '@inertiajs/vue3';
import { useNotifications } from '@/composables/useNotifications';

import {
    Bell,
    Trash2,
    Check,
    AlertTriangle,
    PackageSearch,
} from 'lucide-vue-next';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuTrigger,
    DropdownMenuGroup,
} from '@/Components/ui/dropdown-menu';

const {
    notifications,
    unreadCount,
    initialize,
    markAsRead,
    markAllAsRead,
    deleteNotification,
} = useNotifications();

onMounted(() => {
    initialize();
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child class="cursor-pointer border">
            <Button
                aria-label="Notifikasi"
                variant="ghost"
                size="icon"
                class="relative hover:bg-accent rounded-full transition-all"
            >
                <Bell class="h-5 w-5" />
                <span
                    v-if="unreadCount"
                    class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-destructive text-destructive-foreground text-[10px] font-bold shadow-sm"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent class="w-[400px] p-0 overflow-hidden" align="end">
            <div
                class="flex items-center justify-between px-4 py-3 border-b bg-muted/30"
            >
                <div class="flex items-center gap-2">
                    <DropdownMenuLabel class="p-0 font-bold text-base">
                        Notifikasi
                    </DropdownMenuLabel>
                    <Badge
                        v-if="unreadCount"
                        variant="secondary"
                        class="rounded-full px-2 py-0 h-5 text-[10px]"
                    >
                        {{ unreadCount }} Baru
                    </Badge>
                </div>
                <button
                    v-if="unreadCount"
                    class="text-xs text-primary hover:underline font-semibold transition-all"
                    @click="markAllAsRead"
                >
                    Tandai semua dibaca
                </button>
            </div>

            <DropdownMenuGroup class="max-h-[450px] overflow-y-auto">
                <template v-if="notifications.length">
                    <div
                        v-for="notif in notifications"
                        :key="notif.id"
                        :class="[
                            'relative px-4 py-4 border-b last:border-0 hover:bg-accent/50 cursor-pointer transition-all flex items-start gap-4 group',
                            !notif.read_at && 'bg-primary/5',
                        ]"
                    >
                        <!-- Status Icon / Indicator -->
                        <div class="mt-1 flex-shrink-0 relative">
                            <div
                                class="h-10 w-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600"
                            >
                                <AlertTriangle class="h-5 w-5" />
                            </div>
                            <span
                                v-if="!notif.read_at"
                                class="absolute top-0 right-0 h-3 w-3 rounded-full bg-blue-500 border-2 border-background"
                            />
                        </div>

                        <!-- Content -->
                        <Link :href="route('product.edit', notif.slug)">
                            <div class="flex-1 min-w-0">
                                <div
                                    class="flex items-center justify-between gap-2 mb-1"
                                >
                                    <span class="text-sm font-bold truncate">
                                        {{ notif.product_name }}
                                    </span>
                                    <span
                                        class="text-[10px] text-muted-foreground whitespace-nowrap"
                                    >
                                        {{ notif.time_ago }}
                                    </span>
                                </div>

                                <p
                                    class="text-xs text-muted-foreground leading-relaxed mb-2"
                                >
                                    Stok produk sedang menipis! Segera lakukan
                                    penambahan stok.
                                </p>

                                <div class="flex items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        class="text-[10px] py-0 px-2 font-medium border-orange-200 bg-orange-50 text-orange-700"
                                    >
                                        {{ notif.current_stock }} tersisa
                                    </Badge>
                                    <span
                                        class="text-[10px] text-muted-foreground italic"
                                    >
                                        Batas: {{ notif.alert_level }}
                                    </span>
                                </div>
                            </div>
                        </Link>

                        <!-- Actions (Visible on hover) -->
                        <div
                            class="flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity ml-2 shrink-0 self-center"
                        >
                            <button
                                v-if="!notif.read_at"
                                class="p-2 hover:bg-blue-100 hover:text-blue-600 text-muted-foreground rounded-full transition-colors"
                                title="Tandai dibaca"
                                @click.stop="markAsRead(notif.id)"
                            >
                                <Check class="h-4 w-4" />
                            </button>
                            <button
                                class="p-2 hover:bg-destructive/10 hover:text-destructive text-muted-foreground rounded-full transition-colors"
                                title="Hapus"
                                @click.stop="deleteNotification(notif.id)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div
                        class="flex flex-col items-center justify-center py-12 px-4 text-center opacity-60"
                    >
                        <div
                            class="h-16 w-16 rounded-full bg-muted flex items-center justify-center mb-4"
                        >
                            <PackageSearch
                                class="h-8 w-8 text-muted-foreground"
                            />
                        </div>
                        <h3 class="font-semibold text-sm">
                            Tidak ada notifikasi
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Log stok Anda saat ini sudah aman.
                        </p>
                    </div>
                </template>
            </DropdownMenuGroup>

            <div
                v-if="notifications.length"
                class="p-2 border-t bg-muted/10 text-center"
            >
                <span class="text-[10px] text-muted-foreground"
                    >Menampilkan 10 notifikasi terbaru</span
                >
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
