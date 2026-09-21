import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent } from 'vue';
import OrderPricingConfirmationDialog from '@/components/mobile/molecules/OrderPricingConfirmationDialog.vue';
import { formatRupiah } from '@/lib/mobileOrders';
import type { MobileOrder, MobileService } from '@/types/mobile';

const passThrough = defineComponent({
    template: '<div><slot /></div>',
});
const dialogRootStub = defineComponent({
    name: 'DialogRootStub',
    props: { open: Boolean },
    emits: ['update:open'],
    template: '<div v-if="open"><slot /></div>',
});
const dialogContentStub = defineComponent({
    name: 'DialogContentStub',
    template: '<div data-test="dialog-content"><slot /></div>',
});
const dialogStubs = {
    Dialog: dialogRootStub,
    DialogClose: passThrough,
    DialogContent: dialogContentStub,
    DialogDescription: passThrough,
    DialogFooter: passThrough,
    DialogHeader: passThrough,
    DialogTitle: passThrough,
};

const sampleOrder: MobileOrder = {
    id: 101,
    order_number: 'LDR-20260921-00000101',
    status: 'pickup_requested',
    organization_id: 1,
    organization: { id: 1, slug: 'laundryhub' },
    branch: { id: 1, name: 'Cabang Utama', code: 'UTM01', qris_enabled: true },
    customer: { id: 5, name: 'Budi Santoso' },
    items: [
        {
            id: 201,
            service: { id: 1, name: 'Cuci Kering', code: 'CK' },
            price_option: {
                id: 10,
                name: 'Kiloan Reguler',
                type: 'per_kilogram',
                pricing_snapshot: {
                    package_weight_grams: null,
                    base_price_rupiah: null,
                    price_per_kilogram_rupiah: 15000,
                    min_price_per_kilogram_rupiah: 12000,
                    minimum_chargeable_grams: 1000,
                },
            },
            calculation: {
                requested_weight_grams: 0,
                chargeable_weight_grams: 0,
                base_price_rupiah: 0,
                excess_weight_grams: 0,
                excess_price_rupiah: 0,
                subtotal_rupiah: 0,
            },
        },
    ],
    pricing: {
        confirmed: false,
        confirmed_at: null,
        items_subtotal_rupiah: 0,
        delivery_fee_rupiah: 5000,
        total_rupiah: 0,
    },
    delivery: null,
    status_history: [],
    payments: [],
    payment_summary: { paid_rupiah: 0, outstanding_rupiah: 0, is_paid: false },
    created_at: '2026-09-21T10:00:00Z',
};

const sampleServices: MobileService[] = [
    {
        id: 1,
        name: 'Cuci Kering',
        code: 'CK',
        description: 'Layanan cuci kering',
        price_options: [
            {
                id: 10,
                name: 'Kiloan Reguler',
                type: 'per_kilogram',
                pricing: {
                    price_per_kilogram_rupiah: 15000,
                    min_price_per_kilogram_rupiah: 12000,
                    minimum_chargeable_grams: 1000,
                },
            },
            {
                id: 11,
                name: 'Kiloan Express',
                type: 'per_kilogram',
                pricing: {
                    price_per_kilogram_rupiah: 20000,
                    min_price_per_kilogram_rupiah: 16000,
                    minimum_chargeable_grams: 1000,
                },
            },
        ],
    },
];

describe('OrderPricingConfirmationDialog', () => {
    it('renders ceiling and floor prices and initializes custom price to ceiling price', async () => {
        const wrapper = mount(OrderPricingConfirmationDialog, {
            props: {
                open: true,
                order: sampleOrder,
                services: sampleServices,
                initialWeightGrams: 3000,
            },
            global: { stubs: dialogStubs },
        });

        expect(wrapper.text()).toContain('Pendetailan Berat & Harga');
        expect(wrapper.text()).toContain('LDR-20260921-00000101');
        expect(wrapper.text()).toContain('Budi Santoso');
        expect(wrapper.text()).toContain(formatRupiah(15000));
        expect(wrapper.text()).toContain(formatRupiah(12000));

        const priceInput = wrapper.get<HTMLInputElement>('[data-test="dialog-price-per-kg"]');
        expect(priceInput.element.value).toBe('15.000');
    });

    it('updates custom price when shortcut buttons are clicked', async () => {
        const wrapper = mount(OrderPricingConfirmationDialog, {
            props: {
                open: true,
                order: sampleOrder,
                services: sampleServices,
                initialWeightGrams: 2000,
            },
            global: { stubs: dialogStubs },
        });

        await wrapper.get('[data-test="use-harga-bawah"]').trigger('click');
        expect(
            wrapper.get<HTMLInputElement>('[data-test="dialog-price-per-kg"]').element.value,
        ).toBe('12.000');

        await wrapper.get('[data-test="use-harga-atas"]').trigger('click');
        expect(
            wrapper.get<HTMLInputElement>('[data-test="dialog-price-per-kg"]').element.value,
        ).toBe('15.000');
    });

    it('rejects custom price below floor price and disables confirmation', async () => {
        const wrapper = mount(OrderPricingConfirmationDialog, {
            props: {
                open: true,
                order: sampleOrder,
                services: sampleServices,
                initialWeightGrams: 2000,
            },
            global: { stubs: dialogStubs },
        });

        await wrapper.get<HTMLInputElement>('[data-test="dialog-price-per-kg"]').setValue('11000');

        expect(wrapper.text()).toContain(
            `Harga tidak boleh lebih rendah dari harga bawah (${formatRupiah(12000)}/kg)`,
        );

        const submitButton = wrapper.get<HTMLButtonElement>(
            '[data-test="submit-pricing-confirmation"]',
        );
        expect(submitButton.element.disabled).toBe(true);
    });

    it('allows switching package and updates floor and ceiling rates accordingly', async () => {
        const wrapper = mount(OrderPricingConfirmationDialog, {
            props: {
                open: true,
                order: sampleOrder,
                services: sampleServices,
                initialWeightGrams: 2000,
            },
            global: { stubs: dialogStubs },
        });

        await wrapper.get<HTMLSelectElement>('[data-test="dialog-service-package"]').setValue('11');

        expect(wrapper.text()).toContain(formatRupiah(20000));
        expect(wrapper.text()).toContain(formatRupiah(16000));

        expect(
            wrapper.get<HTMLInputElement>('[data-test="dialog-price-per-kg"]').element.value,
        ).toBe('20.000');
    });

    it('emits confirm with custom price and weight when submitted', async () => {
        const wrapper = mount(OrderPricingConfirmationDialog, {
            props: {
                open: true,
                order: sampleOrder,
                services: sampleServices,
                initialWeightGrams: 2500,
            },
            global: { stubs: dialogStubs },
        });

        await wrapper.get<HTMLInputElement>('[data-test="dialog-price-per-kg"]').setValue('13500');

        const submitButton = wrapper.get<HTMLButtonElement>(
            '[data-test="submit-pricing-confirmation"]',
        );
        expect(submitButton.element.disabled).toBe(false);
        await submitButton.trigger('click');

        expect(wrapper.emitted('confirm')?.[0]).toEqual([
            {
                price_option_id: 10,
                weight_grams: 2500,
                custom_price_per_kilogram_rupiah: 13500,
            },
        ]);
    });
});
