import { useHttp } from '@inertiajs/vue3';
import { computed, readonly, ref } from 'vue';
import { firstRequestError, MobileHttpError, messageFromResponseData } from '@/lib/mobileHttp';
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
    OrderPagination,
    OrderQueue,
    TransitionOrderPayload,
    WhatsAppSuggestion,
} from '@/types/mobile';

type EmptyRequest = Record<string, never>;
export type MobileOrderFilters = {
    search?: string;
    date_from?: string;
    date_to?: string;
    branch_id?: number;
    queue?: OrderQueue;
    status?: string;
    unpaid?: boolean;
    page?: number;
    pages?: number;
};

export const useMobileOrderApi = () => {
    const pagination = ref<OrderPagination>({ current_page: 1, last_page: 1, total: 0 });
    let listGeneration = 0;
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
        custom_price_per_kilogram_rupiah: undefined,
    });
    const whatsAppSuggestionRequest = useHttp<EmptyRequest, ApiEnvelope<WhatsAppSuggestion>>({});

    const loadOrders = async (
        organizationSlug?: string,
        filters: MobileOrderFilters = {},
    ): Promise<MobileOrder[]> => {
        ordersRequest.cancel();
        const generation = ++listGeneration;
        const query = {
            paginated: 1,
            ...(filters.branch_id ? { branch_id: filters.branch_id } : {}),
            ...(filters.queue ? { queue: filters.queue } : {}),
            ...(filters.status ? { status: filters.status } : {}),
            ...(filters.unpaid ? { unpaid: 1 } : {}),
            ...(filters.search?.trim() ? { search: filters.search.trim() } : {}),
            ...(filters.date_from ? { date_from: filters.date_from } : {}),
            ...(filters.date_to ? { date_to: filters.date_to } : {}),
        };
        const orders: MobileOrder[] = [];
        let lastMeta: OrderPagination | undefined;
        for (let page = filters.page ?? 1; page <= (filters.page ?? filters.pages ?? 1); page++) {
            const response = await ordersRequest.get(
                organizationSlug
                    ? orderIndexRoute(organizationSlug, {
                          query: { ...query, page },
                      }).url
                    : customerOrderIndexRoute({ query: { ...query, page } }).url,
            );

            if (!response) {
                throw new Error('Daftar pesanan tidak dapat dimuat.');
            }

            if (generation !== listGeneration) {
                throw new Error('Permintaan daftar pesanan sudah diganti.');
            }
            orders.push(...response.data);
            lastMeta = response.meta
                ? { ...response.meta, statistics: response.statistics }
                : undefined;
            if (!lastMeta || page >= lastMeta.last_page) {
                break;
            }
        }
        pagination.value = lastMeta ?? { current_page: 1, last_page: 1, total: orders.length };
        return orders;
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
        pricingRequest.custom_price_per_kilogram_rupiah = payload.custom_price_per_kilogram_rupiah;
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
        pagination: readonly(pagination),
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
