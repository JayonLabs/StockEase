<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { filterMenuByRole } from '@/lib/utils';
import { ChevronDown, Lock } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UpgradePromptDialog from '@/Components/Subscription/UpgradePromptDialog.vue';

import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuItem,
    SidebarMenuButton,
} from '@/Components/ui/sidebar';

import {
    CollapsibleContent,
    CollapsibleRoot,
    CollapsibleTrigger,
} from 'reka-ui';

const props = defineProps({
    title: String,
    items: Array,
    userRole: String,
    userPermissions: {
        type: Array,
        default: () => [],
    },
    planFeatures: {
        type: Object,
        default: () => ({}),
    },
    collapsible: {
        type: Boolean,
        default: false,
    },
    isSubscriptionActive: {
        type: Boolean,
        default: true,
    },
});

const page = usePage();

const planName = computed(() => page.props.auth.subscription?.plan?.name ?? '');

const filteredItems = computed(() =>
    filterMenuByRole(
        props.items,
        props.userRole,
        props.userPermissions,
        props.planFeatures,
    ),
);

const hasItems = computed(() => filteredItems.value.length > 0);

const isItemActive = (item) => {
    const _ = page.url;

    return item.activeRoute
        ? route().current(item.activeRoute)
        : route().current(item.routeName);
};

const showFeatureDialog = ref(false);
const lockedFeature = ref({ title: '' });
const lockedType = ref('feature');

const showFeatureLock = (item) => {
    lockedFeature.value = item;
    lockedType.value = 'feature';
    showFeatureDialog.value = true;
};

const openSubscriptionLock = () => {
    lockedType.value = 'subscription';
    showFeatureDialog.value = true;
};

const closeFeatureDialog = () => {
    showFeatureDialog.value = false;
};
</script>

<template>
    <template v-if="hasItems">
        <!-- Render Collapsible Group -->
        <CollapsibleRoot
            v-if="collapsible"
            default-open
            class="group/collapsible"
        >
            <SidebarGroup>
                <SidebarGroupLabel as-child>
                    <CollapsibleTrigger>
                        {{ title }}
                        <ChevronDown
                            class="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-180"
                        />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarMenuItem
                                v-for="item in filteredItems"
                                :key="item.title"
                            >
                                <!-- Subscription tidak aktif: semua item dikunci -->
                                <SidebarMenuButton
                                    v-if="!isSubscriptionActive"
                                    class="cursor-pointer opacity-50"
                                    :title="`Aktifkan langganan untuk mengakses ${item.title}`"
                                    @click="openSubscriptionLock"
                                >
                                    <component :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                    <Lock class="ml-auto h-3 w-3 shrink-0" />
                                </SidebarMenuButton>

                                <!-- Item terkunci: tampil transparan dengan ikon gembok -->
                                <SidebarMenuButton
                                    v-else-if="item.locked"
                                    class="cursor-pointer opacity-50"
                                    :title="`Upgrade plan untuk mengakses ${item.title}`"
                                    @click="showFeatureLock(item)"
                                >
                                    <component :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                    <Lock class="ml-auto h-3 w-3 shrink-0" />
                                </SidebarMenuButton>

                                <!-- Item normal -->
                                <SidebarMenuButton
                                    v-else
                                    as-child
                                    :is-active="isItemActive(item)"
                                >
                                    <Link
                                        :href="
                                            route().has(item.routeName)
                                                ? route(item.routeName)
                                                : '#'
                                        "
                                    >
                                        <component :is="item.icon" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </CollapsibleContent>
            </SidebarGroup>
        </CollapsibleRoot>

        <!-- Render Simple Group -->
        <SidebarGroup v-else>
            <SidebarGroupLabel>{{ title }}</SidebarGroupLabel>
            <SidebarGroupContent>
                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="item in filteredItems"
                        :key="item.title"
                    >
                        <!-- Subscription tidak aktif: semua item dikunci -->
                        <SidebarMenuButton
                            v-if="!isSubscriptionActive"
                            class="cursor-pointer opacity-50"
                            :title="`Aktifkan langganan untuk mengakses ${item.title}`"
                            @click="openSubscriptionLock"
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                            <Lock class="ml-auto h-3 w-3 shrink-0" />
                        </SidebarMenuButton>

                        <!-- Item terkunci: tampil transparan dengan ikon gembok -->
                        <SidebarMenuButton
                            v-else-if="item.locked"
                            class="cursor-pointer opacity-50"
                            :title="`Upgrade plan untuk mengakses ${item.title}`"
                            @click="showFeatureLock(item)"
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                            <Lock class="ml-auto h-3 w-3 shrink-0" />
                        </SidebarMenuButton>

                        <!-- Item normal -->
                        <SidebarMenuButton
                            v-else
                            as-child
                            :is-active="isItemActive(item)"
                        >
                            <Link
                                :href="
                                    route().has(item.routeName)
                                        ? route(item.routeName)
                                        : '#'
                                "
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    </template>

    <UpgradePromptDialog
        :open="showFeatureDialog"
        :feature-name="lockedType === 'feature' ? lockedFeature.title : ''"
        :plan-name="planName"
        :lock-type="lockedType"
        @close="closeFeatureDialog"
    />
</template>
