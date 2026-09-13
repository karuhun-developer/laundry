import { describe, expect, it, vi } from 'vitest';
import { makeApi, makeOrder, makePayment } from '@/composables/mobileOrderTestFixtures';
import { useOptimisticOrders } from '@/composables/useOptimisticOrders';
import type { MobilePayment } from '@/types/mobile';

describe('useOptimisticPayments', () => {
    it('shows a draft payment immediately and replaces it with the canonical payment', async () => {
        const initial = makeOrder(42);
        const canonicalPayment = makePayment(55);
        let resolvePayment: (payment: MobilePayment) => void = () => undefined;
        const paymentPromise = new Promise<MobilePayment>((resolve) => {
            resolvePayment = resolve;
        });
        const optimistic = useOptimisticOrders(
            makeApi({ createPayment: vi.fn(() => paymentPromise) }),
        );
        optimistic.setOrders([initial]);

        const request = optimistic.createPayment(42, {
            method: 'qris',
            amount_rupiah: 30000,
        });
        expect(optimistic.orders.value[0]?.payments[0]?.pending).toBe(true);
        expect(optimistic.orders.value[0]?.payments[0]?.payment_number).toBe(
            'Menunggu nomor pembayaran',
        );

        resolvePayment(canonicalPayment);
        await expect(request).resolves.toBe(true);
        expect(optimistic.orders.value[0]?.payments).toEqual([canonicalPayment]);
    });

    it('removes a draft payment and restores the summary after creation fails', async () => {
        const initial = makeOrder(42);
        const optimistic = useOptimisticOrders(
            makeApi({
                createPayment: vi.fn(async () => {
                    throw new Error('Nominal pembayaran ditolak');
                }),
            }),
        );
        optimistic.setOrders([initial]);

        await expect(
            optimistic.createPayment(42, { method: 'cash', amount_rupiah: 30000 }),
        ).resolves.toBe(false);
        expect(optimistic.orders.value[0]?.payments).toEqual([]);
        expect(optimistic.orders.value[0]?.payment_summary).toEqual(initial.payment_summary);
        expect(optimistic.rollbackMessage.value).toBe('Nominal pembayaran ditolak');
    });

    it('shows a paid QRIS state immediately and reconciles the canonical payment', async () => {
        const pendingPayment = makePayment(55);
        const initial = { ...makeOrder(42), payments: [pendingPayment] };
        const canonicalPayment = makePayment(55, 'paid');
        let resolvePayment: (payment: MobilePayment) => void = () => undefined;
        const paymentPromise = new Promise<MobilePayment>((resolve) => {
            resolvePayment = resolve;
        });
        const optimistic = useOptimisticOrders(
            makeApi({ transitionPaymentStatus: vi.fn(() => paymentPromise) }),
        );
        optimistic.setOrders([initial]);

        const request = optimistic.transitionPaymentStatus(42, 55, { status: 'paid' });
        expect(optimistic.orders.value[0]?.payments[0]?.status).toBe('paid');
        expect(optimistic.orders.value[0]?.payments[0]?.pending).toBe(true);
        expect(optimistic.orders.value[0]?.payment_summary.is_paid).toBe(true);

        resolvePayment(canonicalPayment);
        await expect(request).resolves.toBe(true);
        expect(optimistic.orders.value[0]?.payments).toEqual([canonicalPayment]);
    });

    it('restores the payment and summary after reconciliation fails', async () => {
        const pendingPayment = makePayment(55);
        const initial = { ...makeOrder(42), payments: [pendingPayment] };
        const optimistic = useOptimisticOrders(
            makeApi({
                transitionPaymentStatus: vi.fn(async () => {
                    throw new Error('Mutasi belum ditemukan');
                }),
            }),
        );
        optimistic.setOrders([initial]);

        await expect(optimistic.transitionPaymentStatus(42, 55, { status: 'paid' })).resolves.toBe(
            false,
        );
        expect(optimistic.orders.value[0]).toEqual(initial);
        expect(optimistic.rollbackMessage.value).toBe('Mutasi belum ditemukan');
    });

    it('refreshes the canonical order after a conflicting payment reconciliation', async () => {
        const pendingPayment = makePayment(55);
        const initial = { ...makeOrder(42), payments: [pendingPayment] };
        const canonical = { ...makeOrder(42), payments: [makePayment(55, 'failed')] };
        const conflict = Object.assign(new Error('Status pembayaran sudah berubah.'), {
            status: 409,
        });
        const fetchOrder = vi.fn(async () => canonical);
        const optimistic = useOptimisticOrders(
            makeApi({
                fetchOrder,
                transitionPaymentStatus: vi.fn(async () => {
                    throw conflict;
                }),
            }),
        );
        optimistic.setOrders([initial]);

        await expect(optimistic.transitionPaymentStatus(42, 55, { status: 'paid' })).resolves.toBe(
            false,
        );
        expect(fetchOrder).toHaveBeenCalledWith(initial);
        expect(optimistic.orders.value[0]).toEqual(canonical);
        expect(optimistic.rollbackMessage.value).toContain('data pembayaran terbaru');
    });

    it('restores the canonical order and reports a failed payment conflict refresh', async () => {
        const pendingPayment = makePayment(55);
        const initial = { ...makeOrder(42), payments: [pendingPayment] };
        const conflict = Object.assign(new Error('Status pembayaran sudah berubah.'), {
            status: 409,
        });
        const optimistic = useOptimisticOrders(
            makeApi({
                fetchOrder: vi.fn(async () => {
                    throw new Error('Koneksi pemuatan pembayaran terputus.');
                }),
                transitionPaymentStatus: vi.fn(async () => {
                    throw conflict;
                }),
            }),
        );
        optimistic.setOrders([initial]);

        await expect(optimistic.transitionPaymentStatus(42, 55, { status: 'paid' })).resolves.toBe(
            false,
        );
        expect(optimistic.orders.value[0]).toEqual(initial);
        expect(optimistic.rollbackMessage.value).toContain('Koneksi pemuatan pembayaran terputus.');
    });
});
