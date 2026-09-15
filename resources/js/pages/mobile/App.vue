<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Building2,
    MessageCircle,
    Plus,
    RefreshCw,
    Search,
    Shirt,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CustomerCredentialDialog from '@/components/mobile/molecules/CustomerCredentialDialog.vue';
import CustomerFormDialog from '@/components/mobile/molecules/CustomerFormDialog.vue';
import CustomerResetPinDialog from '@/components/mobile/molecules/CustomerResetPinDialog.vue';
import MobileBottomNav from '@/components/mobile/molecules/MobileBottomNav.vue';
import MobileWorkspaceSwitcher from '@/components/mobile/molecules/MobileWorkspaceSwitcher.vue';
import WhatsAppSuggestionDialog from '@/components/mobile/molecules/WhatsAppSuggestionDialog.vue';
import BranchExplorer from '@/components/mobile/organisms/BranchExplorer.vue';
import CustomerAddressDialog from '@/components/mobile/organisms/CustomerAddressDialog.vue';
import CustomerDirectory from '@/components/mobile/organisms/CustomerDirectory.vue';
import CustomerOrderFilters from '@/components/mobile/organisms/CustomerOrderFilters.vue';
import MobileAccount from '@/components/mobile/organisms/MobileAccount.vue';
import OrderComposer from '@/components/mobile/organisms/OrderComposer.vue';
import OrderListPanel from '@/components/mobile/organisms/OrderListPanel.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Toaster } from '@/components/ui/sonner';
import { useMobileWorkspace } from '@/composables/useMobileWorkspace';
import { usePushNotifications } from '@/composables/usePushNotifications';
import type { CustomerOrderFilters as CustomerOrderFilterValues } from '@/lib/mobileOrderFilters';
import {
    emptyCustomerOrderFilters,
    filterCustomerOrders,
    hasCustomerOrderFilters,
    normalizeCustomerOrderFilters,
} from '@/lib/mobileOrderFilters';
import { orderStatusLabels } from '@/lib/mobileOrders';
import { logout } from '@/routes';
import type {
    ConfirmOrderPricingPayload,
    CreateOrderPayload,
    MobileCustomer,
    MobileCustomerCredential,
    MobileCustomerCredentialResult,
    MobileCustomerMutationResult,
    MobileCustomerProfilePayload,
    MobileOrderStatus,
    MobilePriceQuote,
    MobileTab,
} from '@/types/mobile';

const page = usePage();
const workspace = useMobileWorkspace();
const activeTab = ref<MobileTab>('home');
const whatsAppDialogOpen = ref(false);
const customerFormOpen = ref(false);
const customerFormMode = ref<'create' | 'edit'>('create');
const activeCustomer = ref<MobileCustomer | null>(null);
const resetPinDialogOpen = ref(false);
const addressDialogOpen = ref(false);
const orderComposerOpen = ref(false);
const queueSearch = ref('');
const customerOrderFilters = ref<CustomerOrderFilterValues>(emptyCustomerOrderFilters());
const credentialDialogOpen = ref(false);
const revealedCredential = ref<{
    customerName: string;
    credential: MobileCustomerCredential;
} | null>(null);
const customerStatusMessage = ref('');
let queueSearchTimer: ReturnType<typeof setTimeout> | null = null;
let customerOrderSearchTimer: ReturnType<typeof setTimeout> | null = null;
let queueSearchClearedSynchronously = false;

const refreshApp = async (showSuccess = true): Promise<void> => {
    const refreshed = await workspace.refresh();

    if (!refreshed) {
        return;
    }

    if (workspace.context.value?.role === 'branch_admin' && queueSearch.value.trim()) {
        await workspace.searchOrders(queueSearch.value.trim());
    }

    if (
        workspace.context.value?.role === 'customer' &&
        hasCustomerOrderFilters(customerOrderFilters.value)
    ) {
        await workspace.searchCustomerOrders(
            normalizeCustomerOrderFilters(customerOrderFilters.value),
        );
    }

    if (showSuccess && !workspace.requestError.value) {
        toast.success('Data aplikasi berhasil disegarkan.');
    }
};

const pushNotifications = usePushNotifications({
    onRefresh: () => refreshApp(false),
});

const handleLogout = async (): Promise<void> => {
    try {
        await pushNotifications.unregisterDevice();
    } finally {
        router.post(logout().url);
    }
};

const recentOrders = computed(() => workspace.orders.value.slice(0, 2));
const filteredCustomerOrders = computed(() =>
    filterCustomerOrders(workspace.orders.value, customerOrderFilters.value),
);
const activePickupOrderNumber = computed(
    () =>
        workspace.orders.value.find((order) => order.status === 'pickup_requested')?.order_number ??
        null,
);

watch(
    () => workspace.context.value?.role,
    (role) => {
        activeTab.value = role === 'branch_admin' ? 'queue' : 'home';
    },
);

watch(credentialDialogOpen, (isOpen) => {
    if (!isOpen) {
        revealedCredential.value = null;
    }
});

watch(queueSearch, (search) => {
    if (queueSearchClearedSynchronously) {
        queueSearchClearedSynchronously = false;
        return;
    }

    if (queueSearchTimer) {
        clearTimeout(queueSearchTimer);
    }

    queueSearchTimer = setTimeout(() => void workspace.searchOrders(search.trim()), 300);
});

watch(
    customerOrderFilters,
    (filters) => {
        if (customerOrderSearchTimer) {
            clearTimeout(customerOrderSearchTimer);
        }

        const normalizedFilters = normalizeCustomerOrderFilters(filters);
        customerOrderSearchTimer = setTimeout(
            () => void workspace.searchCustomerOrders(normalizedFilters),
            300,
        );
    },
    { deep: true },
);

watch(
    () => workspace.requestError.value,
    (message) => {
        if (message) {
            toast.error(message);
        }
    },
);

watch(
    () => workspace.rollbackMessage.value,
    (message) => {
        if (message) {
            toast.error(message);
        }
    },
);

watch(
    () => workspace.printer.message.value,
    (message) => {
        if (message) {
            toast.success(message);
        }
    },
);

watch(
    () => workspace.printer.error.value,
    (message) => {
        if (message) {
            toast.error(message);
        }
    },
);

onBeforeUnmount(() => {
    if (queueSearchTimer) {
        clearTimeout(queueSearchTimer);
    }

    if (customerOrderSearchTimer) {
        clearTimeout(customerOrderSearchTimer);
    }
});

const openCreateCustomer = (): void => {
    activeCustomer.value = null;
    customerFormMode.value = 'create';
    customerFormOpen.value = true;
};

const openEditCustomer = (customer: MobileCustomer): void => {
    activeCustomer.value = customer;
    customerFormMode.value = 'edit';
    customerFormOpen.value = true;
};

const openResetCustomerPin = (customer: MobileCustomer): void => {
    activeCustomer.value = customer;
    resetPinDialogOpen.value = true;
};

const saveCustomer = (
    payload: MobileCustomerProfilePayload,
): Promise<MobileCustomerMutationResult> => {
    if (customerFormMode.value === 'edit' && activeCustomer.value) {
        return workspace.updateCustomer(activeCustomer.value.id, payload);
    }

    return workspace.createCustomer(payload);
};

const onCustomerSaved = (result: MobileCustomerMutationResult): void => {
    customerStatusMessage.value =
        customerFormMode.value === 'edit'
            ? `Pelanggan ${result.customer.name} berhasil diperbarui.`
            : result.credential
              ? `Pelanggan ${result.customer.name} berhasil dibuat.`
              : `Pelanggan ${result.customer.name} berhasil dihubungkan.`;
    toast.success(customerStatusMessage.value);
    revealTemporaryPin(result);
};

const onCustomerPinReset = (result: MobileCustomerCredentialResult): void => {
    customerStatusMessage.value = `PIN pelanggan ${result.customer.name} berhasil direset.`;
    toast.success(customerStatusMessage.value);
    revealTemporaryPin(result);
};

const revealTemporaryPin = (
    result: MobileCustomerMutationResult | MobileCustomerCredentialResult,
): void => {
    if (!result.credential) {
        return;
    }

    revealedCredential.value = {
        customerName: result.customer.name,
        credential: result.credential,
    };
    credentialDialogOpen.value = true;
};

const onBranchSelected = async (branchId: number): Promise<void> => {
    await workspace.selectBranch(branchId);
};

const updateDeliveryLocation = async (latitude: number, longitude: number): Promise<void> => {
    await workspace.findNearestBranches(latitude, longitude);
};

const onLocationFound = (latitude: number, longitude: number): void => {
    void updateDeliveryLocation(latitude, longitude);
};

const onCreateOrder = async (
    payload: CreateOrderPayload,
    quote: MobilePriceQuote | null,
): Promise<void> => {
    const created = await workspace.createOrder(payload, quote);

    if (!created) {
        return;
    }

    if (workspace.context.value?.role === 'customer') {
        toast.success('Permintaan penjemputan berhasil dibuat dan menunggu verifikasi cabang.');
    } else {
        orderComposerOpen.value = false;
        toast.success('Pesanan baru berhasil dibuat.');
    }
};

const onConfirmPricing = async (
    orderId: number,
    payload: ConfirmOrderPricingPayload,
): Promise<void> => {
    if (await workspace.confirmOrderPricing(orderId, payload)) {
        toast.success('Berat dan harga pesanan berhasil dikonfirmasi.');
        whatsAppDialogOpen.value = true;
    }
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
        toast.success(`Status pesanan berhasil diperbarui menjadi ${orderStatusLabels[status]}.`);
        whatsAppDialogOpen.value = true;
    }
};

const clearQueueSearch = (): void => {
    if (queueSearchTimer) {
        clearTimeout(queueSearchTimer);
        queueSearchTimer = null;
    }

    queueSearchClearedSynchronously = true;
    queueSearch.value = '';
    void workspace.searchOrders('');
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
                            {{
                                workspace.context.value.role === 'customer'
                                    ? 'Jemput, cuci, antar'
                                    : workspace.context.value.organization.name
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        v-if="workspace.context.value"
                        type="button"
                        class="flex size-10 items-center justify-center rounded-xl border border-line bg-surface text-brand-700 shadow-low transition hover:bg-brand-100 disabled:cursor-not-allowed disabled:opacity-60 motion-safe:active:scale-95"
                        :disabled="workspace.refreshing.value"
                        aria-label="Segarkan data aplikasi"
                        data-test="refresh-mobile-app"
                        @click="refreshApp()"
                    >
                        <RefreshCw
                            class="size-4"
                            :class="{ 'animate-spin': workspace.refreshing.value }"
                            aria-hidden="true"
                        />
                    </button>
                    <span class="flex size-10 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">
                        {{ page.props.auth.user.name.slice(0, 1).toUpperCase() }}
                    </span>
                </div>
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
                    :nearest-branches="workspace.nearestBranches.value"
                    :selected-branch-id="workspace.selectedBranchId.value"
                    @branch-change="onBranchSelected"
                />

                <p
                    v-if="workspace.rollbackMessage.value || workspace.requestError.value"
                    class="mb-5 rounded-xl border border-red-600/20 bg-red-600/5 px-4 py-3 text-sm text-red-600"
                    role="status"
                    aria-live="polite"
                >
                    {{ workspace.rollbackMessage.value ?? workspace.requestError.value }}
                </p>
                <p
                    class="sr-only"
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                    data-test="customer-status"
                >
                    {{ customerStatusMessage }}
                </p>

                <template v-if="workspace.context.value.role === 'customer'">
                    <div v-if="activeTab === 'home'" class="space-y-8">
                        <OrderComposer
                            role="customer"
                            :branch="workspace.selectedBranch.value"
                            :services="workspace.services.value"
                            :customers="workspace.customers.value"
                            :delivery-coordinates="workspace.deliveryCoordinates.value"
                            :selected-address="workspace.selectedAddress.value"
                            :active-pickup-order-number="activePickupOrderNumber"
                            :delivery-fee-rupiah="workspace.selectedBranchDeliveryFee.value"
                            :quote-order="workspace.quoteOrder"
                            :processing="workspace.createProcessing.value"
                            @manage-addresses="addressDialogOpen = true"
                            @submit="onCreateOrder"
                        />
                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-brand-600 bg-surface px-5 text-sm font-semibold text-brand-700 shadow-low transition hover:bg-brand-100 motion-safe:active:scale-[0.98]"
                            data-test="view-branches-shortcut"
                            @click="activeTab = 'branches'"
                        >
                            <Building2 class="size-5" aria-hidden="true" />
                            Lihat daftar cabang
                        </button>
                        <OrderListPanel
                            v-if="recentOrders.length"
                            :orders="recentOrders"
                            role="customer"
                            title="Pesanan terbaru"
                            description="Pantau dua pesanan terakhir tanpa meninggalkan beranda."
                            :payment-actions="workspace.paymentActions"
                            :payment-processing="workspace.paymentProcessing.value"
                            :pricing-processing="workspace.pricingProcessing.value"
                            :printer-configured="workspace.printer.profile.value !== null"
                        />
                    </div>

                    <template v-else-if="activeTab === 'orders'">
                        <CustomerOrderFilters
                            v-model="customerOrderFilters"
                            :loading="workspace.customerOrderLoading.value"
                        />
                        <OrderListPanel
                            :orders="filteredCustomerOrders"
                            role="customer"
                            :empty-title="
                                hasCustomerOrderFilters(customerOrderFilters)
                                    ? 'Pesanan tidak ditemukan'
                                    : 'Belum ada pesanan'
                            "
                            :empty-description="
                                hasCustomerOrderFilters(customerOrderFilters)
                                    ? 'Tidak ada pesanan yang cocok dengan filter ini.'
                                    : 'Pesanan yang Anda buat akan muncul di sini.'
                            "
                            :payment-actions="workspace.paymentActions"
                            :payment-processing="workspace.paymentProcessing.value"
                            :pricing-processing="workspace.pricingProcessing.value"
                            :printer-configured="workspace.printer.profile.value !== null"
                        />
                    </template>

                    <BranchExplorer
                        v-else-if="activeTab === 'branches'"
                        :branches="workspace.context.value.branches"
                        :nearest-branches="workspace.nearestBranches.value"
                        :selected-branch-id="workspace.selectedBranchId.value"
                        :selected-coordinates="workspace.deliveryCoordinates.value"
                        @locate="onLocationFound"
                        @select="onBranchSelected"
                    />

                    <MobileAccount
                        v-else
                        :name="page.props.auth.user.name"
                        :email="page.props.auth.user.email ?? undefined"
                        :phone="page.props.auth.user.whatsapp_phone"
                        role="customer"
                        :must-change-pin="page.props.auth.user.must_change_pin"
                        :pin-expires-at="page.props.auth.user.pin_expires_at"
                        :printer-available="workspace.printer.available"
                        :printer-profile="workspace.printer.profile.value"
                        :printer-devices="workspace.printer.devices.value"
                        :printer-discovering="workspace.printer.discovering.value"
                        :printer-printing="workspace.printer.printing.value"
                        :printer-message="workspace.printer.message.value"
                        :printer-error="workspace.printer.error.value"
                        :notification-available="pushNotifications.available"
                        :notification-status="pushNotifications.status.value"
                        :notification-error="pushNotifications.error.value"
                        @enable-notifications="pushNotifications.enable"
                        @logout="handleLogout"
                    />
                </template>

                <template v-else>
                    <template v-if="activeTab === 'queue'">
                        <div class="mb-5 space-y-3 rounded-2xl border border-line bg-surface p-4 shadow-low">
                            <button
                                type="button"
                                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-5 text-sm font-semibold text-white transition hover:bg-brand-600 motion-safe:active:scale-[0.98]"
                                data-test="open-order-composer"
                                @click="orderComposerOpen = true"
                            >
                                <Plus class="size-5" aria-hidden="true" />
                                Buat pesanan baru
                            </button>
                            <label class="relative block">
                                <span class="sr-only">Cari antrean</span>
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-500"
                                    aria-hidden="true"
                                />
                                <input
                                    v-model="queueSearch"
                                    type="search"
                                    inputmode="search"
                                    autocomplete="off"
                                    placeholder="Cari nomor pesanan, pelanggan, atau WhatsApp"
                                    class="min-h-11 w-full rounded-xl border border-line bg-canvas pr-11 pl-10 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                                    data-test="queue-search"
                                />
                                <button
                                    v-if="queueSearch"
                                    type="button"
                                    class="absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-lg text-ink-500 hover:bg-brand-100 hover:text-brand-700"
                                    aria-label="Hapus pencarian antrean"
                                    data-test="clear-queue-search"
                                    @click="clearQueueSearch"
                                >
                                    <X class="size-4" aria-hidden="true" />
                                </button>
                            </label>
                            <p
                                v-if="workspace.queueLoading.value"
                                class="flex items-center gap-2 text-xs text-ink-500"
                                role="status"
                            >
                                <RefreshCw class="size-3.5 animate-spin" aria-hidden="true" />
                                Memuat hasil pencarian…
                            </p>
                        </div>

                        <OrderListPanel
                            :orders="workspace.orders.value"
                            role="branch_admin"
                            :processing="workspace.transitionProcessing.value"
                            title="Antrean cabang"
                            description="Lanjutkan status satu tahap atau batalkan pesanan yang masih aktif."
                            :empty-title="queueSearch ? 'Pesanan tidak ditemukan' : 'Belum ada pesanan'"
                            :empty-description="
                                queueSearch
                                    ? `Tidak ada antrean yang cocok dengan “${queueSearch}”.`
                                    : 'Pesanan baru akan langsung muncul di sini dan dapat diproses dari status pertama.'
                            "
                            :payment-actions="workspace.paymentActions"
                            :payment-processing="workspace.paymentProcessing.value"
                            :pricing-processing="workspace.pricingProcessing.value"
                            :printer-configured="workspace.printer.profile.value !== null"
                            @confirm-pricing="onConfirmPricing"
                            @transition="onTransition"
                        />

                        <button
                            v-if="
                                workspace.whatsAppSuggestion.value ||
                                workspace.whatsAppSuggestionError.value
                            "
                            type="button"
                            class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-mint-500/30 bg-mint-500/10 px-4 text-sm font-semibold text-mint-500"
                            data-test="reopen-whatsapp-suggestion"
                            @click="whatsAppDialogOpen = true"
                        >
                            <MessageCircle class="size-4" aria-hidden="true" />
                            Buka preview WhatsApp terakhir
                        </button>
                    </template>

                    <CustomerDirectory
                        v-else-if="activeTab === 'customers'"
                        :customers="workspace.customers.value"
                        @create="openCreateCustomer"
                        @edit="openEditCustomer"
                        @reset-pin="openResetCustomerPin"
                    />

                    <MobileAccount
                        v-else-if="activeTab === 'more'"
                        :name="page.props.auth.user.name"
                        :email="page.props.auth.user.email ?? undefined"
                        :phone="page.props.auth.user.whatsapp_phone"
                        role="branch_admin"
                        :organization="workspace.context.value.organization"
                        :printer-available="workspace.printer.available"
                        :printer-profile="workspace.printer.profile.value"
                        :printer-devices="workspace.printer.devices.value"
                        :printer-discovering="workspace.printer.discovering.value"
                        :printer-printing="workspace.printer.printing.value"
                        :printer-message="workspace.printer.message.value"
                        :printer-error="workspace.printer.error.value"
                        :notification-available="pushNotifications.available"
                        :notification-status="pushNotifications.status.value"
                        :notification-error="pushNotifications.error.value"
                        @discover-printer="workspace.printer.discover"
                        @save-printer="workspace.printer.saveProfile"
                        @clear-printer="workspace.printer.clearProfile"
                        @test-printer="workspace.printer.testPrint"
                        @enable-notifications="pushNotifications.enable"
                        @logout="handleLogout"
                    />
                </template>
            </template>
        </main>

        <MobileBottomNav
            v-if="workspace.context.value"
            v-model="activeTab"
            :role="workspace.context.value.role"
        />

        <Toaster position="top-center" />

        <WhatsAppSuggestionDialog
            v-model:open="whatsAppDialogOpen"
            :suggestion="workspace.whatsAppSuggestion.value"
            :load-error="workspace.whatsAppSuggestionError.value"
        />

        <CustomerAddressDialog
            v-if="workspace.context.value?.role === 'customer'"
            v-model:open="addressDialogOpen"
            :addresses="workspace.addresses.value"
            :selected-address-id="workspace.selectedAddressId.value"
            :customer-name="page.props.auth.user.name"
            :customer-phone="page.props.auth.user.whatsapp_phone"
            :create-address="workspace.createAddress"
            :update-address="workspace.updateAddress"
            :delete-address="workspace.deleteAddress"
            :set-default-address="workspace.setDefaultAddress"
            :select-address="workspace.selectAddress"
            @success="toast.success"
        />

        <Dialog v-if="workspace.context.value?.role === 'branch_admin'" v-model:open="orderComposerOpen">
            <DialogContent
                class="max-h-[92dvh] max-w-2xl overflow-y-auto rounded-3xl border-0 bg-transparent p-0 shadow-none"
                :show-close-button="false"
            >
                <DialogHeader class="sr-only">
                    <DialogTitle>Buat pesanan baru</DialogTitle>
                    <DialogDescription>Catat pesanan laundry baru untuk pelanggan.</DialogDescription>
                </DialogHeader>
                <OrderComposer
                    role="branch_admin"
                    :branch="workspace.selectedBranch.value"
                    :services="workspace.services.value"
                    :customers="workspace.customers.value"
                    :delivery-coordinates="workspace.deliveryCoordinates.value"
                    :delivery-fee-rupiah="workspace.selectedBranchDeliveryFee.value"
                    :quote-order="workspace.quoteOrder"
                    :processing="workspace.createProcessing.value"
                    :selected-customer-id="workspace.selectedCustomerId.value"
                    @create-customer="openCreateCustomer"
                    @submit="onCreateOrder"
                    @update:selected-customer-id="workspace.selectCustomer"
                >
                    <template #header-action>
                        <DialogClose as-child>
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-white/12 px-3 text-sm font-semibold text-white transition hover:bg-white/20"
                                data-test="close-order-composer"
                                @click="orderComposerOpen = false"
                            >
                                <X class="size-4" aria-hidden="true" />
                                Tutup
                            </button>
                        </DialogClose>
                    </template>
                </OrderComposer>
            </DialogContent>
        </Dialog>

        <template v-if="workspace.context.value?.role === 'branch_admin'">
            <CustomerFormDialog
                v-model:open="customerFormOpen"
                :mode="customerFormMode"
                :customer="activeCustomer"
                :submit="saveCustomer"
                @saved="onCustomerSaved"
            />
            <CustomerResetPinDialog
                v-model:open="resetPinDialogOpen"
                :customer="activeCustomer"
                :reset="workspace.resetCustomerPin"
                @reset="onCustomerPinReset"
            />
            <CustomerCredentialDialog
                v-model:open="credentialDialogOpen"
                :customer-name="revealedCredential?.customerName ?? ''"
                :credential="revealedCredential?.credential ?? null"
                @dismiss="revealedCredential = null"
            />
        </template>
    </div>
</template>
