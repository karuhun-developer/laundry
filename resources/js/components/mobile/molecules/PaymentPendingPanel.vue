<script setup lang="ts">
import { CheckCircle2, XCircle } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { formatRupiah } from '@/lib/mobileOrders';
import type { MobilePayment } from '@/types/mobile';

defineProps<{
    payment: MobilePayment;
    busy: boolean;
}>();

const emit = defineEmits<{
    reconcile: [payment: MobilePayment, status: 'paid' | 'failed'];
}>();
</script>

<template>
    <section class="rounded-2xl border border-line bg-canvas p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold tracking-[0.14em] text-brand-600 uppercase">
                    QRIS aktif
                </p>
                <p class="mt-1 font-semibold text-ink-950">{{ payment.payment_number }}</p>
                <p class="text-sm text-ink-500">{{ formatRupiah(payment.amount_rupiah) }}</p>
            </div>
            <span class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-700">
                Menunggu
            </span>
        </div>

        <div
            v-if="payment.qris_image_data_uri"
            class="mx-auto mt-4 max-w-72 rounded-2xl bg-white p-3 shadow-low"
        >
            <img
                :src="payment.qris_image_data_uri"
                :alt="`QRIS ${payment.payment_number}`"
                class="aspect-square w-full"
                data-test="qris-image"
            />
        </div>
        <div
            v-else
            class="mt-4 flex min-h-40 items-center justify-center rounded-2xl border border-dashed border-line bg-surface text-center text-sm text-ink-500"
        >
            QR sedang dibuat oleh server.
        </div>

        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <Button
                type="button"
                class="bg-mint-500 text-white hover:bg-mint-500/90"
                :disabled="busy || payment.pending"
                data-test="mark-payment-paid"
                @click="emit('reconcile', payment, 'paid')"
            >
                <CheckCircle2 class="size-4" aria-hidden="true" />
                Tandai lunas
            </Button>
            <Button
                type="button"
                variant="outline"
                class="border-red-600/25 text-red-600"
                :disabled="busy || payment.pending"
                data-test="mark-payment-failed"
                @click="emit('reconcile', payment, 'failed')"
            >
                <XCircle class="size-4" aria-hidden="true" />
                Tandai gagal
            </Button>
        </div>
    </section>
</template>
