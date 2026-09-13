import { describe, expect, it, vi } from 'vitest';
import { useOptimisticOrders } from '@/composables/useOptimisticOrders';
import type { CreateOrderPayload, MobileOrder } from '@/types/mobile';

const payload: CreateOrderPayload = {
    items: [{ price_option_id: 5, weight_grams: 2500 }],
};

const makeOrder = (id: number, status: MobileOrder['status'] = 'received'): MobileOrder => ({
    id,
    order_number: id < 0 ? 'Menunggu nomor' : `LDR-20260913-${String(id).padStart(8, '0')}`,
    status,
    organization_id: 1,
    branch: { id: 7, name: 'Cabang Kemang', code: 'KMG01' },
    customer: { id: 9, name: 'Customer Demo' },
    items: [],
    pricing: {
        items_subtotal_rupiah: 30000,
        delivery_fee_rupiah: 0,
        total_rupiah: 30000,
    },
    delivery: null,
    status_history: [
        {
            id,
            status,
            actor: { id: 3, name: 'Admin Kemang' },
            note: null,
            occurred_at: '2026-09-13T08:00:00.000Z',
        },
    ],
    created_at: '2026-09-13T08:00:00.000Z',
    pending: id < 0,
});

describe('useOptimisticOrders', () => {
    it('inserts a draft immediately and replaces it after create succeeds', async () => {
        const canonical = makeOrder(42);
        let resolveCreate: (order: MobileOrder) => void = () => undefined;
        const createPromise = new Promise<MobileOrder>((resolve) => {
            resolveCreate = resolve;
        });
        const optimistic = useOptimisticOrders({
            createOrder: vi.fn(() => createPromise),
            fetchOrder: vi.fn(),
            transitionOrder: vi.fn(),
        });

        const request = optimistic.createOrder(makeOrder(-1), payload);
        expect(optimistic.orders.value[0]?.pending).toBe(true);

        resolveCreate(canonical);
        await expect(request).resolves.toBe(true);
        expect(optimistic.orders.value).toEqual([canonical]);
    });

    it('rolls a draft back after create fails', async () => {
        const optimistic = useOptimisticOrders({
            createOrder: vi.fn(async () => {
                throw new Error('Server tidak tersedia');
            }),
            fetchOrder: vi.fn(),
            transitionOrder: vi.fn(),
        });

        await expect(optimistic.createOrder(makeOrder(-2), payload)).resolves.toBe(false);
        expect(optimistic.orders.value).toHaveLength(0);
        expect(optimistic.rollbackMessage.value).toBe('Server tidak tersedia');
    });

    it('ignores a stale create failure after the tenant order collection changes', async () => {
        let rejectCreate: (reason: Error) => void = () => undefined;
        const createPromise = new Promise<MobileOrder>((_resolve, reject) => {
            rejectCreate = reject;
        });
        const optimistic = useOptimisticOrders({
            createOrder: vi.fn(() => createPromise),
            fetchOrder: vi.fn(),
            transitionOrder: vi.fn(),
        });
        const request = optimistic.createOrder(makeOrder(-3), payload);
        const otherTenantOrder = makeOrder(84);

        optimistic.setOrders([otherTenantOrder]);
        rejectCreate(new Error('Respons tenant lama'));

        await expect(request).resolves.toBe(false);
        expect(optimistic.orders.value).toEqual([otherTenantOrder]);
        expect(optimistic.rollbackMessage.value).toBeNull();
    });

    it('shows an optimistic status and reconciles the canonical response', async () => {
        const initial = makeOrder(42, 'received');
        const canonical = makeOrder(42, 'weighed');
        let resolveTransition: (order: MobileOrder) => void = () => undefined;
        const transitionPromise = new Promise<MobileOrder>((resolve) => {
            resolveTransition = resolve;
        });
        const optimistic = useOptimisticOrders({
            createOrder: vi.fn(),
            fetchOrder: vi.fn(),
            transitionOrder: vi.fn(() => transitionPromise),
        });
        optimistic.setOrders([initial]);

        const request = optimistic.transitionOrder(42, { status: 'weighed' });
        expect(optimistic.orders.value[0]?.status).toBe('weighed');
        expect(optimistic.orders.value[0]?.pending).toBe(true);

        resolveTransition(canonical);
        await expect(request).resolves.toBe(true);
        expect(optimistic.orders.value[0]).toEqual(canonical);
    });

    it('restores the complete order after a status transition fails', async () => {
        const initial = makeOrder(42, 'received');
        const optimistic = useOptimisticOrders({
            createOrder: vi.fn(),
            fetchOrder: vi.fn(),
            transitionOrder: vi.fn(async () => {
                throw new Error('Status bentrok');
            }),
        });
        optimistic.setOrders([initial]);

        await expect(optimistic.transitionOrder(42, { status: 'weighed' })).resolves.toBe(false);
        expect(optimistic.orders.value[0]).toEqual(initial);
        expect(optimistic.rollbackMessage.value).toBe('Status bentrok');
    });

    it('refreshes the canonical order after a conflicting status transition', async () => {
        const initial = makeOrder(42, 'received');
        const canonical = makeOrder(42, 'washing');
        let resolveRefresh: (order: MobileOrder) => void = () => undefined;
        const refreshPromise = new Promise<MobileOrder>((resolve) => {
            resolveRefresh = resolve;
        });
        const fetchOrder = vi.fn(() => refreshPromise);
        const conflict = Object.assign(new Error('Status pesanan sudah berubah.'), { status: 409 });
        const optimistic = useOptimisticOrders({
            createOrder: vi.fn(),
            fetchOrder,
            transitionOrder: vi.fn(async () => {
                throw conflict;
            }),
        });
        optimistic.setOrders([initial]);

        const request = optimistic.transitionOrder(42, { status: 'weighed' });
        await vi.waitFor(() => expect(fetchOrder).toHaveBeenCalledWith(initial));
        expect(optimistic.orders.value[0]).toEqual(initial);

        resolveRefresh(canonical);
        await expect(request).resolves.toBe(false);
        expect(optimistic.orders.value[0]).toEqual(canonical);
        expect(optimistic.rollbackMessage.value).toContain('data terbaru');
    });

    it('ignores a stale conflict refresh after the tenant order collection changes', async () => {
        const initial = makeOrder(42, 'received');
        const refreshedOldTenantOrder = makeOrder(42, 'washing');
        const currentTenantOrder = makeOrder(84, 'received');
        let resolveRefresh: (order: MobileOrder) => void = () => undefined;
        const refreshPromise = new Promise<MobileOrder>((resolve) => {
            resolveRefresh = resolve;
        });
        const fetchOrder = vi.fn(() => refreshPromise);
        const conflict = Object.assign(new Error('Status pesanan sudah berubah.'), { status: 409 });
        const optimistic = useOptimisticOrders({
            createOrder: vi.fn(),
            fetchOrder,
            transitionOrder: vi.fn(async () => {
                throw conflict;
            }),
        });
        optimistic.setOrders([initial]);

        const request = optimistic.transitionOrder(42, { status: 'weighed' });
        await vi.waitFor(() => expect(fetchOrder).toHaveBeenCalledWith(initial));
        optimistic.setOrders([currentTenantOrder]);
        resolveRefresh(refreshedOldTenantOrder);

        await expect(request).resolves.toBe(false);
        expect(optimistic.orders.value).toEqual([currentTenantOrder]);
        expect(optimistic.rollbackMessage.value).toBeNull();
    });
});
