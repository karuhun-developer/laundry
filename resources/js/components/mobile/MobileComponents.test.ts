import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import MobileBottomNav from '@/components/mobile/molecules/MobileBottomNav.vue';
import OrderTicket from '@/components/mobile/molecules/OrderTicket.vue';
import CustomerDirectory from '@/components/mobile/organisms/CustomerDirectory.vue';
import OrderComposer from '@/components/mobile/organisms/OrderComposer.vue';
import OrderListPanel from '@/components/mobile/organisms/OrderListPanel.vue';
import type {
    MobileBranch,
    MobileCustomer,
    MobileCustomerAddress,
    MobileOrder,
    MobilePaymentActions,
    MobilePriceOption,
    MobilePriceQuote,
    MobileService,
    WhatsAppSuggestion,
} from '@/types/mobile';

const paymentActions: MobilePaymentActions = {
    refreshOrder: vi.fn(async () => makeOrder()),
    createPayment: vi.fn(async () => true),
    loadPaymentWhatsAppSuggestion: vi.fn(
        async (): Promise<WhatsAppSuggestion> => ({
            available: false,
            phone: null,
            message: null,
            url: null,
            template_id: null,
            unavailable_reason: 'Nomor WhatsApp cabang belum tersedia atau tidak valid.',
        }),
    ),
    loadPaymentGatewayMethods: vi.fn(async () => []),
    transitionPaymentStatus: vi.fn(async () => true),
    loadReceipt: vi.fn(async () => {
        throw new Error('Not used in this test.');
    }),
    printReceipt: vi.fn(async () => false),
};

const branch: MobileBranch = {
    id: 7,
    name: 'Cabang Kemang',
    code: 'KMG01',
    phone: null,
    address: 'Jl. Kemang Raya',
    organization: { id: 1, slug: 'laundryhub-demo' },
    city: { name: 'Jakarta Selatan', province: 'DKI Jakarta' },
    coordinates: { latitude: -6.2607, longitude: 106.8136 },
    delivery: { free_radius_meters: 10000, fee_per_km_rupiah: 3000 },
    payments: { qris_enabled: true },
};

const packagePriceOption: MobilePriceOption = {
    id: 5,
    name: 'Paket 12 kg',
    type: 'fixed_package',
    pricing: {
        package_weight_grams: 12000,
        base_price_rupiah: 12000,
        excess_price_per_kilogram_rupiah: 5000,
    },
};

const services: MobileService[] = [
    {
        id: 2,
        name: 'Cuci Kering Lipat',
        code: 'CKL',
        description: null,
        price_options: [packagePriceOption],
    },
];

const customers: MobileCustomer[] = [
    {
        id: 9,
        name: 'Customer Demo',
        email: 'customer@laundryhub.test',
        whatsapp_phone: '6281234567890',
        must_change_pin: false,
        pin_expires_at: null,
    },
];

const customerAddress: MobileCustomerAddress = {
    id: 41,
    label: 'Rumah',
    recipient_name: 'Customer Demo',
    whatsapp_phone: '6281234567890',
    address: 'Jl. Kemang Raya No. 10',
    notes: null,
    latitude: -6.2607,
    longitude: 106.8136,
    is_default: true,
    created_at: '2026-09-15T08:00:00+00:00',
    updated_at: '2026-09-15T08:00:00+00:00',
};

const quote: MobilePriceQuote = {
    price_option: packagePriceOption,
    requested_weight_grams: 12500,
    chargeable_weight_grams: 12500,
    base_price_rupiah: 12000,
    excess_weight_grams: 500,
    excess_price_rupiah: 2500,
    total_rupiah: 14500,
};

const makeOrder = (): MobileOrder => ({
    id: 21,
    order_number: 'LDR-20260913-00000021',
    status: 'washing',
    organization_id: 1,
    organization: { id: 1, slug: 'laundryhub-demo' },
    branch: { id: 7, name: 'Cabang Kemang', code: 'KMG01', qris_enabled: true },
    customer: { id: 9, name: 'Customer Demo' },
    items: [
        {
            id: 31,
            service: { id: 2, name: 'Cuci Kering', code: 'CK' },
            price_option: {
                id: 5,
                name: 'Kiloan reguler',
                type: 'per_kilogram',
                pricing_snapshot: {
                    package_weight_grams: null,
                    base_price_rupiah: null,
                    price_per_kilogram_rupiah: 12000,
                    minimum_chargeable_grams: 1000,
                },
            },
            calculation: {
                requested_weight_grams: 2500,
                chargeable_weight_grams: 2500,
                base_price_rupiah: 30000,
                excess_weight_grams: 0,
                excess_price_rupiah: 0,
                subtotal_rupiah: 30000,
            },
        },
    ],
    pricing: {
        confirmed: true,
        confirmed_at: '2026-09-13T08:00:00.000Z',
        items_subtotal_rupiah: 30000,
        delivery_fee_rupiah: 0,
        total_rupiah: 30000,
    },
    delivery: null,
    status_history: [
        {
            id: 1,
            status: 'received',
            actor: { id: 3, name: 'Admin Kemang' },
            note: null,
            occurred_at: '2026-09-13T08:00:00.000Z',
        },
        {
            id: 2,
            status: 'washing',
            actor: { id: 3, name: 'Admin Kemang' },
            note: 'Masuk mesin satu',
            occurred_at: '2026-09-13T08:30:00.000Z',
        },
    ],
    payments: [],
    payment_summary: { paid_rupiah: 0, outstanding_rupiah: 30000, is_paid: false },
    created_at: '2026-09-13T08:00:00.000Z',
});

describe('mobile role-aware components', () => {
    it('searches a bounded organization customer list in the branch-admin order selector', async () => {
        const largeCustomerList: MobileCustomer[] = Array.from({ length: 1000 }, (_, index) => ({
            id: index + 1,
            name: index === 776 ? 'Target Pelanggan' : `Pelanggan ${index + 1}`,
            email: index === 776 ? 'target@example.test' : `customer${index + 1}@example.test`,
            whatsapp_phone: index === 776 ? '628777000777' : `62812000${index + 1}`,
            must_change_pin: false,
            pin_expires_at: null,
        }));
        const wrapper = mount(OrderComposer, {
            props: {
                role: 'branch_admin',
                branch,
                services,
                customers: largeCustomerList,
                deliveryCoordinates: null,
                quoteOrder: vi.fn(async () => quote),
            },
        });
        const search = wrapper.get<HTMLInputElement>('[data-test="customer-search"]');

        await search.trigger('focus');
        expect(wrapper.findAll('[role="option"]')).toHaveLength(50);

        await search.setValue('628777000777');
        expect(wrapper.findAll('[role="option"]')).toHaveLength(1);
        expect(wrapper.get('[data-test="customer-option-777"]').text()).toContain(
            'Target Pelanggan',
        );

        await wrapper.get('[data-test="customer-option-777"]').trigger('click');
        expect(wrapper.emitted('update:selectedCustomerId')?.at(-1)).toEqual([777]);

        await search.trigger('focus');
        await search.trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('keeps delivery selection staff-only and removes the customer location toggle', () => {
        const customerWrapper = mount(OrderComposer, {
            props: {
                role: 'customer',
                branch,
                services,
                customers: [],
                deliveryCoordinates: { latitude: -6.2607, longitude: 106.8136 },
                selectedAddress: customerAddress,
                quoteOrder: vi.fn(async () => quote),
            },
        });
        const staffWrapper = mount(OrderComposer, {
            props: {
                role: 'branch_admin',
                branch,
                services,
                customers,
                deliveryCoordinates: { latitude: -6.2607, longitude: 106.8136 },
                quoteOrder: vi.fn(async () => quote),
            },
        });

        expect(customerWrapper.text()).not.toContain('Gunakan lokasi saya untuk antar-jemput');
        expect(staffWrapper.text()).toContain('Tambahkan antar-jemput untuk pesanan ini');
    });

    it('explains the duplicate pickup with the active order number', () => {
        const wrapper = mount(OrderComposer, {
            props: {
                role: 'customer',
                branch,
                services,
                customers: [],
                deliveryCoordinates: { latitude: -6.2607, longitude: 106.8136 },
                selectedAddress: customerAddress,
                activePickupOrderNumber: 'LDR-20260915-00000088',
                quoteOrder: vi.fn(async () => quote),
            },
        });

        expect(wrapper.get('[data-test="active-pickup-notice"]').text()).toContain(
            'Penjemputan LDR-20260915-00000088 masih menunggu diproses.',
        );
        expect(wrapper.get('[data-test="active-pickup-notice"]').text()).toContain(
            'Buat pesanan berikutnya setelah penjemputan ini dilanjutkan petugas.',
        );
    });

    it('renders the existing customer directory details', () => {
        const wrapper = mount(CustomerDirectory, { props: { customers } });

        expect(wrapper.text()).toContain('Direktori pelanggan');
        expect(wrapper.text()).toContain('Customer Demo');
        expect(wrapper.text()).toContain('customer@laundryhub.test');
    });

    it('shows customer navigation without branch-admin actions', () => {
        const wrapper = mount(MobileBottomNav, {
            props: { role: 'customer', modelValue: 'home' },
        });

        expect(wrapper.text()).toContain('Beranda');
        expect(wrapper.text()).toContain('Pesanan');
        expect(wrapper.text()).not.toContain('Antrean');
        expect(wrapper.find('[data-test="mobile-tab-new-order"]').exists()).toBe(false);
    });

    it('shows branch-admin operational navigation', () => {
        const wrapper = mount(MobileBottomNav, {
            props: { role: 'branch_admin', modelValue: 'queue' },
        });

        expect(wrapper.text()).toContain('Antrean');
        expect(wrapper.text()).toContain('Pelanggan');
        expect(wrapper.text()).toContain('Lainnya');
        expect(wrapper.find('[data-test="mobile-tab-new-order"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="mobile-tab-home"]').exists()).toBe(false);
    });

    it('keeps customer order tracking read-only', () => {
        const wrapper = mount(OrderTicket, {
            props: { order: makeOrder(), role: 'customer' },
        });

        expect(wrapper.text()).toContain('Dicuci');
        expect(wrapper.text()).toContain('Lihat perjalanan laundry');
        expect(wrapper.text()).toContain('LDR-20260913-00000021');
        expect(wrapper.find('h3').classes()).not.toContain('break-all');
        expect(wrapper.text()).toContain('Ditimbang');
        expect(wrapper.text()).toContain('Dikeringkan');
        expect(wrapper.find('[data-timeline-state="current"]').text()).toContain('Dicuci');
        expect(wrapper.findAll('[data-timeline-state="future"]')).toHaveLength(4);
        expect(wrapper.find('.order-ticket').attributes('data-pending')).toBe('false');
        expect(wrapper.find('[data-test="status-controls"]').exists()).toBe(false);
    });

    it('shows dynamic QRIS access on a priced customer order', () => {
        const wrapper = mount(OrderTicket, {
            props: { order: makeOrder(), role: 'customer', paymentActions },
        });

        expect(wrapper.find('[data-test="open-payment-dialog"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="payment-status-badge"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="open-payment-dialog"]').text()).toContain(
            'Bayar dengan QRIS',
        );
    });

    it('shows branch payment status beside laundry status', () => {
        const unpaidWrapper = mount(OrderTicket, {
            props: { order: makeOrder(), role: 'branch_admin', paymentActions },
        });
        const paidOrder = makeOrder();
        paidOrder.payment_summary = {
            paid_rupiah: paidOrder.pricing.total_rupiah,
            outstanding_rupiah: 0,
            is_paid: true,
        };
        const paidWrapper = mount(OrderTicket, {
            props: { order: paidOrder, role: 'branch_admin', paymentActions },
        });

        expect(unpaidWrapper.get('[data-test="payment-status-badge"]').text()).toBe('Belum lunas');
        expect(paidWrapper.get('[data-test="payment-status-badge"]').text()).toBe('Lunas');
    });

    it('places payment actions directly below the payment summary', () => {
        const wrapper = mount(OrderTicket, {
            props: { order: makeOrder(), role: 'branch_admin', paymentActions },
        });
        const paymentSummary = wrapper.get('[data-test="payment-summary"]');
        const paymentActionsElement = wrapper.get('[data-test="payment-actions"]');

        expect(paymentSummary.element.nextElementSibling).toBe(paymentActionsElement.element);
    });

    it('limits optimistic animation to the pending marker', () => {
        const pendingOrder = { ...makeOrder(), pending: true };
        const wrapper = mount(OrderTicket, {
            props: { order: pendingOrder, role: 'branch_admin' },
        });

        expect(wrapper.find('.order-ticket').classes()).not.toContain('opacity-75');
        expect(wrapper.find('.animate-pulse').exists()).toBe(true);
    });

    it('replaces controls with a summary for terminal orders', () => {
        const completedOrder: MobileOrder = { ...makeOrder(), status: 'completed' };
        const wrapper = mount(OrderTicket, {
            props: { order: completedOrder, role: 'branch_admin' },
        });

        expect(wrapper.find('[data-test="terminal-summary"]').text()).toContain('sudah ditutup');
        expect(wrapper.find('[data-test="status-controls"]').exists()).toBe(false);
    });

    it('creates a customer pickup request without weight or a final price', async () => {
        const wrapper = mount(OrderComposer, {
            props: {
                role: 'customer',
                branch,
                services,
                customers: [],
                deliveryCoordinates: { latitude: -6.2607, longitude: 106.8136 },
                selectedAddress: customerAddress,
                quoteOrder: vi.fn(async () => quote),
            },
        });

        await wrapper.find('select').setValue('5');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.find('input[type="number"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Berat aktual dan total harga akan muncul');
        expect(wrapper.text()).not.toContain('Rincian harga server');
        expect(wrapper.emitted('submit')?.[0]).toEqual([
            {
                items: [{ price_option_id: 5 }],
                customer_address_id: 41,
            },
            null,
        ]);
    });

    it('emits only one pickup request for rapid duplicate submission', async () => {
        const wrapper = mount(OrderComposer, {
            props: {
                role: 'customer',
                branch,
                services,
                customers: [],
                deliveryCoordinates: { latitude: -6.2607, longitude: 106.8136 },
                selectedAddress: customerAddress,
                quoteOrder: vi.fn(async () => quote),
            },
        });
        const form = wrapper.get('form');

        await Promise.all([form.trigger('submit'), form.trigger('submit')]);

        expect(wrapper.emitted('submit')).toHaveLength(1);
    });

    it('requires a pickup location before a customer can submit', async () => {
        const wrapper = mount(OrderComposer, {
            props: {
                role: 'customer',
                branch,
                services,
                customers: [],
                deliveryCoordinates: null,
                quoteOrder: vi.fn(async () => quote),
            },
        });

        await wrapper.find('select').setValue('5');

        expect(wrapper.text()).toContain('Alamat penjemputan belum dipilih');
        expect(wrapper.text()).toContain('Tambah alamat penjemputan');
        expect(wrapper.find<HTMLButtonElement>('[data-test="create-order"]').element.disabled).toBe(
            true,
        );
    });

    it('keeps server pricing for branch-admin counter orders', async () => {
        const wrapper = mount(OrderComposer, {
            props: {
                role: 'branch_admin',
                branch,
                services,
                customers,
                deliveryCoordinates: null,
                quoteOrder: vi.fn(async () => quote),
            },
        });

        await wrapper.get('[data-test="customer-search"]').trigger('focus');
        await wrapper.get('[data-test="customer-option-9"]').trigger('click');
        await wrapper.find('select').setValue('5');
        const weightInput = wrapper.find<HTMLInputElement>('input[type="number"]');
        await weightInput.setValue('12.5');

        expect(weightInput.element.validity.valid).toBe(true);
        await vi.waitFor(() => expect(wrapper.text()).toContain('Berat tertagih'));
        expect(wrapper.text()).toContain('Rp 14.500');
    });

    it('hides payment and total until a pickup request is priced', () => {
        const pickupOrder: MobileOrder = {
            ...makeOrder(),
            status: 'pickup_requested',
            pricing: {
                confirmed: false,
                confirmed_at: null,
                items_subtotal_rupiah: 0,
                delivery_fee_rupiah: 0,
                total_rupiah: 0,
            },
            payment_summary: { paid_rupiah: 0, outstanding_rupiah: 0, is_paid: false },
        };
        const wrapper = mount(OrderTicket, {
            props: { order: pickupOrder, role: 'customer' },
        });

        expect(wrapper.text()).toContain('Harga menunggu konfirmasi');
        expect(wrapper.text()).toContain('menunggu dijemput dan ditimbang');
        expect(wrapper.text()).toContain('QRIS dinamis tersedia setelah total harga dikonfirmasi');
        expect(wrapper.text()).not.toContain('Total');
        expect(wrapper.find('[data-test="open-payment-dialog"]').exists()).toBe(false);
    });

    it('lets a branch admin submit the actual weight for a pickup request', async () => {
        const pickupOrder: MobileOrder = {
            ...makeOrder(),
            status: 'pickup_requested',
            pricing: {
                confirmed: false,
                confirmed_at: null,
                items_subtotal_rupiah: 0,
                delivery_fee_rupiah: 0,
                total_rupiah: 0,
            },
            payment_summary: { paid_rupiah: 0, outstanding_rupiah: 0, is_paid: false },
        };
        const wrapper = mount(OrderTicket, {
            props: { order: pickupOrder, role: 'branch_admin' },
        });

        await wrapper.get<HTMLInputElement>('[data-test="actual-weight"]').setValue('3.5');
        await wrapper.get('[data-test="pricing-controls"]').trigger('submit');

        expect(wrapper.emitted('confirm-pricing')?.[0]).toEqual([
            21,
            { price_option_id: 5, weight_grams: 3500 },
        ]);
        expect(wrapper.find('[data-test="status-controls"]').exists()).toBe(false);
    });

    it('forwards actual-weight confirmation through the order list', async () => {
        const pickupOrder: MobileOrder = {
            ...makeOrder(),
            status: 'pickup_requested',
            pricing: {
                confirmed: false,
                confirmed_at: null,
                items_subtotal_rupiah: 0,
                delivery_fee_rupiah: 0,
                total_rupiah: 0,
            },
            payment_summary: { paid_rupiah: 0, outstanding_rupiah: 0, is_paid: false },
        };
        const wrapper = mount(OrderListPanel, {
            props: { orders: [pickupOrder], role: 'branch_admin' },
        });

        await wrapper.get<HTMLInputElement>('[data-test="actual-weight"]').setValue('3.5');
        await wrapper.get('[data-test="pricing-controls"]').trigger('submit');

        expect(wrapper.emitted('confirm-pricing')?.[0]).toEqual([
            21,
            { price_option_id: 5, weight_grams: 3500 },
        ]);
    });
});
