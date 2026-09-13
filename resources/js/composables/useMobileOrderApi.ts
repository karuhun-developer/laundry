import { useHttp } from '@inertiajs/vue3';
import { computed } from 'vue';
import { firstRequestError, MobileHttpError, messageFromResponseData } from '@/lib/mobileHttp';
import {
    show as showOrderRoute,
    store as storeOrderRoute,
} from '@/routes/api/v1/organizations/branches/orders';
import { store as storeStatusRoute } from '@/routes/api/v1/organizations/branches/orders/status-events';
import { index as orderIndexRoute } from '@/routes/api/v1/organizations/orders';
import type {
    ApiEnvelope,
    CreateOrderPayload,
    MobileOrder,
    TransitionOrderPayload,
} from '@/types/mobile';

type EmptyRequest = Record<string, never>;

export const useMobileOrderApi = () => {
    const ordersRequest = useHttp<EmptyRequest, ApiEnvelope<MobileOrder[]>>({});
    const detailRequest = useHttp<EmptyRequest, ApiEnvelope<MobileOrder>>({});
    const createRequest = useHttp<CreateOrderPayload, ApiEnvelope<MobileOrder>>({ items: [] });
    const transitionRequest = useHttp<TransitionOrderPayload, ApiEnvelope<MobileOrder>>({
        status: 'received',
        note: '',
    });

    const loadOrders = async (organizationSlug: string): Promise<MobileOrder[]> => {
        ordersRequest.cancel();
        const response = await ordersRequest.get(orderIndexRoute(organizationSlug).url);

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
        createRequest.items = payload.items;
        createRequest.delivery = payload.delivery;
        const response = await createRequest.post(
            storeOrderRoute({ organization: organizationSlug, branch: branchId }).url,
        );

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

    return {
        createOrder,
        createProcessing: computed(() => createRequest.processing),
        fetchOrder,
        loadOrders,
        transitionOrder,
        transitionProcessing: computed(() => transitionRequest.processing),
    };
};
