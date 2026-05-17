<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, Pencil } from 'lucide-vue-next';
import { useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';

import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { AlertCircle } from 'lucide-vue-next';
import {
    groupPermissions,
    formatPermissionName,
} from '../../RolePermission/partials/permission-groups';

const props = defineProps({
    user: { type: Object, required: true },
    permissions: { type: Array, required: true },
});

const isDialogOpen = ref(false);

const selectedPermissions = ref(props.user.permissions.map((p) => p.name));

const groupedAllPermissions = computed(() =>
    groupPermissions(props.permissions),
);

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
    return names.every((n) => selectedPermissions.value.includes(n));
};

const form = useForm({
    permissions: selectedPermissions.value,
});

const submit = () => {
    form.permissions = selectedPermissions.value;
    form.put(route('user-permissions.update', props.user.id), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Permission user berhasil diperbarui');
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Gagal memperbarui permission user');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button variant="ghost" size="icon" class="group">
                <Pencil
                    class="w-4 h-4 text-blue-500 dark:group-hover:text-white"
                />
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-3xl max-h-[85vh] flex flex-col">
            <DialogHeader>
                <DialogTitle>
                    Edit Direct Permission - {{ user.name }}
                </DialogTitle>
                <DialogDescription>
                    Pilih permission tambahan (override) untuk user ini.
                </DialogDescription>
            </DialogHeader>

            <!-- Warning Banner -->
            <div
                class="rounded-lg border border-amber-500/20 bg-amber-500/10 p-3 mb-2"
            >
                <div class="flex items-start gap-2">
                    <AlertCircle
                        class="w-4 h-4 text-amber-500 mt-0.5 shrink-0"
                    />
                    <p class="text-xs text-amber-500/80">
                        Direct permission sebaiknya hanya untuk kasus exception.
                        User ini sudah memiliki permission dari role:
                        <span class="font-semibold text-amber-500">
                            {{
                                user.roles.map((r) => r.name).join(', ') ||
                                'Tidak ada role'
                            }} </span
                        >.
                    </p>
                </div>
            </div>

            <form
                id="user-perm-form"
                class="flex-1 overflow-y-auto pr-1 space-y-4"
                @submit.prevent="submit"
            >
                <div
                    v-for="(perms, module) in groupedAllPermissions"
                    :key="module"
                    class="rounded-lg border p-3"
                >
                    <div class="flex items-center gap-2 mb-2">
                        <Checkbox
                            :id="`group-${user.id}-${module}`"
                            :checked="isGroupChecked(perms)"
                            @update:checked="toggleGroup(perms, $event)"
                        />
                        <Label
                            :for="`group-${user.id}-${module}`"
                            class="text-sm font-semibold text-primary cursor-pointer"
                        >
                            {{ module }}
                        </Label>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 ml-6">
                        <div
                            v-for="perm in perms"
                            :key="perm.id"
                            class="flex items-center space-x-2"
                        >
                            <Checkbox
                                :id="`perm-${user.id}-${perm.id}`"
                                :checked="
                                    selectedPermissions.includes(perm.name)
                                "
                                @update:checked="togglePermission(perm.name)"
                            />
                            <Label
                                :for="`perm-${user.id}-${perm.id}`"
                                class="text-sm font-normal leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                            >
                                {{ formatPermissionName(perm.name) }}
                            </Label>
                        </div>
                    </div>
                </div>
            </form>
            <DialogFooter class="flex justify-between pt-4 border-t mt-2">
                <DialogClose as-child>
                    <Button type="button" variant="secondary"> Batal </Button>
                </DialogClose>
                <Button
                    type="submit"
                    form="user-perm-form"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                    class="disabled:cursor-not-allowed"
                >
                    <Loader2
                        v-if="form.processing"
                        class="w-4 h-4 animate-spin"
                    />
                    {{ form.processing ? 'Loading...' : 'Simpan' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
