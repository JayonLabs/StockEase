<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Switch } from '@/Components/ui/switch';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { ScrollArea } from '@/Components/ui/scroll-area';
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next';

defineProps({
    plans: { type: Array, required: true },
});

const showSheet = ref(false);
const editingPlan = ref(null);

const emptyForm = () => ({
    name: '',
    slug: '',
    description: '',
    price_monthly: 0,
    price_annual: 0,
    max_products: null,
    max_users: null,
    max_warehouses: null,
    max_shifts: null,
    trial_days: 0,
    is_active: true,
    sort_order: 0,
    features: [],
});

const form = useForm(emptyForm());

const formatPrice = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);

const openCreate = () => {
    editingPlan.value = null;
    form.reset();
    Object.assign(form, emptyForm());
    showSheet.value = true;
};

const openEdit = (plan) => {
    editingPlan.value = plan;
    form.name = plan.name;
    form.slug = plan.slug;
    form.description = plan.description ?? '';
    form.price_monthly = plan.price_monthly;
    form.price_annual = plan.price_annual;
    form.max_products = plan.max_products;
    form.max_users = plan.max_users;
    form.max_warehouses = plan.max_warehouses;
    form.max_shifts = plan.max_shifts;
    form.trial_days = plan.trial_days;
    form.is_active = plan.is_active;
    form.sort_order = plan.sort_order;
    form.features = plan.features ? JSON.parse(JSON.stringify(plan.features)) : [];
    showSheet.value = true;
};

const submit = () => {
    if (editingPlan.value) {
        form.put(route('platform.owner.plans.update', editingPlan.value.id), {
            onSuccess: () => {
                showSheet.value = false;
            },
        });
    } else {
        form.post(route('platform.owner.plans.store'), {
            onSuccess: () => {
                showSheet.value = false;
            },
        });
    }
};

const deletePlan = (plan) => {
    if (!confirm(`Hapus plan "${plan.name}"? Tindakan ini tidak dapat dibatalkan.`)) return;
    router.delete(route('platform.owner.plans.destroy', plan.id));
};

const addFeature = () => {
    form.features.push({
        key: '',
        label: '',
        included: false,
        card_order: null,
    });
};

const removeFeature = (index) => {
    form.features.splice(index, 1);
};
</script>

<template>
    <Head title="Plans - Platform Owner" />

    <Card class="border-zinc-800 bg-zinc-900">
        <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle class="text-zinc-100">Manage Plans</CardTitle>
            <Button
                size="sm"
                class="bg-zinc-100 text-zinc-900 hover:bg-zinc-200"
                @click="openCreate"
            >
                <Plus class="w-4 h-4 mr-1" />
                Tambah Plan
            </Button>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow class="border-zinc-800 hover:bg-transparent">
                        <TableHead class="text-zinc-500">Plan</TableHead>
                        <TableHead class="text-zinc-500">Price/Month</TableHead>
                        <TableHead class="text-zinc-500">Price/Year</TableHead>
                        <TableHead class="text-zinc-500">Max Products</TableHead>
                        <TableHead class="text-zinc-500">Max Users</TableHead>
                        <TableHead class="text-zinc-500">Max Warehouses</TableHead>
                        <TableHead class="text-zinc-500">Trial</TableHead>
                        <TableHead class="text-zinc-500">Subscribers</TableHead>
                        <TableHead class="text-zinc-500">Active</TableHead>
                        <TableHead class="text-zinc-500 w-24">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="plan in plans"
                        :key="plan.id"
                        class="border-zinc-800"
                    >
                        <TableCell class="font-medium text-zinc-100">
                            <div>{{ plan.name }}</div>
                            <div class="text-xs text-zinc-500">{{ plan.slug }}</div>
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ formatPrice(plan.price_monthly) }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ formatPrice(plan.price_annual) }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ plan.max_products ?? 'Unlimited' }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ plan.max_users ?? 'Unlimited' }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ plan.max_warehouses ?? 'Unlimited' }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ plan.trial_days }} days
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ plan.subscriptions_count ?? 0 }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant="outline"
                                :class="
                                    plan.is_active
                                        ? 'border-emerald-800 bg-emerald-950 text-emerald-400'
                                        : 'border-zinc-700 bg-zinc-800 text-zinc-500'
                                "
                            >
                                {{ plan.is_active ? 'Yes' : 'No' }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-zinc-400 hover:text-zinc-100"
                                    @click="openEdit(plan)"
                                >
                                    <Pencil class="w-4 h-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-zinc-400 hover:text-red-400"
                                    @click="deletePlan(plan)"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="plans.length === 0">
                        <TableCell
                            colspan="10"
                            class="text-center text-zinc-600 py-8"
                        >
                            No plans configured yet
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>

    <!-- Create / Edit Sheet -->
    <Sheet v-model:open="showSheet">
        <SheetContent
            side="right"
            class="w-full sm:max-w-2xl border-zinc-800 bg-zinc-900 p-0"
        >
            <SheetHeader class="px-6 pt-6 pb-4 border-b border-zinc-800">
                <SheetTitle class="text-zinc-100">
                    {{ editingPlan ? 'Edit Plan' : 'Tambah Plan' }}
                </SheetTitle>
                <SheetDescription class="text-zinc-500">
                    {{
                        editingPlan
                            ? 'Perbarui data plan dan fitur-fiturnya.'
                            : 'Buat plan baru dengan harga, limit resource, dan daftar fitur.'
                    }}
                </SheetDescription>
            </SheetHeader>

            <ScrollArea class="h-[calc(100vh-10rem)]">
                <form @submit.prevent="submit" class="px-6 py-4 space-y-6">
                    <!-- Basic Info -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-zinc-400 uppercase tracking-wider">
                            Basic Info
                        </h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label class="text-zinc-300">Nama Plan</Label>
                                <Input
                                    v-model="form.name"
                                    placeholder="Pemula"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-600"
                                />
                                <p v-if="form.errors.name" class="text-xs text-red-400">
                                    {{ form.errors.name }}
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-zinc-300">Slug</Label>
                                <Input
                                    v-model="form.slug"
                                    placeholder="pemula"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-600"
                                />
                                <p v-if="form.errors.slug" class="text-xs text-red-400">
                                    {{ form.errors.slug }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-zinc-300">Deskripsi</Label>
                            <Textarea
                                v-model="form.description"
                                placeholder="Deskripsi singkat plan ini..."
                                rows="2"
                                class="border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-600 resize-none"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label class="text-zinc-300">Sort Order</Label>
                                <Input
                                    v-model.number="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100"
                                />
                                <p v-if="form.errors.sort_order" class="text-xs text-red-400">
                                    {{ form.errors.sort_order }}
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-zinc-300">Status Aktif</Label>
                                <div class="flex items-center gap-2 h-9">
                                    <Switch
                                        :checked="form.is_active"
                                        @update:checked="(val) => (form.is_active = val)"
                                    />
                                    <span class="text-sm text-zinc-400">
                                        {{ form.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-zinc-400 uppercase tracking-wider">
                            Harga
                        </h3>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="space-y-1.5">
                                <Label class="text-zinc-300">Harga Bulanan (IDR)</Label>
                                <Input
                                    v-model.number="form.price_monthly"
                                    type="number"
                                    min="0"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100"
                                />
                                <p v-if="form.errors.price_monthly" class="text-xs text-red-400">
                                    {{ form.errors.price_monthly }}
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-zinc-300">Harga Tahunan (IDR)</Label>
                                <Input
                                    v-model.number="form.price_annual"
                                    type="number"
                                    min="0"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100"
                                />
                                <p v-if="form.errors.price_annual" class="text-xs text-red-400">
                                    {{ form.errors.price_annual }}
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-zinc-300">Trial Days</Label>
                                <Input
                                    v-model.number="form.trial_days"
                                    type="number"
                                    min="0"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100"
                                />
                                <p v-if="form.errors.trial_days" class="text-xs text-red-400">
                                    {{ form.errors.trial_days }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Resource Limits -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-zinc-400 uppercase tracking-wider">
                            Limit Resource
                            <span class="normal-case font-normal text-zinc-600 ml-1">(kosongkan = unlimited)</span>
                        </h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label class="text-zinc-300">Max Produk</Label>
                                <Input
                                    v-model.number="form.max_products"
                                    type="number"
                                    min="1"
                                    placeholder="Unlimited"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-600"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-zinc-300">Max Users</Label>
                                <Input
                                    v-model.number="form.max_users"
                                    type="number"
                                    min="1"
                                    placeholder="Unlimited"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-600"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-zinc-300">Max Gudang</Label>
                                <Input
                                    v-model.number="form.max_warehouses"
                                    type="number"
                                    min="1"
                                    placeholder="Unlimited"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-600"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-zinc-300">Max Shift</Label>
                                <Input
                                    v-model.number="form.max_shifts"
                                    type="number"
                                    min="1"
                                    placeholder="Unlimited"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-600"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Features -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-zinc-400 uppercase tracking-wider">
                                Fitur
                            </h3>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-zinc-400 hover:text-zinc-100 h-7 px-2"
                                @click="addFeature"
                            >
                                <Plus class="w-3.5 h-3.5 mr-1" />
                                Tambah Fitur
                            </Button>
                        </div>

                        <p v-if="form.errors.features" class="text-xs text-red-400">
                            {{ form.errors.features }}
                        </p>

                        <div v-if="form.features.length === 0" class="text-sm text-zinc-600 py-2">
                            Belum ada fitur. Klik "Tambah Fitur" untuk menambahkan.
                        </div>

                        <div
                            v-for="(feature, index) in form.features"
                            :key="index"
                            class="grid grid-cols-[1fr_1fr_5rem_2rem_2rem] gap-2 items-start"
                        >
                            <div>
                                <Input
                                    v-model="feature.key"
                                    placeholder="key (cth: purchasing)"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-600 text-xs h-8"
                                />
                                <p
                                    v-if="form.errors[`features.${index}.key`]"
                                    class="text-xs text-red-400 mt-0.5"
                                >
                                    {{ form.errors[`features.${index}.key`] }}
                                </p>
                            </div>
                            <div>
                                <Input
                                    v-model="feature.label"
                                    placeholder="Label tampilan"
                                    class="border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-600 text-xs h-8"
                                />
                                <p
                                    v-if="form.errors[`features.${index}.label`]"
                                    class="text-xs text-red-400 mt-0.5"
                                >
                                    {{ form.errors[`features.${index}.label`] }}
                                </p>
                            </div>
                            <Input
                                v-model.number="feature.card_order"
                                type="number"
                                min="1"
                                placeholder="Order"
                                class="border-zinc-700 bg-zinc-800 text-zinc-100 placeholder:text-zinc-600 text-xs h-8"
                            />
                            <div class="flex items-center h-8">
                                <Switch
                                    :checked="feature.included"
                                    @update:checked="(val) => (feature.included = val)"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="text-zinc-600 hover:text-red-400 h-8 w-8"
                                @click="removeFeature(index)"
                            >
                                <X class="w-3.5 h-3.5" />
                            </Button>
                        </div>
                    </div>
                </form>
            </ScrollArea>

            <SheetFooter class="px-6 py-4 border-t border-zinc-800">
                <Button
                    variant="ghost"
                    class="text-zinc-400 hover:text-zinc-100"
                    @click="showSheet = false"
                >
                    Batal
                </Button>
                <Button
                    class="bg-zinc-100 text-zinc-900 hover:bg-zinc-200"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ form.processing ? 'Menyimpan...' : editingPlan ? 'Simpan Perubahan' : 'Buat Plan' }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
