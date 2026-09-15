import { useHttp } from '@inertiajs/vue3';
import { computed } from 'vue';
import { firstRequestError, MobileHttpError, messageFromResponseData } from '@/lib/mobileHttp';
import type { CustomerOrderFilters } from '@/lib/mobileOrderFilters';
import { index as customerOrderIndexRoute } from '@/routes/api/v1/mobile/orders';
import {
    show as showOrderRoute,
    store as storeOrderRoute,
} from '@/routes/api/v1/organizations/branches/orders';
import { store as storePricingRoute } from '@/routes/api/v1/organizations/branches/orders/pricing';
import { store as storeStatusRoute } from '@/routes/api/v1/organizations/branches/orders/status-events';
import { show as showWhatsAppSuggestionRoute } from '@/routes/api/v1/organizations/branches/orders/whatsapp-suggestion';
import { index as orderIndexRoute } from '@/routes/api/v1/organizations/orders';
import type {
    ApiEnvelope,
    ConfirmOrderPricingPayload,
    CreateOrderPayload,
    MobileOrder,
    TransitionOrderPayload,
    WhatsAppSuggestion,
} from '@/types/mobile';

type EmptyRequest = Record<string, never>;
type MobileOrderFilters = Partial<CustomerOrderFilters>;

export const useMobileOrderApi = () => {
    const ordersRequest = useHttp<EmptyRequest, ApiEnvelope<MobileOrder[]>>({});
    const detailRequest = useHttp<EmptyRequest, ApiEnvelope<MobileOrder>>({});
    const createRequest = useHttp<CreateOrderPayload, ApiEnvelope<MobileOrder>>({
        customer_id: undefined,
        customer_address_id: undefined,
        items: [],
        delivery: undefined,
    });
    const transitionRequest = useHttp<TransitionOrderPayload, ApiEnvelope<MobileOrder>>({
        status: 'received',
        note: '',
    });
    const pricingRequest = useHttp<ConfirmOrderPricingPayload, ApiEnvelope<MobileOrder>>({
        price_option_id: 0,
        weight_grams: 1,
    });
    const whatsAppSuggestionRequest = useHttp<EmptyRequest, ApiEnvelope<WhatsAppSuggestion>>({});

    const loadOrders = async (
        organizationSlug?: string,
        filters: MobileOrderFilters = {},
    ): Promise<MobileOrder[]> => {
        ordersRequest.cancel();
        const query = {
            ...(filters.search?.trim() ? { search: filters.search.trim() } : {}),
            ...(filters.date_from ? { date_from: filters.date_from } : {}),
            ...(filters.date_to ? { date_to: filters.date_to } : {}),
        };
        const response = await ordersRequest.get(
            organizationSlug
                ? orderIndexRoute(organizationSlug, {
                      query,
                  }).url
                : customerOrderIndexRoute({ query }).url,
        );

        if (!response) {
            throw new Error('Daftar pesanan tidak dapat dimuat.');
        }

        return response.data;
    };

    const createOrder = async (
        organizationSlug: string,
        branchId: number,
        payload: CreateOrderPayload,
    ): Promise<MobileOrder> => {
        createRequest.customer_id = payload.customer_id;
        createRequest.customer_address_id = payload.customer_address_id;
        createRequest.items = payload.items;
        createRequest.delivery = payload.delivery;
        const httpException = { value: null as MobileHttpError | null };
        let response: ApiEnvelope<MobileOrder> | undefined;

        try {
            response = await createRequest.post(
                storeOrderRoute({ organization: organizationSlug, branch: branchId }).url,
                {
                    onHttpException: (exceptionResponse) => {
                        httpException.value = new MobileHttpError(
                            messageFromResponseData(
                                exceptionResponse.data,
                                'Pesanan tidak dapat dibuat.',
                            ),
                            exceptionResponse.status,
                        );
                    },
                },
            );
        } catch (error: unknown) {
            if (httpException.value) {
                throw httpException.value;
            }

            throw error;
        }

        if (!response) {
            throw new Error(
                firstRequestError(
                    createRequest.errors,
                    'Periksa kembali data pesanan lalu coba lagi.',
                ),
            );
        }

        return response.data;
    };

    const fetchOrder = async (
        organizationSlug: string,
        order: MobileOrder,
    ): Promise<MobileOrder> => {
        detailRequest.cancel();
        const response = await detailRequest.get(
            showOrderRoute({
                organization: organizationSlug,
                branch: order.branch.id,
                order: order.id,
            }).url,
        );

        if (!response) {
            throw new Error('Data pesanan terbaru tidak dapat dimuat.');
        }

        return response.data;
    };

    const transitionOrder = async (
        organizationSlug: string,
        order: MobileOrder,
        payload: TransitionOrderPayload,
    ): Promise<MobileOrder> => {
        transitionRequest.status = payload.status;
        transitionRequest.note = payload.note;
        const httpException = { value: null as MobileHttpError | null };
        let response: ApiEnvelope<MobileOrder> | undefined;

        try {
            response = await transitionRequest.post(
                storeStatusRoute({
                    organization: organizationSlug,
                    branch: order.branch.id,
                    order: order.id,
                }).url,
                {
                    onHttpException: (exceptionResponse) => {
                        httpException.value = new MobileHttpError(
                            messageFromResponseData(
                                exceptionResponse.data,
                                'Status pesanan tidak dapat diperbarui.',
                            ),
                            exceptionResponse.status,
                        );
                    },
                },
            );
        } catch (error: unknown) {
            if (httpException.value) {
                throw httpException.value;
            }

            throw error;
        }

        if (!response) {
            throw new Error(
                firstRequestError(
                    transitionRequest.errors,
                    'Status tidak dapat disimpan. Periksa data lalu coba lagi.',
                ),
            );
        }

        return response.data;
    };

    const confirmOrderPricing = async (
        organizationSlug: string,
        order: MobileOrder,
        payload: ConfirmOrderPricingPayload,
    ): Promise<MobileOrder> => {
        pricingRequest.price_option_id = payload.price_option_id;
        pricingRequest.weight_grams = payload.weight_grams;
        const httpException = { value: null as MobileHttpError | null };
        let response: ApiEnvelope<MobileOrder> | undefined;

        try {
            response = await pricingRequest.post(
                storePricingRoute({
                    organization: organizationSlug,
                    branch: order.branch.id,
                    order: order.id,
                }).url,
                {
                    onHttpException: (exceptionResponse) => {
                        httpException.value = new MobileHttpError(
                            messageFromResponseData(
                                exceptionResponse.data,
                                'Berat dan harga tidak dapat dikonfirmasi.',
                            ),
                            exceptionResponse.status,
                        );
                    },
                },
            );
        } catch (error: unknown) {
            if (httpException.value) {
                throw httpException.value;
            }

            throw error;
        }

        if (!response) {
            throw new Error(
                firstRequestError(
                    pricingRequest.errors,
                    'Berat dan harga gagal disimpan. Periksa data lalu coba lagi.',
                ),
            );
        }

        return response.data;
    };

    const fetchWhatsAppSuggestion = async (
        organizationSlug: string,
        order: MobileOrder,
    ): Promise<WhatsAppSuggestion> => {
        whatsAppSuggestionRequest.cancel();
        const response = await whatsAppSuggestionRequest.get(
            showWhatsAppSuggestionRoute({
                organization: organizationSlug,
                branch: order.branch.id,
                order: order.id,
            }).url,
        );

        if (!response) {
            throw new Error('Saran pesan WhatsApp tidak dapat dimuat.');
        }

        return response.data;
    };

    return {
        confirmOrderPricing,
        createOrder,
        createProcessing: computed(() => createRequest.processing),
        fetchOrder,
        fetchWhatsAppSuggestion,
        loadOrders,
        pricingProcessing: computed(() => pricingRequest.processing),
        transitionOrder,
        transitionProcessing: computed(() => transitionRequest.processing),
    };
};
