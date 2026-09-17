import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, ref } from 'vue';
import { makeOrder } from '@/composables/mobileOrderTestFixtures';
import { useMobileOrderWorkspace } from '@/composables/useMobileOrderWorkspace';
import type {
    ConfirmOrderPricingPayload,
    MobileBranch,
    MobileContext,
    MobileOrder,
    TransitionOrderPayload,
    WhatsAppSuggestion,
} from '@/types/mobile';

const apiHarness = vi.hoisted(() => ({
    confirmOrderPricing: vi.fn(),
    fetchWhatsAppSuggestion: vi.fn(),
    transitionOrder: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { auth: { user: { id: 9, name: 'Customer Demo' } } } }),
}));

vi.mock('@/composables/useMobileOrderApi', async () => {
    const { computed: vueComputed } = await import('vue');

    return {
        useMobileOrderApi: () => ({
            createOrder: vi.fn(async (): Promise<MobileOrder> => makeOrder(1)),
            createProcessing: vueComputed(() => false),
            confirmOrderPricing: apiHarness.confirmOrderPricing,
            fetchOrder: vi.fn(async (order: MobileOrder): Promise<MobileOrder> => order),
            fetchWhatsAppSuggestion: apiHarness.fetchWhatsAppSuggestion,
            loadOrders: vi.fn(async (): Promise<MobileOrder[]> => []),
            pricingProcessing: vueComputed(() => false),
            transitionOrder: apiHarness.transitionOrder,
            transitionProcessing: vueComputed(() => false),
        }),
    };
});

vi.mock('@/composables/useMobilePaymentApi', async () => {
    const { computed: vueComputed } = await import('vue');

    return {
        useMobilePaymentApi: () => ({
            createPayment: vi.fn(),
            loadReceipt: vi.fn(),
            processing: vueComputed(() => false),
            transitionPaymentStatus: vi.fn(),
        }),
    };
});

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
const context: MobileContext = {
    role: 'branch_admin',
    organization: { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
    organizations: [{ id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' }],
    branches: [branch],
};
const suggestion: WhatsAppSuggestion = {
    available: true,
    phone: '6281234567890',
    message: 'Pesanan siap diambil.',
    url: 'https://wa.me/6281234567890?text=Pesanan%20siap',
    template_id: 12,
    unavailable_reason: null,
};

const createWorkspace = (role: MobileContext['role'] = 'branch_admin') => {
    const selectedBranch = ref<MobileBranch | null>(branch);

    return useMobileOrderWorkspace({
        context: ref<MobileContext | null>({ ...context, role }),
        selectedBranch: computed(() => selectedBranch.value),
        services: ref([]),
        customers: ref([]),
        nearestBranches: ref([]),
        setRequestError: vi.fn(),
    });
};

beforeEach(() => {
    apiHarness.confirmOrderPricing.mockReset();
    apiHarness.fetchWhatsAppSuggestion.mockReset();
    apiHarness.transitionOrder.mockReset();
});

describe('mobile order WhatsApp suggestions', () => {
    it('loads a saved status notification without changing orders or the last transition preview', async () => {
        const workspace = createWorkspace();
        const order = makeOrder(21, 'washing');
        workspace.setOrders([order]);
        apiHarness.fetchWhatsAppSuggestion.mockResolvedValueOnce(suggestion);

        await expect(workspace.loadStatusWhatsAppSuggestion(21)).resolves.toEqual(suggestion);

        expect(apiHarness.fetchWhatsAppSuggestion).toHaveBeenCalledExactlyOnceWith(
            order.organization.slug,
            order,
        );
        expect(apiHarness.transitionOrder).not.toHaveBeenCalled();
        expect(apiHarness.confirmOrderPricing).not.toHaveBeenCalled();
        expect(workspace.orders.value).toEqual([order]);
        expect(workspace.whatsAppSuggestion.value).toBeNull();
    });

    it('does not request staff notifications from a customer workspace', async () => {
        const workspace = createWorkspace('customer');
        workspace.setOrders([makeOrder(21)]);
        await expect(workspace.loadStatusWhatsAppSuggestion(21)).rejects.toThrow(
            'Pesanan belum tersedia',
        );
        expect(apiHarness.fetchWhatsAppSuggestion).not.toHaveBeenCalled();
    });

    it.each([21, -1])('does not request a missing or optimistic order %s', async (orderId) => {
        const workspace = createWorkspace();
        workspace.setOrders([makeOrder(-1)]);
        await expect(workspace.loadStatusWhatsAppSuggestion(orderId)).rejects.toThrow(
            'Pesanan belum tersedia',
        );
        expect(apiHarness.fetchWhatsAppSuggestion).not.toHaveBeenCalled();
    });

    it('fetches a price message after actual weight confirmation succeeds', async () => {
        const pickupOrder: MobileOrder = {
            ...makeOrder(21, 'pickup_requested'),
            pricing: {
                confirmed: false,
                confirmed_at: null,
                items_subtotal_rupiah: 0,
                delivery_fee_rupiah: 0,
                total_rupiah: 0,
            },
        };
        const pricingPayload: ConfirmOrderPricingPayload = {
            price_option_id: 5,
            weight_grams: 3500,
        };
        apiHarness.confirmOrderPricing.mockResolvedValue(makeOrder(21, 'weighed'));
        apiHarness.fetchWhatsAppSuggestion.mockResolvedValue(suggestion);
        const workspace = createWorkspace();
        workspace.setOrders([pickupOrder]);

        await expect(workspace.confirmOrderPricing(21, pricingPayload)).resolves.toBe(true);

        expect(apiHarness.fetchWhatsAppSuggestion).toHaveBeenCalledWith(
            'laundryhub-demo',
            pickupOrder,
        );
        expect(workspace.whatsAppSuggestion.value).toEqual(suggestion);
    });

    it('fetches a suggestion only after a successful status transition', async () => {
        apiHarness.transitionOrder.mockImplementation(
            async (order: MobileOrder, payload: TransitionOrderPayload): Promise<MobileOrder> => ({
                ...order,
                status: payload.status,
            }),
        );
        apiHarness.fetchWhatsAppSuggestion.mockResolvedValue(suggestion);
        const workspace = createWorkspace();
        workspace.setOrders([makeOrder(21)]);

        await expect(workspace.transitionOrder(21, { status: 'washing' })).resolves.toBe(true);

        expect(apiHarness.fetchWhatsAppSuggestion).toHaveBeenCalledOnce();
        expect(apiHarness.fetchWhatsAppSuggestion).toHaveBeenCalledWith(
            'laundryhub-demo',
            expect.objectContaining({ id: 21 }),
        );
        expect(workspace.whatsAppSuggestion.value).toEqual(suggestion);
    });

    it('does not fetch a suggestion when the optimistic transition rolls back', async () => {
        apiHarness.transitionOrder.mockRejectedValue(new Error('Server tidak dapat dijangkau.'));
        const workspace = createWorkspace();
        workspace.setOrders([makeOrder(21)]);

        await expect(workspace.transitionOrder(21, { status: 'washing' })).resolves.toBe(false);

        expect(apiHarness.fetchWhatsAppSuggestion).not.toHaveBeenCalled();
        expect(workspace.whatsAppSuggestion.value).toBeNull();
    });

    it('clears the last suggestion when a new organization order set is loaded', async () => {
        apiHarness.transitionOrder.mockImplementation(
            async (order: MobileOrder, payload: TransitionOrderPayload): Promise<MobileOrder> => ({
                ...order,
                status: payload.status,
            }),
        );
        apiHarness.fetchWhatsAppSuggestion.mockResolvedValue(suggestion);
        const workspace = createWorkspace();
        workspace.setOrders([makeOrder(21)]);
        await workspace.transitionOrder(21, { status: 'washing' });

        workspace.setOrders([makeOrder(22)]);

        expect(workspace.whatsAppSuggestion.value).toBeNull();
        expect(workspace.whatsAppSuggestionError.value).toBeNull();
    });
});
