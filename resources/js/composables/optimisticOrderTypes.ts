import type { Ref } from 'vue';
import type {
    CreateOrderPayload,
    CreatePaymentPayload,
    MobileOrder,
    MobilePayment,
    TransitionOrderPayload,
    TransitionPaymentStatusPayload,
} from '@/types/mobile';

export type MobileOrderApi = {
    createOrder: (payload: CreateOrderPayload) => Promise<MobileOrder>;
    fetchOrder: (order: MobileOrder) => Promise<MobileOrder>;
    transitionOrder: (order: MobileOrder, payload: TransitionOrderPayload) => Promise<MobileOrder>;
    createPayment: (order: MobileOrder, payload: CreatePaymentPayload) => Promise<MobilePayment>;
    transitionPaymentStatus: (
        order: MobileOrder,
        payment: MobilePayment,
        payload: TransitionPaymentStatusPayload,
    ) => Promise<MobilePayment>;
};

export type OptimisticOrderState = {
    orders: Ref<MobileOrder[]>;
    rollbackMessage: Ref<string | null>;
    generation: () => number;
};

export const isConflictError = (error: unknown): error is Error & { status: number } =>
    error instanceof Error && 'status' in error && error.status === 409;

export const errorMessage = (error: unknown, fallback: string): string =>
    error instanceof Error ? error.message : fallback;
