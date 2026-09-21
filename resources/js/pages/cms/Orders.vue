<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Layers, PackageCheck, ShoppingBag } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CmsEmptyState from '@/components/cms/CmsEmptyState.vue';
import CmsMetricCard from '@/components/cms/CmsMetricCard.vue';
import CmsPageHeader from '@/components/cms/CmsPageHeader.vue';
import OrderDetailDialog from '@/components/cms/OrderDetailDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/cms';
import { index as ordersIndex } from '@/routes/cms/orders';
import type {
    CmsOrderItemSummary,
    CmsOrderSummary,
    OrderBranchOption,
    OrderStatusOption,
    OrganizationOption,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Antrean pesanan', href: ordersIndex() },
        ],
    },
});

const props = defineProps<{
    orders: CmsOrderSummary[];
    branches: OrderBranchOption[];
    organizations: OrganizationOption[];
    orderStatuses: OrderStatusOption[];
}>();
const page = usePage();
const isSuperadmin = computed(() => page.props.auth.user.roles.includes('superadmin'));

const organizationFilter = ref<string>('all');
const branchFilter = ref<string>('all');
const statusFilter = ref<string>('all');
const searchQuery = ref<string>('');

watch(organizationFilter, () => {
    branchFilter.value = 'all';
});

const availableBranches = computed(() => {
    if (!isSuperadmin.value || organizationFilter.value === 'all') {
        return props.branches;
    }

    return props.branches.filter(
        (branch) => branch.organization_id.toString() === organizationFilter.value,
    );
});

const visibleOrders = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return props.orders.filter((order) => {
        if (isSuperadmin.value && organizationFilter.value !== 'all') {
            if (order.organization_id.toString() !== organizationFilter.value) {
                return false;
            }
        }

        if (branchFilter.value !== 'all') {
            if (order.branch.id.toString() !== branchFilter.value) {
                return false;
            }
        }

        if (statusFilter.value !== 'all') {
            if (order.status !== statusFilter.value) {
                return false;
            }
        }

        if (query !== '') {
            const matchNumber = order.order_number?.toLowerCase().includes(query) ?? false;
            const matchCustomer = order.customer.name.toLowerCase().includes(query);
            const matchPhone =
                order.customer.whatsapp_phone?.toLowerCase().includes(query) ?? false;

            if (!matchNumber && !matchCustomer && !matchPhone) {
                return false;
            }
        }

        return true;
    });
});

const inProgressStatuses = [
    'pickup_requested',
    'received',
    'weighed',
    'washing',
    'drying',
    'ironing',
];

const inProgressOrdersCount = computed(
    () => visibleOrders.value.filter((order) => inProgressStatuses.includes(order.status)).length,
);

const readyOrdersCount = computed(
    () => visibleOrders.value.filter((order) => order.status === 'ready_for_pickup').length,
);

const completedOrdersCount = computed(
    () => visibleOrders.value.filter((order) => order.status === 'completed').length,
);

const formatRupiah = (amount: number): string =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);

const formatDate = (dateStr?: string | null): string => {
    if (!dateStr) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(dateStr));
};

const statusLabel = (statusVal: string): string =>
    props.orderStatuses.find((s) => s.value === statusVal)?.label ?? statusVal;

const statusBadgeVariant = (
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' => {
    switch (status) {
        case 'completed':
            return 'default';
        case 'cancelled':
            return 'destructive';
        case 'ready_for_pickup':
            return 'default';
        default:
            return 'secondary';
    }
};

const formatWeight = (grams: number): string =>
    `${(grams / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 })} kg`;

const getOrderItems = (order: CmsOrderSummary): CmsOrderItemSummary[] => {
    if (Array.isArray(order.items)) {
        return order.items;
    }
    if (
        order.items &&
        typeof order.items === 'object' &&
        'data' in order.items &&
        Array.isArray((order.items as { data: CmsOrderItemSummary[] }).data)
    ) {
        return (order.items as { data: CmsOrderItemSummary[] }).data;
    }
    return [];
};

const itemsSummary = (order: CmsOrderSummary): string => {
    const items = getOrderItems(order);
    if (items.length === 0 || !items[0]) return 'Tanpa paket';
    const firstItem = items[0];
    const weightGrams =
        firstItem.calculation?.chargeable_weight_grams ??
        firstItem.calculation?.requested_weight_grams ??
        0;
    const weight = formatWeight(weightGrams);
    const serviceName = firstItem.service?.name ?? 'Layanan';
    if (items.length === 1) {
        return `${serviceName} (${weight})`;
    }
    return `${serviceName} +${items.length - 1} lainnya (${weight})`;
};
</script>

<template>
    <Head title="Antrean Pesanan" />

    <main class="bg-canvas flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-[1440px] space-y-6">
            <CmsPageHeader
                eyebrow="Operasional laundry"
                title="Antrean pesanan"
                description="Pantau alur pengerjaan dan status pembayaran pesanan pelanggan di seluruh cabang."
            />

            <!-- Metric Cards -->
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan antrean pesanan">
                <CmsMetricCard
                    label="Total antrean"
                    :value="visibleOrders.length"
                    detail="Semua pesanan terpantau"
                    :icon="Layers"
                />
                <CmsMetricCard
                    label="Perlu diproses"
                    :value="inProgressOrdersCount"
                    detail="Cuci, kering & setrika"
                    :icon="Clock"
                />
                <CmsMetricCard
                    label="Siap diambil"
                    :value="readyOrdersCount"
                    detail="Menunggu pelanggan"
                    :icon="PackageCheck"
                />
                <CmsMetricCard
                    label="Selesai"
                    :value="completedOrdersCount"
                    detail="Pesanan tuntas"
                    :icon="CheckCircle2"
                />
            </section>

            <!-- Filter Card (Matches /cms/services filter card pattern) -->
            <section
                class="border-line bg-surface grid gap-4 rounded-3xl border p-5 shadow-low"
                :class="isSuperadmin ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3'"
                aria-label="Filter pesanan"
            >
                <!-- Filter Organisasi (Superadmin only) -->
                <div v-if="isSuperadmin" class="grid gap-2">
                    <Label for="order-organization-filter">Filter organisasi</Label>
                    <select
                        id="order-organization-filter"
                        v-model="organizationFilter"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    >
                        <option value="all">Semua organisasi</option>
                        <option
                            v-for="organization in organizations"
                            :key="organization.id"
                            :value="organization.id.toString()"
                        >
                            {{ organization.name }}
                        </option>
                    </select>
                </div>

                <!-- Filter Cabang -->
                <div class="grid gap-2">
                    <Label for="order-branch-filter">Filter cabang</Label>
                    <select
                        id="order-branch-filter"
                        v-model="branchFilter"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    >
                        <option value="all">Semua cabang</option>
                        <option
                            v-for="branch in availableBranches"
                            :key="branch.id"
                            :value="branch.id.toString()"
                        >
                            {{ branch.name }}
                        </option>
                    </select>
                </div>

                <!-- Filter Status -->
                <div class="grid gap-2">
                    <Label for="order-status-filter">Filter status</Label>
                    <select
                        id="order-status-filter"
                        v-model="statusFilter"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    >
                        <option value="all">Semua status</option>
                        <option
                            v-for="status in orderStatuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="grid gap-2">
                    <Label for="order-search">Cari pesanan</Label>
                    <Input
                        id="order-search"
                        v-model="searchQuery"
                        type="search"
                        placeholder="No. pesanan / pelanggan..."
                        class="h-11"
                    />
                </div>
            </section>

            <!-- Orders Table & Mobile List -->
            <section class="border-line bg-surface overflow-hidden rounded-3xl border shadow-low">
                <div class="border-line border-b px-5 py-4">
                    <h2 class="font-display text-ink-950 font-semibold">Daftar antrean pesanan</h2>
                    <p class="text-ink-500 mt-1 text-sm">
                        Menampilkan pesanan yang sedang berjalan dan status pembayaran pelanggan.
                    </p>
                </div>

                <!-- Empty State -->
                <CmsEmptyState
                    v-if="visibleOrders.length === 0"
                    class="m-5"
                    title="Belum ada pesanan"
                    :description="orders.length === 0 ? 'Belum ada pesanan yang terdaftar pada sistem.' : 'Tidak ada pesanan yang sesuai dengan filter yang dipilih.'"
                    :icon="ShoppingBag"
                />

                <!-- Desktop Table View -->
                <div v-else class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-canvas text-ink-500">
                            <tr>
                                <th class="px-5 py-3 font-medium">No. Pesanan</th>
                                <th class="px-5 py-3 font-medium">Pelanggan</th>
                                <th class="px-5 py-3 font-medium">Cabang</th>
                                <th class="px-5 py-3 font-medium">Layanan</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Pembayaran</th>
                                <th class="px-5 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-line divide-y">
                            <tr v-for="order in visibleOrders" :key="order.id">
                                <!-- No. Pesanan & Tanggal -->
                                <td class="px-5 py-4">
                                    <p class="text-ink-950 font-medium">
                                        {{ order.order_number ?? `Pesanan #${order.id}` }}
                                    </p>
                                    <p class="text-ink-500 mt-0.5 text-xs">
                                        {{ formatDate(order.created_at) }}
                                    </p>
                                </td>

                                <!-- Pelanggan -->
                                <td class="px-5 py-4">
                                    <p class="text-ink-950 font-medium">{{ order.customer.name }}</p>
                                    <p v-if="order.customer.whatsapp_phone" class="text-ink-500 mt-0.5 text-xs">
                                        WA: {{ order.customer.whatsapp_phone }}
                                    </p>
                                </td>

                                <!-- Cabang & Organisasi -->
                                <td class="px-5 py-4">
                                    <p class="text-ink-950 font-medium">{{ order.branch.name }}</p>
                                    <p class="text-ink-500 mt-0.5 text-xs">{{ order.organization.name }}</p>
                                </td>

                                <!-- Layanan & Berat -->
                                <td class="px-5 py-4">
                                    <p class="text-ink-800 text-xs">{{ itemsSummary(order) }}</p>
                                </td>

                                <!-- Status Pesanan -->
                                <td class="px-5 py-4">
                                    <Badge :variant="statusBadgeVariant(order.status)">
                                        {{ statusLabel(order.status) }}
                                    </Badge>
                                </td>

                                <!-- Pembayaran -->
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-ink-950 text-xs">
                                        {{ formatRupiah(order.pricing.total_rupiah) }}
                                    </p>
                                    <div class="mt-1">
                                        <Badge
                                            v-if="order.payment_summary.is_paid"
                                            variant="default"
                                            class="bg-emerald-600 hover:bg-emerald-600 text-white text-[10px]"
                                        >
                                            Lunas
                                        </Badge>
                                        <Badge
                                            v-else-if="order.payment_summary.paid_rupiah > 0"
                                            variant="secondary"
                                            class="bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950 dark:text-amber-200 text-[10px]"
                                        >
                                            Sisa {{ formatRupiah(order.payment_summary.outstanding_rupiah) }}
                                        </Badge>
                                        <Badge v-else variant="outline" class="text-[10px]">
                                            Belum bayar
                                        </Badge>
                                    </div>
                                </td>

                                <!-- Aksi -->
                                <td class="px-5 py-4 text-right">
                                    <OrderDetailDialog
                                        :order="order"
                                        :order-statuses="orderStatuses"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div v-if="visibleOrders.length > 0" class="divide-line divide-y lg:hidden">
                    <article
                        v-for="order in visibleOrders"
                        :key="order.id"
                        class="space-y-3 p-5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-ink-950 font-semibold text-sm">
                                    {{ order.order_number ?? `Pesanan #${order.id}` }}
                                </h3>
                                <p class="text-ink-500 text-xs mt-0.5">
                                    {{ order.customer.name }} · {{ order.branch.name }}
                                </p>
                            </div>
                            <Badge :variant="statusBadgeVariant(order.status)">
                                {{ statusLabel(order.status) }}
                            </Badge>
                        </div>

                        <div class="flex items-center justify-between text-xs text-ink-600 bg-canvas/40 p-2.5 rounded-xl">
                            <div>
                                <span>{{ itemsSummary(order) }}</span>
                            </div>
                            <div class="text-right">
                                <span class="font-semibold text-ink-950">{{ formatRupiah(order.pricing.total_rupiah) }}</span>
                                <span v-if="order.payment_summary.is_paid" class="text-emerald-600 ml-1.5 font-medium">· Lunas</span>
                                <span v-else class="text-amber-600 ml-1.5 font-medium">· Belum Lunas</span>
                            </div>
                        </div>

                        <div class="border-line flex items-center justify-between border-t pt-3">
                            <span class="text-ink-400 text-[11px]">{{ formatDate(order.created_at) }}</span>
                            <OrderDetailDialog
                                :order="order"
                                :order-statuses="orderStatuses"
                            />
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </main>
</template>
