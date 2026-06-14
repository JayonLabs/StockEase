<script setup>
import { Head } from '@inertiajs/vue3';
import { Building2, Users, CreditCard, DollarSign } from 'lucide-vue-next';

import StatsCard from './Components/StatsCard.vue';
import SubscriptionChart from './Components/SubscriptionChart.vue';
import RevenueChart from './Components/RevenueChart.vue';
import TenantsTable from './Components/TenantsTable.vue';
import RecentRegistrations from './Components/RecentRegistrations.vue';

const props = defineProps({
    data: { type: Object, required: true },
});

const overview = props.data.overview;
const subscriptionBreakdown = props.data.subscription_breakdown;
const recentCompanies = props.data.recent_companies;
const activeCompanies = props.data.active_companies;
const growthTrend = props.data.growth_trend;

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}
</script>

<template>
    <Head title="Platform Dashboard" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatsCard
            title="Total Companies"
            :value="overview.total_companies"
            :icon="Building2"
            description="All registered tenants"
        />
        <StatsCard
            title="Active Subscriptions"
            :value="overview.active_subscriptions"
            :icon="CreditCard"
            description="Currently paying or trialing"
        />
        <StatsCard
            title="Total Users"
            :value="overview.total_users"
            :icon="Users"
            description="Across all tenants"
        />
        <StatsCard
            title="Monthly Revenue (MRR)"
            :value="formatCurrency(overview.mrr)"
            :icon="DollarSign"
            description="Recurring monthly revenue"
        />
    </div>

    <div class="mt-8 grid gap-4 lg:grid-cols-2">
        <SubscriptionChart :data="subscriptionBreakdown" />
        <RevenueChart :data="growthTrend" />
    </div>

    <div class="mt-8 grid gap-4 lg:grid-cols-2">
        <TenantsTable :data="activeCompanies" />
        <RecentRegistrations :data="recentCompanies" />
    </div>
</template>
