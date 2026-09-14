import { useHttp } from '@inertiajs/vue3';
import { computed } from 'vue';
import { firstRequestError, MobileHttpError, messageFromResponseData } from '@/lib/mobileHttp';
import { store as storePaymentRoute } from '@/routes/api/v1/organizations/branches/orders/payments';
import { store as storePaymentStatusRoute } from '@/routes/api/v1/organizations/branches/orders/payments/status-events';
import { show as showReceiptRoute } from '@/routes/api/v1/organizations/branches/orders/receipt';
import { index as paymentGatewayMethodsRoute } from '@/routes/api/v1/payment-gateway-methods';
import type {
    ApiEnvelope,
    CreatePaymentPayload,
    MobileOrder,
    MobileOrderReceipt,
    MobilePayment,
    MobilePaymentGatewayMethod,
    TransitionPaymentStatusPayload,
} from '@/types/mobile';

type EmptyRequest = Record<string, never>;

export const useMobilePaymentApi = () => {
    const createRequest = useHttp<CreatePaymentPayload, ApiEnvelope<MobilePayment>>({
        method: 'cash',
        amount_rupiah: 1,
        note: '',
    });
    const statusRequest = useHttp<TransitionPaymentStatusPayload, ApiEnvelope<MobilePayment>>({
        status: 'pending',
        note: '',
    });
    const receiptRequest = useHttp<EmptyRequest, ApiEnvelope<MobileOrderReceipt>>({});
    const gatewayMethodsRequest = useHttp<
        EmptyRequest,
        ApiEnvelope<readonly MobilePaymentGatewayMethod[]>
    >({});

    const createPayment = async (
        organizationSlug: string,
        order: MobileOrder,
        payload: CreatePaymentPayload,
    ): Promise<MobilePayment> => {
        createRequest.method = payload.method;
        createRequest.amount_rupiah = payload.amount_rupiah;
        createRequest.note = payload.note;
        createRequest.gateway_driver = payload.gateway_driver;
        createRequest.gateway_method = payload.gateway_method;
        const httpException = { value: null as MobileHttpError | null };
        let response: ApiEnvelope<MobilePayment> | undefined;

        try {
            response = await createRequest.post(
                storePaymentRoute({
                    organization: organizationSlug,
                    branch: order.branch.id,
                    order: order.id,
                }).url,
                {
                    onHttpException: (exceptionResponse) => {
                        httpException.value = new MobileHttpError(
                            messageFromResponseData(
                                exceptionResponse.data,
                                'Pembayaran tidak dapat dibuat.',
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
                    'Pembayaran gagal disimpan. Periksa nominal lalu coba lagi.',
                ),
            );
        }

        return response.data;
    };

    const transitionPaymentStatus = async (
        organizationSlug: string,
        order: MobileOrder,
        payment: MobilePayment,
        payload: TransitionPaymentStatusPayload,
    ): Promise<MobilePayment> => {
        statusRequest.status = payload.status;
        statusRequest.note = payload.note;
        const httpException = { value: null as MobileHttpError | null };
        let response: ApiEnvelope<MobilePayment> | undefined;

        try {
            response = await statusRequest.post(
                storePaymentStatusRoute({
                    organization: organizationSlug,
                    branch: order.branch.id,
                    order: order.id,
                    payment: payment.id,
                }).url,
                {
                    onHttpException: (exceptionResponse) => {
                        httpException.value = new MobileHttpError(
                            messageFromResponseData(
                                exceptionResponse.data,
                                'Status pembayaran tidak dapat diperbarui.',
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
                    statusRequest.errors,
                    'Status pembayaran gagal disimpan. Coba lagi.',
                ),
            );
        }

        return response.data;
    };

    const loadReceipt = async (
        organizationSlug: string,
        order: MobileOrder,
    ): Promise<MobileOrderReceipt> => {
        receiptRequest.cancel();
        const response = await receiptRequest.get(
            showReceiptRoute({
                organization: organizationSlug,
                branch: order.branch.id,
                order: order.id,
            }).url,
        );

        if (!response) {
            throw new Error('Struk digital tidak dapat dimuat.');
        }

        return response.data;
    };

    const loadPaymentGatewayMethods = async (): Promise<readonly MobilePaymentGatewayMethod[]> => {
        gatewayMethodsRequest.cancel();
        const response = await gatewayMethodsRequest.get(paymentGatewayMethodsRoute().url);

        if (!response) {
            throw new Error('Metode pembayaran online tidak dapat dimuat.');
        }

        return response.data;
    };

    return {
        createPayment,
        loadPaymentGatewayMethods,
        loadReceipt,
        processing: computed(
            () =>
                createRequest.processing ||
                statusRequest.processing ||
                receiptRequest.processing ||
                gatewayMethodsRequest.processing,
        ),
        transitionPaymentStatus,
    };
};
