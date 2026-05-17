<script setup>
import {
    FlexRender,
    getCoreRowModel,
    getExpandedRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useVueTable,
} from "@tanstack/vue-table";

import { router } from "@inertiajs/vue3";
import { watchDebounced } from "@vueuse/core";

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";

import { computed, ref } from "vue";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";

import { Input } from "@/Components/ui/input";

import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from "@/Components/ui/pagination";
import { Search } from "lucide-vue-next";
import { Button } from "../button";
import { DoubleArrowLeftIcon, DoubleArrowRightIcon } from "@radix-icons/vue";

const props = defineProps({
    data: Array,
    columns: Array,
    routeName: String,
    pagination: Object,
    routeParams: {
        type: Object,
        required: false,
    },
    pageParam: {
        type: String,
        default: 'page',
    },
    perPageParam: {
        type: String,
        default: 'per_page',
    },
});

const table = useVueTable({
    get data() {
        return props.data;
    },
    get columns() {
        return props.columns;
    },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getExpandedRowModel: getExpandedRowModel(),
    manualPagination: true,
    state: {
        get pagination() {
            return {
                pageIndex: props.pagination.current_page - 1,
                pageSize: props.pagination.per_page,
            };
        },
    },
});

const search = ref(new URLSearchParams(window.location.search).get("search") || "");

const goToPage = (pageIndex, pageSize = null) => {
    const query = {
        ...Object.fromEntries(new URLSearchParams(window.location.search)),
        [props.pageParam]: pageIndex + 1,
        [props.perPageParam]: pageSize ?? props.pagination.per_page,
        search: search.value,
    };

    router.get(route(props.routeName, props.routeParams), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const goToFirstPage = () => goToPage(0);
const goToPreviousPage = () => goToPage(props.pagination.current_page - 2);
const goToNextPage = () => goToPage(props.pagination.current_page);
const goToLastPage = () => goToPage(props.pagination.last_page - 1);

const isLastPage = computed(() => {
    return props.pagination.current_page >= props.pagination.last_page;
});

const canGoPrevious = computed(() => {
    return props.pagination.current_page > 1;
});

watchDebounced(
    search,
    (newSearch) => {
        const query = {
            ...Object.fromEntries(new URLSearchParams(window.location.search)),
            [props.pageParam]: 1,
            [props.perPageParam]: props.pagination.per_page,
            search: newSearch,
        };

        router.get(route(props.routeName, props.routeParams), query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    },
    { debounce: 300 },
);
</script>

<template>
  <div class="w-full">
    <div class="relative w-full max-w-sm items-center mb-4">
      <Input
        id="search"
        v-model="search"
        type="text"
        placeholder="Search..."
        autocomplete="off"
        class="pl-10 shadow-md focus:ring-0 focus:ring-offset-0"
      />
      <span
        class="absolute inset-s-0 inset-y-0 flex items-center justify-center px-2"
      >
        <Search class="w-5 h-5 text-muted-foreground" />
      </span>
    </div>

    <div class="rounded-md border">
      <Table>
        <TableHeader>
          <TableRow
            v-for="headerGroup in table.getHeaderGroups()"
            :key="headerGroup.id"
            class="hover:bg-transparent"
          >
            <TableHead
              v-for="header in headerGroup.headers"
              :key="header.id"
              class="border-b"
            >
              <FlexRender
                v-if="!header.isPlaceholder"
                :render="header.column.columnDef.header"
                :props="header.getContext()"
              />
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody class="divide-y">
          <template v-if="table.getRowModel().rows?.length">
            <template
              v-for="row in table.getRowModel().rows"
              :key="row.id"
            >
              <TableRow
                :data-state="row.getIsSelected() && 'selected'"
                class="hover:bg-transparent"
              >
                <TableCell
                  v-for="cell in row.getVisibleCells()"
                  :key="cell.id"
                  class="border-b"
                >
                  <FlexRender
                    :render="cell.column.columnDef.cell"
                    :props="cell.getContext()"
                  />
                </TableCell>
              </TableRow>
              <TableRow v-if="row.getIsExpanded()">
                <TableCell :colspan="row.getAllCells().length">
                  {{ JSON.stringify(row.original) }}
                </TableCell>
              </TableRow>
            </template>
          </template>

          <TableRow v-else>
            <TableCell
              :colspan="columns.length"
              class="h-24 text-center"
            >
              No results.
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
    <div class="flex items-center justify-end space-x-2 py-4 sm:space-x-6">
      <div class="flex items-center space-x-2">
        <p class="text-sm font-medium">
          Rows per page
        </p>
        <Select
          :model-value="`${props.pagination.per_page}`"
          @update:model-value="goToPage(0, Number($event))"
        >
          <SelectTrigger class="h-8 w-17.5">
            <SelectValue
              :placeholder="`${
                table.getState().pagination.pageSize
              }`"
            />
          </SelectTrigger>
          <SelectContent side="top">
            <SelectItem
              v-for="pageSize in [5, 10, 20, 30, 40, 50]"
              :key="pageSize"
              class="cursor-pointer"
              :value="`${pageSize}`"
            >
              {{ pageSize }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="overflow-auto sm:overflow-visible max-w-full">
        <div class="flex items-center space-x-2">
          <Pagination
            v-slot="{ page }"
            :page="props.pagination.current_page"
            :items-per-page="props.pagination.per_page"
            :total="props.pagination.total"
          >
            <PaginationContent
              v-slot="{ items }"
              class="flex"
            >
              <Button
                variant="outline"
                class="hidden w-8 h-8 p-0 lg:flex"
                :disabled="!canGoPrevious"
                @click="goToFirstPage"
              >
                <span class="sr-only">Go to first page</span>
                <DoubleArrowLeftIcon class="w-4 h-4" />
              </Button>

              <PaginationPrevious
                class="border"
                :disabled="!canGoPrevious"
                @click="goToPreviousPage"
              />

              <template
                v-for="(item, index) in items"
                :key="index"
              >
                <PaginationItem
                  v-if="item.type === 'page'"
                  class="border disabled:opacity-50 disabled:cursor-not-allowed"
                  :value="item.value"
                  :is-active="item.value === page"
                  :disabled="item.value === page"
                  @click="goToPage(item.value - 1)"
                >
                  {{ item.value }}
                </PaginationItem>
              </template>

              <PaginationNext
                class="border"
                :disabled="isLastPage"
                @click="goToNextPage"
              />
              <Button
                variant="outline"
                class="hidden w-8 h-8 p-0 lg:flex"
                :disabled="isLastPage"
                @click="goToLastPage"
              >
                <span class="sr-only">Go to last page</span>
                <DoubleArrowRightIcon class="w-4 h-4" />
              </Button>
            </PaginationContent>
          </Pagination>
        </div>
      </div>
    </div>
  </div>
</template>
