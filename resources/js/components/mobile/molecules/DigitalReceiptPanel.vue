<script setup lang="ts">
import { Printer } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { formatRupiah, formatWeight } from '@/lib/mobileOrders';
import type { MobileOrderReceipt } from '@/types/mobile';

withDefaults(
    defineProps<{
        receipt: MobileOrderReceipt | null;
        busy: boolean;
        printerConfigured: boolean;
        showPrintAction?: boolean;
    }>(),
    { showPrintAction: true },
);

const emit = defineEmits<{
    load: [];
    print: [];
}>();
</script>

<template>
    <section class="rounded-2xl border border-line p-4" aria-labelledby="digital-receipt-title">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 id="digital-receipt-title" class="text-sm font-semibold text-ink-950">
                    Struk digital
                </h3>
                <p class="mt-1 text-xs leading-5 text-ink-500">
                    Ambil snapshot terbaru sebelum melihat struk.
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                class="shrink-0"
                :disabled="busy"
                data-test="load-digital-receipt"
                @click="emit('load')"
            >
                Lihat struk
            </Button>
        </div>

        <div v-if="receipt" class="mt-4 rounded-xl bg-canvas p-4 text-sm">
            <div class="text-center">
                <p class="font-display text-lg font-semibold text-ink-950">
                    {{ receipt.branch.name }}
                </p>
                <p class="text-xs text-ink-500">{{ receipt.receipt_number }}</p>
            </div>
            <dl class="mt-4 space-y-2 border-y border-dashed border-line py-3">
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-500">Pelanggan</dt>
                    <dd class="text-right font-semibold text-ink-950">
                        {{ receipt.customer.name }}
                    </dd>
                </div>
                <div
                    v-for="item in receipt.items"
                    :key="item.id"
                    class="flex justify-between gap-3"
                >
                    <dt class="text-ink-500">
                        {{ item.service.name }} · {{ formatWeight(item.calculation.requested_weight_grams) }}
                    </dt>
                    <dd class="text-right font-semibold text-ink-950">
                        {{ formatRupiah(item.calculation.subtotal_rupiah) }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="font-semibold text-ink-950">Total</dt>
                    <dd class="text-right font-display font-semibold text-brand-700">
                        {{ formatRupiah(receipt.pricing.total_rupiah) }}
                    </dd>
                </div>
            </dl>
            <Button
                v-if="showPrintAction"
                type="button"
                class="mt-4 w-full bg-brand-700 text-white"
                :disabled="busy || !printerConfigured"
                data-test="print-receipt"
                @click="emit('print')"
            >
                <Printer class="size-4" aria-hidden="true" />
                {{ printerConfigured ? 'Cetak ulang struk' : 'Atur printer di Akun' }}
            </Button>
        </div>
    </section>
</template>
