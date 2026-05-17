<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { AlertCircle, Loader2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import {
    groupPermissions,
    formatPermissionName,
} from './partials/permission-groups';

const props = defineProps({
    role: {
        type: Object,
        required: true,
    },
    permissions: {
        type: Array,
        required: true,
    },
});

const searchQuery = ref('');

const selectedPermissions = ref(props.role.permissions.map((p) => p.name));

const groupedAllPermissions = computed(() => {
    const grouped = groupPermissions(props.permissions);

    if (!searchQuery.value) {
        return grouped;
    }

    const filtered = {};
    for (const [module, perms] of Object.entries(grouped)) {
        const matchModule = module
            .toLowerCase()
            .includes(searchQuery.value.toLowerCase());
        const matchingPerms = perms.filter((p) =>
            formatPermissionName(p.name)
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase()),
        );

        if (matchModule || matchingPerms.length > 0) {
            filtered[module] = matchModule ? perms : matchingPerms;
        }
    }

    return filtered;
});

const hasFilteredResults = computed(() => {
    return Object.keys(groupedAllPermissions.value).length > 0;
});

const togglePermission = (name) => {
    if (selectedPermissions.value.includes(name)) {
        selectedPermissions.value = selectedPermissions.value.filter(
            (p) => p !== name,
        );
    } else {
        selectedPermissions.value.push(name);
    }
};

const toggleGroup = (perms, checked) => {
    const names = perms.map((p) => p.name);
    if (checked) {
        const toAdd = names.filter(
            (n) => !selectedPermissions.value.includes(n),
        );
        selectedPermissions.value.push(...toAdd);
    } else {
        selectedPermissions.value = selectedPermissions.value.filter(
            (n) => !names.includes(n),
        );
    }
};

const isGroupChecked = (perms) => {
    const names = perms.map((p) => p.name);
    return (
        names.length > 0 &&
        names.every((n) => selectedPermissions.value.includes(n))
    );
};

const isGroupPartial = (perms) => {
    const names = perms.map((p) => p.name);
    const checked = names.filter((n) => selectedPermissions.value.includes(n));
    return checked.length > 0 && checked.length < names.length;
};

const form = useForm({
    permissions: selectedPermissions.value,
});

const submit = () => {
    form.permissions = selectedPermissions.value;
    form.put(route('role-permissions.update', props.role.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Permission role berhasil diperbarui');
        },
        onError: () => {
            toast.error('Gagal memperbarui permission role');
        },
    });
};

const clearSearch = () => {
    searchQuery.value = '';
};
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Edit Permission Role - {{ role.name }}</title>
        </Head>
        <template #breadcrumb>
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <Link :href="route('dashboard')">
                            <BreadcrumbLink>Dashboard</BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <Link :href="route('role-permissions.index')">
                            <BreadcrumbLink>Role Permission</BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>Edit {{ role.name }}</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <div class="p-4">
            <div class="rounded-xl bg-muted/50">
                <!-- Header -->
                <div
                    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 pb-0"
                >
                    <div>
                        <h4 class="font-semibold text-lg capitalize">
                            Edit Permission Role - {{ role.name }}
                        </h4>
                        <p class="text-sm text-muted-foreground">
                            Pilih permission yang ingin diberikan ke role ini
                        </p>
                    </div>
                </div>

                <Separator class="my-3" />

                <!-- Search & Controls -->
                <div
                    class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 px-4 pb-2"
                >
                    <div class="relative w-full sm:w-72">
                        <Input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Cari permission atau modul..."
                            class="h-9 w-full"
                        />
                    </div>
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Badge variant="outline" class="text-xs h-5">
                            {{ selectedPermissions.length }} permission dipilih
                        </Badge>
                    </div>
                </div>

                <Separator class="my-3" />

                <!-- Permission Groups -->
                <div class="px-4 pb-4">
                    <div
                        v-if="!hasFilteredResults"
                        class="flex flex-col items-center justify-center py-12 text-muted-foreground"
                    >
                        <AlertCircle class="w-8 h-8 opacity-40 mb-2" />
                        <p class="text-sm">Tidak ada permission yang cocok</p>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="mt-2 h-8"
                            @click="clearSearch"
                        >
                            Reset Pencarian
                        </Button>
                    </div>

                    <form
                        v-else
                        id="role-perm-edit-form"
                        class="space-y-3"
                        @submit.prevent="submit"
                    >
                        <div
                            v-for="(perms, module) in groupedAllPermissions"
                            :key="module"
                            class="rounded-lg border p-3"
                        >
                            <div class="flex items-center gap-2 mb-2">
                                <Switch
                                    :id="`group-${role.id}-${module}`"
                                    :model-value="isGroupChecked(perms)"
                                    @update:model-value="
                                        toggleGroup(perms, $event)
                                    "
                                />
                                <Label
                                    :for="`group-${role.id}-${module}`"
                                    class="text-sm font-semibold cursor-pointer"
                                    :class="{
                                        'text-primary': isGroupChecked(perms),
                                        'text-amber-500': isGroupPartial(perms),
                                    }"
                                >
                                    {{ module }}
                                    <span
                                        v-if="isGroupPartial(perms)"
                                        class="text-xs text-amber-500 ml-1"
                                        >(sebagian)</span
                                    >
                                </Label>
                            </div>
                            <div
                                class="grid grid-cols-1 sm:grid-cols-2 gap-2 ml-8"
                            >
                                <div
                                    v-for="perm in perms"
                                    :key="perm.id"
                                    class="flex items-center space-x-2"
                                >
                                    <Switch
                                        :id="`perm-${role.id}-${perm.id}`"
                                        :model-value="
                                            selectedPermissions.includes(
                                                perm.name,
                                            )
                                        "
                                        @update:model-value="
                                            togglePermission(perm.name)
                                        "
                                    />
                                    <Label
                                        :for="`perm-${role.id}-${perm.id}`"
                                        class="text-sm font-normal leading-none cursor-pointer peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                    >
                                        {{ formatPermissionName(perm.name) }}
                                    </Label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Footer -->
                <div class="flex justify-between items-center p-4 pt-0">
                    <Link :href="route('role-permissions.index')">
                        <Button type="button" variant="secondary">
                            Batal
                        </Button>
                    </Link>
                    <Button
                        type="submit"
                        form="role-perm-edit-form"
                        :disabled="form.processing"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="w-4 h-4 animate-spin mr-2"
                        />
                        {{
                            form.processing
                                ? 'Menyimpan...'
                                : 'Simpan Perubahan'
                        }}
                    </Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
