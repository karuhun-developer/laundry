import { vi } from 'vitest';
import type { MobileOrderApi } from '@/composables/useOptimisticOrders';
import type {
    CreateOrderPayload,
    MobileOrder,
    MobilePayment,
    MobilePaymentMethod,
    MobilePaymentStatus,
} from '@/types/mobile';

export const payload: CreateOrderPayload = {
    items: [{ price_option_id: 5, weight_grams: 2500 }],
};

export const makeOrder = (id: number, status: MobileOrder['status'] = 'received'): MobileOrder => ({
    id,
    order_number: id < 0 ? 'Menunggu nomor' : `LDR-20260913-${String(id).padStart(8, '0')}`,
    status,
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
    status_history: [
        {
            id,
            status,
            actor: { id: 3, name: 'Admin Kemang' },
            note: null,
            occurred_at: '2026-09-13T08:00:00.000Z',
        },
    ],
    payments: [],
    payment_summary: { paid_rupiah: 0, outstanding_rupiah: 30000, is_paid: false },
    created_at: '2026-09-13T08:00:00.000Z',
    pending: id < 0,
});

export const makePayment = (
    id: number,
    status: MobilePaymentStatus = 'pending',
    method: MobilePaymentMethod = 'qris',
): MobilePayment => ({
    id,
    payment_number: `PAY-20260913-${String(id).padStart(8, '0')}`,
    method,
    method_label: method === 'qris' ? 'QRIS' : 'Tunai',
    status,
    status_label: status === 'paid' ? 'Lunas' : 'Menunggu pembayaran',
    amount_rupiah: 30000,
    qris_payload: method === 'qris' ? '000201010212' : null,
    qris_image_data_uri: method === 'qris' ? 'data:image/svg+xml;base64,PHN2Zy8+' : null,
    gateway: null,
    note: null,
    settled_at: status === 'paid' ? '2026-09-13T09:00:00.000Z' : null,
    status_history: [],
    created_at: '2026-09-13T08:30:00.000Z',
});

export const makeApi = (overrides: Partial<MobileOrderApi> = {}): MobileOrderApi => ({
    createOrder: vi.fn(async () => makeOrder(1)),
    fetchOrder: vi.fn(async (order) => order),
    confirmOrderPricing: vi.fn(async (order) => order),
    transitionOrder: vi.fn(async (order) => order),
    createPayment: vi.fn(async () => makePayment(1)),
    transitionPaymentStatus: vi.fn(async (_order, payment) => payment),
    ...overrides,
});
