import { usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { useMobileOrderApi } from '@/composables/useMobileOrderApi';
import { useOptimisticOrders } from '@/composables/useOptimisticOrders';
import { buildDraftOrder, findCatalogOption } from '@/lib/mobileOrders';
import type {
    CreateOrderPayload,
    MobileBranch,
    MobileContext,
    MobileCustomer,
    MobilePriceQuote,
    MobileService,
    NearestMobileBranch,
} from '@/types/mobile';

type MobileOrderWorkspaceState = {
    context: Ref<MobileContext | null>;
    selectedBranch: ComputedRef<MobileBranch | null>;
    services: Ref<MobileService[]>;
    customers: Ref<MobileCustomer[]>;
    nearestBranches: Ref<NearestMobileBranch[]>;
    setRequestError: (message: string | null) => void;
};

export const useMobileOrderWorkspace = (state: MobileOrderWorkspaceState) => {
    const page = usePage();
    const api = useMobileOrderApi();
    const optimistic = useOptimisticOrders({
        createOrder: async (payload) => {
            const context = state.context.value;
            const branch = state.selectedBranch.value;

            if (!context || !branch) {
                throw new Error('Pilih cabang sebelum membuat pesanan.');
            }

            return api.createOrder(context.organization.slug, branch.id, payload);
        },
        fetchOrder: async (order) => {
            const context = state.context.value;

            if (!context) {
                throw new Error('Konteks organisasi tidak tersedia.');
            }

            return api.fetchOrder(context.organization.slug, order);
        },
        transitionOrder: async (order, payload) => {
            const context = state.context.value;

            if (!context) {
                throw new Error('Konteks organisasi tidak tersedia.');
            }

            return api.transitionOrder(context.organization.slug, order, payload);
        },
    });

    const createOrder = async (
        payload: CreateOrderPayload,
        quote: MobilePriceQuote,
    ): Promise<boolean> => {
        state.setRequestError(null);
        const branch = state.selectedBranch.value;
        const item = payload.items[0];
        const catalogEntry = item
            ? findCatalogOption(state.services.value, item.price_option_id)
            : null;

        if (
            !branch ||
            !item ||
            !catalogEntry ||
            quote.price_option.id !== item.price_option_id ||
            quote.requested_weight_grams !== item.weight_grams
        ) {
            state.setRequestError('Pilih cabang, layanan, dan berat laundry.');
            return false;
        }

        const customer =
            state.context.value?.role === 'branch_admin'
                ? state.customers.value.find((entry) => entry.id === payload.customer_id)
                : { id: page.props.auth.user.id, name: page.props.auth.user.name };

        if (!customer) {
            state.setRequestError('Pilih customer untuk pesanan ini.');
            return false;
        }

        const nearestBranch = state.nearestBranches.value.find((entry) => entry.id === branch.id);
        const draft = buildDraftOrder({
            branch,
            customer: { id: customer.id, name: customer.name },
            quote,
            service: catalogEntry.service,
            deliveryFeeRupiah: payload.delivery ? (nearestBranch?.delivery.fee_rupiah ?? 0) : 0,
        });

        return optimistic.createOrder(draft, payload);
    };

    return {
        createOrder,
        createProcessing: api.createProcessing,
        loadOrders: api.loadOrders,
        orders: optimistic.orders,
        rollbackMessage: optimistic.rollbackMessage,
        setOrders: optimistic.setOrders,
        transitionOrder: optimistic.transitionOrder,
        transitionProcessing: api.transitionProcessing,
    };
};
