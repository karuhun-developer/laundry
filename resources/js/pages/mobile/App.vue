<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ChevronDown, RefreshCw, Shirt, Store } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import MobileBottomNav from '@/components/mobile/molecules/MobileBottomNav.vue';
import BranchExplorer from '@/components/mobile/organisms/BranchExplorer.vue';
import CustomerDirectory from '@/components/mobile/organisms/CustomerDirectory.vue';
import MobileAccount from '@/components/mobile/organisms/MobileAccount.vue';
import OrderComposer from '@/components/mobile/organisms/OrderComposer.vue';
import OrderListPanel from '@/components/mobile/organisms/OrderListPanel.vue';
import type { Coordinates } from '@/composables/useGeolocation';
import { useMobileWorkspace } from '@/composables/useMobileWorkspace';
import { orderStatusLabels } from '@/lib/mobileOrders';
import type {
    CreateOrderPayload,
    MobileOrderStatus,
    MobilePriceQuote,
    MobileTab,
} from '@/types/mobile';

const page = usePage();
const workspace = useMobileWorkspace();
const activeTab = ref<MobileTab>('home');
const deliveryCoordinates = ref<Coordinates | null>(null);

const recentOrders = computed(() => workspace.orders.value.slice(0, 2));

watch(
    () => workspace.context.value?.role,
    (role) => {
        activeTab.value = role === 'branch_admin' ? 'queue' : 'home';
    },
);

const changeOrganization = async (organizationSlug: string): Promise<void> => {
    const previousCoordinates = deliveryCoordinates.value;
    deliveryCoordinates.value = null;
    await workspace.load(organizationSlug);

    if (
        previousCoordinates &&
        workspace.context.value?.organization.slug === organizationSlug &&
        (await workspace.findNearestBranches(
            previousCoordinates.latitude,
            previousCoordinates.longitude,
        ))
    ) {
        deliveryCoordinates.value = previousCoordinates;
    }
};

const onOrganizationChange = (event: Event): void => {
    if (event.target instanceof HTMLSelectElement) {
        void changeOrganization(event.target.value);
    }
};

const onBranchChange = (event: Event): void => {
    if (event.target instanceof HTMLSelectElement) {
        void workspace.selectBranch(Number(event.target.value));
    }
};

const onBranchSelected = (branchId: number): void => {
    void workspace.selectBranch(branchId);
};

const updateDeliveryLocation = async (latitude: number, longitude: number): Promise<void> => {
    if (await workspace.findNearestBranches(latitude, longitude)) {
        deliveryCoordinates.value = { latitude, longitude };
    }
};

const onLocationFound = (latitude: number, longitude: number): void => {
    void updateDeliveryLocation(latitude, longitude);
};

const onCreateOrder = (payload: CreateOrderPayload, quote: MobilePriceQuote): void => {
    void workspace.createOrder(payload, quote);
};

const onTransition = (orderId: number, status: MobileOrderStatus): void => {
    void workspace.transitionOrder(orderId, {
        status,
        note:
            status === 'cancelled'
                ? 'Pesanan dibatalkan oleh admin cabang'
                : `Status diperbarui menjadi ${orderStatusLabels[status]}`,
    });
};
</script>

<template>
    <Head title="LaundryHub Mobile" />

    <div class="mobile-shell bg-canvas text-ink-950">
        <header class="mobile-fixed-header fixed inset-x-0 top-0 z-30 border-b border-line bg-surface/95 backdrop-blur">
            <div class="mx-auto flex min-h-16 max-w-3xl items-center justify-between gap-3 px-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-700 text-white shadow-low">
                        <Shirt class="size-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-display text-base font-semibold text-ink-950">LaundryHub</p>
                        <p v-if="workspace.context.value" class="truncate text-xs text-ink-500">
                            {{ workspace.context.value.organization.name }}
                        </p>
                    </div>
                </div>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">
                    {{ page.props.auth.user.name.slice(0, 1).toUpperCase() }}
                </span>
            </div>
        </header>

        <main class="mobile-scroll-owner mx-auto w-full max-w-3xl px-4 pt-5 pb-6 sm:px-6 sm:pt-7">
            <div v-if="workspace.loading.value" class="space-y-4" aria-label="Memuat aplikasi">
                <div class="h-36 animate-pulse rounded-3xl bg-brand-100" />
                <div class="h-56 animate-pulse rounded-2xl bg-surface shadow-low" />
            </div>

            <div
                v-else-if="!workspace.context.value"
                class="rounded-3xl border border-red-600/20 bg-surface p-6 text-center shadow-low"
            >
                <AlertTriangle class="mx-auto size-8 text-red-600" aria-hidden="true" />
                <h1 class="mt-4 font-display text-xl font-semibold">Aplikasi belum dapat dimuat</h1>
                <p class="mt-2 text-sm leading-6 text-ink-500">
                    {{ workspace.requestError.value ?? 'Periksa koneksi lalu coba lagi.' }}
                </p>
                <button
                    type="button"
                    class="mt-5 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-700 px-5 text-sm font-semibold text-white"
                    @click="workspace.load()"
                >
                    <RefreshCw class="size-4" aria-hidden="true" />
                    Coba lagi
                </button>
            </div>

            <template v-else>
                <div class="mb-5 grid gap-3 sm:grid-cols-2">
                    <label
                        v-if="workspace.context.value.role === 'customer' && workspace.context.value.organizations.length > 1"
                        class="relative block"
                    >
                        <span class="sr-only">Pilih organisasi laundry</span>
                        <select
                            :value="workspace.context.value.organization.slug"
                            class="min-h-11 w-full appearance-none rounded-xl border border-line bg-surface py-2 pr-10 pl-3 text-sm font-semibold text-ink-950 shadow-low outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                            @change="onOrganizationChange"
                        >
                            <option
                                v-for="organization in workspace.context.value.organizations"
                                :key="organization.id"
                                :value="organization.slug"
                            >
                                {{ organization.name }}
                            </option>
                        </select>
                        <ChevronDown class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-ink-500" aria-hidden="true" />
                    </label>

                    <label class="relative block" :class="{ 'sm:col-start-2': workspace.context.value.role === 'customer' }">
                        <span class="sr-only">Pilih cabang</span>
                        <Store class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-brand-700" aria-hidden="true" />
                        <select
                            :value="workspace.selectedBranchId.value ?? ''"
                            class="min-h-11 w-full appearance-none rounded-xl border border-line bg-surface py-2 pr-10 pl-10 text-sm font-semibold text-ink-950 shadow-low outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                            @change="onBranchChange"
                        >
                            <option v-if="workspace.context.value.branches.length === 0" value="">Belum ada cabang</option>
                            <option
                                v-for="branch in workspace.context.value.branches"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }} · {{ branch.city.name }}
                            </option>
                        </select>
                        <ChevronDown class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-ink-500" aria-hidden="true" />
                    </label>
                </div>

                <p
                    v-if="workspace.rollbackMessage.value || workspace.requestError.value"
                    class="mb-5 rounded-xl border border-red-600/20 bg-red-600/5 px-4 py-3 text-sm text-red-600"
                    role="status"
                    aria-live="polite"
                >
                    {{ workspace.rollbackMessage.value ?? workspace.requestError.value }}
                </p>

                <template v-if="workspace.context.value.role === 'customer'">
                    <div v-if="activeTab === 'home'" class="space-y-8">
                        <OrderComposer
                            role="customer"
                            :branch="workspace.selectedBranch.value"
                            :services="workspace.services.value"
                            :customers="workspace.customers.value"
                            :delivery-coordinates="deliveryCoordinates"
                            :delivery-fee-rupiah="workspace.selectedBranchDeliveryFee.value"
                            :quote-order="workspace.quoteOrder"
                            :processing="workspace.createProcessing.value"
                            @submit="onCreateOrder"
                        />
                        <OrderListPanel
                            v-if="recentOrders.length"
                            :orders="recentOrders"
                            role="customer"
                            title="Pesanan terbaru"
                            description="Pantau dua pesanan terakhir tanpa meninggalkan beranda."
                        />
                    </div>

                    <OrderListPanel
                        v-else-if="activeTab === 'orders'"
                        :orders="workspace.orders.value"
                        role="customer"
                    />

                    <BranchExplorer
                        v-else-if="activeTab === 'branches'"
                        :branches="workspace.context.value.branches"
                        :nearest-branches="workspace.nearestBranches.value"
                        :selected-branch-id="workspace.selectedBranchId.value"
                        @locate="onLocationFound"
                        @select="onBranchSelected"
                    />

                    <MobileAccount
                        v-else
                        :name="page.props.auth.user.name"
                        :email="page.props.auth.user.email"
                        role="customer"
                        :organization="workspace.context.value.organization"
                    />
                </template>

                <template v-else>
                    <OrderListPanel
                        v-if="activeTab === 'queue'"
                        :orders="workspace.orders.value"
                        role="branch_admin"
                        :processing="workspace.transitionProcessing.value"
                        title="Antrean cabang"
                        description="Lanjutkan status satu tahap atau batalkan pesanan yang masih aktif."
                        @transition="onTransition"
                    />

                    <OrderComposer
                        v-else-if="activeTab === 'new-order'"
                        role="branch_admin"
                        :branch="workspace.selectedBranch.value"
                        :services="workspace.services.value"
                        :customers="workspace.customers.value"
                        :delivery-coordinates="deliveryCoordinates"
                        :delivery-fee-rupiah="workspace.selectedBranchDeliveryFee.value"
                        :quote-order="workspace.quoteOrder"
                        :processing="workspace.createProcessing.value"
                        @submit="onCreateOrder"
                    />

                    <CustomerDirectory
                        v-else-if="activeTab === 'customers'"
                        :customers="workspace.customers.value"
                    />

                    <MobileAccount
                        v-else
                        :name="page.props.auth.user.name"
                        :email="page.props.auth.user.email"
                        role="branch_admin"
                        :organization="workspace.context.value.organization"
                    />
                </template>
            </template>
        </main>

        <MobileBottomNav
            v-if="workspace.context.value"
            v-model="activeTab"
            :role="workspace.context.value.role"
        />
    </div>
</template>
