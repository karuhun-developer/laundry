<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Ban, Banknote, ClipboardList, Download, Store, Timer } from '@lucide/vue';
import { computed, reactive } from 'vue';
import CmsEmptyState from '@/components/cms/CmsEmptyState.vue';
import CmsMetricCard from '@/components/cms/CmsMetricCard.vue';
import CmsPageHeader from '@/components/cms/CmsPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatRupiah } from '@/lib/formatters';
import {
    buildReportQuery,
    filterReportBranches,
    filterReportServices,
    formatReportDuration,
} from '@/lib/reporting';
import { dashboard } from '@/routes/cms';
import dashboardRoutes from '@/routes/cms/dashboard';
import type { OperationsReportPageProps, ReportFilterForm } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<OperationsReportPageProps>();
const page = usePage();
const isSuperadmin = computed(() => page.props.auth.user.roles.includes('superadmin'));
const selectClass =
    'border-input bg-surface text-ink-950 h-11 w-full rounded-md border px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

const form = reactive<ReportFilterForm>({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    organization_id:
        isSuperadmin.value && props.filters.organization_id !== null
            ? String(props.filters.organization_id)
            : '',
    city_id: props.filters.city_id === null ? '' : String(props.filters.city_id),
    branch_id: props.filters.branch_id === null ? '' : String(props.filters.branch_id),
    service_id: props.filters.service_id === null ? '' : String(props.filters.service_id),
    status: props.filters.status ?? '',
});

const effectiveOrganizationId = computed(() =>
    isSuperadmin.value ? form.organization_id : String(props.filters.organization_id ?? ''),
);
const availableBranches = computed(() =>
    filterReportBranches(props.options.branches, effectiveOrganizationId.value, form.city_id),
);
const availableServices = computed(() =>
    filterReportServices(props.options.services, effectiveOrganizationId.value),
);
const appliedFilters = computed<ReportFilterForm>(() => ({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    organization_id:
        isSuperadmin.value && props.filters.organization_id !== null
            ? String(props.filters.organization_id)
            : '',
    city_id: props.filters.city_id === null ? '' : String(props.filters.city_id),
    branch_id: props.filters.branch_id === null ? '' : String(props.filters.branch_id),
    service_id: props.filters.service_id === null ? '' : String(props.filters.service_id),
    status: props.filters.status ?? '',
}));
const exportUrl = computed(() =>
    dashboardRoutes.export.url({ query: buildReportQuery(appliedFilters.value) }),
);

const formatNumber = (value: number): string => new Intl.NumberFormat('id-ID').format(value);
const formatPercentage = (value: number): string =>
    `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(value)}%`;

const applyFilters = (): void => {
    router.get(dashboard.url(), buildReportQuery(form), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = (): void => {
    Object.assign(form, {
        date_from: props.defaultFilters.date_from,
        date_to: props.defaultFilters.date_to,
        organization_id: '',
        city_id: '',
        branch_id: '',
        service_id: '',
        status: '',
    } satisfies ReportFilterForm);

    applyFilters();
};

const handleOrganizationChange = (): void => {
    form.branch_id = '';
    form.service_id = '';
};

const handleCityChange = (): void => {
    form.branch_id = '';
};
</script>

<template>
    <Head title="Performa laundry" />

    <main class="bg-canvas flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-[1440px] space-y-6">
            <CmsPageHeader
                eyebrow="Laporan operasional"
                title="Performa laundry"
                description="Bandingkan pendapatan lunas, volume pesanan, waktu penyelesaian, dan pembatalan setiap cabang."
            >
                <template #actions>
                    <Button as="a" :href="exportUrl" variant="outline">
                        <Download aria-hidden="true" />
                        Unduh CSV
                    </Button>
                </template>
            </CmsPageHeader>

            <section class="border-line bg-surface rounded-3xl border p-5 shadow-low">
                <div>
                    <h2 class="font-display text-ink-950 font-semibold">Filter laporan</h2>
                    <p class="text-ink-500 mt-1 text-sm">
                        Periode menggunakan tanggal pembuatan pesanan. Kota tetap berasal dari katalog global.
                    </p>
                </div>

                <form class="mt-5 space-y-5" @submit.prevent="applyFilters">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="grid gap-2">
                            <Label for="report-date-from">Dari tanggal</Label>
                            <Input
                                id="report-date-from"
                                v-model="form.date_from"
                                type="date"
                                :aria-invalid="Boolean(page.props.errors.date_from)"
                                aria-describedby="report-date-from-error"
                            />
                            <InputError id="report-date-from-error" :message="page.props.errors.date_from" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="report-date-to">Sampai tanggal</Label>
                            <Input
                                id="report-date-to"
                                v-model="form.date_to"
                                type="date"
                                :aria-invalid="Boolean(page.props.errors.date_to)"
                                aria-describedby="report-date-to-error"
                            />
                            <InputError id="report-date-to-error" :message="page.props.errors.date_to" />
                        </div>

                        <div v-if="isSuperadmin" class="grid gap-2">
                            <Label for="report-organization">Organisasi</Label>
                            <select
                                id="report-organization"
                                v-model="form.organization_id"
                                :class="selectClass"
                                @change="handleOrganizationChange"
                            >
                                <option value="">Semua organisasi</option>
                                <option
                                    v-for="organization in options.organizations"
                                    :key="organization.id"
                                    :value="String(organization.id)"
                                >
                                    {{ organization.name }}
                                </option>
                            </select>
                            <InputError :message="page.props.errors.organization_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="report-city">Kota</Label>
                            <select
                                id="report-city"
                                v-model="form.city_id"
                                :class="selectClass"
                                @change="handleCityChange"
                            >
                                <option value="">Semua kota</option>
                                <option v-for="city in options.cities" :key="city.id" :value="String(city.id)">
                                    {{ city.name }}, {{ city.province }}
                                </option>
                            </select>
                            <InputError :message="page.props.errors.city_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="report-branch">Cabang</Label>
                            <select id="report-branch" v-model="form.branch_id" :class="selectClass">
                                <option value="">Semua cabang</option>
                                <option
                                    v-for="branch in availableBranches"
                                    :key="branch.id"
                                    :value="String(branch.id)"
                                >
                                    {{ branch.name }} ({{ branch.code }})
                                </option>
                            </select>
                            <InputError :message="page.props.errors.branch_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="report-service">Layanan</Label>
                            <select id="report-service" v-model="form.service_id" :class="selectClass">
                                <option value="">Semua layanan</option>
                                <option
                                    v-for="service in availableServices"
                                    :key="service.id"
                                    :value="String(service.id)"
                                >
                                    {{ service.name }} ({{ service.code }})
                                </option>
                            </select>
                            <InputError :message="page.props.errors.service_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="report-status">Status pesanan</Label>
                            <select id="report-status" v-model="form.status" :class="selectClass">
                                <option value="">Semua status</option>
                                <option v-for="status in options.statuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                            <InputError :message="page.props.errors.status" />
                        </div>
                    </div>

                    <div class="border-line flex flex-col gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                        <Button type="button" variant="ghost" @click="resetFilters">Reset filter</Button>
                        <Button type="submit">Terapkan filter</Button>
                    </div>
                </form>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan performa">
                <CmsMetricCard
                    class="cms-metric-featured"
                    label="Pendapatan lunas"
                    :value="formatRupiah(summary.paid_revenue_rupiah)"
                    detail="Pembayaran berstatus lunas"
                    :icon="Banknote"
                />
                <CmsMetricCard
                    label="Jumlah pesanan"
                    :value="formatNumber(summary.order_count)"
                    detail="Pesanan pada periode terpilih"
                    :icon="ClipboardList"
                />
                <CmsMetricCard
                    label="Rata-rata turnaround"
                    :value="formatReportDuration(summary.average_turnaround_minutes)"
                    detail="Dari dibuat hingga pertama selesai"
                    :icon="Timer"
                />
                <CmsMetricCard
                    label="Pembatalan"
                    :value="formatNumber(summary.cancelled_order_count)"
                    :detail="`${formatPercentage(summary.cancellation_rate)} dari seluruh pesanan`"
                    :icon="Ban"
                />
            </section>

            <section class="border-line bg-surface overflow-hidden rounded-3xl border shadow-low">
                <div class="border-line border-b px-5 py-4">
                    <h2 class="font-display text-ink-950 font-semibold">Perbandingan cabang</h2>
                    <p class="text-ink-500 mt-1 text-sm">
                        Cabang tanpa pesanan tetap ditampilkan agar cakupan laporan mudah diperiksa.
                    </p>
                </div>

                <CmsEmptyState
                    v-if="branches.length === 0"
                    class="m-5"
                    title="Tidak ada cabang pada filter ini"
                    description="Ubah organisasi, kota, atau cabang untuk melihat perbandingan operasional."
                    :icon="Store"
                />

                <div v-else class="hidden overflow-x-auto lg:block">
                    <table class="w-full min-w-[1080px] text-left text-sm">
                        <caption class="sr-only">
                            Perbandingan performa cabang berdasarkan filter laporan yang aktif
                        </caption>
                        <thead class="bg-canvas text-ink-500">
                            <tr>
                                <th class="px-5 py-3 font-medium">Cabang</th>
                                <th class="px-5 py-3 font-medium">Area</th>
                                <th class="px-5 py-3 text-right font-medium">Pesanan</th>
                                <th class="px-5 py-3 text-right font-medium">Pendapatan lunas</th>
                                <th class="px-5 py-3 text-right font-medium">Selesai</th>
                                <th class="px-5 py-3 text-right font-medium">Dibatalkan</th>
                                <th class="px-5 py-3 text-right font-medium">Turnaround</th>
                            </tr>
                        </thead>
                        <tbody class="divide-line divide-y">
                            <tr v-for="branch in branches" :key="branch.branch_id">
                                <td class="px-5 py-4">
                                    <p class="text-ink-950 font-medium">{{ branch.branch_name }}</p>
                                    <p class="text-ink-500 mt-0.5 text-xs">
                                        {{ branch.branch_code }} · {{ branch.organization_name }}
                                    </p>
                                </td>
                                <td class="text-ink-700 px-5 py-4">{{ branch.city_name }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ formatNumber(branch.order_count) }}</td>
                                <td class="text-ink-950 px-5 py-4 text-right font-medium tabular-nums">
                                    {{ formatRupiah(branch.paid_revenue_rupiah) }}
                                </td>
                                <td class="px-5 py-4 text-right tabular-nums">
                                    {{ formatNumber(branch.completed_order_count) }}
                                </td>
                                <td class="px-5 py-4 text-right tabular-nums">
                                    <span>{{ formatNumber(branch.cancelled_order_count) }}</span>
                                    <span class="text-ink-500 ml-1 text-xs">
                                        ({{ formatPercentage(branch.cancellation_rate) }})
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    {{ formatReportDuration(branch.average_turnaround_minutes) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="branches.length > 0" class="divide-line divide-y lg:hidden">
                    <article v-for="branch in branches" :key="branch.branch_id" class="space-y-4 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-ink-950 truncate font-semibold">{{ branch.branch_name }}</h3>
                                <p class="text-ink-500 mt-0.5 truncate text-xs">
                                    {{ branch.branch_code }} · {{ branch.organization_name }}
                                </p>
                            </div>
                            <span class="bg-surface-blue text-brand-700 shrink-0 rounded-full px-3 py-1 text-xs font-medium">
                                {{ branch.city_name }}
                            </span>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div class="border-line rounded-lg border p-3">
                                <dt class="text-ink-500 text-xs">Pesanan</dt>
                                <dd class="text-ink-950 mt-1 font-medium tabular-nums">{{ formatNumber(branch.order_count) }}</dd>
                            </div>
                            <div class="border-line rounded-lg border p-3">
                                <dt class="text-ink-500 text-xs">Selesai</dt>
                                <dd class="text-ink-950 mt-1 font-medium tabular-nums">
                                    {{ formatNumber(branch.completed_order_count) }}
                                </dd>
                            </div>
                            <div class="border-line rounded-lg border p-3">
                                <dt class="text-ink-500 text-xs">Dibatalkan</dt>
                                <dd class="text-ink-950 mt-1 font-medium tabular-nums">
                                    {{ formatNumber(branch.cancelled_order_count) }}
                                    <span class="text-ink-500 text-xs">({{ formatPercentage(branch.cancellation_rate) }})</span>
                                </dd>
                            </div>
                            <div class="border-line rounded-lg border p-3">
                                <dt class="text-ink-500 text-xs">Turnaround</dt>
                                <dd class="text-ink-950 mt-1 font-medium">
                                    {{ formatReportDuration(branch.average_turnaround_minutes) }}
                                </dd>
                            </div>
                        </dl>

                        <div class="bg-surface-blue rounded-lg p-3">
                            <p class="text-ink-500 text-xs">Pendapatan lunas</p>
                            <p class="font-display text-ink-950 mt-1 text-lg font-semibold tabular-nums">
                                {{ formatRupiah(branch.paid_revenue_rupiah) }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </main>
</template>
