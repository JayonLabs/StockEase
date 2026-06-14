<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Mail, Calendar, Users } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import { Button } from '@/Components/ui/button';
import { formatPrice } from '@/lib/utils';

const props = defineProps({
    company: { type: Object, required: true },
});

const formatDate = (d) => {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const statusBadge = (status) => {
    const m = {
        active: 'border-emerald-800 bg-emerald-950 text-emerald-400',
        trialing: 'border-yellow-800 bg-yellow-950 text-yellow-400',
        canceled: 'border-red-800 bg-red-950 text-red-400',
        expired: 'border-zinc-700 bg-zinc-800 text-zinc-500',
        pending: 'border-blue-800 bg-blue-950 text-blue-400',
    };
    return m[status] || 'border-zinc-700 bg-zinc-800 text-zinc-500';
};
</script>

<template>
    <Head :title="`${company.name} - Platform Owner`" />

    <div>
        <Link :href="route('platform.owner.companies.index')">
            <Button
                variant="ghost"
                size="sm"
                class="text-zinc-400 hover:text-zinc-100 mb-4"
            >
                &larr; Back to Companies
            </Button>
        </Link>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <Card class="border-zinc-800 bg-zinc-900">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-zinc-100">
                    <Building2 class="w-5 h-5 text-emerald-400" />
                    Company Details
                </CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-zinc-500">Name</dt>
                        <dd class="font-medium text-zinc-100">
                            {{ company.name }}
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Slug</dt>
                        <dd class="text-zinc-400">{{ company.slug }}</dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Status</dt>
                        <dd>
                            <Badge
                                variant="outline"
                                :class="
                                    company.is_active
                                        ? 'border-emerald-800 bg-emerald-950 text-emerald-400'
                                        : 'border-zinc-700 bg-zinc-800 text-zinc-500'
                                "
                            >
                                {{ company.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Registered</dt>
                        <dd class="text-zinc-400">
                            {{ formatDate(company.created_at) }}
                        </dd>
                    </div>
                    <template v-if="company.address">
                        <Separator class="bg-zinc-800" />
                        <div>
                            <dt class="text-sm text-zinc-500">Address</dt>
                            <dd class="text-zinc-400">{{ company.address }}</dd>
                        </div>
                    </template>
                    <template v-if="company.phone">
                        <Separator class="bg-zinc-800" />
                        <div>
                            <dt class="text-sm text-zinc-500">Phone</dt>
                            <dd class="text-zinc-400">{{ company.phone }}</dd>
                        </div>
                    </template>
                </dl>
            </CardContent>
        </Card>

        <Card v-if="company.subscription" class="border-zinc-800 bg-zinc-900">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-zinc-100">
                    <Building2 class="w-5 h-5 text-emerald-400" />
                    Subscription
                </CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-zinc-500">Plan</dt>
                        <dd class="font-medium text-zinc-100">
                            {{ company.subscription.plan?.name ?? '-' }}
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Status</dt>
                        <dd>
                            <Badge
                                variant="outline"
                                :class="
                                    statusBadge(company.subscription.status)
                                "
                            >
                                {{ company.subscription.status }}
                            </Badge>
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Billing Cycle</dt>
                        <dd class="text-zinc-400">
                            {{
                                company.subscription.billing_cycle === 'annual'
                                    ? 'Annual'
                                    : 'Monthly'
                            }}
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Price</dt>
                        <dd class="text-zinc-400">
                            {{
                                company.subscription.billing_cycle === 'annual'
                                    ? formatPrice(
                                          company.subscription.plan
                                              ?.price_annual ?? 0,
                                      )
                                    : formatPrice(
                                          company.subscription.plan
                                              ?.price_monthly ?? 0,
                                      )
                            }}
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Ends At</dt>
                        <dd class="text-zinc-400">
                            {{ formatDate(company.subscription.ends_at) }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card v-if="company.owner" class="border-zinc-800 bg-zinc-900">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-zinc-100">
                    <Users class="w-5 h-5 text-emerald-400" />
                    Owner
                </CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-zinc-500">Name</dt>
                        <dd class="font-medium text-zinc-100">
                            {{ company.owner.name }}
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Email</dt>
                        <dd class="flex items-center gap-1 text-zinc-400">
                            <Mail class="w-4 h-4" />
                            {{ company.owner.email }}
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Joined</dt>
                        <dd class="flex items-center gap-1 text-zinc-400">
                            <Calendar class="w-4 h-4" />
                            {{ formatDate(company.owner.created_at) }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
