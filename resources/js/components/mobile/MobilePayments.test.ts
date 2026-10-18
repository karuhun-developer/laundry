import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import OrderTicket from '@/components/mobile/molecules/OrderTicket.vue';
import PaymentDialog from '@/components/mobile/organisms/PaymentDialog.vue';
import type {
    MobileOrder,
    MobileOrderReceipt,
    MobilePaymentActions,
    MobilePaymentGatewayMethod,
    WhatsAppSuggestion,
} from '@/types/mobile';

const externalLinkMocks = vi.hoisted(() => ({
    open: vi.fn(async (_url: string): Promise<void> => undefined),
}));
const qrisDownloadMocks = vi.hoisted(() => ({
    save: vi.fn(async () => ({ status: 'saved' as const })),
}));

vi.mock('@/lib/externalLinks/transport', () => ({
    externalLinkTransport: externalLinkMocks,
}));
vi.mock('@/lib/qrisDownload/transport', () => ({
    qrisDownloadTransport: qrisDownloadMocks,
}));

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
    share_url: 'https://example.test/struk/21?expires=1789466400&signature=signed-receipt',
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

const makeActions = (refreshedOrder: MobileOrder = makeOrder()): MobilePaymentActions => ({
    refreshOrder: vi.fn(async () => refreshedOrder),
    createPayment: vi.fn(async () => true),
    loadPaymentWhatsAppSuggestion: vi.fn(
        async (): Promise<WhatsAppSuggestion> => ({
            available: true,
            phone: '6281234567890',
            message: 'Mohon verifikasi pembayaran.',
            url: 'https://wa.me/6281234567890?text=Mohon%20verifikasi%20pembayaran.',
            template_id: null,
            unavailable_reason: null,
        }),
    ),
    loadPaymentGatewayMethods: vi.fn(async () => []),
    transitionPaymentStatus: vi.fn(async () => true),
    loadReceipt: vi.fn(async () => receipt),
    printReceipt: vi.fn(async () => true),
});

beforeEach(() => {
    externalLinkMocks.open.mockClear();
    qrisDownloadMocks.save.mockClear();
});

describe('mobile payment dialog', () => {
    it('opens order details and payment actions directly without changing payments', async () => {
        const order = makeOrder();
        const actions = makeActions(order);
        const wrapper = mount(OrderTicket, {
            props: { order, role: 'branch_admin', paymentActions: actions },
            attachTo: document.body,
        });

        try {
            const detailsButton = wrapper.get('[data-test="open-order-details"]');
            const paymentButton = wrapper.get('[data-test="open-payment-dialog"]');
            expect(detailsButton.element.closest('details')).toBeNull();
            expect(paymentButton.element.closest('details')).toBeNull();

            await detailsButton.trigger('click');
            await flushPromises();

            const detailsDialog = document.body.querySelector('[data-test="order-details-dialog"]');
            expect(detailsDialog?.textContent).toContain(order.order_number);
            expect(detailsDialog?.textContent).toContain(order.branch.name);
            expect(detailsDialog?.textContent).toContain(order.customer.name);
            expect(actions.refreshOrder).not.toHaveBeenCalled();
            detailsDialog?.querySelector<HTMLButtonElement>('[data-slot="dialog-close"]')?.click();
            await flushPromises();

            await paymentButton.trigger('click');
            await flushPromises();

            expect(actions.refreshOrder).toHaveBeenCalledWith(order.id);
            expect(document.body.querySelector('[data-test="mark-payment-paid"]')).not.toBeNull();
            expect(
                document.body.querySelector('[data-test="share-payment-whatsapp"]'),
            ).not.toBeNull();
            expect(actions.createPayment).not.toHaveBeenCalled();
            expect(actions.transitionPaymentStatus).not.toHaveBeenCalled();
            expect(actions.loadPaymentWhatsAppSuggestion).not.toHaveBeenCalled();
            expect(externalLinkMocks.open).not.toHaveBeenCalled();
        } finally {
            wrapper.unmount();
        }
    });

    it('hides stale QRIS actions until the latest order detail is loaded', async () => {
        let resolveRefresh: (order: MobileOrder) => void = () => undefined;
        const refreshPromise = new Promise<MobileOrder>((resolve) => {
            resolveRefresh = resolve;
        });
        const staleOrder = makeOrder();
        staleOrder.payments = [];
        const paidOrder = makeOrder();
        paidOrder.payments = [];
        paidOrder.payment_summary = { paid_rupiah: 30000, outstanding_rupiah: 0, is_paid: true };
        const actions = makeActions(staleOrder);
        vi.mocked(actions.refreshOrder).mockReturnValue(refreshPromise);
        const wrapper = mount(PaymentDialog, {
            props: { order: staleOrder, actions, role: 'customer' },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');

        expect(document.body.querySelector('[data-test="payment-detail-loading"]')).not.toBeNull();
        expect(document.body.querySelector('[data-test="customer-create-qris"]')).toBeNull();

        resolveRefresh(paidOrder);

        await vi.waitFor(() => expect(document.body.textContent).toContain('Pesanan sudah lunas'));
        expect(document.body.querySelector('[data-test="customer-create-qris"]')).toBeNull();

        wrapper.unmount();
    });

    it('lets a customer create dynamic QRIS for the full outstanding amount', async () => {
        const order = makeOrder();
        order.payments = [];
        const actions = makeActions(order);
        const wrapper = mount(PaymentDialog, {
            props: { order, actions, role: 'customer' },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');

        expect(actions.loadPaymentGatewayMethods).not.toHaveBeenCalled();
        expect(document.body.querySelector('[data-test="payment-form"]')).toBeNull();
        expect(document.body.querySelector('[data-test="customer-qris-payment"]')).not.toBeNull();
        document.body
            .querySelector<HTMLButtonElement>('[data-test="customer-create-qris"]')
            ?.click();

        await vi.waitFor(() =>
            expect(actions.createPayment).toHaveBeenCalledWith(21, {
                method: 'qris',
                amount_rupiah: 30000,
                note: undefined,
            }),
        );

        wrapper.unmount();
    });

    it('shows customer QRIS without staff reconciliation or printer actions', async () => {
        const actions = makeActions();
        const wrapper = mount(PaymentDialog, {
            props: { order: makeOrder(), actions, role: 'customer', printerConfigured: true },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');

        expect(document.body.querySelector('[data-test="qris-image"]')).not.toBeNull();
        expect(document.body.querySelector('[data-test="download-qris"]')).not.toBeNull();
        expect(document.body.querySelector('[data-test="mark-payment-paid"]')).toBeNull();
        expect(document.body.querySelector('[data-test="mark-payment-failed"]')).toBeNull();
        expect(document.body.querySelector('[data-test="print-receipt"]')).toBeNull();
        expect(document.body.textContent).not.toContain('Atur printer di Akun');

        document.body.querySelector<HTMLButtonElement>('[data-test="download-qris"]')?.click();
        await vi.waitFor(() =>
            expect(qrisDownloadMocks.save).toHaveBeenCalledWith({
                dataUri: 'data:image/svg+xml;base64,PHN2Zy8+',
                filename: 'PAY-20260913-00000055.svg',
            }),
        );

        document.body
            .querySelector<HTMLButtonElement>('[data-test="load-digital-receipt"]')
            ?.click();
        await vi.waitFor(() => expect(actions.loadReceipt).toHaveBeenCalledWith(21));
        await vi.waitFor(() => expect(document.body.textContent).toContain('Customer Demo'));
        expect(document.body.querySelector('[data-test="print-receipt"]')).toBeNull();

        expect(document.body.querySelector('[data-test="chat-branch"]')).not.toBeNull();
        document.body
            .querySelector<HTMLButtonElement>('[data-test="share-receipt-whatsapp"]')
            ?.click();
        await vi.waitFor(() =>
            expect(externalLinkMocks.open).toHaveBeenCalledWith(
                expect.stringMatching(/^https:\/\/wa\.me\/\?text=/),
            ),
        );
        const sharedReceiptUrl = new URL(
            vi.mocked(externalLinkMocks.open).mock.calls[0]?.[0] ?? '',
        );
        expect(sharedReceiptUrl.searchParams.get('text')).toContain(receipt.receipt_number);
        expect(sharedReceiptUrl.searchParams.get('text')).toContain(receipt.share_url);

        document.body
            .querySelector<HTMLButtonElement>('[data-test="request-payment-verification"]')
            ?.click();

        await vi.waitFor(() =>
            expect(actions.loadPaymentWhatsAppSuggestion).toHaveBeenCalledWith(21, 55),
        );
        expect(externalLinkMocks.open).toHaveBeenCalledWith(
            'https://wa.me/6281234567890?text=Mohon%20verifikasi%20pembayaran.',
        );

        wrapper.unmount();
    });

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

    it('lets branch admin view pending QRIS and share payment details to customer WhatsApp', async () => {
        const actions = makeActions();
        const wrapper = mount(PaymentDialog, {
            props: { order: makeOrder(), actions, role: 'branch_admin' },
            attachTo: document.body,
        });

        expect(wrapper.get('[data-test="open-payment-dialog"]').text()).toBe(
            'Verifikasi / kirim tagihan',
        );

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');

        const shareButton = document.body.querySelector<HTMLButtonElement>(
            '[data-test="share-payment-whatsapp"]',
        );
        expect(shareButton).not.toBeNull();
        shareButton?.click();

        await vi.waitFor(() =>
            expect(actions.loadPaymentWhatsAppSuggestion).toHaveBeenCalledWith(21, 55),
        );
        expect(externalLinkMocks.open).toHaveBeenCalledWith(
            'https://wa.me/6281234567890?text=Mohon%20verifikasi%20pembayaran.',
        );

        wrapper.unmount();
    });

    it('shows error toast when branch admin shares payment but customer WhatsApp is unavailable', async () => {
        const actions = makeActions();
        vi.mocked(actions.loadPaymentWhatsAppSuggestion).mockResolvedValueOnce({
            available: false,
            phone: null,
            message: null,
            url: null,
            template_id: null,
            unavailable_reason: 'Nomor WhatsApp pelanggan belum tersedia atau tidak valid.',
        });

        const wrapper = mount(PaymentDialog, {
            props: { order: makeOrder(), actions, role: 'branch_admin' },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');

        document.body
            .querySelector<HTMLButtonElement>('[data-test="share-payment-whatsapp"]')
            ?.click();

        await vi.waitFor(() =>
            expect(document.body.textContent).toContain(
                'Nomor WhatsApp pelanggan belum tersedia atau tidak valid.',
            ),
        );

        wrapper.unmount();
    });

    it('loads a digital receipt and offers a configured printer reprint', async () => {
        const order = makeOrder();
        order.payments = [];
        order.payment_summary = { paid_rupiah: 30000, outstanding_rupiah: 0, is_paid: true };
        const actions = makeActions(order);
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

        expect(document.body.querySelector('[data-test="chat-branch"]')).toBeNull();
        expect(document.body.textContent).not.toContain('Telp/WA');
        expect(document.body.textContent).not.toContain(receipt.branch.phone);
        document.body.querySelector<HTMLButtonElement>('[data-test="print-receipt"]')?.click();
        await vi.waitFor(() => expect(actions.printReceipt).toHaveBeenCalledWith(21));

        wrapper.unmount();
    });

    it('hides payment gateway controls when no provider methods are available', async () => {
        const order = makeOrder();
        order.payments = [];
        const actions = makeActions(order);
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
        const order = makeOrder();
        order.payments = [];
        const actions = makeActions(order);
        vi.mocked(actions.loadPaymentGatewayMethods).mockResolvedValue(gatewayMethods);
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
        const actions = makeActions(order);
        const wrapper = mount(PaymentDialog, {
            props: { order, actions },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');

        expect(document.body.querySelector('[data-test="download-qris"]')).not.toBeNull();
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
        const actions = makeActions(order);
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

    it('allows branch admin to accept cash payment even when a dynamic QRIS is pending', async () => {
        const order = makeOrder();
        const actions = makeActions(order);
        const wrapper = mount(PaymentDialog, {
            props: { order, actions, role: 'branch_admin' },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');

        expect(document.body.querySelector('[data-test="qris-image"]')).not.toBeNull();

        const paymentForm = document.body.querySelector('[data-test="payment-form"]');
        expect(paymentForm).not.toBeNull();
        expect(document.body.textContent).toContain('Terima pembayaran tunai di kasir');

        const amountInput = document.body.querySelector<HTMLInputElement>(
            '[data-test="payment-amount-input"]',
        );
        expect(amountInput).not.toBeNull();
        expect(amountInput?.value).toBe('30.000');

        if (amountInput) {
            amountInput.value = '15000';
            amountInput.dispatchEvent(new Event('input'));
        }
        await wrapper.vm.$nextTick();

        expect(amountInput?.value).toBe('15.000');

        const submitButton = paymentForm?.querySelector<HTMLButtonElement>('button[type="submit"]');
        expect(submitButton?.disabled).toBe(false);
        submitButton?.click();

        await vi.waitFor(() =>
            expect(actions.createPayment).toHaveBeenCalledWith(21, {
                method: 'cash',
                amount_rupiah: 15000,
                note: undefined,
                gateway_driver: undefined,
                gateway_method: undefined,
            }),
        );

        wrapper.unmount();
    });

    it('hides QR image and shows regenerate button when QRIS has expired', async () => {
        const order = makeOrder();
        order.payments[0]!.qris_expires_at = new Date(Date.now() - 60000).toISOString();
        const actions = makeActions(order);
        const wrapper = mount(PaymentDialog, {
            props: { order, actions, role: 'branch_admin' },
            attachTo: document.body,
        });

        await wrapper.get('[data-test="open-payment-dialog"]').trigger('click');

        await vi.waitFor(() => {
            expect(document.body.textContent).toContain('QRIS Telah Kedaluwarsa');
        });
        expect(document.body.querySelector('[data-test="qris-image"]')).toBeNull();
        expect(document.body.querySelector('[data-test="regenerate-qris-button"]')).not.toBeNull();

        wrapper.unmount();
    });
});

