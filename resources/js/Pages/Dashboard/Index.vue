<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminDashboard from './Admin/AdminDashboard.vue';
import { Head, usePage } from '@inertiajs/vue3';
import CashierDashboard from './Cashier/CashierDashboard.vue';
import WarehouseDashboard from './Warehouse/WarehouseDashboard.vue';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

const page = usePage();

// Data Admin
const salesSummary = page.props.data?.salesSummary;
const lowStock = page.props.data?.lowStock;
const activities = page.props.data?.activities;
const weeklySalesChart = page.props.data?.weeklySalesChart;
const priceUpdateChart = page.props.data?.priceUpdateChart;

// Data Cashier
const cashierSalesSummary = page.props.data?.cashierSalesSummary;
const cashierRecentTrasactions = page.props.data?.recentTransaction;
const cashierWeeklySalesChart = page.props.data?.weeklySalesChart;

// Data Warehouse
const warehouseSummary = page.props.data?.warehouseSummary;
const activityLogWarehouse = page.props.data?.activityLogWarehouse;
const warehouseChart = page.props.data?.warehouseChart;

const role = page.props.auth.user.role;
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Dashboard</title>
        </Head>
        <template #breadcrumb>
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbSeparator class="hidden md:block" />
                    <BreadcrumbItem>
                        <BreadcrumbPage> Dashboard </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <AdminDashboard
            v-if="role === 'admin' || role === 'super_admin'"
            :sales-summary="salesSummary"
            :low-stock="lowStock"
            :activities="activities"
            :weekly-sales-chart="weeklySalesChart"
            :price-update-chart="priceUpdateChart"
        />

        <CashierDashboard
            v-if="role === 'cashier'"
            :cashier-sales-summary="cashierSalesSummary"
            :cashier-recent-trasactions="cashierRecentTrasactions"
            :cashier-weekly-sales-chart="cashierWeeklySalesChart"
        />

        <WarehouseDashboard
            v-if="role === 'warehouse'"
            :warehouse-summary="warehouseSummary"
            :activity-log-warehouse="activityLogWarehouse"
            :warehouse-chart="warehouseChart"
        />
    </AuthenticatedLayout>
</template>
