import { usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { readonly, ref } from 'vue';
import { useMobileOrderApi } from '@/composables/useMobileOrderApi';
import { useMobilePaymentApi } from '@/composables/useMobilePaymentApi';
import { useOptimisticOrders } from '@/composables/useOptimisticOrders';
import { buildDraftOrder, findCatalogOption } from '@/lib/mobileOrders';
import type {
    ConfirmOrderPricingPayload,
    CreateOrderPayload,
    CreatePaymentPayload,
    MobileBranch,
    MobileContext,
    MobileCustomer,
    MobileOrder,
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
            const branch = state.selectedBranch.value;

            if (!branch) {
                throw new Error('Pilih cabang sebelum membuat pesanan.');
            }

            return api.createOrder(branch.organization.slug, branch.id, payload);
        },
        fetchOrder: async (order) => api.fetchOrder(order.organization.slug, order),
        confirmOrderPricing: async (order, payload) =>
            api.confirmOrderPricing(order.organization.slug, order, payload),
        transitionOrder: async (order, payload) =>
            api.transitionOrder(order.organization.slug, order, payload),
        createPayment: async (order, payload) =>
            paymentApi.createPayment(order.organization.slug, order, payload),
        transitionPaymentStatus: async (order, payment, payload) => {
            return paymentApi.transitionPaymentStatus(
                order.organization.slug,
                order,
                payment,
                payload,
            );
        },
    });

    const createOrder = async (
        payload: CreateOrderPayload,
        quote: MobilePriceQuote | null,
    ): Promise<boolean> => {
        state.setRequestError(null);
        const branch = state.selectedBranch.value;
        const item = payload.items[0];
        const isCustomer = state.context.value?.role === 'customer';
        const catalogEntry = item
            ? findCatalogOption(state.services.value, item.price_option_id)
            : null;

        if (!branch || !item || !catalogEntry) {
            state.setRequestError('Pilih cabang dan layanan laundry.');
            return false;
        }

        if (
            isCustomer &&
            (!payload.customer_address_id || item.weight_grams !== undefined || quote !== null)
        ) {
            state.setRequestError('Pilih alamat penjemputan. Berat akan diisi petugas.');
            return false;
        }

        if (
            !isCustomer &&
            (!quote ||
                quote.price_option.id !== item.price_option_id ||
                quote.requested_weight_grams !== item.weight_grams)
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

        if (isCustomer) {
            try {
                const order = await api.createOrder(branch.organization.slug, branch.id, payload);
                optimistic.setOrders([
                    order,
                    ...optimistic.orders.value.filter((entry) => entry.id !== order.id),
                ]);

                return true;
            } catch (error: unknown) {
                state.setRequestError(
                    error instanceof Error ? error.message : 'Pesanan tidak dapat dibuat.',
                );

                return false;
            }
        }

        const nearestBranch = state.nearestBranches.value.find((entry) => entry.id === branch.id);
        const draft = buildDraftOrder({
            branch,
            customer: { id: customer.id, name: customer.name },
            option: catalogEntry.option,
            quote,
            service: catalogEntry.service,
            deliveryFeeRupiah: payload.delivery ? (nearestBranch?.delivery.fee_rupiah ?? 0) : 0,
        });

        return optimistic.createOrder(draft, payload);
    };

    const createPayment = (orderId: number, payload: CreatePaymentPayload): Promise<boolean> =>
        optimistic.createPayment(orderId, payload);

    const refreshOrder = async (orderId: number): Promise<MobileOrder> => {
        const order = optimistic.orders.value.find((entry) => entry.id === orderId);

        if (!order) {
            throw new Error('Pesanan tidak lagi tersedia. Muat ulang daftar pesanan.');
        }

        const refreshedOrder = await api.fetchOrder(order.organization.slug, order);
        optimistic.setOrders(
            optimistic.orders.value.map((entry) =>
                entry.id === refreshedOrder.id ? refreshedOrder : entry,
            ),
        );

        return refreshedOrder;
    };

    const loadPaymentWhatsAppSuggestion = async (
        orderId: number,
        paymentId: number,
    ): Promise<WhatsAppSuggestion> => {
        const order = optimistic.orders.value.find((entry) => entry.id === orderId);
        const payment = order?.payments.find((entry) => entry.id === paymentId);

        if (!order || !payment) {
            throw new Error('Pembayaran tidak lagi tersedia. Muat ulang detail pesanan.');
        }

        return paymentApi.loadPaymentWhatsAppSuggestion(order.organization.slug, order, payment);
    };

    const loadStatusWhatsAppSuggestion = async (orderId: number): Promise<WhatsAppSuggestion> => {
        const order = optimistic.orders.value.find((entry) => entry.id === orderId);

        if (state.context.value?.role !== 'branch_admin' || !order || order.pending) {
            throw new Error('Pesanan belum tersedia untuk notifikasi. Muat ulang daftar pesanan.');
        }

        return api.fetchWhatsAppSuggestion(order.organization.slug, order);
    };

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
        const order = optimistic.orders.value.find((entry) => entry.id === orderId);
        const requestGeneration = ++whatsAppSuggestionGeneration;
        whatsAppSuggestion.value = null;
        whatsAppSuggestionError.value = null;
        const saved = await optimistic.transitionOrder(orderId, payload);

        if (!saved || !order) {
            return saved;
        }

        try {
            const suggestion = await api.fetchWhatsAppSuggestion(order.organization.slug, order);

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

    const confirmOrderPricing = async (
        orderId: number,
        payload: ConfirmOrderPricingPayload,
    ): Promise<boolean> => {
        const order = optimistic.orders.value.find((entry) => entry.id === orderId);
        const requestGeneration = ++whatsAppSuggestionGeneration;
        whatsAppSuggestion.value = null;
        whatsAppSuggestionError.value = null;
        const saved = await optimistic.confirmOrderPricing(orderId, payload);

        if (!saved || !order) {
            return saved;
        }

        try {
            const suggestion = await api.fetchWhatsAppSuggestion(order.organization.slug, order);

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
        const order = optimistic.orders.value.find((entry) => entry.id === orderId);

        if (!order) {
            throw new Error('Pesanan tidak lagi tersedia. Muat ulang daftar pesanan.');
        }

        return paymentApi.loadReceipt(order.organization.slug, order);
    };

    return {
        pagination: api.pagination,
        confirmOrderPricing,
        createOrder,
        createPayment,
        createProcessing: api.createProcessing,
        loadPaymentWhatsAppSuggestion,
        loadStatusWhatsAppSuggestion,
        loadReceipt,
        loadPaymentGatewayMethods: paymentApi.loadPaymentGatewayMethods,
        loadOrders: api.loadOrders,
        orders: optimistic.orders,
        pricingProcessing: api.pricingProcessing,
        refreshOrder,
        rollbackMessage: optimistic.rollbackMessage,
        setOrders,
        synchronizeOrders: optimistic.synchronizeOrders,
        transitionOrder,
        transitionProcessing: api.transitionProcessing,
        transitionPaymentStatus,
        paymentProcessing: paymentApi.processing,
        whatsAppSuggestion: readonly(whatsAppSuggestion),
        whatsAppSuggestionError: readonly(whatsAppSuggestionError),
    };
};
