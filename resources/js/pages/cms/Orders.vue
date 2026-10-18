<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Download, FileSpreadsheet, Layers, PackageCheck, ShoppingBag } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CmsEmptyState from '@/components/cms/CmsEmptyState.vue';
import CmsMetricCard from '@/components/cms/CmsMetricCard.vue';
import CmsPageHeader from '@/components/cms/CmsPageHeader.vue';
import OrderDeleteDialog from '@/components/cms/OrderDeleteDialog.vue';
import OrderDetailDialog from '@/components/cms/OrderDetailDialog.vue';
import OrderImportDialog from '@/components/cms/OrderImportDialog.vue';
import DailySummaryDialog from '@/components/mobile/organisms/DailySummaryDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/cms';
import {
    exportMethod as ordersExport,
    index as ordersIndex,
    template as ordersTemplate,
} from '@/routes/cms/orders';
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
    pagination: { current_page: number; last_page: number; total: number };
    statistics: { total: number; in_progress: number; ready: number; completed: number };
    filters: {
        search?: string;
        organization_id?: number;
        branch_id?: number;
        status?: string;
        date_from?: string;
        date_to?: string;
        overdue?: boolean;
        unpaid?: boolean;
    };
}>();
const page = usePage();
const isSuperadmin = computed(() => page.props.auth.user.roles.includes('superadmin'));

const organizationFilter = ref(props.filters.organization_id?.toString() ?? 'all');
const branchFilter = ref(props.filters.branch_id?.toString() ?? 'all');
const statusFilter = ref(props.filters.status ?? 'all');
const searchQuery = ref(props.filters.search ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const overdueOnly = ref(Boolean(props.filters.overdue));
const unpaidOnly = ref(Boolean(props.filters.unpaid));
const loading = ref(false);
const summaryOpen = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | undefined;
const visitPage = (pageNumber = 1): void => {
    clearTimeout(searchTimer);
    router.get(
        ordersIndex().url,
        {
            page: pageNumber,
            ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
            ...(organizationFilter.value !== 'all'
                ? { organization_id: organizationFilter.value }
                : {}),
            ...(branchFilter.value !== 'all' ? { branch_id: branchFilter.value } : {}),
            ...(statusFilter.value !== 'all' ? { status: statusFilter.value } : {}),
            ...(dateFrom.value ? { date_from: dateFrom.value } : {}),
            ...(dateTo.value ? { date_to: dateTo.value } : {}),
            ...(overdueOnly.value ? { overdue: 1 } : {}),
            ...(unpaidOnly.value ? { unpaid: 1 } : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
};

const handleExport = (): void => {
    const params = new URLSearchParams();
    if (searchQuery.value.trim()) params.append('search', searchQuery.value.trim());
    if (organizationFilter.value !== 'all') params.append('organization_id', organizationFilter.value);
    if (branchFilter.value !== 'all') params.append('branch_id', branchFilter.value);
    if (statusFilter.value !== 'all') params.append('status', statusFilter.value);
    if (dateFrom.value) params.append('date_from', dateFrom.value);
    if (dateTo.value) params.append('date_to', dateTo.value);
    if (overdueOnly.value) params.append('overdue', '1');
    if (unpaidOnly.value) params.append('unpaid', '1');

    const queryString = params.toString();
    window.location.href = `${ordersExport().url}${queryString ? `?${queryString}` : ''}`;
};

watch(
    [
        organizationFilter,
        branchFilter,
        statusFilter,
        searchQuery,
        dateFrom,
        dateTo,
        overdueOnly,
        unpaidOnly,
    ],
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => visitPage(), 300);
    },
);
onBeforeUnmount(() => clearTimeout(searchTimer));
const pageNumbers = computed(() => {
    const first = Math.max(
        1,
        Math.min(props.pagination.current_page - 2, props.pagination.last_page - 4),
    );
    return Array.from(
        { length: Math.min(5, props.pagination.last_page) },
        (_, index) => first + index,
    );
});
const reloadOrders = (): void => {
    router.reload({ only: ['orders', 'statistics', 'pagination'] });
};

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

const visibleOrders = computed(() => props.orders);

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
            >
                <template #actions>
                    <Button as-child variant="outline" class="gap-2">
                        <a :href="ordersTemplate().url" download>
                            <FileSpreadsheet class="size-4" />
                            Template CSV
                        </a>
                    </Button>
                    <Button variant="outline" class="gap-2" @click="handleExport">
                        <Download class="size-4" />
                        Export CSV
                    </Button>
                    <OrderImportDialog />
                </template>
            </CmsPageHeader>

            <!-- Metric Cards -->
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan antrean pesanan">
                <CmsMetricCard
                    label="Total antrean"
                    :value="statistics.total"
                    detail="Semua pesanan terpantau"
                    :icon="Layers"
                />
                <CmsMetricCard
                    label="Perlu diproses"
                    :value="statistics.in_progress"
                    detail="Cuci, kering & setrika"
                    :icon="Clock"
                />
                <CmsMetricCard
                    label="Siap diambil"
                    :value="statistics.ready"
                    detail="Menunggu pelanggan"
                    :icon="PackageCheck"
                />
                <CmsMetricCard
                    label="Selesai"
                    :value="statistics.completed"
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
            <section class="flex flex-wrap items-end gap-4 rounded-2xl border border-line bg-surface p-5" aria-label="Filter waktu dan pembayaran">
                <label class="grid gap-2 text-sm">Dari tanggal<input v-model="dateFrom" type="date" class="min-h-11 rounded-lg border border-line px-3" /></label>
                <label class="grid gap-2 text-sm">Sampai tanggal<input v-model="dateTo" type="date" :min="dateFrom" class="min-h-11 rounded-lg border border-line px-3" /></label>
                <label class="flex min-h-11 items-center gap-2 text-sm"><input v-model="overdueOnly" type="checkbox" />Lewat estimasi</label>
                <label class="flex min-h-11 items-center gap-2 text-sm"><input v-model="unpaidOnly" type="checkbox" />Belum lunas</label>
                <button type="button" class="min-h-11 rounded-xl bg-brand-700 px-4 text-sm font-semibold text-white" @click="summaryOpen = true">Ringkasan harian</button>
                <p v-if="loading" class="text-sm text-ink-500" role="status">Memuat pesanan…</p>
            </section>
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
                                    <div class="flex items-center justify-end gap-1">
                                        <OrderDetailDialog
                                            :order="order"
                                            :order-statuses="orderStatuses"
                                            @saved="reloadOrders"
                                        />
                                        <OrderDeleteDialog
                                            :order="order"
                                            @deleted="reloadOrders"
                                        />
                                    </div>
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
                            <div class="flex items-center gap-1">
                                <OrderDetailDialog
                                    :order="order"
                                    :order-statuses="orderStatuses"
                                    @saved="reloadOrders"
                                />
                                <OrderDeleteDialog
                                    :order="order"
                                    @deleted="reloadOrders"
                                />
                            </div>
                        </div>
                    </article>
                </div>
            </section>
            <nav class="flex flex-wrap items-center justify-between gap-3" aria-label="Halaman pesanan">
                <p class="text-sm text-ink-500">{{ pagination.total }} pesanan · Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-3 text-sm disabled:opacity-40" :disabled="loading || pagination.current_page === 1" @click="visitPage(pagination.current_page - 1)">Sebelumnya</button>
                    <button v-for="pageNumber in pageNumbers" :key="pageNumber" type="button" class="min-h-11 min-w-11 rounded-lg px-3 text-sm" :class="pageNumber === pagination.current_page ? 'bg-brand-700 text-white' : 'border border-line bg-surface'" :aria-current="pageNumber === pagination.current_page ? 'page' : undefined" :disabled="loading" @click="visitPage(pageNumber)">{{ pageNumber }}</button>
                    <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-3 text-sm disabled:opacity-40" :disabled="loading || pagination.current_page === pagination.last_page" @click="visitPage(pagination.current_page + 1)">Berikutnya</button>
                </div>
            </nav>
            <DailySummaryDialog v-if="summaryOpen" v-model:open="summaryOpen" :organization-id="organizationFilter !== 'all' ? Number(organizationFilter) : undefined" :branch-id="branchFilter !== 'all' ? Number(branchFilter) : undefined" />
        </div>
    </main>
</template>
