import { readonly, ref } from 'vue';
import type { MobileOrderApi, OptimisticOrderState } from '@/composables/optimisticOrderTypes';
import { errorMessage } from '@/composables/optimisticOrderTypes';
import { useOptimisticOrderPricing } from '@/composables/useOptimisticOrderPricing';
import { useOptimisticOrderTransitions } from '@/composables/useOptimisticOrderTransitions';
import { useOptimisticPayments } from '@/composables/useOptimisticPayments';
import type { CreateOrderPayload, MobileOrder } from '@/types/mobile';

export type { MobileOrderApi } from '@/composables/optimisticOrderTypes';

export const useOptimisticOrders = (api: MobileOrderApi) => {
    const orders = ref<MobileOrder[]>([]);
    const rollbackMessage = ref<string | null>(null);
    let collectionGeneration = 0;

    const state: OptimisticOrderState = {
        orders,
        rollbackMessage,
        generation: () => collectionGeneration,
    };
    const { transitionOrder } = useOptimisticOrderTransitions(state, api);
    const { confirmOrderPricing } = useOptimisticOrderPricing(state, api);
    const { createPayment, transitionPaymentStatus } = useOptimisticPayments(state, api);

    const setOrders = (nextOrders: MobileOrder[]): void => {
        collectionGeneration += 1;
        rollbackMessage.value = null;
        orders.value = nextOrders;
    };

    const clearRollbackMessage = (): void => {
        rollbackMessage.value = null;
    };

    const synchronizeOrders = (nextOrders: MobileOrder[]): void => {
        const pending = orders.value.filter(
            (order) => order.pending || order.payments.some((payment) => payment.pending),
        );
        const pendingIds = new Set(pending.map((order) => order.id));
        orders.value = [
            ...pending.filter((order) => !nextOrders.some((next) => next.id === order.id)),
            ...nextOrders.map((order) =>
                pendingIds.has(order.id)
                    ? (pending.find((entry) => entry.id === order.id) ?? order)
                    : order,
            ),
        ];
    };

    const createOrder = async (
        draft: MobileOrder,
        payload: CreateOrderPayload,
    ): Promise<boolean> => {
        const operationGeneration = collectionGeneration;
        rollbackMessage.value = null;
        orders.value = [draft, ...orders.value];

        try {
            const canonicalOrder = await api.createOrder(payload);

            if (operationGeneration === collectionGeneration) {
                orders.value = orders.value.map((order) =>
                    order.id === draft.id ? canonicalOrder : order,
                );
            }

            return true;
        } catch (error: unknown) {
            if (operationGeneration === collectionGeneration) {
                orders.value = orders.value.filter((order) => order.id !== draft.id);
                rollbackMessage.value = errorMessage(
                    error,
                    'Pesanan gagal disimpan. Perubahan sementara sudah dibatalkan.',
                );
            }

            return false;
        }
    };

    return {
        orders: readonly(orders),
        rollbackMessage: readonly(rollbackMessage),
        clearRollbackMessage,
        confirmOrderPricing,
        createOrder,
        createPayment,
        setOrders,
        synchronizeOrders,
        transitionOrder,
        transitionPaymentStatus,
    };
};
