<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2, Network, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import CmsDeleteDialog from '@/components/cms/CmsDeleteDialog.vue';
import CmsEmptyState from '@/components/cms/CmsEmptyState.vue';
import CmsMetricCard from '@/components/cms/CmsMetricCard.vue';
import CmsPageHeader from '@/components/cms/CmsPageHeader.vue';
import OrganizationFormDialog from '@/components/cms/OrganizationFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import { destroy, index } from '@/routes/cms/organizations';
import type { OrganizationsPageProps } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Organisasi', href: index() },
        ],
    },
});

const props = defineProps<OrganizationsPageProps>();
const activeOrganizations = computed(
    () => props.organizations.filter((organization) => organization.is_active).length,
);
const branchCount = computed(() =>
    props.organizations.reduce((total, organization) => total + organization.branches_count, 0),
);
</script>

<template>
    <Head title="Organisasi" />

    <main class="bg-canvas flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-[1440px] space-y-6">
            <CmsPageHeader
                eyebrow="Kontrol platform"
                title="Organisasi"
                description="Kelola tenant laundry dan lihat skala operasional setiap bisnis dari satu tempat."
            >
                <template #actions>
                    <OrganizationFormDialog />
                </template>
            </CmsPageHeader>

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan organisasi">
                <CmsMetricCard label="Total organisasi" :value="organizations.length" detail="Tenant terdaftar" :icon="Building2" />
                <CmsMetricCard label="Organisasi aktif" :value="activeOrganizations" detail="Siap beroperasi" :icon="Network" />
                <CmsMetricCard label="Total cabang" :value="branchCount" detail="Di seluruh tenant" :icon="UsersRound" />
            </section>

            <section class="border-line bg-surface overflow-hidden rounded-xl border shadow-sm">
                    <div class="border-line border-b px-5 py-4">
                        <h2 class="font-display text-ink-950 font-semibold">Daftar organisasi</h2>
                        <p class="text-ink-500 mt-1 text-sm">Status, jumlah cabang, dan akun terhubung.</p>
                    </div>

                    <CmsEmptyState
                        v-if="organizations.length === 0"
                        class="m-5"
                        title="Belum ada organisasi"
                        description="Buat tenant pertama untuk mulai menyiapkan kota dan cabang."
                        :icon="Building2"
                    />

                    <div v-else class="hidden overflow-x-auto md:block">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-canvas text-ink-500">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Organisasi</th>
                                    <th class="px-5 py-3 font-medium">Cabang</th>
                                    <th class="px-5 py-3 font-medium">Pengguna</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                    <th class="px-5 py-3 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-line divide-y">
                                <tr
                                    v-for="organization in organizations"
                                    :key="organization.id"
                                >
                                    <td class="px-5 py-4">
                                        <p class="text-ink-950 font-medium">{{ organization.name }}</p>
                                        <p class="text-ink-500 mt-0.5 text-xs">{{ organization.slug }}</p>
                                    </td>
                                    <td class="px-5 py-4 tabular-nums">{{ organization.branches_count }}</td>
                                    <td class="px-5 py-4 tabular-nums">{{ organization.users_count }}</td>
                                    <td class="px-5 py-4">
                                        <Badge :variant="organization.is_active ? 'default' : 'secondary'">
                                            {{ organization.pending ? 'Menyimpan' : organization.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </Badge>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-1">
                                            <OrganizationFormDialog :organization="organization" />
                                            <CmsDeleteDialog
                                                title="Hapus organisasi?"
                                                :description="`Organisasi ${organization.name} akan dihapus permanen jika sudah tidak memiliki data operasional.`"
                                                :confirmation="organization.name"
                                                confirmation-label="Nama organisasi"
                                                :route="destroy(organization.id)"
                                                :disabled="organization.pending"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="organizations.length > 0" class="divide-line divide-y md:hidden">
                        <article
                            v-for="organization in organizations"
                            :key="organization.id"
                            class="space-y-3 p-5"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2 class="text-ink-950 truncate font-semibold">{{ organization.name }}</h2>
                                    <p class="text-ink-500 truncate text-xs">{{ organization.slug }}</p>
                                </div>
                                <Badge :variant="organization.is_active ? 'default' : 'secondary'">{{ organization.pending ? 'Menyimpan' : organization.is_active ? 'Aktif' : 'Nonaktif' }}</Badge>
                            </div>
                            <dl class="grid grid-cols-2 gap-3 text-sm">
                                <div><dt class="text-ink-500">Cabang</dt><dd class="font-medium">{{ organization.branches_count }}</dd></div>
                                <div><dt class="text-ink-500">Pengguna</dt><dd class="font-medium">{{ organization.users_count }}</dd></div>
                            </dl>
                            <div class="flex justify-end gap-1 border-t pt-3">
                                <OrganizationFormDialog :organization="organization" />
                                <CmsDeleteDialog
                                    title="Hapus organisasi?"
                                    :description="`Organisasi ${organization.name} akan dihapus permanen jika sudah tidak memiliki data operasional.`"
                                    :confirmation="organization.name"
                                    confirmation-label="Nama organisasi"
                                    :route="destroy(organization.id)"
                                    :disabled="organization.pending"
                                />
                            </div>
                        </article>
                    </div>
                </section>
        </div>
    </main>
</template>
