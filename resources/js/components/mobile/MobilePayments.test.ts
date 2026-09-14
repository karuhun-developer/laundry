import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import PaymentDialog from '@/components/mobile/organisms/PaymentDialog.vue';
import type {
    MobileOrder,
    MobileOrderReceipt,
    MobilePaymentActions,
    MobilePaymentGatewayMethod,
} from '@/types/mobile';

const gatewayMethods: readonly MobilePaymentGatewayMethod[] = [
    {
        driver: 'paywuz',
        driver_label: 'Paywuz',
        code: 'BNIVA',
        name: 'BNI Virtual Account',
        type: 'virtual_account',
        fee_rupiah: 4000,
        minimum_rupiah: 10000,
        maximum_rupiah: 10000000,
        is_default: false,
    },
    {
        driver: 'paywuz',
        driver_label: 'Paywuz',
        code: 'QRIS',
        name: 'QRIS',
        type: 'qris',
        fee_rupiah: 0,
        minimum_rupiah: 1000,
        maximum_rupiah: 10000000,
        is_default: true,
    },
];

const receipt: MobileOrderReceipt = {
    receipt_number: 'LDR-20260913-00000021',
    order_id: 21,
    order_status: 'washing',
    branch: {
        id: 7,
        name: 'Cabang Kemang',
        code: 'KMG01',
        phone: '081234567890',
        address: 'Jl. Kemang Raya',
        city: { name: 'Jakarta Selatan', province: 'DKI Jakarta' },
    },
    customer: { id: 9, name: 'Customer Demo' },
    items: [],
    pricing: {
        confirmed: true,
        confirmed_at: '2026-09-13T08:00:00.000Z',
        items_subtotal_rupiah: 30000,
        delivery_fee_rupiah: 0,
        total_rupiah: 30000,
        paid_rupiah: 0,
        outstanding_rupiah: 30000,
    },
    payments: [],
    issued_at: '2026-09-13T10:30:00.000Z',
    ordered_at: '2026-09-13T08:00:00.000Z',
};

const makeOrder = (): MobileOrder => ({
    id: 21,
    order_number: 'LDR-20260913-00000021',
    status: 'washing',
    organization_id: 1,
    organization: { id: 1, slug: 'laundryhub-demo' },
    branch: { id: 7, name: 'Cabang Kemang', code: 'KMG01', qris_enabled: true },
    customer: { id: 9, name: 'Customer Demo' },
    items: [],
    pricing: {
        confirmed: true,
        confirmed_at: '2026-09-13T08:00:00.000Z',
        items_subtotal_rupiah: 30000,
        delivery_fee_rupiah: 0,
        total_rupiah: 30000,
    },
    delivery: null,
    status_history: [],
    payments: [
        {
            id: 55,
            payment_number: 'PAY-20260913-00000055',
            method: 'qris',
            method_label: 'QRIS',
            status: 'pending',
            status_label: 'Menunggu pembayaran',
            amount_rupiah: 30000,
            qris_payload: '000201010212',
            qris_image_data_uri: 'data:image/svg+xml;base64,PHN2Zy8+',
            gateway: null,
            note: null,
            settled_at: null,
            status_history: [],
            created_at: '2026-09-13T10:00:00.000Z',
        },
    ],
    payment_summary: { paid_rupiah: 0, outstanding_rupiah: 30000, is_paid: false },
    created_at: '2026-09-13T08:00:00.000Z',
});

const makeActions = (): MobilePaymentActions => ({
    createPayment: vi.fn(async () => true),
    loadPaymentGatewayMethods: vi.fn(async () => []),
    transitionPaymentStatus: vi.fn(async () => true),
    loadReceipt: vi.fn(async () => receipt),
    printReceipt: vi.fn(async () => true),
});

describe('mobile payment dialog', () => {
    it('shows a scannable QRIS image and reconciliation actions', async () => {
        const actions = makeActions();
        const wrapper = mount(PaymentDialog, {
            props: { order: makeOrder(), actions, printerConfigured: true },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');

        expect(
            document.body.querySelector<HTMLImageElement>('[data-test="qris-image"]')?.src,
        ).toContain('data:image/svg+xml;base64,');

        const paidButton = document.body.querySelector<HTMLButtonElement>(
            '[data-test="mark-payment-paid"]',
        );
        expect(paidButton).not.toBeNull();
        paidButton?.click();

        await vi.waitFor(() =>
            expect(actions.transitionPaymentStatus).toHaveBeenCalledWith(21, 55, {
                status: 'paid',
                note: 'Pembayaran QRIS dikonfirmasi admin cabang',
            }),
        );

        wrapper.unmount();
    });

    it('loads a digital receipt and offers a configured printer reprint', async () => {
        const actions = makeActions();
        const order = makeOrder();
        order.payments = [];
        order.payment_summary = { paid_rupiah: 30000, outstanding_rupiah: 0, is_paid: true };
        const wrapper = mount(PaymentDialog, {
            props: { order, actions, printerConfigured: true },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');
        document.body
            .querySelector<HTMLButtonElement>('[data-test="load-digital-receipt"]')
            ?.click();

        await vi.waitFor(() => expect(actions.loadReceipt).toHaveBeenCalledWith(21));
        await vi.waitFor(() => expect(document.body.textContent).toContain('Customer Demo'));

        document.body.querySelector<HTMLButtonElement>('[data-test="print-receipt"]')?.click();
        await vi.waitFor(() => expect(actions.printReceipt).toHaveBeenCalledWith(21));

        wrapper.unmount();
    });

    it('hides payment gateway controls when no provider methods are available', async () => {
        const actions = makeActions();
        const order = makeOrder();
        order.payments = [];
        const wrapper = mount(PaymentDialog, {
            props: { order, actions },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');
        await vi.waitFor(() => expect(actions.loadPaymentGatewayMethods).toHaveBeenCalled());

        expect(document.body.querySelector('[data-test="payment-method-gateway"]')).toBeNull();
        wrapper.unmount();
    });

    it('selects Paywuz QRIS by default and submits a direct VA method', async () => {
        const actions = makeActions();
        vi.mocked(actions.loadPaymentGatewayMethods).mockResolvedValue(gatewayMethods);
        const order = makeOrder();
        order.payments = [];
        const wrapper = mount(PaymentDialog, {
            props: { order, actions },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');
        await vi.waitFor(() =>
            expect(
                document.body.querySelector('[data-test="payment-method-gateway"]'),
            ).not.toBeNull(),
        );
        document.body
            .querySelector<HTMLButtonElement>('[data-test="payment-method-gateway"]')
            ?.click();

        await vi.waitFor(() =>
            expect(
                document.body.querySelector('[data-test="gateway-method-QRIS"]')?.className,
            ).toContain('border-brand-600'),
        );
        expect(
            document.body
                .querySelector<HTMLImageElement>('[data-test="gateway-method-logo-QRIS"]')
                ?.getAttribute('src'),
        ).toBe('/images/payment-methods/qris.svg');
        expect(
            document.body
                .querySelector<HTMLImageElement>('[data-test="gateway-method-logo-BNIVA"]')
                ?.getAttribute('src'),
        ).toBe('/images/payment-methods/bni.svg');

        document.body
            .querySelector<HTMLButtonElement>('[data-test="gateway-method-BNIVA"]')
            ?.click();
        document.body.querySelector<HTMLFormElement>('[data-test="payment-form"]')?.requestSubmit();

        await vi.waitFor(() =>
            expect(actions.createPayment).toHaveBeenCalledWith(21, {
                method: 'gateway',
                amount_rupiah: 30000,
                note: undefined,
                gateway_driver: 'paywuz',
                gateway_method: 'BNIVA',
            }),
        );

        wrapper.unmount();
    });

    it('shows downloadable gateway QRIS without manual reconciliation', async () => {
        const actions = makeActions();
        const order = makeOrder();
        const payment = order.payments[0];

        if (!payment) {
            throw new Error('Payment fixture is missing.');
        }

        payment.method = 'gateway';
        payment.method_label = 'Payment gateway';
        payment.gateway = {
            driver: 'paywuz',
            driver_label: 'Paywuz',
            payment_id: 'trx-55',
            order_id: payment.payment_number,
            method: 'QRIS',
            status: 'pending',
            payment_number: payment.qris_payload,
            payment_url: null,
            expires_at: '2026-09-14T16:00:00.000Z',
        };
        const wrapper = mount(PaymentDialog, {
            props: { order, actions },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');

        expect(document.body.querySelector('[data-test="download-gateway-qris"]')).not.toBeNull();
        expect(document.body.querySelector('[data-test="mark-payment-paid"]')).toBeNull();
        expect(document.body.querySelector('[data-test="mark-payment-failed"]')).toBeNull();
        wrapper.unmount();
    });

    it('shows and copies a direct VA payment number', async () => {
        const writeText = vi.fn(async () => undefined);
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        });
        const actions = makeActions();
        const order = makeOrder();
        const payment = order.payments[0];

        if (!payment) {
            throw new Error('Payment fixture is missing.');
        }

        payment.method = 'gateway';
        payment.method_label = 'Payment gateway';
        payment.qris_payload = null;
        payment.qris_image_data_uri = null;
        payment.gateway = {
            driver: 'paywuz',
            driver_label: 'Paywuz',
            payment_id: 'trx-55',
            order_id: payment.payment_number,
            method: 'BNIVA',
            status: 'pending',
            payment_number: '1234567890',
            payment_url: null,
            expires_at: '2026-09-14T16:00:00.000Z',
        };
        const wrapper = mount(PaymentDialog, {
            props: { order, actions },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');
        document.body.querySelector<HTMLButtonElement>('[data-test="copy-gateway-va"]')?.click();

        await vi.waitFor(() => expect(writeText).toHaveBeenCalledWith('1234567890'));
        expect(document.body.querySelector('[data-test="mark-payment-paid"]')).toBeNull();
        wrapper.unmount();
    });
});
