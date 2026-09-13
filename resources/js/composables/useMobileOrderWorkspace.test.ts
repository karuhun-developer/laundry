import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, ref } from 'vue';
import { makeOrder } from '@/composables/mobileOrderTestFixtures';
import { useMobileOrderWorkspace } from '@/composables/useMobileOrderWorkspace';
import type {
    MobileBranch,
    MobileContext,
    MobileOrder,
    TransitionOrderPayload,
    WhatsAppSuggestion,
} from '@/types/mobile';

const apiHarness = vi.hoisted(() => ({
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
            fetchOrder: vi.fn(async (order: MobileOrder): Promise<MobileOrder> => order),
            fetchWhatsAppSuggestion: apiHarness.fetchWhatsAppSuggestion,
            loadOrders: vi.fn(async (): Promise<MobileOrder[]> => []),
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

const createWorkspace = () => {
    const selectedBranch = ref<MobileBranch | null>(branch);

    return useMobileOrderWorkspace({
        context: ref<MobileContext | null>(context),
        selectedBranch: computed(() => selectedBranch.value),
        services: ref([]),
        customers: ref([]),
        nearestBranches: ref([]),
        setRequestError: vi.fn(),
    });
};

beforeEach(() => {
    apiHarness.fetchWhatsAppSuggestion.mockReset();
    apiHarness.transitionOrder.mockReset();
});

describe('mobile order WhatsApp suggestions', () => {
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
