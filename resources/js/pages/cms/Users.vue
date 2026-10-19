<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Building2, LoaderCircle, Search, ShieldCheck, UsersRound, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CmsDeleteDialog from '@/components/cms/CmsDeleteDialog.vue';
import CmsEmptyState from '@/components/cms/CmsEmptyState.vue';
import CmsMetricCard from '@/components/cms/CmsMetricCard.vue';
import CmsPageHeader from '@/components/cms/CmsPageHeader.vue';
import UserFormDialog from '@/components/cms/UserFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { roleLabel } from '@/lib/formatters';
import { dashboard } from '@/routes/cms';
import { destroy, index } from '@/routes/cms/users';
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
const currentUserId = computed(() => page.props.auth.user.id);
const loading = ref(false);
const searchQuery = ref(props.filters.search ?? '');
let searchTimer: ReturnType<typeof setTimeout> | undefined;

const totalUsers = computed(() => props.statistics?.total ?? props.pagination.total);
const branchAdmins = computed(
    () =>
        props.statistics?.branch_admins ??
        props.users.filter((user) => user.roles.some((role) => role.name === 'branch_admin'))
            .length,
);

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

const visitPage = (pageNumber = 1): void => {
    clearTimeout(searchTimer);
    router.get(
        index().url,
        {
            page: pageNumber,
            ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
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

watch(searchQuery, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visitPage(), 300);
});
onBeforeUnmount(() => clearTimeout(searchTimer));

const clearSearch = (): void => {
    searchQuery.value = '';
};

const organizationName = (user: UserSummary): string =>
    user.organization?.name ??
    props.organizations.find((organization) => organization.id === user.organization_id)?.name ??
    'Tanpa organisasi';
const userConfirmations = (user: UserSummary): string[] =>
    [user.email, user.whatsapp_phone, user.name].filter(
        (val): val is string => typeof val === 'string' && val.trim() !== '',
    );
const isOwner = (user: UserSummary): boolean => user.roles.some((role) => role.name === 'owner');
const canManageUser = (user: UserSummary): boolean => isSuperadmin.value || !isOwner(user);
</script>

<template>
    <Head title="Pengguna dan akses" />

    <main class="bg-canvas flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-[1440px] space-y-6">
            <CmsPageHeader
                eyebrow="Kontrol akses"
                title="Pengguna dan akses"
                description="Buat akun operasional, tetapkan role, dan batasi admin ke cabang yang memang menjadi tanggung jawabnya."
            >
                <template #actions>
                    <UserFormDialog
                        :branches="branches"
                        :organizations="organizations"
                        :current-organization-id="currentOrganizationId"
                        :is-superadmin="isSuperadmin"
                    />
                </template>
            </CmsPageHeader>

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan pengguna">
                <CmsMetricCard label="Total pengguna" :value="totalUsers" detail="Dalam cakupan Anda" :icon="UsersRound" />
                <CmsMetricCard label="Admin cabang" :value="branchAdmins" detail="Akun operasional" :icon="ShieldCheck" />
                <CmsMetricCard label="Cabang tersedia" :value="branches.length" detail="Untuk penetapan akses" :icon="Building2" />
            </section>

            <section class="border-line bg-surface overflow-hidden rounded-3xl border shadow-low">
                <div class="border-line border-b px-5 py-4">
                    <h2 class="font-display text-ink-950 font-semibold">Daftar pengguna</h2>
                    <p class="text-ink-500 mt-1 text-sm">Role dan cabang yang dapat diakses setiap akun.</p>
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="relative max-w-xl flex-1">
                            <Search
                                class="text-ink-500 pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                id="user-search"
                                v-model="searchQuery"
                                type="search"
                                class="h-11 pr-10 pl-10"
                                placeholder="Cari nama, email, atau WhatsApp"
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="text-ink-500 hover:text-ink-950 absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-lg"
                                aria-label="Hapus pencarian pengguna"
                                data-test="clear-user-search"
                                @click="clearSearch"
                            >
                                <X class="size-4" aria-hidden="true" />
                            </button>
                        </div>
                        <p v-if="loading" class="text-ink-500 flex items-center gap-2 text-sm" role="status">
                            <LoaderCircle class="size-4 animate-spin" aria-hidden="true" />
                            Mencari pengguna…
                        </p>
                    </div>
                </div>

                <CmsEmptyState
                    v-if="users.length === 0"
                    class="m-5"
                    :title="searchQuery.trim() ? 'Pengguna tidak ditemukan' : 'Belum ada pengguna'"
                    :description="
                        searchQuery.trim()
                            ? 'Coba gunakan nama, email, atau nomor WhatsApp yang lain.'
                            : 'Tambahkan owner, admin cabang, atau pelanggan untuk memulai.'
                    "
                    :icon="UsersRound"
                />

                <div v-else class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-canvas text-ink-500">
                            <tr>
                                <th class="px-5 py-3 font-medium">Pengguna</th>
                                <th class="px-5 py-3 font-medium">Organisasi</th>
                                <th class="px-5 py-3 font-medium">Role</th>
                                <th class="px-5 py-3 font-medium">Akses cabang</th>
                                <th class="px-5 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-line divide-y">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-5 py-4">
                                    <p class="text-ink-950 font-medium">{{ user.name }}</p>
                                    <p class="text-ink-500 mt-0.5 text-xs">{{ user.email ?? 'Tanpa email' }}</p>
                                    <p v-if="user.whatsapp_phone" class="text-ink-500 mt-0.5 text-xs">
                                        WhatsApp {{ user.whatsapp_phone }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">{{ organizationName(user) }}</td>
                                <td class="px-5 py-4">
                                    <Badge variant="secondary">
                                        {{ user.pending ? 'Menyimpan' : roleLabel(user.roles[0]?.name ?? 'customer') }}
                                    </Badge>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex max-w-64 flex-wrap gap-1.5">
                                        <Badge v-for="branch in user.branches" :key="branch.id" variant="outline">
                                            {{ branch.name }}
                                        </Badge>
                                        <span v-if="user.branches.length === 0" class="text-ink-500 text-xs">
                                            {{ isOwner(user) ? 'Semua cabang' : 'Semua / tanpa cabang' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div v-if="canManageUser(user)" class="flex justify-end gap-1">
                                        <UserFormDialog
                                            :user="user"
                                            :branches="branches"
                                            :organizations="organizations"
                                            :current-organization-id="currentOrganizationId"
                                            :is-superadmin="isSuperadmin"
                                        />
                                        <CmsDeleteDialog
                                            v-if="user.id !== currentUserId"
                                            title="Hapus pengguna?"
                                            :description="`Akun ${user.name} akan dihapus permanen beserta seluruh akses cabangnya.`"
                                            :confirmation="userConfirmations(user)"
                                            confirmation-label="Email, WhatsApp, atau nama pengguna"
                                            :route="destroy(user.id)"
                                            :disabled="user.pending"
                                        />
                                    </div>
                                    <span v-else class="text-ink-400 block text-right text-xs">Dikelola superadmin</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="users.length > 0" class="divide-line divide-y lg:hidden">
                    <article v-for="user in users" :key="user.id" class="space-y-3 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-ink-950 truncate font-semibold">{{ user.name }}</h3>
                                <p class="text-ink-500 truncate text-xs">{{ user.email ?? 'Tanpa email' }}</p>
                                <p v-if="user.whatsapp_phone" class="text-ink-500 truncate text-xs">
                                    WhatsApp {{ user.whatsapp_phone }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ user.pending ? 'Menyimpan' : roleLabel(user.roles[0]?.name ?? 'customer') }}
                            </Badge>
                        </div>
                        <p class="text-ink-700 text-sm">{{ organizationName(user) }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-for="branch in user.branches" :key="branch.id" variant="outline">
                                {{ branch.name }}
                            </Badge>
                            <span v-if="user.branches.length === 0" class="text-ink-500 text-xs">
                                {{ isOwner(user) ? 'Semua cabang' : 'Tidak dibatasi ke cabang' }}
                            </span>
                        </div>
                        <div v-if="canManageUser(user)" class="flex justify-end gap-1 border-t pt-3">
                            <UserFormDialog
                                :user="user"
                                :branches="branches"
                                :organizations="organizations"
                                :current-organization-id="currentOrganizationId"
                                :is-superadmin="isSuperadmin"
                            />
                            <CmsDeleteDialog
                                v-if="user.id !== currentUserId"
                                title="Hapus pengguna?"
                                :description="`Akun ${user.name} akan dihapus permanen beserta seluruh akses cabangnya.`"
                                :confirmation="userConfirmations(user)"
                                confirmation-label="Email, WhatsApp, atau nama pengguna"
                                :route="destroy(user.id)"
                                :disabled="user.pending"
                            />
                        </div>
                        <p v-else class="border-line text-ink-400 border-t pt-3 text-right text-xs">Dikelola superadmin</p>
                    </article>
                </div>
            </section>
            <nav
                v-if="pagination.total > 0"
                class="flex flex-wrap items-center justify-between gap-3"
                aria-label="Halaman pengguna"
            >
                <p class="text-sm text-ink-500">
                    {{ pagination.total }} pengguna · Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-sm disabled:opacity-40"
                        :disabled="loading || pagination.current_page === 1"
                        @click="visitPage(pagination.current_page - 1)"
                    >
                        Sebelumnya
                    </button>
                    <button
                        v-for="pageNumber in pageNumbers"
                        :key="pageNumber"
                        type="button"
                        class="min-h-11 min-w-11 rounded-lg px-3 text-sm"
                        :class="pageNumber === pagination.current_page ? 'bg-brand-700 text-white' : 'border border-line bg-surface'"
                        :aria-current="pageNumber === pagination.current_page ? 'page' : undefined"
                        :disabled="loading"
                        @click="visitPage(pageNumber)"
                    >
                        {{ pageNumber }}
                    </button>
                    <button
                        type="button"
                        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-sm disabled:opacity-40"
                        :disabled="loading || pagination.current_page === pagination.last_page"
                        @click="visitPage(pagination.current_page + 1)"
                    >
                        Berikutnya
                    </button>
                </div>
            </nav>
        </div>
    </main>
</template>
