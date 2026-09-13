<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Building2, CircleCheckBig, MapPinned } from '@lucide/vue';
import { computed } from 'vue';
import BranchCreateForm from '@/components/cms/BranchCreateForm.vue';
import CityCreateForm from '@/components/cms/CityCreateForm.vue';
import CmsEmptyState from '@/components/cms/CmsEmptyState.vue';
import CmsMetricCard from '@/components/cms/CmsMetricCard.vue';
import CmsPageHeader from '@/components/cms/CmsPageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { formatKilometers, formatRupiah } from '@/lib/formatters';
import { dashboard } from '@/routes';
import { index } from '@/routes/cms/branches';
import type { BranchesPageProps } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Kota & cabang', href: index() },
        ],
    },
});

const props = defineProps<BranchesPageProps>();
const page = usePage();
const isSuperadmin = computed(() => page.props.auth.user.roles.includes('superadmin'));
const currentOrganizationId = computed(() => page.props.auth.user.organization_id);
const activeBranches = computed(() => props.branches.filter((branch) => branch.is_active).length);
</script>

<template>
    <Head title="Kota dan cabang" />

    <main class="bg-canvas flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-[1440px] space-y-6">
            <CmsPageHeader
                eyebrow="Jaringan operasional"
                title="Kota dan cabang"
                description="Atur lokasi outlet, radius gratis antar-jemput, dan tarif kelebihan jarak per kilometer."
            />

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan cabang">
                <CmsMetricCard label="Total cabang" :value="branches.length" detail="Outlet terdaftar" :icon="Building2" />
                <CmsMetricCard label="Cabang aktif" :value="activeBranches" detail="Menerima order" :icon="CircleCheckBig" />
                <CmsMetricCard label="Kota layanan" :value="cities.length" detail="Area operasional" :icon="MapPinned" />
            </section>

            <section class="border-line bg-surface rounded-xl border p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-ink-950 font-semibold">Area tersedia</h2>
                        <p class="text-ink-500 mt-1 text-sm">Kota yang dapat dipakai saat membuat cabang.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="city in cities" :key="city.id" variant="outline" :class="city.pending ? 'opacity-60' : ''">
                            {{ city.name }}, {{ city.province }}{{ city.pending ? ' · menyimpan' : '' }}
                        </Badge>
                        <span v-if="cities.length === 0" class="text-ink-500 text-sm">Belum ada kota.</span>
                    </div>
                </div>
            </section>

            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
                <section class="border-line bg-surface overflow-hidden rounded-xl border shadow-sm">
                    <div class="border-line border-b px-5 py-4">
                        <h2 class="font-display text-ink-950 font-semibold">Daftar cabang</h2>
                        <p class="text-ink-500 mt-1 text-sm">Konfigurasi wilayah dan biaya delivery tiap outlet.</p>
                    </div>

                    <CmsEmptyState
                        v-if="branches.length === 0"
                        class="m-5"
                        title="Belum ada cabang"
                        description="Tambahkan kota lalu buat outlet pertama untuk organisasi ini."
                        :icon="Building2"
                    />

                    <div v-else class="hidden overflow-x-auto lg:block">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-canvas text-ink-500"><tr><th class="px-5 py-3 font-medium">Cabang</th><th class="px-5 py-3 font-medium">Area</th><th class="px-5 py-3 font-medium">Delivery</th><th class="px-5 py-3 font-medium">Status</th></tr></thead>
                            <tbody class="divide-line divide-y">
                                <tr v-for="branch in branches" :key="branch.id" :class="branch.pending ? 'opacity-60' : ''">
                                    <td class="px-5 py-4"><p class="text-ink-950 font-medium">{{ branch.name }}</p><p class="text-ink-500 mt-0.5 text-xs">{{ branch.code }} · {{ branch.organization.name }}</p></td>
                                    <td class="px-5 py-4"><p>{{ branch.city.name }}</p><p class="text-ink-500 mt-0.5 max-w-56 truncate text-xs">{{ branch.address }}</p></td>
                                    <td class="px-5 py-4"><p>Gratis {{ formatKilometers(branch.free_delivery_radius_meters) }}</p><p class="text-ink-500 mt-0.5 text-xs">{{ formatRupiah(branch.delivery_fee_per_km_rupiah) }}/km berikutnya</p></td>
                                    <td class="px-5 py-4"><Badge :variant="branch.is_active ? 'default' : 'secondary'">{{ branch.pending ? 'Menyimpan' : branch.is_active ? 'Aktif' : 'Nonaktif' }}</Badge></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="branches.length > 0" class="divide-line divide-y lg:hidden">
                        <article v-for="branch in branches" :key="branch.id" class="space-y-3 p-5" :class="branch.pending ? 'opacity-60' : ''">
                            <div class="flex items-start justify-between gap-3"><div><h3 class="text-ink-950 font-semibold">{{ branch.name }}</h3><p class="text-ink-500 text-xs">{{ branch.code }} · {{ branch.city.name }}</p></div><Badge>{{ branch.pending ? 'Menyimpan' : branch.is_active ? 'Aktif' : 'Nonaktif' }}</Badge></div>
                            <p class="text-ink-700 text-sm leading-6">{{ branch.address }}</p>
                            <div class="bg-surface-blue grid grid-cols-2 gap-3 rounded-lg p-3 text-sm"><div><p class="text-ink-500 text-xs">Gratis sampai</p><p class="font-medium">{{ formatKilometers(branch.free_delivery_radius_meters) }}</p></div><div><p class="text-ink-500 text-xs">Tarif lanjutan</p><p class="font-medium">{{ formatRupiah(branch.delivery_fee_per_km_rupiah) }}/km</p></div></div>
                        </article>
                    </div>
                </section>

                <aside class="space-y-6">
                    <CityCreateForm :organizations="organizations" :current-organization-id="currentOrganizationId" :is-superadmin="isSuperadmin" />
                    <BranchCreateForm :cities="cities" :organizations="organizations" :current-organization-id="currentOrganizationId" :is-superadmin="isSuperadmin" />
                </aside>
            </div>
        </div>
    </main>
</template>
