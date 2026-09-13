<script setup lang="ts">
import { formatOrderDate, formatRupiah } from '@/lib/mobileOrders';
import type { MobilePayment, MobilePaymentStatus } from '@/types/mobile';

defineProps<{
    payments: readonly MobilePayment[];
}>();

const statusClasses = (status: MobilePaymentStatus): string => {
    if (status === 'paid') {
        return 'bg-mint-500/10 text-mint-500';
    }

    if (status === 'pending') {
        return 'bg-amber-500/10 text-amber-700';
    }

    return 'bg-red-600/5 text-red-600';
};
</script>

<template>
    <section aria-labelledby="payment-history-title">
        <h3 id="payment-history-title" class="text-sm font-semibold text-ink-950">
            Riwayat pembayaran
        </h3>
        <ul class="mt-2 space-y-2">
            <li
                v-for="payment in payments"
                :key="payment.id"
                class="flex items-start justify-between gap-3 rounded-xl bg-canvas p-3"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-ink-950">
                        {{ payment.method_label }} · {{ payment.payment_number }}
                    </p>
                    <p class="mt-0.5 text-xs text-ink-500">
                        {{ formatOrderDate(payment.created_at) }}
                    </p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-sm font-semibold text-ink-950">
                        {{ formatRupiah(payment.amount_rupiah) }}
                    </p>
                    <span
                        class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="statusClasses(payment.status)"
                    >
                        {{ payment.pending ? 'Menunggu server' : payment.status_label }}
                    </span>
                </div>
            </li>
        </ul>
    </section>
</template>
