<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import { Head, Link } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatDateTime } from '@/lib/utils';

const props = defineProps({
    activity: {
        type: Object,
        required: true,
    },
});

const eventBadgeVariant = (event) => {
    switch (event) {
        case 'created':
            return 'success';
        case 'updated':
            return 'default';
        case 'deleted':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const changes = computed(() => {
    if (!props.activity.attribute_changes) return {};

    const changes = {};
    for (const [key, values] of Object.entries(
        props.activity.attribute_changes,
    )) {
        if (
            typeof values === 'object' &&
            values !== null &&
            'old' in values &&
            'new' in values
        ) {
            changes[key] = values;
        }
    }
    return changes;
});

const hasChanges = computed(() => Object.keys(changes.value).length > 0);

const extraProperties = computed(() => {
    if (!props.activity.properties) return {};
    const extra = { ...props.activity.properties };
    delete extra.attributes;
    delete extra.old;
    return extra;
});
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Detail Activity Log</title>
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
                        <Link :href="route('activity-logs.index')">
                            <BreadcrumbLink>Activity Log</BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage
                            >Detail #{{ activity.id }}</BreadcrumbPage
                        >
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <div class="p-4 space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Badge :variant="eventBadgeVariant(activity.event)">
                            {{ activity.event }}
                        </Badge>
                        <span class="text-lg">{{ activity.description }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-muted-foreground">ID:</span>
                            <span class="ml-2 font-mono"
                                >#{{ activity.id }}</span
                            >
                        </div>
                        <div>
                            <span class="text-muted-foreground">Log Name:</span>
                            <span class="ml-2">{{ activity.log_name }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >Subject Type:</span
                            >
                            <span class="ml-2 font-mono text-xs">{{
                                activity.subject_type
                            }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >Subject ID:</span
                            >
                            <span class="ml-2 font-mono"
                                >#{{ activity.subject_id }}</span
                            >
                        </div>
                        <div>
                            <span class="text-muted-foreground">Causer:</span>
                            <span v-if="activity.causer" class="ml-2">
                                {{ activity.causer.name }} ({{
                                    activity.causer.email
                                }})
                            </span>
                            <span
                                v-else
                                class="ml-2 italic text-muted-foreground"
                                >System</span
                            >
                        </div>
                        <div>
                            <span class="text-muted-foreground">Waktu:</span>
                            <span class="ml-2">{{
                                formatDateTime(activity.created_at)
                            }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="hasChanges">
                <CardHeader>
                    <CardTitle>Perubahan Atribut</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <div
                            v-for="(values, key) in changes"
                            :key="key"
                            class="border rounded-lg p-3 text-sm"
                        >
                            <div class="font-semibold mb-1">{{ key }}</div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <span class="text-muted-foreground text-xs"
                                        >Lama:</span
                                    >
                                    <span
                                        class="ml-1 text-red-600 line-through"
                                        >{{ values.old }}</span
                                    >
                                </div>
                                <div>
                                    <span class="text-muted-foreground text-xs"
                                        >Baru:</span
                                    >
                                    <span class="ml-1 text-green-600">{{
                                        values.new
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="Object.keys(extraProperties).length > 0">
                <CardHeader>
                    <CardTitle>Properties</CardTitle>
                </CardHeader>
                <CardContent>
                    <pre
                        class="text-xs bg-muted rounded-lg p-4 overflow-x-auto whitespace-pre-wrap"
                        >{{ JSON.stringify(extraProperties, null, 2) }}</pre
                    >
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
