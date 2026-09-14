<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Building2, CircleCheckBig, MapPinned } from '@lucide/vue';
import { computed } from 'vue';
import BranchFormDialog from '@/components/cms/BranchFormDialog.vue';
import CityFormDialog from '@/components/cms/CityFormDialog.vue';
import CmsDeleteDialog from '@/components/cms/CmsDeleteDialog.vue';
import CmsEmptyState from '@/components/cms/CmsEmptyState.vue';
import CmsMetricCard from '@/components/cms/CmsMetricCard.vue';
import CmsPageHeader from '@/components/cms/CmsPageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { formatKilometers, formatRupiah } from '@/lib/formatters';
import { dashboard } from '@/routes/cms';
import { destroy as destroyBranch, index } from '@/routes/cms/branches';
import { destroy as destroyCity } from '@/routes/cms/cities';
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
            >
                <template #actions>
                    <CityFormDialog />
                    <BranchFormDialog
                        :cities="cities"
                        :organizations="organizations"
                        :current-organization-id="currentOrganizationId"
                        :is-superadmin="isSuperadmin"
                    />
                </template>
            </CmsPageHeader>

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan cabang">
                <CmsMetricCard label="Total cabang" :value="branches.length" detail="Outlet terdaftar" :icon="Building2" />
                <CmsMetricCard label="Cabang aktif" :value="activeBranches" detail="Menerima order" :icon="CircleCheckBig" />
                <CmsMetricCard label="Kota layanan" :value="cities.length" detail="Area operasional" :icon="MapPinned" />
            </section>

            <section class="border-line bg-surface rounded-xl border p-5 shadow-sm">
                <div>
                    <h2 class="font-display text-ink-950 font-semibold">Area tersedia</h2>
                    <p class="text-ink-500 mt-1 text-sm">Kota yang dapat dipakai saat membuat cabang.</p>
                </div>

                <div v-if="cities.length > 0" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="city in cities"
                        :key="city.id"
                        class="border-line flex items-center justify-between gap-3 rounded-lg border p-3"
                    >
                        <div class="min-w-0">
                            <p class="text-ink-950 truncate text-sm font-medium">{{ city.name }}</p>
                            <p class="text-ink-500 truncate text-xs">{{ city.province }}</p>
                            <Badge v-if="city.pending" class="mt-2" variant="secondary">Menyimpan</Badge>
                        </div>
                        <div v-if="isSuperadmin" class="flex shrink-0 gap-1">
                            <CityFormDialog :city="city" />
                            <CmsDeleteDialog
                                title="Hapus kota?"
                                :description="`Kota ${city.name} akan dihapus permanen jika tidak lagi dipakai oleh cabang.`"
                                :confirmation="city.name"
                                confirmation-label="Nama kota"
                                :route="destroyCity(city.id)"
                                :disabled="city.pending"
                            />
                        </div>
                    </article>
                </div>
                <p v-else class="text-ink-500 mt-4 text-sm">Belum ada kota.</p>
            </section>

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
                        <thead class="bg-canvas text-ink-500">
                            <tr>
                                <th class="px-5 py-3 font-medium">Cabang</th>
                                <th class="px-5 py-3 font-medium">Area</th>
                                <th class="px-5 py-3 font-medium">Delivery</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-line divide-y">
                            <tr v-for="branch in branches" :key="branch.id">
                                <td class="px-5 py-4">
                                    <p class="text-ink-950 font-medium">{{ branch.name }}</p>
                                    <p class="text-ink-500 mt-0.5 text-xs">{{ branch.code }} · {{ branch.organization.name }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p>{{ branch.city.name }}</p>
                                    <p class="text-ink-500 mt-0.5 max-w-56 truncate text-xs">{{ branch.address }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p>Gratis {{ formatKilometers(branch.free_delivery_radius_meters) }}</p>
                                    <p class="text-ink-500 mt-0.5 text-xs">{{ formatRupiah(branch.delivery_fee_per_km_rupiah) }}/km berikutnya</p>
                                </td>
                                <td class="px-5 py-4">
                                    <Badge :variant="branch.is_active ? 'default' : 'secondary'">
                                        {{ branch.pending ? 'Menyimpan' : branch.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </Badge>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-1">
                                        <BranchFormDialog
                                            :branch="branch"
                                            :cities="cities"
                                            :organizations="organizations"
                                            :current-organization-id="currentOrganizationId"
                                            :is-superadmin="isSuperadmin"
                                        />
                                        <CmsDeleteDialog
                                            title="Hapus cabang?"
                                            :description="`Cabang ${branch.name} akan dihapus permanen jika belum memiliki data operasional.`"
                                            :confirmation="branch.code"
                                            confirmation-label="Kode cabang"
                                            :route="destroyBranch(branch.id)"
                                            :disabled="branch.pending"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="branches.length > 0" class="divide-line divide-y lg:hidden">
                    <article v-for="branch in branches" :key="branch.id" class="space-y-3 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-ink-950 font-semibold">{{ branch.name }}</h3>
                                <p class="text-ink-500 text-xs">{{ branch.code }} · {{ branch.city.name }}</p>
                            </div>
                            <Badge :variant="branch.is_active ? 'default' : 'secondary'">
                                {{ branch.pending ? 'Menyimpan' : branch.is_active ? 'Aktif' : 'Nonaktif' }}
                            </Badge>
                        </div>
                        <p class="text-ink-700 text-sm leading-6">{{ branch.address }}</p>
                        <div class="bg-surface-blue grid grid-cols-2 gap-3 rounded-lg p-3 text-sm">
                            <div>
                                <p class="text-ink-500 text-xs">Gratis sampai</p>
                                <p class="font-medium">{{ formatKilometers(branch.free_delivery_radius_meters) }}</p>
                            </div>
                            <div>
                                <p class="text-ink-500 text-xs">Tarif lanjutan</p>
                                <p class="font-medium">{{ formatRupiah(branch.delivery_fee_per_km_rupiah) }}/km</p>
                            </div>
                        </div>
                        <div class="flex justify-end gap-1 border-t pt-3">
                            <BranchFormDialog
                                :branch="branch"
                                :cities="cities"
                                :organizations="organizations"
                                :current-organization-id="currentOrganizationId"
                                :is-superadmin="isSuperadmin"
                            />
                            <CmsDeleteDialog
                                title="Hapus cabang?"
                                :description="`Cabang ${branch.name} akan dihapus permanen jika belum memiliki data operasional.`"
                                :confirmation="branch.code"
                                confirmation-label="Kode cabang"
                                :route="destroyBranch(branch.id)"
                                :disabled="branch.pending"
                            />
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </main>
</template>
