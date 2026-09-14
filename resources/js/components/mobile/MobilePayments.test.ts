import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import PaymentDialog from '@/components/mobile/organisms/PaymentDialog.vue';
import type { MobileOrder, MobileOrderReceipt, MobilePaymentActions } from '@/types/mobile';

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
});
