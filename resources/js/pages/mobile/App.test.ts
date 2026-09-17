import { DOMWrapper, flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import CustomerAddressDialog from '@/components/mobile/organisms/CustomerAddressDialog.vue';
import CustomerDirectory from '@/components/mobile/organisms/CustomerDirectory.vue';
import MobileAccount from '@/components/mobile/organisms/MobileAccount.vue';
import OrderComposer from '@/components/mobile/organisms/OrderComposer.vue';
import OrderListPanel from '@/components/mobile/organisms/OrderListPanel.vue';
import { makeOrder } from '@/composables/mobileOrderTestFixtures';
import MobileApp from '@/pages/mobile/App.vue';
import type { MobileOrder, MobileService, MobileTab, WhatsAppSuggestion } from '@/types/mobile';

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
    services: { value: [] as MobileService[] },
    selectedBranch: { value: null },
    selectedBranchId: { value: null },
    selectedBranchDeliveryFee: { value: 0 },
    selectedAddress: { value: null },
    selectedAddressId: { value: null },
    selectedCustomerId: { value: null },
    whatsAppSuggestion: { value: null as WhatsAppSuggestion | null },
    whatsAppSuggestionError: { value: null as string | null },
    loadStatusWhatsAppSuggestion: vi.fn(),
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
vi.mock('@/composables/useGeolocation', () => ({
    useGeolocation: () => ({
        coordinates: { value: null },
        error: { value: null },
        locating: { value: false },
        locate: vi.fn(async () => ({ latitude: -6.26, longitude: 106.81 })),
    }),
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

const mountedMobileApps: VueWrapper[] = [];

const mountMobileApp = (realDialogs = false): VueWrapper => {
    const wrapper = mount(MobileApp, {
        attachTo: realDialogs ? document.body : undefined,
        global: {
            stubs: {
                BranchExplorer: true,
                CustomerCredentialDialog: true,
                CustomerAddressDialog: !realDialogs,
                CustomerFormDialog: true,
                CustomerPinSettings: true,
                CustomerResetPinDialog: true,
                Dialog: realDialogs ? false : dialogStub,
                DialogContent: realDialogs ? false : passThroughStub,
                DialogClose: realDialogs ? false : passThroughStub,
                DialogDescription: realDialogs ? false : passThroughStub,
                DialogHeader: realDialogs ? false : passThroughStub,
                DialogTitle: realDialogs ? false : passThroughStub,
                LocationPicker: true,
                MobileAppUpdateSettings: true,
                MobileWorkspaceSwitcher: true,
                WhatsAppSuggestionDialog: whatsAppDialogStub,
            },
        },
    });

    mountedMobileApps.push(wrapper);

    return wrapper;
};

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
    for (const wrapper of mountedMobileApps.splice(0)) {
        wrapper.unmount();
    }

    document.body.innerHTML = '';
    vi.useRealTimers();
    vi.clearAllMocks();
});

describe('customer mobile navigation', () => {
    it('keeps order details out of Home while retaining all orders in the Orders shortcut', async () => {
        mobileWorkspace.context.value.role = 'customer';
        mobileWorkspace.orders.value = [makeOrder(42), makeOrder(43), makeOrder(44)];
        const wrapper = mountMobileApp();

        expect(wrapper.findComponent(OrderListPanel).exists()).toBe(false);
        for (const order of mobileWorkspace.orders.value) {
            expect(wrapper.text()).not.toContain(order.order_number);
        }

        await wrapper.get('[data-test="view-orders-shortcut"]').trigger('click');

        expect(wrapper.getComponent(OrderListPanel).props('orders')).toEqual(
            mobileWorkspace.orders.value,
        );
        expect(wrapper.get('[data-test="mobile-tab-orders"]').attributes('aria-current')).toBe(
            'page',
        );
    });

    it('opens customer addresses and account from Home shortcuts', async () => {
        mobileWorkspace.context.value.role = 'customer';
        const wrapper = mountMobileApp();

        await wrapper.get('[data-test="manage-addresses-shortcut"]').trigger('click');
        expect(wrapper.getComponent(CustomerAddressDialog).props('open')).toBe(true);
        wrapper.getComponent(CustomerAddressDialog).vm.$emit('update:open', false);
        await flushPromises();
        await wrapper.get('[data-test="view-account-shortcut"]').trigger('click');

        expect(wrapper.getComponent(MobileAccount).props('role')).toBe('customer');
        expect(wrapper.get('[data-test="mobile-tab-account"]').attributes('aria-current')).toBe(
            'page',
        );
    });

    it.each(['home', 'orders'] as const)(
        'opens the same customer composer from %s and keeps the tab after creation',
        async (tab) => {
            mobileWorkspace.context.value.role = 'customer';
            mobileWorkspace.createOrder.mockResolvedValue(true);
            const wrapper = mountMobileApp();
            await selectTab(wrapper, tab);
            const trigger =
                tab === 'home'
                    ? 'open-customer-order-composer'
                    : 'open-customer-order-composer-orders';

            await wrapper.get(`[data-test="${trigger}"]`).trigger('click');
            const composer = wrapper.getComponent(OrderComposer);
            expect(composer.props('role')).toBe('customer');
            const payload = { items: [{ price_option_id: 31 }], customer_address_id: 41 };
            composer.vm.$emit('submit', payload, null);
            await flushPromises();

            expect(mobileWorkspace.createOrder).toHaveBeenCalledWith(payload, null);
            expect(wrapper.findComponent(OrderComposer).exists()).toBe(false);
            expect(wrapper.get(`[data-test="mobile-tab-${tab}"]`).attributes('aria-current')).toBe(
                'page',
            );
        },
    );

    it('keeps filtered empty orders and the composer accessible when creation fails', async () => {
        mobileWorkspace.context.value.role = 'customer';
        const wrapper = mountMobileApp();
        await selectTab(wrapper, 'orders');
        await wrapper.get('[data-test="customer-order-search"]').setValue('Tidak ada');

        await wrapper.get('[data-test="open-customer-order-composer-orders"]').trigger('click');
        wrapper.getComponent(OrderComposer).vm.$emit(
            'submit',
            {
                items: [{ price_option_id: 31 }],
                customer_address_id: 41,
            },
            null,
        );
        await flushPromises();

        expect(wrapper.findComponent(OrderComposer).exists()).toBe(true);
        await wrapper.get('[data-test="close-order-composer"]').trigger('click');
        expect(
            wrapper.get<HTMLInputElement>('[data-test="customer-order-search"]').element.value,
        ).toBe('Tidak ada');
        expect(wrapper.text()).toContain('Pesanan tidak ditemukan');
        expect(wrapper.get('[data-test="mobile-tab-orders"]').attributes('aria-current')).toBe(
            'page',
        );
    });

    it('layers the address form above the composer, retains input on failure, and restores focus on Escape', async () => {
        mobileWorkspace.context.value.role = 'customer';
        mobileWorkspace.createAddress.mockRejectedValueOnce(new Error('Alamat belum lengkap.'));
        mobileWorkspace.services.value = [
            {
                id: 2,
                name: 'Cuci lipat',
                code: 'CL',
                description: null,
                price_options: [
                    {
                        id: 31,
                        name: 'Reguler',
                        type: 'fixed_package',
                        pricing: {
                            package_weight_grams: 1000,
                            base_price_rupiah: 12000,
                            excess_price_per_kilogram_rupiah: 5000,
                        },
                    },
                ],
            },
        ];
        const wrapper = mountMobileApp(true);
        const body = new DOMWrapper(document.body);
        await wrapper.get('[data-test="open-customer-order-composer"]').trigger('click');
        await flushPromises();
        const composer = wrapper.getComponent(OrderComposer);
        await composer.get('select').setValue('31');
        const addressButton = composer.get<HTMLButtonElement>(
            '[data-test="manage-pickup-address"]',
        );
        addressButton.element.focus();

        await addressButton.trigger('click');
        await flushPromises();
        const nestedDialog = body.get('[role="dialog"][data-dialog-layer="nested"]');
        expect(nestedDialog.classes()).toContain('z-61');
        expect(
            body
                .findAll('[data-slot="dialog-overlay"]')
                .some((overlay) => overlay.classes().includes('z-60')),
        ).toBe(true);
        await nestedDialog.get('[data-test="add-address"]').trigger('click');
        await flushPromises();
        await nestedDialog.get('#address-text').setValue('Jl. Melati No. 10');
        await nestedDialog.get('[data-test="address-form"]').trigger('submit');
        await flushPromises();

        expect(mobileWorkspace.createAddress).toHaveBeenCalledOnce();
        expect(nestedDialog.text()).toContain('Alamat belum lengkap.');
        expect(nestedDialog.get<HTMLTextAreaElement>('#address-text').element.value).toBe(
            'Jl. Melati No. 10',
        );
        expect(composer.get<HTMLSelectElement>('select').element.value).toBe('31');
        await vi.waitFor(() =>
            expect(nestedDialog.element.contains(document.activeElement)).toBe(true),
        );
        document.activeElement?.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
        );

        await vi.waitFor(() => {
            expect(body.find('[role="dialog"][data-dialog-layer="nested"]').exists()).toBe(false);
            expect(document.activeElement).toBe(addressButton.element);
        });
        expect(
            wrapper.getComponent(OrderComposer).get<HTMLSelectElement>('select').element.value,
        ).toBe('31');
        expect(mobileWorkspace.createOrder).not.toHaveBeenCalled();
    });

    it('searches customer orders without rendering date filters', async () => {
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

        expect(wrapper.text()).toContain('LDR-TARGET-0042');
        expect(wrapper.find('[data-test="customer-order-date-from"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="customer-order-date-to"]').exists()).toBe(false);
        await vi.advanceTimersByTimeAsync(300);
        expect(mobileWorkspace.searchCustomerOrders).toHaveBeenLastCalledWith({
            search: 'CKL',
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

    it('renders the real account, organization, printer, and logout content only on Akun', async () => {
        const wrapper = mountMobileApp();

        await selectTab(wrapper, 'more');

        expect(wrapper.get('[data-test="mobile-tab-more"]').text()).toBe('Akun');
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
        expect(wrapper.getComponent(OrderListPanel).props('loadStatusWhatsAppSuggestion')).toBe(
            mobileWorkspace.loadStatusWhatsAppSuggestion,
        );
        expectNoAccountContent(wrapper);
        expect(wrapper.get('[data-test="reopen-whatsapp-suggestion"]').text()).toContain(
            'Buka preview WhatsApp terakhir',
        );
        expect(wrapper.findComponent(whatsAppDialogStub).props('suggestion')).toEqual(suggestion);

        await wrapper.get('[data-test="reopen-whatsapp-suggestion"]').trigger('click');

        expect(wrapper.findComponent(whatsAppDialogStub).props('open')).toBe(true);
    });
});
