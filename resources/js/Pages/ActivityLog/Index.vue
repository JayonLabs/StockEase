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
import { Head, Link, router } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    Pagination,
    PaginationContent,
    PaginationFirst,
    PaginationItem,
    PaginationLast,
    PaginationNext,
    PaginationPrevious,
} from '@/Components/ui/pagination';
import { Badge } from '@/Components/ui/badge';
import { formatDateTime } from '@/lib/utils';
import { History } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps({
    activities: {
        type: Object,
        required: true,
    },
    events: {
        type: Array,
        default: () => [],
    },
    logNames: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', event: '', log_name: '' }),
    },
});

const searchQuery = ref(props.filters.search || '');
const selectedEvent = ref(props.filters.event || '');
const selectedLogName = ref(props.filters.log_name || '');

function applyFilters() {
    const params = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedEvent.value) params.event = selectedEvent.value;
    if (selectedLogName.value) params.log_name = selectedLogName.value;

    router.reload({
        only: ['activities', 'filters'],
        data: params,
        preserveScroll: true,
        preserveState: true,
    });
}

function clearFilters() {
    searchQuery.value = '';
    selectedEvent.value = '';
    selectedLogName.value = '';
    router.reload({
        only: ['activities', 'filters'],
        preserveScroll: true,
        preserveState: true,
    });
}

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

const subjectName = (activity) => {
    if (!activity.subject) return null;
    const event = activity.event;

    if (event === 'deleted' && activity.properties?.old) {
        return (
            activity.properties.old?.name ||
            activity.properties.old?.title ||
            'Deleted'
        );
    }

    return (
        activity.subject.name ||
        activity.subject.title ||
        `#${activity.subject.id}`
    );
};
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Activity Log</title>
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
                        <BreadcrumbPage>Activity Log</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <div class="p-4">
            <div class="rounded-xl bg-muted/50">
                <div
                    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 pb-0"
                >
                    <div>
                        <h4 class="font-semibold text-lg">Activity Log</h4>
                        <p class="text-sm text-muted-foreground">
                            Riwayat aktivitas pengguna dalam sistem
                        </p>
                    </div>
                </div>

                <Separator class="my-3" />

                <div
                    class="flex flex-col sm:flex-row items-start sm:items-center gap-3 px-4"
                >
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <Input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Cari aktivitas..."
                            class="h-9 w-full sm:w-64"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Select
                            v-model="selectedEvent"
                            @update:model-value="applyFilters"
                        >
                            <SelectTrigger class="h-9 w-36">
                                <SelectValue placeholder="Semua Event" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="event in events"
                                    :key="event"
                                    :value="event"
                                >
                                    {{ event }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            v-model="selectedLogName"
                            @update:model-value="applyFilters"
                        >
                            <SelectTrigger class="h-9 w-44">
                                <SelectValue placeholder="Semua Modul" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="name in logNames"
                                    :key="name"
                                    :value="name"
                                >
                                    {{ name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button
                        v-if="searchQuery || selectedEvent || selectedLogName"
                        variant="ghost"
                        size="sm"
                        class="h-9"
                        @click="clearFilters"
                    >
                        Clear
                    </Button>
                    <div
                        class="flex items-center gap-1 ml-auto text-xs text-muted-foreground"
                    >
                        <Badge variant="outline" class="text-xs h-5">
                            {{ activities.total }} aktivitas
                        </Badge>
                    </div>
                </div>

                <Separator class="mt-3" />

                <div class="px-4 py-2">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-12">#</TableHead>
                                <TableHead>Deskripsi</TableHead>
                                <TableHead class="w-24">Event</TableHead>
                                <TableHead class="w-32">Modul</TableHead>
                                <TableHead class="w-36">Pengguna</TableHead>
                                <TableHead class="w-40">Waktu</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="(activity, index) in activities.data"
                                :key="activity.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="
                                    $inertia.visit(
                                        route(
                                            'activity-logs.show',
                                            activity.id,
                                        ),
                                    )
                                "
                            >
                                <TableCell
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        (activities.current_page - 1) *
                                            activities.per_page +
                                        index +
                                        1
                                    }}
                                </TableCell>
                                <TableCell>
                                    <div class="text-sm">
                                        {{ activity.description }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            eventBadgeVariant(activity.event)
                                        "
                                    >
                                        {{ activity.event }}
                                    </Badge>
                                </TableCell>
                                <TableCell
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ activity.log_name }}
                                </TableCell>
                                <TableCell class="text-xs">
                                    <div v-if="activity.causer">
                                        <div class="font-medium">
                                            {{ activity.causer.name }}
                                        </div>
                                        <div class="text-muted-foreground">
                                            {{ activity.causer.email }}
                                        </div>
                                    </div>
                                    <span
                                        v-else
                                        class="text-muted-foreground italic"
                                        >System</span
                                    >
                                </TableCell>
                                <TableCell
                                    class="text-xs text-muted-foreground whitespace-nowrap"
                                >
                                    {{ formatDateTime(activity.created_at) }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!activities.data.length">
                                <TableCell
                                    colspan="6"
                                    class="text-center py-12 text-muted-foreground"
                                >
                                    <div
                                        class="flex flex-col items-center gap-2"
                                    >
                                        <History class="w-8 h-8 opacity-40" />
                                        <p>
                                            Tidak ada aktivitas yang ditemukan
                                        </p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div
                    v-if="activities.total > activities.per_page"
                    class="px-4 py-3"
                >
                    <Pagination>
                        <PaginationContent>
                            <PaginationItem v-if="activities.current_page > 1">
                                <Link
                                    :href="activities.first_page_url"
                                    preserve-scroll
                                >
                                    <PaginationFirst />
                                </Link>
                            </PaginationItem>
                            <PaginationItem v-if="activities.prev_page_url">
                                <Link
                                    :href="activities.prev_page_url"
                                    preserve-scroll
                                >
                                    <PaginationPrevious />
                                </Link>
                            </PaginationItem>
                            <PaginationItem>
                                <span
                                    class="text-sm text-muted-foreground px-2"
                                >
                                    {{ activities.current_page }} /
                                    {{ activities.last_page }}
                                </span>
                            </PaginationItem>
                            <PaginationItem v-if="activities.next_page_url">
                                <Link
                                    :href="activities.next_page_url"
                                    preserve-scroll
                                >
                                    <PaginationNext />
                                </Link>
                            </PaginationItem>
                            <PaginationItem
                                v-if="
                                    activities.current_page <
                                    activities.last_page
                                "
                            >
                                <Link
                                    :href="activities.last_page_url"
                                    preserve-scroll
                                >
                                    <PaginationLast />
                                </Link>
                            </PaginationItem>
                        </PaginationContent>
                    </Pagination>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
