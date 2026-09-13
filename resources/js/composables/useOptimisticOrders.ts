import { readonly, ref } from 'vue';
import type { CreateOrderPayload, MobileOrder, TransitionOrderPayload } from '@/types/mobile';

export type MobileOrderApi = {
    createOrder: (payload: CreateOrderPayload) => Promise<MobileOrder>;
    fetchOrder: (order: MobileOrder) => Promise<MobileOrder>;
    transitionOrder: (order: MobileOrder, payload: TransitionOrderPayload) => Promise<MobileOrder>;
};

const isConflictError = (error: unknown): error is Error & { status: number } =>
    error instanceof Error && 'status' in error && error.status === 409;

export const useOptimisticOrders = (api: MobileOrderApi) => {
    const orders = ref<MobileOrder[]>([]);
    const rollbackMessage = ref<string | null>(null);
    let collectionGeneration = 0;

    const setOrders = (nextOrders: MobileOrder[]): void => {
        collectionGeneration += 1;
        rollbackMessage.value = null;
        orders.value = nextOrders;
    };

    const clearRollbackMessage = (): void => {
        rollbackMessage.value = null;
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
                rollbackMessage.value =
                    error instanceof Error
                        ? error.message
                        : 'Pesanan gagal disimpan. Perubahan sementara sudah dibatalkan.';
            }

            return false;
        }
    };

    const transitionOrder = async (
        orderId: number,
        payload: TransitionOrderPayload,
    ): Promise<boolean> => {
        rollbackMessage.value = null;
        const index = orders.value.findIndex((order) => order.id === orderId);

        if (index < 0) {
            rollbackMessage.value = 'Pesanan tidak lagi tersedia. Muat ulang daftar pesanan.';
            return false;
        }

        const canonicalOrder = orders.value[index];

        if (!canonicalOrder) {
            rollbackMessage.value = 'Pesanan tidak lagi tersedia. Muat ulang daftar pesanan.';
            return false;
        }

        const operationGeneration = collectionGeneration;

        const optimisticOrder: MobileOrder = {
            ...canonicalOrder,
            status: payload.status,
            pending: true,
            status_history: [
                ...canonicalOrder.status_history,
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

        orders.value = orders.value.map((order) =>
            order.id === orderId ? optimisticOrder : order,
        );

        try {
            const nextOrder = await api.transitionOrder(canonicalOrder, payload);

            if (operationGeneration === collectionGeneration) {
                orders.value = orders.value.map((order) =>
                    order.id === orderId ? nextOrder : order,
                );
            }

            return true;
        } catch (error: unknown) {
            if (operationGeneration !== collectionGeneration) {
                return false;
            }

            orders.value = orders.value.map((order) =>
                order.id === orderId ? canonicalOrder : order,
            );

            if (isConflictError(error)) {
                try {
                    const refreshedOrder = await api.fetchOrder(canonicalOrder);

                    if (operationGeneration !== collectionGeneration) {
                        return false;
                    }

                    orders.value = orders.value.map((order) =>
                        order.id === orderId ? refreshedOrder : order,
                    );
                    rollbackMessage.value = `${error.message}; data terbaru sudah dimuat.`;
                } catch {
                    if (operationGeneration === collectionGeneration) {
                        rollbackMessage.value = `${error.message}; data terbaru belum dapat dimuat.`;
                    }
                }

                return false;
            }

            rollbackMessage.value =
                error instanceof Error
                    ? error.message
                    : 'Status gagal diperbarui. Status sebelumnya sudah dipulihkan.';

            return false;
        }
    };

    return {
        orders: readonly(orders),
        rollbackMessage: readonly(rollbackMessage),
        clearRollbackMessage,
        createOrder,
        setOrders,
        transitionOrder,
    };
};
