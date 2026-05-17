<script setup>
import { Button } from '@/Components/ui/button';
import { Eye } from 'lucide-vue-next';
import { ref, computed } from 'vue';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import {
    groupPermissions,
    formatPermissionName,
} from '../partials/permission-groups';

const props = defineProps({
    role: { type: Object, required: true },
});

const isDialogOpen = ref(false);

const groupedPermissions = computed(() =>
    groupPermissions(props.role.permissions),
);

const totalPermissions = computed(() => props.role.permissions.length);
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button variant="ghost" size="sm" class="gap-1">
                <Eye class="w-4 h-4" />
                <span class="text-xs">Lihat</span>
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-2xl max-h-[80vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="capitalize">
                    Detail Permission - {{ role.name }}
                </DialogTitle>
                <DialogDescription>
                    Total {{ totalPermissions }} permission
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 mt-2">
                <div
                    v-for="(perms, module) in groupedPermissions"
                    :key="module"
                    class="rounded-lg border p-3"
                >
                    <h5 class="text-sm font-semibold mb-2 text-primary">
                        {{ module }}
                    </h5>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="perm in perms"
                            :key="perm.id"
                            class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80"
                        >
                            {{ formatPermissionName(perm.name) }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="totalPermissions === 0"
                    class="text-center text-muted-foreground py-4"
                >
                    Tidak ada permission untuk role ini.
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
