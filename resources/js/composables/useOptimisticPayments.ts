import type { MobileOrderApi, OptimisticOrderState } from '@/composables/optimisticOrderTypes';
import { errorMessage, isConflictError } from '@/composables/optimisticOrderTypes';
import { buildDraftPayment, paymentStatusLabels, summarizePayments } from '@/lib/mobilePayments';
import type {
    CreatePaymentPayload,
    MobileOrder,
    MobilePayment,
    TransitionPaymentStatusPayload,
} from '@/types/mobile';

const missingPaymentMessage = 'Pembayaran tidak lagi tersedia. Muat ulang daftar pesanan.';

const replaceOrderPayments = (
    order: MobileOrder,
    payments: readonly MobilePayment[],
): MobileOrder => ({
    ...order,
    payments,
    payment_summary: summarizePayments(order.pricing.total_rupiah, payments),
});

export const useOptimisticPayments = (state: OptimisticOrderState, api: MobileOrderApi) => {
    const createPayment = async (
        orderId: number,
        payload: CreatePaymentPayload,
    ): Promise<boolean> => {
        state.rollbackMessage.value = null;
        const canonicalOrder = state.orders.value.find((order) => order.id === orderId);

        if (!canonicalOrder) {
            state.rollbackMessage.value = missingPaymentMessage;
            return false;
        }

        const operationGeneration = state.generation();
        const draftPayment = buildDraftPayment(payload);
        state.orders.value = state.orders.value.map((order) =>
            order.id === orderId
                ? replaceOrderPayments(order, [...canonicalOrder.payments, draftPayment])
                : order,
        );

        try {
            const payment = await api.createPayment(canonicalOrder, payload);

            if (operationGeneration === state.generation()) {
                state.orders.value = state.orders.value.map((order) =>
                    order.id === orderId
                        ? replaceOrderPayments(
                              order,
                              order.payments.map((currentPayment) =>
                                  currentPayment.id === draftPayment.id ? payment : currentPayment,
                              ),
                          )
                        : order,
                );
            }

            return true;
        } catch (error: unknown) {
            if (operationGeneration === state.generation()) {
                state.orders.value = state.orders.value.map((order) =>
                    order.id === orderId ? canonicalOrder : order,
                );
                state.rollbackMessage.value = errorMessage(
                    error,
                    'Pembayaran gagal disimpan. Perubahan sementara sudah dibatalkan.',
                );
            }

            return false;
        }
    };

    const transitionPaymentStatus = async (
        orderId: number,
        paymentId: number,
        payload: TransitionPaymentStatusPayload,
    ): Promise<boolean> => {
        state.rollbackMessage.value = null;
        const canonicalOrder = state.orders.value.find((order) => order.id === orderId);
        const canonicalPayment = canonicalOrder?.payments.find(
            (payment) => payment.id === paymentId,
        );

        if (!canonicalOrder || !canonicalPayment) {
            state.rollbackMessage.value = missingPaymentMessage;
            return false;
        }

        const operationGeneration = state.generation();
        const optimisticPayment: MobilePayment = {
            ...canonicalPayment,
            status: payload.status,
            status_label: paymentStatusLabels[payload.status],
            settled_at: payload.status === 'paid' ? new Date().toISOString() : null,
            pending: true,
            status_history: [
                ...canonicalPayment.status_history,
                {
                    id: -Date.now(),
                    status: payload.status,
                    actor: { id: 0, name: 'Menunggu konfirmasi server' },
                    note: payload.note ?? null,
                    occurred_at: new Date().toISOString(),
                    pending: true,
                },
            ],
        };

        state.orders.value = state.orders.value.map((order) =>
            order.id === orderId
                ? replaceOrderPayments(
                      order,
                      canonicalOrder.payments.map((payment) =>
                          payment.id === paymentId ? optimisticPayment : payment,
                      ),
                  )
                : order,
        );

        try {
            const payment = await api.transitionPaymentStatus(
                canonicalOrder,
                canonicalPayment,
                payload,
            );

            if (operationGeneration === state.generation()) {
                state.orders.value = state.orders.value.map((order) =>
                    order.id === orderId
                        ? replaceOrderPayments(
                              order,
                              order.payments.map((currentPayment) =>
                                  currentPayment.id === paymentId ? payment : currentPayment,
                              ),
                          )
                        : order,
                );
            }

            return true;
        } catch (error: unknown) {
            if (operationGeneration !== state.generation()) {
                return false;
            }

            state.orders.value = state.orders.value.map((order) =>
                order.id === orderId ? canonicalOrder : order,
            );

            if (!isConflictError(error)) {
                state.rollbackMessage.value = errorMessage(
                    error,
                    'Status pembayaran gagal diperbarui. Status sebelumnya sudah dipulihkan.',
                );
                return false;
            }

            try {
                const refreshedOrder = await api.fetchOrder(canonicalOrder);

                if (operationGeneration === state.generation()) {
                    state.orders.value = state.orders.value.map((order) =>
                        order.id === orderId ? refreshedOrder : order,
                    );
                    state.rollbackMessage.value = `${error.message}; data pembayaran terbaru sudah dimuat.`;
                }
            } catch (refreshError: unknown) {
                if (operationGeneration === state.generation()) {
                    state.rollbackMessage.value = `${error.message}; data pembayaran terbaru belum dapat dimuat: ${errorMessage(refreshError, 'kesalahan tidak diketahui')}`;
                }
            }

            return false;
        }
    };

    return { createPayment, transitionPaymentStatus };
};
