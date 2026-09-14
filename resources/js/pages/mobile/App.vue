<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { AlertTriangle, MessageCircle, RefreshCw, Shirt } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import MobileBottomNav from '@/components/mobile/molecules/MobileBottomNav.vue';
import MobileWorkspaceSwitcher from '@/components/mobile/molecules/MobileWorkspaceSwitcher.vue';
import WhatsAppSuggestionDialog from '@/components/mobile/molecules/WhatsAppSuggestionDialog.vue';
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
const whatsAppDialogOpen = ref(false);

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

const onTransition = async (orderId: number, status: MobileOrderStatus): Promise<void> => {
    const saved = await workspace.transitionOrder(orderId, {
        status,
        note:
            status === 'cancelled'
                ? 'Pesanan dibatalkan oleh admin cabang'
                : `Status diperbarui menjadi ${orderStatusLabels[status]}`,
    });

    if (saved) {
        whatsAppDialogOpen.value = true;
    }
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
                <MobileWorkspaceSwitcher
                    :context="workspace.context.value"
                    :selected-branch-id="workspace.selectedBranchId.value"
                    @organization-change="changeOrganization"
                    @branch-change="workspace.selectBranch"
                />

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
                            :payment-actions="workspace.paymentActions"
                            :payment-processing="workspace.paymentProcessing.value"
                            :printer-configured="workspace.printer.profile.value !== null"
                        />
                    </div>

                    <OrderListPanel
                        v-else-if="activeTab === 'orders'"
                        :orders="workspace.orders.value"
                        role="customer"
                        :payment-actions="workspace.paymentActions"
                        :payment-processing="workspace.paymentProcessing.value"
                        :printer-configured="workspace.printer.profile.value !== null"
                    />

                    <BranchExplorer
                        v-else-if="activeTab === 'branches'"
                        :branches="workspace.context.value.branches"
                        :nearest-branches="workspace.nearestBranches.value"
                        :selected-branch-id="workspace.selectedBranchId.value"
                        :selected-coordinates="deliveryCoordinates"
                        @locate="onLocationFound"
                        @select="onBranchSelected"
                    />

                    <MobileAccount
                        v-else
                        :name="page.props.auth.user.name"
                        :email="page.props.auth.user.email"
                        role="customer"
                        :organization="workspace.context.value.organization"
                        :printer-available="workspace.printer.available"
                        :printer-profile="workspace.printer.profile.value"
                        :printer-devices="workspace.printer.devices.value"
                        :printer-discovering="workspace.printer.discovering.value"
                        :printer-printing="workspace.printer.printing.value"
                        :printer-message="workspace.printer.message.value"
                        :printer-error="workspace.printer.error.value"
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
                        :payment-actions="workspace.paymentActions"
                        :payment-processing="workspace.paymentProcessing.value"
                        :printer-configured="workspace.printer.profile.value !== null"
                        @transition="onTransition"
                    />

                    <button
                        v-if="
                            activeTab === 'queue' &&
                            (workspace.whatsAppSuggestion.value ||
                                workspace.whatsAppSuggestionError.value)
                        "
                        type="button"
                        class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-mint-500/30 bg-mint-500/10 px-4 text-sm font-semibold text-mint-500"
                        data-test="reopen-whatsapp-suggestion"
                        @click="whatsAppDialogOpen = true"
                    >
                        <MessageCircle class="size-4" aria-hidden="true" />
                        Buka preview WhatsApp terakhir
                    </button>

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
                        :printer-available="workspace.printer.available"
                        :printer-profile="workspace.printer.profile.value"
                        :printer-devices="workspace.printer.devices.value"
                        :printer-discovering="workspace.printer.discovering.value"
                        :printer-printing="workspace.printer.printing.value"
                        :printer-message="workspace.printer.message.value"
                        :printer-error="workspace.printer.error.value"
                        @discover-printer="workspace.printer.discover"
                        @save-printer="workspace.printer.saveProfile"
                        @clear-printer="workspace.printer.clearProfile"
                        @test-printer="workspace.printer.testPrint"
                    />
                </template>
            </template>
        </main>

        <MobileBottomNav
            v-if="workspace.context.value"
            v-model="activeTab"
            :role="workspace.context.value.role"
        />

        <WhatsAppSuggestionDialog
            v-model:open="whatsAppDialogOpen"
            :suggestion="workspace.whatsAppSuggestion.value"
            :load-error="workspace.whatsAppSuggestionError.value"
        />
    </div>
</template>
