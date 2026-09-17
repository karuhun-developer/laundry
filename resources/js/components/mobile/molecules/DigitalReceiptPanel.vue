<script setup lang="ts">
import { MessageCircle, Printer } from '@lucide/vue';
import WhatsAppContactButton from '@/components/mobile/molecules/WhatsAppContactButton.vue';
import { Button } from '@/components/ui/button';
import { formatRupiah, formatWeight } from '@/lib/mobileOrders';
import type { MobileOrderReceipt } from '@/types/mobile';

withDefaults(
    defineProps<{
        receipt: MobileOrderReceipt | null;
        busy: boolean;
        printerConfigured: boolean;
        showPrintAction?: boolean;
        showBranchContact?: boolean;
    }>(),
    { showPrintAction: true, showBranchContact: false },
);

const emit = defineEmits<{
    load: [];
    print: [];
    share: [];
}>();
</script>

<template>
    <section
        class="overflow-hidden rounded-3xl border border-line bg-surface shadow-low"
        aria-labelledby="digital-receipt-title"
    >
        <div class="flex items-start justify-between gap-4 bg-brand-700 px-5 py-5 text-white">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold tracking-[0.18em] text-white/65 uppercase">
                    Bukti transaksi
                </p>
                <h3 id="digital-receipt-title" class="mt-1 font-display text-xl font-semibold">
                    Struk digital
                </h3>
                <p class="mt-1 max-w-xs text-xs leading-5 text-white/70">
                    Muat ulang struk sebelum dibagikan atau dicetak.
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                class="shrink-0 border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                :disabled="busy"
                data-test="load-digital-receipt"
                @click="emit('load')"
            >
                Lihat struk
            </Button>
        </div>

        <div v-if="receipt" class="p-5 text-sm">
            <div class="rounded-2xl bg-surface-blue p-4">
                <div class="flex flex-col items-start gap-3 sm:flex-row sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-display text-lg font-semibold text-ink-950">
                            {{ receipt.branch.name }}
                        </p>
                        <p class="mt-1 text-xs leading-5 text-ink-500">
                            {{ receipt.branch.address }}, {{ receipt.branch.city.name }}
                        </p>
                        <WhatsAppContactButton
                            v-if="showBranchContact && receipt.branch.phone"
                            :phone="receipt.branch.phone"
                            class="mt-3"
                        />
                    </div>
                    <span
                        class="max-w-full rounded-lg bg-brand-700 px-2.5 py-1 font-mono text-[11px] font-semibold break-words text-white"
                    >
                        {{ receipt.receipt_number }}
                    </span>
                </div>
            </div>

            <dl class="mt-4 space-y-3 border-y border-dashed border-line py-4">
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-500">Pelanggan</dt>
                    <dd class="text-right font-semibold text-ink-950">
                        {{ receipt.customer.name }}
                    </dd>
                </div>
                <div
                    v-for="item in receipt.items"
                    :key="item.id"
                    class="flex items-start justify-between gap-4"
                >
                    <dt class="min-w-0 text-ink-700">
                        <span class="block font-medium text-ink-950">{{ item.service.name }}</span>
                        <span class="text-xs text-ink-500">
                            {{ formatWeight(item.calculation.requested_weight_grams) }}
                        </span>
                    </dt>
                    <dd class="shrink-0 text-right font-semibold text-ink-950">
                        {{ formatRupiah(item.calculation.subtotal_rupiah) }}
                    </dd>
                </div>
            </dl>

            <div class="mt-4 rounded-2xl bg-brand-100 p-4">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold tracking-[0.14em] text-brand-600 uppercase">
                            Total pesanan
                        </p>
                        <p class="mt-1 text-xs text-ink-500">
                            Terbayar {{ formatRupiah(receipt.pricing.paid_rupiah) }}
                        </p>
                    </div>
                    <p class="text-right font-display text-xl font-bold text-brand-700">
                        {{ formatRupiah(receipt.pricing.total_rupiah) }}
                    </p>
                </div>
            </div>

            <div class="mt-4 grid gap-2" :class="showPrintAction ? 'sm:grid-cols-2' : ''">
                <Button
                    type="button"
                    class="w-full bg-mint-accent text-ink-950 hover:bg-mint-accent/90"
                    :disabled="busy"
                    data-test="share-receipt-whatsapp"
                    @click="emit('share')"
                >
                    <MessageCircle class="size-4" aria-hidden="true" />
                    Bagikan ke WhatsApp
                </Button>
                <Button
                    v-if="showPrintAction"
                    type="button"
                    class="w-full bg-brand-700 text-white hover:bg-brand-600"
                    :disabled="busy || !printerConfigured"
                    data-test="print-receipt"
                    @click="emit('print')"
                >
                    <Printer class="size-4" aria-hidden="true" />
                    {{ printerConfigured ? 'Cetak ulang struk' : 'Atur printer di Akun' }}
                </Button>
            </div>
        </div>
    </section>
</template>
