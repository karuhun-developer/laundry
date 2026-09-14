import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import MobileBottomNav from '@/components/mobile/molecules/MobileBottomNav.vue';
import OrderTicket from '@/components/mobile/molecules/OrderTicket.vue';
import OrderComposer from '@/components/mobile/organisms/OrderComposer.vue';
import OrderListPanel from '@/components/mobile/organisms/OrderListPanel.vue';
import type {
    MobileBranch,
    MobileCustomer,
    MobileOrder,
    MobilePriceOption,
    MobilePriceQuote,
    MobileService,
} from '@/types/mobile';

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
    { id: 9, name: 'Customer Demo', email: 'customer@laundryhub.test' },
];

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
        expect(wrapper.text()).toContain('Pesanan baru');
        expect(wrapper.text()).toContain('Pelanggan');
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
                delivery: { latitude: -6.2607, longitude: 106.8136 },
            },
            null,
        ]);
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

        expect(wrapper.text()).toContain('Pilih lokasi penjemputan');
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

        await wrapper.findAll('select')[0]?.setValue('9');
        await wrapper.findAll('select')[1]?.setValue('5');
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
