import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import CustomerDirectory from '@/components/mobile/organisms/CustomerDirectory.vue';
import MobileAccount from '@/components/mobile/organisms/MobileAccount.vue';
import OrderComposer from '@/components/mobile/organisms/OrderComposer.vue';
import OrderListPanel from '@/components/mobile/organisms/OrderListPanel.vue';
import { makeOrder } from '@/composables/mobileOrderTestFixtures';
import MobileApp from '@/pages/mobile/App.vue';
import type { MobileOrder, MobileTab, WhatsAppSuggestion } from '@/types/mobile';

const mobileWorkspace = vi.hoisted(() => ({
    context: {
        value: {
            role: 'branch_admin',
            organization: { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
            organizations: [],
            branches: [],
        },
    },
    loading: { value: false },
    refreshing: { value: false },
    requestError: { value: null },
    rollbackMessage: { value: null },
    queueLoading: { value: false },
    customerOrderLoading: { value: false },
    orders: { value: [] as MobileOrder[] },
    addresses: { value: [] },
    customers: { value: [] },
    nearestBranches: { value: [] },
    deliveryCoordinates: { value: null },
    services: { value: [] },
    selectedBranch: { value: null },
    selectedBranchId: { value: null },
    selectedBranchDeliveryFee: { value: 0 },
    selectedAddress: { value: null },
    selectedAddressId: { value: null },
    selectedCustomerId: { value: null },
    whatsAppSuggestion: { value: null as WhatsAppSuggestion | null },
    whatsAppSuggestionError: { value: null as string | null },
    createProcessing: { value: false },
    pricingProcessing: { value: false },
    transitionProcessing: { value: false },
    paymentProcessing: { value: false },
    printer: {
        available: true,
        profile: { value: null },
        devices: { value: [] },
        discovering: { value: false },
        printing: { value: false },
        message: { value: null },
        error: { value: null },
        discover: vi.fn(),
        saveProfile: vi.fn(),
        clearProfile: vi.fn(),
        testPrint: vi.fn(),
    },
    paymentActions: {},
    load: vi.fn(),
    refresh: vi.fn(),
    selectBranch: vi.fn(),
    selectCustomer: vi.fn(),
    findNearestBranches: vi.fn(),
    quoteOrder: vi.fn(),
    confirmOrderPricing: vi.fn(),
    createOrder: vi.fn(),
    transitionOrder: vi.fn(),
    createCustomer: vi.fn(),
    updateCustomer: vi.fn(),
    resetCustomerPin: vi.fn(),
    searchOrders: vi.fn(),
    searchCustomerOrders: vi.fn(),
    createAddress: vi.fn(),
    updateAddress: vi.fn(),
    deleteAddress: vi.fn(),
    setDefaultAddress: vi.fn(),
    selectAddress: vi.fn(),
}));
const pushNotificationMocks = vi.hoisted(() => ({
    enable: vi.fn(),
    unregisterDevice: vi.fn(),
}));
const routerMocks = vi.hoisted(() => ({
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ template: '<span />' }),
    Link: defineComponent({
        inheritAttrs: false,
        props: {
            href: { type: [String, Object], required: true },
            method: { type: String, default: 'get' },
            as: { type: String, default: 'a' },
        },
        template: '<button type="button"><slot /></button>',
    }),
    useHttp: vi.fn(),
    router: routerMocks,
    usePage: () => ({
        props: {
            auth: {
                user: {
                    name: 'Admin Cabang',
                    email: 'admin@example.test',
                    whatsapp_phone: '628111222333',
                },
            },
        },
    }),
}));
vi.mock('@/composables/useMobileWorkspace', () => ({
    useMobileWorkspace: () => mobileWorkspace,
}));
vi.mock('@/composables/usePushNotifications', () => ({
    usePushNotifications: () => ({
        available: true,
        status: { value: 'enabled' },
        error: { value: null },
        enable: pushNotificationMocks.enable,
        unregisterDevice: pushNotificationMocks.unregisterDevice,
    }),
}));

const whatsAppDialogStub = defineComponent({
    name: 'WhatsAppSuggestionDialog',
    props: {
        open: Boolean,
        suggestion: { type: Object, default: null },
        loadError: { type: String, default: null },
    },
    emits: ['update:open'],
    template: '<section data-test="whatsapp-dialog" />',
});
const dialogStub = defineComponent({
    name: 'DialogStub',
    props: { open: Boolean },
    emits: ['update:open'],
    template: '<div v-if="open"><slot /></div>',
});
const passThroughStub = defineComponent({
    template: '<div><slot /></div>',
});

const mountMobileApp = (): VueWrapper =>
    mount(MobileApp, {
        global: {
            stubs: {
                BranchExplorer: true,
                CustomerCredentialDialog: true,
                CustomerAddressDialog: true,
                CustomerFormDialog: true,
                CustomerResetPinDialog: true,
                Dialog: dialogStub,
                DialogContent: passThroughStub,
                DialogClose: passThroughStub,
                DialogDescription: passThroughStub,
                DialogHeader: passThroughStub,
                DialogTitle: passThroughStub,
                MobileAppUpdateSettings: true,
                MobileWorkspaceSwitcher: true,
                WhatsAppSuggestionDialog: whatsAppDialogStub,
            },
        },
    });

const selectTab = async (wrapper: VueWrapper, tab: MobileTab): Promise<void> => {
    await wrapper.get(`[data-test="mobile-tab-${tab}"]`).trigger('click');
};

const expectNoAccountContent = (wrapper: VueWrapper): void => {
    expect(wrapper.findComponent(MobileAccount).exists()).toBe(false);
    expect(wrapper.find('#mobile-account-title').exists()).toBe(false);
    expect(wrapper.find('#printer-settings-title').exists()).toBe(false);
    expect(wrapper.find('[data-test="discover-printers"]').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('Admin Cabang');
    expect(wrapper.text()).not.toContain('admin@example.test');
    expect(wrapper.text()).not.toContain('628111222333');
    expect(wrapper.text()).not.toContain('Nomor WhatsApp');
    expect(wrapper.text()).not.toContain('Organisasi aktif');
    expect(wrapper.text()).not.toContain('Printer thermal');
    expect(wrapper.text()).not.toContain('Cari printer');
    expect(wrapper.text()).not.toContain('Keluar');
};

const suggestion: WhatsAppSuggestion = {
    available: true,
    phone: '6281234567890',
    message: 'LaundryHub Demo\nPesanan ORD-0042 milik Budi sudah siap diambil di Cabang Kemang.',
    url: 'https://wa.me/6281234567890?text=preserved-server-template',
    template_id: 12,
    unavailable_reason: null,
};

beforeEach(() => {
    mobileWorkspace.context.value = {
        role: 'branch_admin',
        organization: { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
        organizations: [],
        branches: [],
    };
    mobileWorkspace.whatsAppSuggestion.value = null;
    mobileWorkspace.whatsAppSuggestionError.value = null;
    mobileWorkspace.orders.value = [];
    mobileWorkspace.addresses.value = [];
    mobileWorkspace.customers.value = [];
    mobileWorkspace.nearestBranches.value = [];
    mobileWorkspace.deliveryCoordinates.value = null;
    mobileWorkspace.services.value = [];
    mobileWorkspace.selectedBranch.value = null;
    mobileWorkspace.selectedBranchId.value = null;
    mobileWorkspace.selectedAddress.value = null;
    mobileWorkspace.selectedAddressId.value = null;
    mobileWorkspace.selectedCustomerId.value = null;
    mobileWorkspace.refreshing.value = false;
    mobileWorkspace.requestError.value = null;
    mobileWorkspace.rollbackMessage.value = null;
    mobileWorkspace.queueLoading.value = false;
    mobileWorkspace.customerOrderLoading.value = false;
    mobileWorkspace.createProcessing.value = false;
    mobileWorkspace.pricingProcessing.value = false;
    mobileWorkspace.transitionProcessing.value = false;
    mobileWorkspace.paymentProcessing.value = false;
    mobileWorkspace.searchOrders.mockResolvedValue(undefined);
    mobileWorkspace.searchCustomerOrders.mockResolvedValue(undefined);
    mobileWorkspace.createOrder.mockResolvedValue(false);
    mobileWorkspace.refresh.mockResolvedValue(true);
    pushNotificationMocks.unregisterDevice.mockResolvedValue(undefined);
});

afterEach(() => {
    document.body.innerHTML = '';
    vi.useRealTimers();
    vi.clearAllMocks();
});

describe('customer mobile navigation', () => {
    it('filters customer orders by order, branch, service, and inclusive dates', async () => {
        vi.useFakeTimers();
        const matchingOrder = {
            ...makeOrder(42),
            order_number: 'LDR-TARGET-0042',
            branch: {
                ...makeOrder(42).branch,
                name: 'Cabang Kemang',
                code: 'KMG01',
            },
            items: [
                {
                    ...makeOrder(42).items[0],
                    id: 99,
                    service: { id: 2, name: 'Cuci Kering Lipat', code: 'CKL' },
                    price_option: {
                        id: 31,
                        name: 'Reguler',
                        type: 'per_kilogram' as const,
                        pricing_snapshot: {
                            package_weight_grams: null,
                            base_price_rupiah: null,
                            price_per_kilogram_rupiah: 12000,
                            minimum_chargeable_grams: 1000,
                        },
                    },
                    calculation: {
                        requested_weight_grams: 1000,
                        chargeable_weight_grams: 1000,
                        base_price_rupiah: 12000,
                        excess_weight_grams: 0,
                        excess_price_rupiah: 0,
                        subtotal_rupiah: 12000,
                    },
                },
            ],
            created_at: '2026-09-10T23:59:59+07:00',
        };
        mobileWorkspace.context.value = {
            role: 'customer',
            organization: { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
            organizations: [],
            branches: [],
        };
        mobileWorkspace.orders.value = [matchingOrder];
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'orders');
        await wrapper.get('[data-test="customer-order-search"]').setValue('  CKL  ');
        await wrapper.get('[data-test="customer-order-date-from"]').setValue('2026-09-10');
        await wrapper.get('[data-test="customer-order-date-to"]').setValue('2026-09-10');

        expect(wrapper.text()).toContain('LDR-TARGET-0042');
        await vi.advanceTimersByTimeAsync(300);
        expect(mobileWorkspace.searchCustomerOrders).toHaveBeenLastCalledWith({
            search: 'CKL',
            date_from: '2026-09-10',
            date_to: '2026-09-10',
        });
    });

    it('refreshes the customer workspace from the global header action', async () => {
        mobileWorkspace.context.value = {
            role: 'customer',
            organization: { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
            organizations: [],
            branches: [],
        };
        const wrapper = mountMobileApp();

        await wrapper.get('[data-test="refresh-mobile-app"]').trigger('click');
        await flushPromises();

        expect(mobileWorkspace.refresh).toHaveBeenCalledOnce();
    });

    it('shows the exact Home shortcut and opens the branch list', async () => {
        mobileWorkspace.context.value = {
            role: 'customer',
            organization: { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
            organizations: [],
            branches: [],
        };
        const wrapper = mountMobileApp();

        const shortcut = wrapper.get('[data-test="view-branches-shortcut"]');

        expect(shortcut.text()).toBe('Lihat daftar cabang');
        await shortcut.trigger('click');
        expect(wrapper.find('branch-explorer-stub').exists()).toBe(true);
    });
});

describe('branch-admin mobile tab rendering', () => {
    it('disables the global refresh action while data is being refreshed', () => {
        mobileWorkspace.refreshing.value = true;
        const wrapper = mountMobileApp();
        const refreshButton = wrapper.get<HTMLButtonElement>('[data-test="refresh-mobile-app"]');

        expect(refreshButton.element.disabled).toBe(true);
        expect(refreshButton.get('svg').classes()).toContain('animate-spin');
    });

    it('reapplies the active queue search after a successful global refresh', async () => {
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'queue');
        await wrapper.get('[data-test="queue-search"]').setValue('  Waki  ');
        await wrapper.get('[data-test="refresh-mobile-app"]').trigger('click');
        await flushPromises();

        expect(mobileWorkspace.refresh).toHaveBeenCalledOnce();
        expect(mobileWorkspace.searchOrders).toHaveBeenCalledWith('Waki');
    });

    it('unregisters push notifications before logging out', async () => {
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'more');
        await wrapper.get('[data-test="logout-mobile"]').trigger('click');
        await flushPromises();

        expect(pushNotificationMocks.unregisterDevice).toHaveBeenCalledOnce();
        expect(routerMocks.post).toHaveBeenCalledOnce();
        expect(pushNotificationMocks.unregisterDevice.mock.invocationCallOrder[0]).toBeLessThan(
            routerMocks.post.mock.invocationCallOrder[0] ?? Number.MAX_SAFE_INTEGER,
        );
    });

    it('never renders account, device, or logout content on Queue without a WhatsApp suggestion', async () => {
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'queue');

        expect(wrapper.findComponent(OrderListPanel).exists()).toBe(true);
        expect(wrapper.get('#order-list-title').text()).toBe('Antrean cabang');
        expect(wrapper.text()).toContain('Belum ada pesanan');
        expect(wrapper.findComponent(OrderComposer).exists()).toBe(false);
        expect(wrapper.findComponent(CustomerDirectory).exists()).toBe(false);
        expectNoAccountContent(wrapper);
    });

    it('opens the real order composer from Queue without account leakage', async () => {
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'queue');
        await wrapper.get('[data-test="open-order-composer"]').trigger('click');

        expect(wrapper.findComponent(OrderListPanel).exists()).toBe(true);
        expect(wrapper.findComponent(OrderComposer).exists()).toBe(true);
        expect(wrapper.get('#new-order-title').text()).toBe('Catat laundry masuk');
        expect(wrapper.find('[data-test="customer-select"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Layanan laundry');
        expect(wrapper.text()).toContain('Berat laundry');
        expect(wrapper.text()).toContain('Rincian harga server');
        expect(wrapper.findComponent(CustomerDirectory).exists()).toBe(false);
        expectNoAccountContent(wrapper);
    });

    it('shows a visible close action on the branch-admin order modal', async () => {
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'queue');
        await wrapper.get('[data-test="open-order-composer"]').trigger('click');

        const closeButton = wrapper.get('[data-test="close-order-composer"]');

        expect(closeButton.text()).toContain('Tutup');
        await closeButton.trigger('click');
        expect(wrapper.findComponent(OrderComposer).exists()).toBe(false);
    });

    it('closes the new-order dialog after the order is created', async () => {
        mobileWorkspace.createOrder.mockResolvedValue(true);
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'queue');
        await wrapper.get('[data-test="open-order-composer"]').trigger('click');
        wrapper.findComponent(OrderComposer).vm.$emit(
            'submit',
            {
                customer_id: 21,
                items: [{ price_option_id: 31, weight_grams: 1000 }],
            },
            null,
        );
        await flushPromises();

        expect(mobileWorkspace.createOrder).toHaveBeenCalledOnce();
        expect(wrapper.findComponent(OrderComposer).exists()).toBe(false);
    });

    it('keeps the new-order dialog open when creation fails', async () => {
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'queue');
        await wrapper.get('[data-test="open-order-composer"]').trigger('click');
        wrapper.findComponent(OrderComposer).vm.$emit(
            'submit',
            {
                customer_id: 21,
                items: [{ price_option_id: 31, weight_grams: 1000 }],
            },
            null,
        );
        await flushPromises();

        expect(mobileWorkspace.createOrder).toHaveBeenCalledOnce();
        expect(wrapper.findComponent(OrderComposer).exists()).toBe(true);
    });

    it('debounces and trims queue search requests', async () => {
        vi.useFakeTimers();
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'queue');
        await wrapper.get('[data-test="queue-search"]').setValue('  Waki  ');
        await vi.advanceTimersByTimeAsync(299);
        expect(mobileWorkspace.searchOrders).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        expect(mobileWorkspace.searchOrders).toHaveBeenCalledWith('Waki');
    });

    it('clears queue search immediately and renders the filtered empty state', async () => {
        vi.useFakeTimers();
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'queue');
        await wrapper.get('[data-test="queue-search"]').setValue('Tidak ada');

        expect(wrapper.text()).toContain('Pesanan tidak ditemukan');
        await wrapper.get('[data-test="clear-queue-search"]').trigger('click');

        expect(mobileWorkspace.searchOrders).toHaveBeenCalledWith('');
        expect(wrapper.text()).toContain('Belum ada pesanan');
        await vi.advanceTimersByTimeAsync(300);
        expect(mobileWorkspace.searchOrders).toHaveBeenCalledTimes(1);
    });

    it('renders the real customer directory on Customers without account leakage', async () => {
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'customers');

        expect(wrapper.findComponent(OrderListPanel).exists()).toBe(false);
        expect(wrapper.findComponent(OrderComposer).exists()).toBe(false);
        expect(wrapper.findComponent(CustomerDirectory).exists()).toBe(true);
        expect(wrapper.get('#customer-directory-title').text()).toBe('Direktori pelanggan');
        expect(wrapper.text()).toContain('Belum ada pelanggan yang tersedia untuk organisasi ini.');
        expect(wrapper.find('[data-test="create-customer-from-directory"]').exists()).toBe(true);
        expectNoAccountContent(wrapper);
    });

    it('renders the real account, organization, printer, and logout content only on More', async () => {
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'more');

        expect(wrapper.findComponent(OrderListPanel).exists()).toBe(false);
        expect(wrapper.findComponent(OrderComposer).exists()).toBe(false);
        expect(wrapper.findComponent(CustomerDirectory).exists()).toBe(false);
        expect(wrapper.findComponent(MobileAccount).exists()).toBe(true);
        expect(wrapper.get('#mobile-account-title').text()).toBe('Admin Cabang');
        expect(wrapper.text()).toContain('admin@example.test');
        expect(wrapper.text()).toContain('628111222333');
        expect(wrapper.text()).toContain('Nomor WhatsApp');
        expect(wrapper.text()).toContain('Organisasi aktif');
        expect(wrapper.text()).toContain('LaundryHub Demo');
        expect(wrapper.get('#printer-settings-title').text()).toBe('Printer thermal');
        expect(wrapper.get('[data-test="discover-printers"]').text()).toContain('Cari printer');
        expect(wrapper.text()).toContain('Keluar');
    });

    it('keeps the normalized WhatsApp destination and complete server template on Queue', async () => {
        mobileWorkspace.whatsAppSuggestion.value = suggestion;
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'queue');

        expect(wrapper.findComponent(OrderListPanel).exists()).toBe(true);
        expectNoAccountContent(wrapper);
        expect(wrapper.get('[data-test="reopen-whatsapp-suggestion"]').text()).toContain(
            'Buka preview WhatsApp terakhir',
        );
        expect(wrapper.findComponent(whatsAppDialogStub).props('suggestion')).toEqual(suggestion);

        await wrapper.get('[data-test="reopen-whatsapp-suggestion"]').trigger('click');

        expect(wrapper.findComponent(whatsAppDialogStub).props('open')).toBe(true);
    });
});
