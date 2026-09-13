import { usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { readonly, ref } from 'vue';
import { useMobileOrderApi } from '@/composables/useMobileOrderApi';
import { useMobilePaymentApi } from '@/composables/useMobilePaymentApi';
import { useOptimisticOrders } from '@/composables/useOptimisticOrders';
import { buildDraftOrder, findCatalogOption } from '@/lib/mobileOrders';
import type {
    CreateOrderPayload,
    CreatePaymentPayload,
    MobileBranch,
    MobileContext,
    MobileCustomer,
    MobileOrderReceipt,
    MobilePriceQuote,
    MobileService,
    NearestMobileBranch,
    TransitionOrderPayload,
    TransitionPaymentStatusPayload,
    WhatsAppSuggestion,
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
    const paymentApi = useMobilePaymentApi();
    const whatsAppSuggestion = ref<WhatsAppSuggestion | null>(null);
    const whatsAppSuggestionError = ref<string | null>(null);
    let whatsAppSuggestionGeneration = 0;
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
        createPayment: async (order, payload) => {
            const context = state.context.value;

            if (!context) {
                throw new Error('Konteks organisasi tidak tersedia.');
            }

            return paymentApi.createPayment(context.organization.slug, order, payload);
        },
        transitionPaymentStatus: async (order, payment, payload) => {
            const context = state.context.value;

            if (!context) {
                throw new Error('Konteks organisasi tidak tersedia.');
            }

            return paymentApi.transitionPaymentStatus(
                context.organization.slug,
                order,
                payment,
                payload,
            );
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

    const createPayment = (orderId: number, payload: CreatePaymentPayload): Promise<boolean> =>
        optimistic.createPayment(orderId, payload);

    const clearWhatsAppSuggestion = (): void => {
        whatsAppSuggestionGeneration += 1;
        whatsAppSuggestion.value = null;
        whatsAppSuggestionError.value = null;
    };

    const setOrders = (orders: Parameters<typeof optimistic.setOrders>[0]): void => {
        clearWhatsAppSuggestion();
        optimistic.setOrders(orders);
    };

    const transitionOrder = async (
        orderId: number,
        payload: TransitionOrderPayload,
    ): Promise<boolean> => {
        const context = state.context.value;
        const order = optimistic.orders.value.find((entry) => entry.id === orderId);
        const requestGeneration = ++whatsAppSuggestionGeneration;
        whatsAppSuggestion.value = null;
        whatsAppSuggestionError.value = null;
        const saved = await optimistic.transitionOrder(orderId, payload);

        if (!saved || !context || !order) {
            return saved;
        }

        try {
            const suggestion = await api.fetchWhatsAppSuggestion(context.organization.slug, order);

            if (requestGeneration === whatsAppSuggestionGeneration) {
                whatsAppSuggestion.value = suggestion;
            }
        } catch (error: unknown) {
            if (requestGeneration === whatsAppSuggestionGeneration) {
                whatsAppSuggestionError.value =
                    error instanceof Error
                        ? error.message
                        : 'Saran pesan WhatsApp tidak dapat dimuat.';
            }
        }

        return true;
    };

    const transitionPaymentStatus = (
        orderId: number,
        paymentId: number,
        payload: TransitionPaymentStatusPayload,
    ): Promise<boolean> => optimistic.transitionPaymentStatus(orderId, paymentId, payload);

    const loadReceipt = async (orderId: number): Promise<MobileOrderReceipt> => {
        const context = state.context.value;
        const order = optimistic.orders.value.find((entry) => entry.id === orderId);

        if (!context || !order) {
            throw new Error('Pesanan tidak lagi tersedia. Muat ulang daftar pesanan.');
        }

        return paymentApi.loadReceipt(context.organization.slug, order);
    };

    return {
        createOrder,
        createPayment,
        createProcessing: api.createProcessing,
        loadReceipt,
        loadOrders: api.loadOrders,
        orders: optimistic.orders,
        rollbackMessage: optimistic.rollbackMessage,
        setOrders,
        transitionOrder,
        transitionProcessing: api.transitionProcessing,
        transitionPaymentStatus,
        paymentProcessing: paymentApi.processing,
        whatsAppSuggestion: readonly(whatsAppSuggestion),
        whatsAppSuggestionError: readonly(whatsAppSuggestionError),
    };
};
