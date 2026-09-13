<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Building2, ShieldCheck, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import CmsEmptyState from '@/components/cms/CmsEmptyState.vue';
import CmsMetricCard from '@/components/cms/CmsMetricCard.vue';
import CmsPageHeader from '@/components/cms/CmsPageHeader.vue';
import UserCreateForm from '@/components/cms/UserCreateForm.vue';
import { Badge } from '@/components/ui/badge';
import { roleLabel } from '@/lib/formatters';
import { dashboard } from '@/routes';
import { index } from '@/routes/cms/users';
import type { UserSummary, UsersPageProps } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Pengguna & akses', href: index() },
        ],
    },
});

const props = defineProps<UsersPageProps>();
const page = usePage();
const isSuperadmin = computed(() => page.props.auth.user.roles.includes('superadmin'));
const currentOrganizationId = computed(() => page.props.auth.user.organization_id);
const branchAdmins = computed(
    () =>
        props.users.filter((user) => user.roles.some((role) => role.name === 'branch_admin'))
            .length,
);

const organizationName = (user: UserSummary): string =>
    user.organization?.name ??
    props.organizations.find((organization) => organization.id === user.organization_id)?.name ??
    'Organisasi saya';
</script>

<template>
    <Head title="Pengguna dan akses" />

    <main class="bg-canvas flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-[1440px] space-y-6">
            <CmsPageHeader
                eyebrow="Kontrol akses"
                title="Pengguna dan akses"
                description="Buat akun operasional, tetapkan role, dan batasi admin ke cabang yang memang menjadi tanggung jawabnya."
            />

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan pengguna">
                <CmsMetricCard label="Total pengguna" :value="users.length" detail="Dalam cakupan Anda" :icon="UsersRound" />
                <CmsMetricCard label="Admin cabang" :value="branchAdmins" detail="Akun operasional" :icon="ShieldCheck" />
                <CmsMetricCard label="Cabang tersedia" :value="branches.length" detail="Untuk penetapan akses" :icon="Building2" />
            </section>

            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
                <section class="border-line bg-surface overflow-hidden rounded-xl border shadow-sm">
                    <div class="border-line border-b px-5 py-4">
                        <h2 class="font-display text-ink-950 font-semibold">Daftar pengguna</h2>
                        <p class="text-ink-500 mt-1 text-sm">Role dan cabang yang dapat diakses setiap akun.</p>
                    </div>

                    <CmsEmptyState
                        v-if="users.length === 0"
                        class="m-5"
                        title="Belum ada pengguna"
                        description="Tambahkan owner, admin cabang, atau pelanggan untuk memulai."
                        :icon="UsersRound"
                    />

                    <div v-else class="hidden overflow-x-auto lg:block">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-canvas text-ink-500"><tr><th class="px-5 py-3 font-medium">Pengguna</th><th class="px-5 py-3 font-medium">Organisasi</th><th class="px-5 py-3 font-medium">Role</th><th class="px-5 py-3 font-medium">Akses cabang</th></tr></thead>
                            <tbody class="divide-line divide-y">
                                <tr v-for="user in users" :key="user.id" :class="user.pending ? 'opacity-60' : ''">
                                    <td class="px-5 py-4"><p class="text-ink-950 font-medium">{{ user.name }}</p><p class="text-ink-500 mt-0.5 text-xs">{{ user.email }}</p></td>
                                    <td class="px-5 py-4">{{ organizationName(user) }}</td>
                                    <td class="px-5 py-4"><Badge variant="secondary">{{ user.pending ? 'Menyimpan' : roleLabel(user.roles[0]?.name ?? 'customer') }}</Badge></td>
                                    <td class="px-5 py-4"><div class="flex max-w-64 flex-wrap gap-1.5"><Badge v-for="branch in user.branches" :key="branch.id" variant="outline">{{ branch.name }}</Badge><span v-if="user.branches.length === 0" class="text-ink-500 text-xs">Semua / tanpa cabang</span></div></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="users.length > 0" class="divide-line divide-y lg:hidden">
                        <article v-for="user in users" :key="user.id" class="space-y-3 p-5" :class="user.pending ? 'opacity-60' : ''">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="text-ink-950 truncate font-semibold">{{ user.name }}</h3><p class="text-ink-500 truncate text-xs">{{ user.email }}</p></div><Badge variant="secondary">{{ user.pending ? 'Menyimpan' : roleLabel(user.roles[0]?.name ?? 'customer') }}</Badge></div>
                            <p class="text-ink-700 text-sm">{{ organizationName(user) }}</p>
                            <div class="flex flex-wrap gap-1.5"><Badge v-for="branch in user.branches" :key="branch.id" variant="outline">{{ branch.name }}</Badge><span v-if="user.branches.length === 0" class="text-ink-500 text-xs">Tidak dibatasi ke cabang</span></div>
                        </article>
                    </div>
                </section>

                <UserCreateForm :branches="branches" :organizations="organizations" :current-organization-id="currentOrganizationId" :is-superadmin="isSuperadmin" />
            </div>
        </div>
    </main>
</template>
