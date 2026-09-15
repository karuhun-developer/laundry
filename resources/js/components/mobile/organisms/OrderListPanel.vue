<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import OrderTicket from '@/components/mobile/molecules/OrderTicket.vue';
import type {
    ConfirmOrderPricingPayload,
    MobileOrder,
    MobileOrderStatus,
    MobilePaymentActions,
    MobileRole,
} from '@/types/mobile';

type Props = {
    orders: readonly MobileOrder[];
    role: MobileRole;
    processing?: boolean;
    title?: string;
    description?: string;
    emptyTitle?: string;
    emptyDescription?: string;
    paymentActions?: MobilePaymentActions;
    paymentProcessing?: boolean;
    pricingProcessing?: boolean;
    printerConfigured?: boolean;
};

withDefaults(defineProps<Props>(), {
    processing: false,
    title: 'Pesanan laundry',
    description: 'Status terbaru dan riwayat proses ada di satu tempat.',
    emptyTitle: 'Belum ada pesanan',
    emptyDescription:
        'Pesanan baru akan langsung muncul di sini dan dapat dilacak dari status pertama.',
    paymentActions: undefined,
    paymentProcessing: false,
    pricingProcessing: false,
    printerConfigured: false,
});

const emit = defineEmits<{
    'confirm-pricing': [orderId: number, payload: ConfirmOrderPricingPayload];
    transition: [orderId: number, status: MobileOrderStatus];
}>();

const onTransition = (orderId: number, status: MobileOrderStatus): void => {
    emit('transition', orderId, status);
};

const onConfirmPricing = (orderId: number, payload: ConfirmOrderPricingPayload): void => {
    emit('confirm-pricing', orderId, payload);
};
</script>

<template>
    <section aria-labelledby="order-list-title">
        <div class="mb-5">
            <p class="text-xs font-semibold tracking-[0.18em] text-brand-600 uppercase">
                Laundry aktif
            </p>
            <h2 id="order-list-title" class="mt-1 font-display text-2xl font-semibold text-ink-950">
                {{ title }}
            </h2>
            <p class="mt-1 text-sm leading-6 text-ink-500">{{ description }}</p>
        </div>

        <div v-if="orders.length" class="space-y-4">
            <OrderTicket
                v-for="order in orders"
                :key="order.id"
                :order="order"
                :role="role"
                :processing="processing"
                :payment-actions="paymentActions"
                :payment-processing="paymentProcessing"
                :pricing-processing="pricingProcessing"
                :printer-configured="printerConfigured"
                @confirm-pricing="onConfirmPricing"
                @transition="onTransition"
            />
        </div>

        <div
            v-else
            class="rounded-2xl border border-dashed border-line bg-surface p-8 text-center shadow-low"
        >
            <span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-brand-100 text-brand-700">
                <Inbox class="size-6" aria-hidden="true" />
            </span>
            <h3 class="mt-4 font-display text-lg font-semibold text-ink-950">{{ emptyTitle }}</h3>
            <p class="mt-1 text-sm leading-6 text-ink-500">
                {{ emptyDescription }}
            </p>
        </div>
    </section>
</template>
