<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { BarChart3, Download, Table2, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import CashflowCategoryDialog from '@/components/cms/CashflowCategoryDialog.vue';
import CashflowEntryFormDialog from '@/components/cms/CashflowEntryFormDialog.vue';
import CashflowImportDialog from '@/components/cms/CashflowImportDialog.vue';
import CmsDeleteDialog from '@/components/cms/CmsDeleteDialog.vue';
import CmsEmptyState from '@/components/cms/CmsEmptyState.vue';
import CmsPageHeader from '@/components/cms/CmsPageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/cms';
import { destroy, exportMethod as cashflowExport, index as cashflowIndex } from '@/routes/cms/cashflow';
import type {
    CashflowBranchOption,
    CashflowCategoryOption,
    CashflowEntrySummary,
    CashflowStatement,
    CashflowStatementCategory,
    OrganizationOption,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Cashflow', href: cashflowIndex() },
        ],
    },
});

const props = defineProps<{
    statement: CashflowStatement;
    entries: CashflowEntrySummary[];
    branches: CashflowBranchOption[];
    organizations: OrganizationOption[];
    categories: CashflowCategoryOption[];
    filters: {
        year: number;
        branch_id: string | null;
        organization_id: string | null;
    };
}>();

const page = usePage();
const isSuperadmin = computed(() => page.props.auth.user.roles.includes('superadmin'));

const viewMode = ref<'laporan' | 'tabel'>('laporan');

const yearFilter = ref(props.filters.year);
const branchFilter = ref(props.filters.branch_id ?? 'all');
const organizationFilter = ref(props.filters.organization_id ?? 'all');

const applyFilters = (): void => {
    const query: Record<string, string | number> = { year: yearFilter.value };

    if (branchFilter.value !== 'all') {
        query.branch_id = branchFilter.value;
    }
    if (organizationFilter.value !== 'all' && isSuperadmin.value) {
        query.organization_id = organizationFilter.value;
    }

    router.get(cashflowIndex.url(), query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const handleExport = (): void => {
    const params = new URLSearchParams();
    params.append('year', yearFilter.value.toString());
    if (branchFilter.value !== 'all') {
        params.append('branch_id', branchFilter.value);
    }
    if (organizationFilter.value !== 'all' && isSuperadmin.value) {
        params.append('organization_id', organizationFilter.value);
    }

    const queryString = params.toString();
    window.location.href = `${cashflowExport().url}?${queryString}`;
};


const formatRupiah = (amount: number): string =>
    new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);

const formatDate = (dateStr: string): string =>
    new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(dateStr));

const monthNames = [
    '',
    'JAN',
    'FEB',
    'MAR',
    'APR',
    'MEI',
    'JUN',
    'JUL',
    'AGT',
    'SEP',
    'OKT',
    'NOV',
    'DES',
];

const monthRange = Array.from({ length: 12 }, (_, i) => i + 1);

// Statement helpers
const debitCategories = computed(() =>
    props.statement.categories.filter((c) => c.type === 'debit'),
);

const creditCategories = computed(() =>
    props.statement.categories.filter((c) => c.type === 'credit'),
);

const groupedDebit = computed(() => groupByGroup(debitCategories.value));
const groupedCredit = computed(() => groupByGroup(creditCategories.value));

type GroupedCategories = {
    rutin: CashflowStatementCategory[];
    non_rutin: CashflowStatementCategory[];
};

function groupByGroup(categories: CashflowStatementCategory[]): GroupedCategories {
    return {
        rutin: categories.filter((c) => c.group === 'rutin'),
        non_rutin: categories.filter((c) => c.group === 'non_rutin'),
    };
}

function groupTotal(categories: CashflowStatementCategory[], month: number): number {
    return categories.reduce((sum, cat) => sum + (cat.monthly[month] ?? 0), 0);
}

function typeMonthTotal(type: 'debit' | 'credit', month: number): number {
    return props.statement.months[month]?.[type === 'debit' ? 'debit' : 'credit'] ?? 0;
}

const availableBranches = computed(() => {
    if (!isSuperadmin.value || organizationFilter.value === 'all') {
        return props.branches;
    }
    return props.branches.filter((b) => b.organization_id.toString() === organizationFilter.value);
});
</script>

<template>
    <Head title="Cashflow" />

    <main class="bg-canvas flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-[1600px] space-y-6">
            <CmsPageHeader
                eyebrow="Keuangan"
                title="Cashflow"
                description="Manage data cashflow untuk keperluan pencatatan arus kas."
            >
                <template #actions>
                    <Button variant="outline" class="gap-2" @click="handleExport">
                        <Download class="size-4" />
                        Export CSV
                    </Button>

                    <CashflowImportDialog />

                    <CashflowCategoryDialog
                        :categories="categories"
                        :organizations="organizations"
                    />

                    <CashflowEntryFormDialog
                        :branches="branches"
                        :organizations="organizations"
                        :categories="categories"
                    />

                    <!-- View Toggle -->
                    <div class="flex rounded-lg border border-neutral-200 shadow-sm dark:border-neutral-700">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-l-lg px-3.5 py-2.5 text-sm font-medium transition"
                            :class="viewMode === 'laporan' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-ink-600 hover:bg-neutral-50 dark:hover:bg-neutral-800'"
                            @click="viewMode = 'laporan'"
                        >
                            <BarChart3 class="size-4" />
                            Laporan
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-r-lg px-3.5 py-2.5 text-sm font-medium transition"
                            :class="viewMode === 'tabel' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-ink-600 hover:bg-neutral-50 dark:hover:bg-neutral-800'"
                            @click="viewMode = 'tabel'"
                        >
                            <Table2 class="size-4" />
                            Tabel
                        </button>
                    </div>
                </template>
            </CmsPageHeader>

            <!-- Filters -->
            <section
                class="border-line bg-surface grid gap-4 rounded-3xl border p-5 shadow-low"
                :class="isSuperadmin ? 'sm:grid-cols-3' : 'sm:grid-cols-2'"
                aria-label="Filter cashflow"
            >
                <!-- Filter Organisasi (Superadmin only) -->
                <div v-if="isSuperadmin" class="grid gap-2">
                    <Label for="cf-org-filter">Filter organisasi</Label>
                    <select
                        id="cf-org-filter"
                        v-model="organizationFilter"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                        @change="branchFilter = 'all'; applyFilters()"
                    >
                        <option value="all">Semua organisasi</option>
                        <option
                            v-for="org in organizations"
                            :key="org.id"
                            :value="org.id.toString()"
                        >
                            {{ org.name }}
                        </option>
                    </select>
                </div>

                <!-- Filter Cabang -->
                <div class="grid gap-2">
                    <Label for="cf-branch-filter">Filter cabang</Label>
                    <select
                        id="cf-branch-filter"
                        v-model="branchFilter"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                        @change="applyFilters()"
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

                <!-- Filter Tahun -->
                <div class="grid gap-2">
                    <Label for="cf-year-filter">Tahun</Label>
                    <input
                        id="cf-year-filter"
                        v-model.number="yearFilter"
                        type="number"
                        min="2020"
                        :max="new Date().getFullYear() + 1"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                        @change="applyFilters()"
                    />
                </div>
            </section>

            <!-- Summary Cards -->
            <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan cashflow">
                <!-- Total Pemasukan -->
                <div class="rounded-2xl border-2 border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-800 dark:bg-emerald-950/30">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Total Pemasukan</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-emerald-900 dark:text-emerald-200">
                        {{ formatRupiah(statement.total_debit) }}
                    </p>
                </div>

                <!-- Total Pengeluaran -->
                <div class="rounded-2xl border-2 border-red-200 bg-red-50 p-5 dark:border-red-800 dark:bg-red-950/30">
                    <p class="text-xs font-semibold uppercase tracking-wider text-red-700 dark:text-red-400">Total Pengeluaran</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-red-900 dark:text-red-200">
                        {{ formatRupiah(statement.total_credit) }}
                    </p>
                </div>

                <!-- Laba/Rugi Bersih -->
                <div
                    class="rounded-2xl border-2 p-5"
                    :class="statement.net_profit >= 0 ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30' : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/30'"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-wider"
                        :class="statement.net_profit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400'"
                    >
                        Laba/Rugi Bersih
                    </p>
                    <p
                        class="mt-2 text-2xl font-bold tabular-nums"
                        :class="statement.net_profit >= 0 ? 'text-emerald-900 dark:text-emerald-200' : 'text-red-900 dark:text-red-200'"
                    >
                        {{ formatRupiah(statement.net_profit) }}
                    </p>
                </div>
            </section>

            <!-- LAPORAN VIEW (Cashflow Statement) -->
            <section
                v-if="viewMode === 'laporan'"
                class="border-line bg-surface overflow-hidden rounded-3xl border shadow-low"
            >
                <div class="border-line border-b px-5 py-4">
                    <h2 class="font-display text-ink-950 font-semibold">
                        Cashflow Statement — {{ statement.year }}
                    </h2>
                </div>

                <CmsEmptyState
                    v-if="statement.categories.length === 0"
                    class="m-5"
                    title="Belum ada data cashflow"
                    description="Belum ada transaksi yang tercatat pada periode ini."
                    :icon="Wallet"
                />

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <!-- Header -->
                        <thead class="bg-neutral-900 text-white dark:bg-neutral-800">
                            <tr>
                                <th class="sticky left-0 z-10 bg-neutral-900 px-4 py-3 font-semibold dark:bg-neutral-800" style="min-width: 200px">
                                    KETERANGAN
                                </th>
                                <th
                                    v-for="m in monthRange"
                                    :key="m"
                                    class="whitespace-nowrap px-3 py-3 text-right font-semibold"
                                    style="min-width: 110px"
                                >
                                    {{ monthNames[m] }} {{ statement.year }}
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <!-- ========== PEMASUKAN ========== -->
                            <tr class="bg-emerald-50 dark:bg-emerald-950/20">
                                <td colspan="13" class="sticky left-0 z-10 bg-emerald-50 px-4 py-2.5 font-bold text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400">
                                    PEMASUKAN
                                </td>
                            </tr>

                            <!-- Debit Rutin -->
                            <template v-if="groupedDebit.rutin.length > 0">
                                <tr>
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-2 pl-6 font-semibold text-emerald-600 dark:text-emerald-400">
                                        Rutin
                                    </td>
                                    <td v-for="m in monthRange" :key="m" class="px-3 py-2"></td>
                                </tr>
                                <tr v-for="cat in groupedDebit.rutin" :key="cat.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-1.5 pl-10 text-ink-700 dark:text-ink-300 hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                        {{ cat.name }}
                                    </td>
                                    <td
                                        v-for="m in monthRange"
                                        :key="m"
                                        class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-ink-700"
                                    >
                                        {{ cat.monthly[m] ? formatRupiah(cat.monthly[m]) : '-' }}
                                    </td>
                                </tr>
                                <tr class="border-t border-neutral-200 dark:border-neutral-700">
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-2 pl-6 font-semibold text-emerald-700 dark:text-emerald-400">
                                        Total Rutin
                                    </td>
                                    <td
                                        v-for="m in monthRange"
                                        :key="m"
                                        class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums text-emerald-700 dark:text-emerald-400"
                                    >
                                        {{ groupTotal(groupedDebit.rutin, m) ? formatRupiah(groupTotal(groupedDebit.rutin, m)) : '-' }}
                                    </td>
                                </tr>
                            </template>

                            <!-- Debit Non-Rutin -->
                            <template v-if="groupedDebit.non_rutin.length > 0">
                                <tr>
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-2 pl-6 font-semibold text-emerald-600 dark:text-emerald-400">
                                        Non-Rutin
                                    </td>
                                    <td v-for="m in monthRange" :key="m" class="px-3 py-2"></td>
                                </tr>
                                <tr v-for="cat in groupedDebit.non_rutin" :key="cat.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-1.5 pl-10 text-ink-700 dark:text-ink-300 hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                        {{ cat.name }}
                                    </td>
                                    <td
                                        v-for="m in monthRange"
                                        :key="m"
                                        class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-ink-700"
                                    >
                                        {{ cat.monthly[m] ? formatRupiah(cat.monthly[m]) : '-' }}
                                    </td>
                                </tr>
                                <tr class="border-t border-neutral-200 dark:border-neutral-700">
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-2 pl-6 font-semibold text-emerald-700 dark:text-emerald-400">
                                        Total Non-Rutin
                                    </td>
                                    <td
                                        v-for="m in monthRange"
                                        :key="m"
                                        class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums text-emerald-700 dark:text-emerald-400"
                                    >
                                        {{ groupTotal(groupedDebit.non_rutin, m) ? formatRupiah(groupTotal(groupedDebit.non_rutin, m)) : '-' }}
                                    </td>
                                </tr>
                            </template>

                            <!-- Total Pemasukan Row -->
                            <tr class="border-t-2 border-emerald-300 bg-emerald-100/50 font-bold dark:border-emerald-700 dark:bg-emerald-950/30">
                                <td class="sticky left-0 z-10 bg-emerald-100/50 px-4 py-2.5 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300">
                                    Total Pemasukan
                                </td>
                                <td
                                    v-for="m in monthRange"
                                    :key="m"
                                    class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-emerald-800 dark:text-emerald-300"
                                >
                                    {{ typeMonthTotal('debit', m) ? formatRupiah(typeMonthTotal('debit', m)) : '-' }}
                                </td>
                            </tr>

                            <!-- Spacer -->
                            <tr><td colspan="13" class="h-3"></td></tr>

                            <!-- ========== PENGELUARAN ========== -->
                            <tr class="bg-red-50 dark:bg-red-950/20">
                                <td colspan="13" class="sticky left-0 z-10 bg-red-50 px-4 py-2.5 font-bold text-red-700 dark:bg-red-950/20 dark:text-red-400">
                                    PENGELUARAN
                                </td>
                            </tr>

                            <!-- Credit Rutin -->
                            <template v-if="groupedCredit.rutin.length > 0">
                                <tr>
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-2 pl-6 font-semibold text-red-600 dark:text-red-400">
                                        Rutin
                                    </td>
                                    <td v-for="m in monthRange" :key="m" class="px-3 py-2"></td>
                                </tr>
                                <tr v-for="cat in groupedCredit.rutin" :key="cat.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-1.5 pl-10 text-ink-700 dark:text-ink-300 hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                        {{ cat.name }}
                                    </td>
                                    <td
                                        v-for="m in monthRange"
                                        :key="m"
                                        class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-ink-700"
                                    >
                                        {{ cat.monthly[m] ? formatRupiah(cat.monthly[m]) : '-' }}
                                    </td>
                                </tr>
                                <tr class="border-t border-neutral-200 dark:border-neutral-700">
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-2 pl-6 font-semibold text-red-700 dark:text-red-400">
                                        Total Rutin
                                    </td>
                                    <td
                                        v-for="m in monthRange"
                                        :key="m"
                                        class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums text-red-700 dark:text-red-400"
                                    >
                                        {{ groupTotal(groupedCredit.rutin, m) ? formatRupiah(groupTotal(groupedCredit.rutin, m)) : '-' }}
                                    </td>
                                </tr>
                            </template>

                            <!-- Credit Non-Rutin -->
                            <template v-if="groupedCredit.non_rutin.length > 0">
                                <tr>
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-2 pl-6 font-semibold text-red-600 dark:text-red-400">
                                        Non-Rutin
                                    </td>
                                    <td v-for="m in monthRange" :key="m" class="px-3 py-2"></td>
                                </tr>
                                <tr v-for="cat in groupedCredit.non_rutin" :key="cat.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-1.5 pl-10 text-ink-700 dark:text-ink-300 hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                        {{ cat.name }}
                                    </td>
                                    <td
                                        v-for="m in monthRange"
                                        :key="m"
                                        class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-ink-700"
                                    >
                                        {{ cat.monthly[m] ? formatRupiah(cat.monthly[m]) : '-' }}
                                    </td>
                                </tr>
                                <tr class="border-t border-neutral-200 dark:border-neutral-700">
                                    <td class="sticky left-0 z-10 bg-surface px-4 py-2 pl-6 font-semibold text-red-700 dark:text-red-400">
                                        Total Non-Rutin
                                    </td>
                                    <td
                                        v-for="m in monthRange"
                                        :key="m"
                                        class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums text-red-700 dark:text-red-400"
                                    >
                                        {{ groupTotal(groupedCredit.non_rutin, m) ? formatRupiah(groupTotal(groupedCredit.non_rutin, m)) : '-' }}
                                    </td>
                                </tr>
                            </template>

                            <!-- Total Pengeluaran Row -->
                            <tr class="border-t-2 border-red-300 bg-red-100/50 font-bold dark:border-red-700 dark:bg-red-950/30">
                                <td class="sticky left-0 z-10 bg-red-100/50 px-4 py-2.5 text-red-800 dark:bg-red-950/30 dark:text-red-300">
                                    Total Pengeluaran
                                </td>
                                <td
                                    v-for="m in monthRange"
                                    :key="m"
                                    class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-red-800 dark:text-red-300"
                                >
                                    {{ typeMonthTotal('credit', m) ? formatRupiah(typeMonthTotal('credit', m)) : '-' }}
                                </td>
                            </tr>

                            <!-- Spacer -->
                            <tr><td colspan="13" class="h-3"></td></tr>

                            <!-- ========== LABA / RUGI BERSIH ========== -->
                            <tr class="border-t-2 border-neutral-400 bg-neutral-100 font-bold dark:border-neutral-600 dark:bg-neutral-800/50">
                                <td class="sticky left-0 z-10 bg-neutral-100 px-4 py-3 text-sm text-ink-950 dark:bg-neutral-800/50 dark:text-white">
                                    LABA / RUGI BERSIH
                                </td>
                                <td
                                    v-for="m in monthRange"
                                    :key="m"
                                    class="whitespace-nowrap px-3 py-3 text-right text-sm tabular-nums"
                                    :class="(statement.months[m]?.net ?? 0) >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400'"
                                >
                                    {{ (statement.months[m]?.net ?? 0) !== 0 ? formatRupiah(statement.months[m]?.net ?? 0) : '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- TABEL VIEW (Entries Table) -->
            <section
                v-if="viewMode === 'tabel'"
                class="border-line bg-surface overflow-hidden rounded-3xl border shadow-low"
            >
                <div class="border-line border-b px-5 py-4">
                    <h2 class="font-display text-ink-950 font-semibold">Daftar entri cashflow</h2>
                    <p class="text-ink-500 mt-1 text-sm">
                        Semua transaksi pemasukan dan pengeluaran pada tahun {{ statement.year }}.
                    </p>
                </div>

                <CmsEmptyState
                    v-if="entries.length === 0"
                    class="m-5"
                    title="Belum ada entri cashflow"
                    description="Belum ada transaksi yang tercatat. Klik 'Buat Baru' untuk menambahkan."
                    :icon="Wallet"
                />

                <!-- Desktop Table -->
                <div v-else class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-canvas text-ink-500">
                            <tr>
                                <th class="px-5 py-3 font-medium">Tanggal</th>
                                <th class="px-5 py-3 font-medium">Kategori</th>
                                <th class="px-5 py-3 font-medium">Deskripsi</th>
                                <th class="px-5 py-3 font-medium">Cabang</th>
                                <th class="px-5 py-3 font-medium">Tipe</th>
                                <th class="px-5 py-3 text-right font-medium">Jumlah</th>
                                <th class="px-5 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-line divide-y">
                            <tr v-for="entry in entries" :key="entry.id">
                                <td class="px-5 py-4 text-ink-950 whitespace-nowrap">
                                    {{ formatDate(entry.entry_date) }}
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-ink-950 font-medium">{{ entry.category.name }}</p>
                                    <p class="text-ink-500 mt-0.5 text-xs capitalize">{{ entry.category.group === 'non_rutin' ? 'Non-Rutin' : 'Rutin' }}</p>
                                </td>
                                <td class="px-5 py-4 text-ink-700 max-w-xs truncate">
                                    {{ entry.description }}
                                </td>
                                <td class="px-5 py-4 text-ink-700">
                                    {{ entry.branch.name }}
                                </td>
                                <td class="px-5 py-4">
                                    <Badge
                                        :variant="entry.type === 'debit' ? 'default' : 'destructive'"
                                        :class="entry.type === 'debit' ? 'bg-emerald-600 hover:bg-emerald-600 text-white' : ''"
                                    >
                                        {{ entry.type === 'debit' ? 'Pemasukan' : 'Pengeluaran' }}
                                    </Badge>
                                </td>
                                <td class="px-5 py-4 text-right font-semibold tabular-nums" :class="entry.type === 'debit' ? 'text-emerald-700' : 'text-red-700'">
                                    {{ formatRupiah(entry.amount_rupiah) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <CmsDeleteDialog
                                        v-if="entry.payment_id === null"
                                        title="Hapus entri cashflow"
                                        description="Hapus entri cashflow ini secara permanen."
                                        :confirmation="entry.description"
                                        confirmation-label="Ketik deskripsi untuk konfirmasi"
                                        :route="destroy(entry.id)"
                                    />
                                    <span v-else class="text-ink-400 text-xs italic">Otomatis</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div v-if="entries.length > 0" class="divide-line divide-y lg:hidden">
                    <article v-for="entry in entries" :key="entry.id" class="space-y-3 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-ink-950 text-sm font-semibold">{{ entry.category.name }}</h3>
                                <p class="text-ink-500 mt-0.5 text-xs">
                                    {{ entry.branch.name }} · {{ formatDate(entry.entry_date) }}
                                </p>
                            </div>
                            <Badge
                                :variant="entry.type === 'debit' ? 'default' : 'destructive'"
                                :class="entry.type === 'debit' ? 'bg-emerald-600 hover:bg-emerald-600 text-white' : ''"
                            >
                                {{ entry.type === 'debit' ? 'Pemasukan' : 'Pengeluaran' }}
                            </Badge>
                        </div>

                        <div class="flex items-center justify-between rounded-xl bg-canvas/40 p-2.5 text-xs text-ink-600">
                            <span class="max-w-[60%] truncate">{{ entry.description }}</span>
                            <span class="font-semibold tabular-nums" :class="entry.type === 'debit' ? 'text-emerald-700' : 'text-red-700'">
                                {{ formatRupiah(entry.amount_rupiah) }}
                            </span>
                        </div>

                        <div v-if="entry.payment_id === null" class="border-line flex items-center justify-end border-t pt-3">
                            <CmsDeleteDialog
                                title="Hapus entri cashflow"
                                description="Hapus entri cashflow ini secara permanen."
                                :confirmation="entry.description"
                                confirmation-label="Ketik deskripsi untuk konfirmasi"
                                :route="destroy(entry.id)"
                            />
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </main>
</template>
