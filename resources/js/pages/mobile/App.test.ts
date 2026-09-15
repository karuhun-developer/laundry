import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import CustomerDirectory from '@/components/mobile/organisms/CustomerDirectory.vue';
import MobileAccount from '@/components/mobile/organisms/MobileAccount.vue';
import OrderComposer from '@/components/mobile/organisms/OrderComposer.vue';
import OrderListPanel from '@/components/mobile/organisms/OrderListPanel.vue';
import MobileApp from '@/pages/mobile/App.vue';
import type { MobileTab, WhatsAppSuggestion } from '@/types/mobile';

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
    requestError: { value: null },
    rollbackMessage: { value: null },
    queueLoading: { value: false },
    orders: { value: [] },
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
    createAddress: vi.fn(),
    updateAddress: vi.fn(),
    deleteAddress: vi.fn(),
    setDefaultAddress: vi.fn(),
    selectAddress: vi.fn(),
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
                DialogDescription: passThroughStub,
                DialogHeader: passThroughStub,
                DialogTitle: passThroughStub,
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
});

afterEach(() => {
    document.body.innerHTML = '';
    vi.useRealTimers();
    vi.clearAllMocks();
});

describe('customer mobile navigation', () => {
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
