<script setup lang="ts">
import { ChevronRight, Clock3, MapPin, X } from '@lucide/vue';
import { computed } from 'vue';
import MobileStatusBadge from '@/components/mobile/atoms/MobileStatusBadge.vue';
import PaymentDialog from '@/components/mobile/organisms/PaymentDialog.vue';
import {
    buildOrderTimeline,
    formatOrderDate,
    formatRupiah,
    formatWeight,
    nextOrderStatus,
    orderStatusLabels,
} from '@/lib/mobileOrders';
import type {
    MobileOrder,
    MobileOrderStatus,
    MobilePaymentActions,
    MobileRole,
} from '@/types/mobile';

type Props = {
    order: MobileOrder;
    role: MobileRole;
    processing?: boolean;
    paymentProcessing?: boolean;
    paymentActions?: MobilePaymentActions;
    printerConfigured?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    processing: false,
    paymentProcessing: false,
    paymentActions: undefined,
    printerConfigured: false,
});

const emit = defineEmits<{
    transition: [orderId: number, status: MobileOrderStatus];
}>();

const nextStatus = computed(() => nextOrderStatus(props.order.status));
const timeline = computed(() => buildOrderTimeline(props.order));
const isTerminal = computed(
    () => props.order.status === 'completed' || props.order.status === 'cancelled',
);
</script>

<template>
    <article
        class="order-ticket relative overflow-hidden rounded-2xl border border-line bg-surface shadow-low"
        :data-order-id="order.id"
        :data-pending="order.pending ? 'true' : 'false'"
    >
        <div class="p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-[0.16em] text-ink-500 uppercase">
                        {{ order.branch.code }} · {{ order.branch.name }}
                    </p>
                    <h3 class="mt-1 break-words font-display text-lg font-semibold text-ink-950">
                        {{ order.order_number }}
                    </h3>
                </div>
                <MobileStatusBadge :status="order.status" :pending="order.pending" />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-ink-500">Pelanggan</p>
                    <p class="mt-0.5 truncate font-semibold text-ink-950">
                        {{ order.customer.name }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-ink-500">Total</p>
                    <p class="mt-0.5 font-display font-semibold text-brand-700">
                        {{ formatRupiah(order.pricing.total_rupiah) }}
                    </p>
                </div>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3 rounded-xl bg-canvas px-3 py-2 text-sm">
                <span class="text-ink-500">Pembayaran</span>
                <span
                    class="font-semibold"
                    :class="order.payment_summary.is_paid ? 'text-mint-500' : 'text-amber-700'"
                >
                    {{ order.payment_summary.is_paid ? 'Lunas' : `Sisa ${formatRupiah(order.payment_summary.outstanding_rupiah)}` }}
                </span>
            </div>

            <div class="mt-4 border-t border-dashed border-line pt-4">
                <div
                    v-for="item in order.items"
                    :key="item.id"
                    class="flex items-start justify-between gap-3 text-sm"
                >
                    <div class="min-w-0">
                        <p class="font-medium text-ink-950">{{ item.service.name }}</p>
                        <p class="text-ink-500">
                            {{ item.price_option.name }} ·
                            {{ formatWeight(item.calculation.requested_weight_grams) }}
                        </p>
                    </div>
                    <p class="shrink-0 font-medium text-ink-700">
                        {{ formatRupiah(item.calculation.subtotal_rupiah) }}
                    </p>
                </div>
            </div>
        </div>

        <details class="border-t border-dashed border-line px-5 py-3">
            <summary
                class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-brand-700"
            >
                <span class="flex items-center gap-2">
                    <Clock3 class="size-4" aria-hidden="true" />
                    Lihat perjalanan laundry
                </span>
                <ChevronRight class="size-4" aria-hidden="true" />
            </summary>
            <ol class="space-y-4 pb-2 pt-3" data-test="order-timeline">
                <li
                    v-for="step in timeline"
                    :key="`${step.status}-${step.event?.id ?? 'future'}`"
                    class="relative border-l-2 pl-4"
                    :class="step.state === 'future' ? 'border-line' : 'border-brand-100'"
                    :data-timeline-state="step.state"
                >
                    <span
                        class="absolute top-1 -left-[7px] size-3 rounded-full border-2 border-surface"
                        :class="[
                            step.state === 'future' ? 'bg-line' : step.state === 'current' ? 'bg-amber-500' : 'bg-brand-600',
                            step.event?.pending ? 'animate-pulse' : '',
                        ]"
                    />
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-sm font-semibold"
                                :class="step.state === 'future' ? 'text-ink-500' : 'text-ink-950'"
                            >
                                {{ orderStatusLabels[step.status] }}
                                <span v-if="step.event?.pending" class="text-amber-500">· menunggu</span>
                            </p>
                            <p v-if="step.event" class="text-xs text-ink-500">{{ step.event.actor.name }}</p>
                            <p v-else class="text-xs text-ink-500">Menunggu tahap sebelumnya</p>
                            <p v-if="step.event?.note" class="mt-1 text-sm text-ink-700">
                                {{ step.event.note }}
                            </p>
                        </div>
                        <time v-if="step.event" class="shrink-0 text-right text-xs text-ink-500">
                            {{ formatOrderDate(step.event.occurred_at) }}
                        </time>
                    </div>
                </li>
            </ol>
        </details>

        <div
            v-if="isTerminal"
            class="border-t border-line px-5 py-4 text-sm"
            :class="order.status === 'completed' ? 'bg-mint-500/10 text-mint-500' : 'bg-red-600/5 text-red-600'"
            data-test="terminal-summary"
        >
            {{ order.status === 'completed' ? 'Laundry selesai dan pesanan sudah ditutup.' : 'Pesanan dibatalkan. Tidak ada proses lanjutan.' }}
        </div>

        <div
            v-if="role === 'branch_admin' && !isTerminal"
            class="grid gap-2 border-t border-line bg-canvas p-4 sm:grid-cols-[1fr_auto]"
            data-test="status-controls"
        >
            <button
                v-if="nextStatus"
                type="button"
                class="min-h-11 rounded-xl bg-brand-700 px-4 text-sm font-semibold text-white transition motion-safe:active:scale-[0.98] disabled:opacity-60"
                :disabled="processing || order.pending"
                @click="emit('transition', order.id, nextStatus)"
            >
                Lanjut ke {{ orderStatusLabels[nextStatus] }}
            </button>
            <button
                type="button"
                class="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-red-600/25 px-4 text-sm font-semibold text-red-600 transition hover:bg-red-600/5 motion-safe:active:scale-[0.98] disabled:opacity-60"
                :disabled="processing || order.pending"
                @click="emit('transition', order.id, 'cancelled')"
            >
                <X class="size-4" aria-hidden="true" />
                Batalkan
            </button>
        </div>

        <div v-if="role === 'branch_admin' && paymentActions" class="border-t border-line bg-surface px-4 py-3">
            <PaymentDialog
                :order="order"
                :actions="paymentActions"
                :processing="paymentProcessing"
                :printer-configured="printerConfigured"
            />
        </div>

        <div v-if="order.delivery" class="flex items-center gap-2 bg-surface-blue px-5 py-3 text-xs text-brand-700">
            <MapPin class="size-4 shrink-0" aria-hidden="true" />
            Antar-jemput termasuk {{ formatRupiah(order.delivery.fee_rupiah) }}
        </div>
    </article>
</template>

<style scoped>
.order-ticket::before,
.order-ticket::after {
    position: absolute;
    top: 10.9rem;
    width: 1rem;
    height: 1rem;
    content: '';
    border-radius: 9999px;
    background: var(--laundry-canvas);
}

.order-ticket::before {
    left: -0.5rem;
}

.order-ticket::after {
    right: -0.5rem;
}
</style>
