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
import {
    groupPermissions,
    formatPermissionName,
} from '../partials/permission-groups';

const props = defineProps({
    role: { type: Object, required: true },
    permissions: { type: Array, required: true },
});

const isDialogOpen = ref(false);

const selectedPermissions = ref(props.role.permissions.map((p) => p.name));

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

const isGroupIndeterminate = (perms) => {
    const names = perms.map((p) => p.name);
    const selectedCount = names.filter((n) =>
        selectedPermissions.value.includes(n),
    ).length;
    return selectedCount > 0 && selectedCount < names.length;
};

const form = useForm({
    permissions: selectedPermissions.value,
});

const submit = () => {
    form.permissions = selectedPermissions.value;
    form.put(route('role-permissions.update', props.role.id), {
        showProgress: false,
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Permission role berhasil diperbarui');
            isDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Gagal memperbarui permission role');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button
                aria-label="Kelola permission"
                variant="ghost"
                size="icon"
                class="group"
            >
                <Pencil
                    class="w-4 h-4 text-blue-500 dark:group-hover:text-white"
                />
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-3xl max-h-[85vh] flex flex-col">
            <DialogHeader>
                <DialogTitle class="capitalize">
                    Edit Permission Role - {{ role.name }}
                </DialogTitle>
                <DialogDescription>
                    Pilih permission yang ingin diberikan ke role ini.
                </DialogDescription>
            </DialogHeader>
            <form
                id="role-perm-form"
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
                            :id="`group-${role.id}-${module}`"
                            :checked="isGroupChecked(perms)"
                            @update:checked="toggleGroup(perms, $event)"
                        />
                        <Label
                            :for="`group-${role.id}-${module}`"
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
                                :id="`perm-${role.id}-${perm.id}`"
                                :checked="
                                    selectedPermissions.includes(perm.name)
                                "
                                @update:checked="togglePermission(perm.name)"
                            />
                            <Label
                                :for="`perm-${role.id}-${perm.id}`"
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
                    form="role-perm-form"
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
