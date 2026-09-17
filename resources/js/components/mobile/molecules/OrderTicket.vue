<script setup lang="ts">
import { Building2, ChevronRight, Clock3, Scale, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import MobileStatusBadge from '@/components/mobile/atoms/MobileStatusBadge.vue';
import WhatsAppIcon from '@/components/mobile/atoms/WhatsAppIcon.vue';
import BranchWhatsAppButton from '@/components/mobile/molecules/BranchWhatsAppButton.vue';
import OrderDetailsDialog from '@/components/mobile/molecules/OrderDetailsDialog.vue';
import WhatsAppSuggestionDialog from '@/components/mobile/molecules/WhatsAppSuggestionDialog.vue';
import PaymentDialog from '@/components/mobile/organisms/PaymentDialog.vue';
import {
    buildOrderTimeline,
    formatOrderDate,
    formatRupiah,
    nextOrderStatus,
    orderStatusLabels,
} from '@/lib/mobileOrders';
import type {
    ConfirmOrderPricingPayload,
    MobileBranch,
    MobileOrder,
    MobileOrderStatus,
    MobilePaymentActions,
    MobileRole,
    WhatsAppSuggestion,
} from '@/types/mobile';

type Props = {
    order: MobileOrder;
    role: MobileRole;
    branches?: readonly MobileBranch[];
    processing?: boolean;
    pricingProcessing?: boolean;
    paymentProcessing?: boolean;
    paymentActions?: MobilePaymentActions;
    printerConfigured?: boolean;
    loadStatusWhatsAppSuggestion?: (orderId: number) => Promise<WhatsAppSuggestion>;
};

const props = withDefaults(defineProps<Props>(), {
    branches: () => [],
    processing: false,
    pricingProcessing: false,
    paymentProcessing: false,
    paymentActions: undefined,
    printerConfigured: false,
    loadStatusWhatsAppSuggestion: undefined,
});

const emit = defineEmits<{
    'confirm-pricing': [orderId: number, payload: ConfirmOrderPricingPayload];
    transition: [orderId: number, status: MobileOrderStatus];
}>();

const actualWeightKilograms = ref<number | string>('');
const statusMessageOpen = ref(false);
const statusMessageLoading = ref(false);
const statusMessage = ref<WhatsAppSuggestion | null>(null);
const statusMessageError = ref<string | null>(null);
const statusMessageDescription = ref('');
let statusMessageGeneration = 0;

watch(statusMessageOpen, (open) => {
    if (!open) {
        statusMessageGeneration += 1;
        statusMessageLoading.value = false;
    }
});

onBeforeUnmount(() => {
    statusMessageGeneration += 1;
});

const previewStatusMessage = async (eventId: number): Promise<void> => {
    const order = props.order;
    const event = order.status_history.find((entry) => entry.id === eventId);

    if (
        props.role !== 'branch_admin' ||
        !props.loadStatusWhatsAppSuggestion ||
        !event ||
        event.pending ||
        order.pending ||
        props.processing ||
        props.pricingProcessing ||
        statusMessageLoading.value
    ) {
        return;
    }

    const generation = ++statusMessageGeneration;
    statusMessage.value = null;
    statusMessageError.value = null;
    statusMessageDescription.value = `${order.order_number} · ${orderStatusLabels[event.status]}. Periksa pesan sebelum membuka WhatsApp.`;
    statusMessageLoading.value = true;
    statusMessageOpen.value = true;

    try {
        const suggestion = await props.loadStatusWhatsAppSuggestion(order.id);

        if (generation !== statusMessageGeneration) {
            return;
        }

        if (suggestion.available && event.status !== props.order.status) {
            const message = `Riwayat pesanan ${order.order_number} di ${order.branch.name}:\n${orderStatusLabels[event.status]} pada ${formatOrderDate(event.occurred_at)}.\n\nInformasi terbaru dari cabang:\n${suggestion.message}`;
            const url = new URL(suggestion.url);
            url.searchParams.set('text', message);
            statusMessage.value = { ...suggestion, message, url: url.toString() };
        } else {
            statusMessage.value = suggestion;
        }
    } catch (error: unknown) {
        if (generation === statusMessageGeneration) {
            statusMessageError.value =
                error instanceof Error ? error.message : 'Pesan WhatsApp tidak dapat dimuat.';
        }
    } finally {
        if (generation === statusMessageGeneration) {
            statusMessageLoading.value = false;
        }
    }
};

const nextStatus = computed(() => nextOrderStatus(props.order.status));
const timeline = computed(() => buildOrderTimeline(props.order));
const currentEvent = computed(() => props.order.status_history.at(-1) ?? null);
const branchDetails = computed(
    () => props.branches.find((branch) => branch.id === props.order.branch.id) ?? null,
);
const isPricingPending = computed(() => !props.order.pricing.confirmed);
const actualWeightGrams = computed(() => Math.round(Number(actualWeightKilograms.value) * 1000));
const canConfirmPricing = computed(
    () =>
        props.order.items[0] !== undefined &&
        Number.isFinite(actualWeightGrams.value) &&
        actualWeightGrams.value > 0,
);
const isTerminal = computed(
    () => props.order.status === 'completed' || props.order.status === 'cancelled',
);

const confirmPricing = (): void => {
    const item = props.order.items[0];

    if (!item || !canConfirmPricing.value) {
        return;
    }

    emit('confirm-pricing', props.order.id, {
        price_option_id: item.price_option.id,
        weight_grams: actualWeightGrams.value,
    });
};
</script>

<template>
    <article
        class="order-ticket mobile-card overflow-hidden"
        :data-order-id="order.id"
        :data-pending="order.pending ? 'true' : 'false'"
    >
        <div class="space-y-4 p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-[0.16em] text-brand-600 uppercase">
                        {{ order.branch.code }}
                    </p>
                    <h3 class="mt-1 break-words font-display text-lg font-semibold text-ink-950">
                        {{ order.order_number }}
                    </h3>
                    <p class="mt-1 text-sm text-ink-500">{{ order.customer.name }}</p>
                </div>
                <span
                    v-if="role === 'branch_admin' && !isPricingPending"
                    class="inline-flex min-h-7 shrink-0 items-center rounded-full px-3 text-xs font-semibold"
                    :class="
                        order.payment_summary.is_paid
                            ? 'bg-mint-500/15 text-brand-600'
                            : 'bg-amber-500/15 text-amber-700'
                    "
                    data-test="payment-status-badge"
                >
                    {{ order.payment_summary.is_paid ? 'Lunas' : 'Belum lunas' }}
                </span>
            </div>

            <section
                class="rounded-2xl border border-line bg-surface-blue/70 p-4"
                aria-label="Informasi cabang"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-700 text-brand-100"
                    >
                        <Building2 class="size-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-display text-base font-semibold text-ink-950">
                            {{ order.branch.name }}
                        </p>
                        <p v-if="branchDetails" class="mt-1 text-sm leading-6 text-ink-700">
                            {{ branchDetails.address }}
                        </p>
                        <p v-if="branchDetails" class="mt-1 text-xs text-ink-500">
                            {{ branchDetails.city.name }}, {{ branchDetails.city.province }}
                        </p>
                        <BranchWhatsAppButton
                            v-if="role === 'customer' && branchDetails?.phone"
                            :phone="branchDetails.phone"
                            class="mt-3"
                        />
                    </div>
                </div>
            </section>

            <section
                class="mobile-hero relative overflow-hidden rounded-2xl px-4 py-4 text-white"
                data-test="current-status"
                aria-label="Status pengerjaan saat ini"
            >
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.14em] text-brand-100/70 uppercase"
                        >
                            Status pengerjaan
                        </p>
                        <p class="mt-1 font-display text-xl font-semibold">
                            {{ orderStatusLabels[order.status] }}
                        </p>
                        <p class="mt-1 text-xs leading-5 text-white/65">
                            <template v-if="currentEvent">
                                Diperbarui {{ formatOrderDate(currentEvent.occurred_at) }}
                            </template>
                            <template v-else>
                                Dibuat {{ formatOrderDate(order.created_at) }}
                            </template>
                        </p>
                    </div>
                    <MobileStatusBadge :status="order.status" :pending="order.pending" />
                </div>
            </section>
        </div>

        <details class="group border-t border-line">
            <summary
                class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-5 text-sm font-semibold text-brand-700 transition hover:bg-brand-100/60"
            >
                <span class="flex items-center gap-2">
                    <Clock3 class="size-4" aria-hidden="true" />
                    Lihat perjalanan laundry
                </span>
                <ChevronRight
                    class="size-4 transition-transform duration-200 group-open:rotate-90"
                    aria-hidden="true"
                />
            </summary>
            <ol
                class="space-y-4 border-t border-dashed border-line px-5 py-5"
                data-test="order-timeline"
            >
                <li
                    v-for="step in timeline"
                    :key="step.status + '-' + (step.event?.id ?? 'future')"
                    class="relative border-l-2 pl-4"
                    :class="step.state === 'future' ? 'border-line' : 'border-brand-100'"
                    :data-timeline-state="step.state"
                >
                    <span
                        class="absolute top-1 -left-[7px] size-3 rounded-full border-2 border-surface"
                        :class="[
                            step.state === 'future'
                                ? 'bg-line'
                                : step.state === 'current'
                                  ? 'bg-amber-500'
                                  : 'bg-brand-600',
                            step.event?.pending ? 'animate-pulse' : '',
                        ]"
                    />
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                        <div>
                            <p
                                class="text-sm font-semibold"
                                :class="
                                    step.state === 'future' ? 'text-ink-500' : 'text-ink-950'
                                "
                            >
                                {{ orderStatusLabels[step.status] }}
                                <span v-if="step.event?.pending" class="text-amber-500">
                                    · menunggu
                                </span>
                            </p>
                            <p v-if="step.event" class="text-xs text-ink-500">
                                {{ step.event.actor.name }}
                            </p>
                            <p v-else class="text-xs text-ink-500">Menunggu tahap sebelumnya</p>
                            <p v-if="step.event?.note" class="mt-1 text-sm text-ink-700">
                                {{ step.event.note }}
                            </p>
                        </div>
                        <time
                            v-if="step.event"
                            class="shrink-0 text-xs text-ink-500 sm:text-right"
                        >
                            {{ formatOrderDate(step.event.occurred_at) }}
                        </time>
                    </div>
                    <button
                        v-if="role === 'branch_admin' && step.event && step.state !== 'future'"
                        type="button"
                        class="mt-2 inline-flex min-h-11 items-center gap-2 rounded-xl border border-brand-600/20 bg-brand-100/60 px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-100 disabled:opacity-50"
                        :disabled="order.pending || step.event.pending || processing || pricingProcessing || statusMessageLoading || !loadStatusWhatsAppSuggestion"
                        :aria-label="'Kirim WhatsApp status ' + orderStatusLabels[step.status]"
                        data-test="notify-order-status"
                        @click="previewStatusMessage(step.event.id)"
                    >
                        <WhatsAppIcon class="size-4 shrink-0" />
                        Kirim WhatsApp
                    </button>
                </li>
            </ol>
        </details>

        <div class="space-y-3 border-t border-line p-4 sm:p-5">
            <div
                v-if="isPricingPending && role === 'customer'"
                class="rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm leading-6 text-amber-800"
                data-test="pricing-pending-summary"
            >
                Harga menunggu konfirmasi. Pesanan Anda menunggu dijemput dan ditimbang oleh
                petugas cabang. QRIS dinamis tersedia setelah total harga dikonfirmasi.
            </div>

            <div
                v-if="!isPricingPending"
                class="flex flex-wrap items-end justify-between gap-3 rounded-2xl bg-surface-blue p-4"
                data-test="payment-summary"
            >
                <div>
                    <p class="text-xs text-ink-500">Total pesanan</p>
                    <p class="mt-1 font-display text-xl font-semibold text-brand-700">{{ formatRupiah(order.pricing.total_rupiah) }}</p>
                </div>
                <p class="text-sm font-semibold" :class="order.payment_summary.is_paid ? 'text-brand-600' : 'text-amber-700'">
                    {{ order.payment_summary.is_paid ? 'Lunas' : 'Sisa ' + formatRupiah(order.payment_summary.outstanding_rupiah) }}
                </p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2" data-test="payment-actions">
                <OrderDetailsDialog :order="order" :branch="branchDetails" :role="role" />
                <PaymentDialog
                    v-if="!isPricingPending && paymentActions"
                    :order="order"
                    :actions="paymentActions"
                    :role="role"
                    :processing="paymentProcessing"
                    :printer-configured="printerConfigured"
                />
            </div>
        </div>

        <div
            v-if="isTerminal"
            class="border-t border-line px-5 py-4 text-sm"
            :class="
                order.status === 'completed'
                    ? 'bg-mint-500/10 text-brand-600'
                    : 'bg-red-600/5 text-red-600'
            "
            data-test="terminal-summary"
        >
            {{
                order.status === 'completed'
                    ? 'Laundry selesai dan pesanan sudah ditutup.'
                    : 'Pesanan dibatalkan. Tidak ada proses lanjutan.'
            }}
        </div>

        <form
            v-if="role === 'branch_admin' && isPricingPending && !isTerminal"
            class="space-y-3 border-t border-line bg-canvas p-4"
            data-test="pricing-controls"
            @submit.prevent="confirmPricing"
        >
            <label class="block">
                <span class="text-sm font-semibold text-ink-950">Berat aktual</span>
                <span class="relative mt-2 block">
                    <Scale
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-500"
                        aria-hidden="true"
                    />
                    <input
                        v-model.number="actualWeightKilograms"
                        data-test="actual-weight"
                        type="number"
                        min="0.1"
                        max="1000"
                        step="any"
                        inputmode="decimal"
                        required
                        class="min-h-11 w-full rounded-xl border border-line bg-surface pr-12 pl-10 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                    />
                    <span
                        class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-ink-500"
                    >
                        kg
                    </span>
                </span>
            </label>
            <p class="text-xs leading-5 text-ink-500">
                Harga dihitung server dari layanan dan berat aktual. Setelah tersimpan, preview
                WhatsApp harga dan pembayaran QRIS akan tersedia.
            </p>
            <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                <button
                    type="submit"
                    data-test="confirm-pricing"
                    class="min-h-11 rounded-xl bg-brand-700 px-4 text-sm font-semibold text-white transition motion-safe:active:scale-[0.98] disabled:opacity-60"
                    :disabled="pricingProcessing || order.pending || !canConfirmPricing"
                >
                    {{ pricingProcessing ? 'Menyimpan harga…' : 'Konfirmasi berat & harga' }}
                </button>
                <button
                    type="button"
                    class="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-red-600/25 px-4 text-sm font-semibold text-red-600 transition hover:bg-red-600/5 motion-safe:active:scale-[0.98] disabled:opacity-60"
                    :disabled="processing || pricingProcessing || order.pending"
                    @click="emit('transition', order.id, 'cancelled')"
                >
                    <X class="size-4" aria-hidden="true" />
                    Batalkan
                </button>
            </div>
        </form>

        <div
            v-else-if="role === 'branch_admin' && !isTerminal"
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
        <WhatsAppSuggestionDialog
            v-if="role === 'branch_admin'"
            v-model:open="statusMessageOpen"
            :suggestion="statusMessage"
            :load-error="statusMessageError"
            :loading="statusMessageLoading"
            :description="statusMessageDescription"
        />
    </article>
</template>
