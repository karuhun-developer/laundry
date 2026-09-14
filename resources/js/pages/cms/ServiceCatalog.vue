<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { BadgeDollarSign, CircleCheckBig, PackageOpen } from '@lucide/vue';
import { computed, ref } from 'vue';
import CmsDeleteDialog from '@/components/cms/CmsDeleteDialog.vue';
import CmsEmptyState from '@/components/cms/CmsEmptyState.vue';
import CmsMetricCard from '@/components/cms/CmsMetricCard.vue';
import CmsPageHeader from '@/components/cms/CmsPageHeader.vue';
import PriceOptionFormDialog from '@/components/cms/PriceOptionFormDialog.vue';
import ServiceFormDialog from '@/components/cms/ServiceFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { formatRupiah } from '@/lib/formatters';
import { dashboard } from '@/routes';
import { destroy as destroyPriceOption } from '@/routes/cms/price-options';
import { destroy as destroyService, index } from '@/routes/cms/services';
import type { PriceOptionSummary, ServicesPageProps } from '@/types';

defineOptions({
    name: 'CmsServiceCatalog',
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Layanan & paket', href: index() },
        ],
    },
});

const props = defineProps<ServicesPageProps>();
const page = usePage();
const isSuperadmin = computed(() => page.props.auth.user.roles.includes('superadmin'));
const currentOrganizationId = computed(() => page.props.auth.user.organization_id);
const organizationFilter = ref('all');
const visibleServices = computed(() =>
    organizationFilter.value === 'all'
        ? props.services
        : props.services.filter(
              (service) => service.organization_id.toString() === organizationFilter.value,
          ),
);
const activeServices = computed(
    () => visibleServices.value.filter((service) => service.is_active).length,
);
const packageCount = computed(() =>
    visibleServices.value.reduce((total, service) => total + service.price_options.length, 0),
);

const formatWeight = (grams: number): string =>
    `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(grams / 1000)} kg`;
const pricingSummary = (priceOption: PriceOptionSummary): string => {
    if (priceOption.type === 'fixed_package') {
        return `${formatWeight(priceOption.package_weight_grams ?? 0)} · ${formatRupiah(priceOption.base_price_rupiah ?? 0)}`;
    }

    return `${formatRupiah(priceOption.price_per_kilogram_rupiah ?? 0)}/kg`;
};
const pricingDetail = (priceOption: PriceOptionSummary): string => {
    if (priceOption.type === 'fixed_package') {
        return priceOption.price_per_kilogram_rupiah === null
            ? 'Tanpa tarif kelebihan berat'
            : `Kelebihan ${formatRupiah(priceOption.price_per_kilogram_rupiah)}/kg`;
    }

    return priceOption.minimum_chargeable_grams === null
        ? 'Tanpa minimum berat'
        : `Minimum ${formatWeight(priceOption.minimum_chargeable_grams)}`;
};
</script>

<template>
    <Head title="Layanan dan paket" />

    <main class="bg-canvas flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-[1440px] space-y-6">
            <CmsPageHeader
                eyebrow="Katalog harga"
                title="Layanan dan paket"
                description="Atur jenis layanan, paket berat tetap, dan tarif per kilogram untuk setiap cabang."
            >
                <template #actions>
                    <ServiceFormDialog
                        :organizations="organizations"
                        :current-organization-id="currentOrganizationId"
                        :is-superadmin="isSuperadmin"
                    />
                </template>
            </CmsPageHeader>

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan katalog">
                <CmsMetricCard
                    label="Total layanan"
                    :value="visibleServices.length"
                    detail="Jenis layanan terdaftar"
                    :icon="PackageOpen"
                />
                <CmsMetricCard
                    label="Layanan aktif"
                    :value="activeServices"
                    detail="Dapat dipilih pelanggan"
                    :icon="CircleCheckBig"
                />
                <CmsMetricCard
                    label="Total paket"
                    :value="packageCount"
                    detail="Tarif per cabang"
                    :icon="BadgeDollarSign"
                />
            </section>

            <section
                v-if="isSuperadmin"
                class="border-line bg-surface grid gap-2 rounded-xl border p-5 shadow-sm sm:max-w-md"
            >
                <Label for="catalog-organization-filter">Filter organisasi</Label>
                <select
                    id="catalog-organization-filter"
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
            </section>

            <CmsEmptyState
                v-if="visibleServices.length === 0"
                title="Belum ada layanan"
                description="Tambahkan layanan pertama, lalu tentukan paket atau tarif kiloan per cabang."
                :icon="PackageOpen"
            />

            <section v-else class="grid gap-5 xl:grid-cols-2" aria-label="Daftar layanan">
                <article
                    v-for="service in visibleServices"
                    :key="service.id"
                    class="border-line bg-surface overflow-hidden rounded-xl border shadow-sm"
                >
                    <header class="border-line flex flex-col gap-4 border-b p-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-display text-ink-950 text-lg font-semibold">
                                    {{ service.name }}
                                </h2>
                                <Badge :variant="service.is_active ? 'default' : 'secondary'">
                                    {{ service.pending ? 'Menyimpan' : service.is_active ? 'Aktif' : 'Nonaktif' }}
                                </Badge>
                            </div>
                            <p class="text-ink-500 mt-1 text-xs">
                                {{ service.code }} · {{ service.organization.name }}
                            </p>
                            <p v-if="service.description" class="text-ink-700 mt-3 text-sm leading-6">
                                {{ service.description }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-1">
                            <ServiceFormDialog
                                :service="service"
                                :organizations="organizations"
                                :current-organization-id="currentOrganizationId"
                                :is-superadmin="isSuperadmin"
                            />
                            <CmsDeleteDialog
                                title="Hapus layanan?"
                                :description="`Layanan ${service.name} hanya dapat dihapus jika belum memiliki paket atau riwayat order.`"
                                :confirmation="service.name"
                                confirmation-label="Nama layanan"
                                :route="destroyService(service.id)"
                                :disabled="service.pending"
                            />
                        </div>
                    </header>

                    <div class="space-y-4 p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-ink-950 text-sm font-semibold">Paket cabang</h3>
                                <p class="text-ink-500 mt-1 text-xs">
                                    Setiap cabang dapat memiliki nama dan model harga berbeda.
                                </p>
                            </div>
                            <PriceOptionFormDialog
                                :service="service"
                                :branches="branches"
                            />
                        </div>

                        <div
                            v-if="service.price_options.length === 0"
                            class="border-line bg-canvas rounded-lg border border-dashed p-4 text-center"
                        >
                            <p class="text-ink-500 text-sm">Belum ada paket untuk layanan ini.</p>
                        </div>

                        <div v-else class="divide-line overflow-hidden rounded-lg border">
                            <article
                                v-for="priceOption in service.price_options"
                                :key="priceOption.id"
                                class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-ink-950 font-medium">{{ priceOption.name }}</p>
                                        <Badge :variant="priceOption.is_active ? 'default' : 'secondary'">
                                            {{
                                                priceOption.pending
                                                    ? 'Menyimpan'
                                                    : priceOption.is_active
                                                      ? 'Aktif'
                                                      : 'Nonaktif'
                                            }}
                                        </Badge>
                                    </div>
                                    <p class="text-ink-700 mt-1 text-sm">{{ pricingSummary(priceOption) }}</p>
                                    <p class="text-ink-500 mt-1 text-xs">
                                        {{ priceOption.branch.name }} · {{ pricingDetail(priceOption) }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-1">
                                    <PriceOptionFormDialog
                                        :service="service"
                                        :branches="branches"
                                        :price-option="priceOption"
                                    />
                                    <CmsDeleteDialog
                                        title="Hapus paket?"
                                        :description="`Paket ${priceOption.name} akan dihapus permanen jika belum digunakan pada order.`"
                                        :confirmation="priceOption.name"
                                        confirmation-label="Nama paket"
                                        :route="destroyPriceOption(priceOption.id)"
                                        :disabled="priceOption.pending"
                                    />
                                </div>
                            </article>
                        </div>
                    </div>
                </article>
            </section>
        </div>
    </main>
</template>
