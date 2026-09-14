import type { MobileOrderApi, OptimisticOrderState } from '@/composables/optimisticOrderTypes';
import { errorMessage, isConflictError } from '@/composables/optimisticOrderTypes';
import type { ConfirmOrderPricingPayload, MobileOrder } from '@/types/mobile';

const missingOrderMessage = 'Pesanan tidak lagi tersedia. Muat ulang daftar pesanan.';

export const useOptimisticOrderPricing = (state: OptimisticOrderState, api: MobileOrderApi) => {
    const confirmOrderPricing = async (
        orderId: number,
        payload: ConfirmOrderPricingPayload,
    ): Promise<boolean> => {
        state.rollbackMessage.value = null;
        const canonicalOrder = state.orders.value.find((order) => order.id === orderId);

        if (!canonicalOrder) {
            state.rollbackMessage.value = missingOrderMessage;
            return false;
        }

        const operationGeneration = state.generation();
        const optimisticOrder: MobileOrder = { ...canonicalOrder, pending: true };
        state.orders.value = state.orders.value.map((order) =>
            order.id === orderId ? optimisticOrder : order,
        );

        try {
            const nextOrder = await api.confirmOrderPricing(canonicalOrder, payload);

            if (operationGeneration === state.generation()) {
                state.orders.value = state.orders.value.map((order) =>
                    order.id === orderId ? nextOrder : order,
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
                    'Konfirmasi harga gagal. Data pesanan sebelumnya sudah dipulihkan.',
                );
                return false;
            }

            try {
                const refreshedOrder = await api.fetchOrder(canonicalOrder);

                if (operationGeneration === state.generation()) {
                    state.orders.value = state.orders.value.map((order) =>
                        order.id === orderId ? refreshedOrder : order,
                    );
                    state.rollbackMessage.value = `${error.message}; data terbaru sudah dimuat.`;
                }
            } catch (refreshError: unknown) {
                if (operationGeneration === state.generation()) {
                    state.rollbackMessage.value = `${error.message}; data terbaru belum dapat dimuat: ${errorMessage(refreshError, 'kesalahan tidak diketahui')}`;
                }
            }

            return false;
        }
    };

    return { confirmOrderPricing };
};
