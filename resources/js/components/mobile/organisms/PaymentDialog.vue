<script setup lang="ts">
import { CheckCircle2, ReceiptText } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DigitalReceiptPanel from '@/components/mobile/molecules/DigitalReceiptPanel.vue';
import PaymentEntryForm from '@/components/mobile/molecules/PaymentEntryForm.vue';
import PaymentHistory from '@/components/mobile/molecules/PaymentHistory.vue';
import PaymentPendingPanel from '@/components/mobile/molecules/PaymentPendingPanel.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatRupiah } from '@/lib/mobileOrders';
import type {
    MobileOrder,
    MobileOrderReceipt,
    MobilePayment,
    MobilePaymentActions,
    MobilePaymentMethod,
} from '@/types/mobile';

const props = withDefaults(
    defineProps<{
        order: MobileOrder;
        actions: MobilePaymentActions;
        processing?: boolean;
        printerConfigured?: boolean;
    }>(),
    {
        processing: false,
        printerConfigured: false,
    },
);

const open = ref(false);
const method = ref<MobilePaymentMethod>('cash');
const amountInput = ref('');
const note = ref('');
const actionProcessing = ref(false);
const receipt = ref<MobileOrderReceipt | null>(null);
const message = ref<string | null>(null);
const error = ref<string | null>(null);

const latestPendingPayment = computed<MobilePayment | null>(() => {
    const payments = [...props.order.payments].reverse();

    return payments.find((payment) => payment.status === 'pending') ?? null;
});
const amountRupiah = computed(() => Number(amountInput.value));
const isBusy = computed(() => props.processing || actionProcessing.value);
const canCreatePayment = computed(() => {
    const amount = amountRupiah.value;

    return (
        !isBusy.value &&
        !latestPendingPayment.value &&
        props.order.payment_summary.outstanding_rupiah > 0 &&
        Number.isInteger(amount) &&
        amount > 0 &&
        amount <= props.order.payment_summary.outstanding_rupiah &&
        (method.value !== 'qris' || props.order.branch.qris_enabled)
    );
});

watch(
    [open, () => props.order.payment_summary.outstanding_rupiah],
    ([isOpen, outstandingRupiah]) => {
        if (isOpen) {
            amountInput.value = String(outstandingRupiah);
        }
    },
    { immediate: true },
);

const resetAnnouncements = (): void => {
    message.value = null;
    error.value = null;
};

const submitPayment = async (): Promise<void> => {
    if (!canCreatePayment.value) {
        return;
    }

    actionProcessing.value = true;
    resetAnnouncements();

    try {
        const saved = await props.actions.createPayment(props.order.id, {
            method: method.value,
            amount_rupiah: amountRupiah.value,
            note: note.value.trim() || undefined,
        });

        if (saved) {
            message.value =
                method.value === 'qris'
                    ? 'QRIS dinamis siap dipindai.'
                    : 'Pembayaran tunai berhasil dicatat.';
            note.value = '';
        } else {
            error.value = 'Pembayaran belum tersimpan. Perubahan sementara sudah dibatalkan.';
        }
    } finally {
        actionProcessing.value = false;
    }
};

const reconcile = async (payment: MobilePayment, status: 'paid' | 'failed'): Promise<void> => {
    actionProcessing.value = true;
    resetAnnouncements();

    try {
        const saved = await props.actions.transitionPaymentStatus(props.order.id, payment.id, {
            status,
            note:
                status === 'paid'
                    ? 'Pembayaran QRIS dikonfirmasi admin cabang'
                    : 'Pembayaran QRIS ditandai gagal oleh admin cabang',
        });

        if (saved) {
            message.value =
                status === 'paid' ? 'Pembayaran sudah lunas.' : 'Pembayaran ditandai gagal.';
        } else {
            error.value = 'Status pembayaran gagal diperbarui dan sudah dikembalikan.';
        }
    } finally {
        actionProcessing.value = false;
    }
};

const loadReceipt = async (): Promise<void> => {
    actionProcessing.value = true;
    resetAnnouncements();

    try {
        receipt.value = await props.actions.loadReceipt(props.order.id);
        message.value = 'Struk digital berhasil dimuat.';
    } catch (loadError: unknown) {
        error.value =
            loadError instanceof Error ? loadError.message : 'Struk digital tidak dapat dimuat.';
    } finally {
        actionProcessing.value = false;
    }
};

const printReceipt = async (): Promise<void> => {
    actionProcessing.value = true;
    resetAnnouncements();

    try {
        const printed = await props.actions.printReceipt(props.order.id);

        if (printed) {
            message.value = 'Struk dikirim ke printer.';
        } else {
            error.value = 'Struk belum dapat dicetak. Periksa profil dan koneksi printer.';
        }
    } finally {
        actionProcessing.value = false;
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                type="button"
                variant="outline"
                class="min-h-11 w-full border-brand-600/25 text-brand-700"
                data-test="open-payment-dialog"
            >
                <ReceiptText class="size-4" aria-hidden="true" />
                Pembayaran &amp; struk
            </Button>
        </DialogTrigger>

        <DialogScrollContent
            class="max-h-[calc(100dvh-2rem)] max-w-xl overflow-y-auto rounded-2xl bg-surface"
        >
            <DialogHeader>
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-xl bg-surface-blue text-brand-700"
                >
                    <ReceiptText class="size-5" aria-hidden="true" />
                </span>
                <DialogTitle>Pembayaran {{ order.order_number }}</DialogTitle>
                <DialogDescription>
                    Catat tunai, tampilkan QRIS dinamis, rekonsiliasi pembayaran, lalu buka atau cetak struk.
                </DialogDescription>
            </DialogHeader>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-surface-blue p-3">
                    <p class="text-xs text-ink-500">Sudah dibayar</p>
                    <p class="mt-1 font-display text-lg font-semibold text-brand-700">
                        {{ formatRupiah(order.payment_summary.paid_rupiah) }}
                    </p>
                </div>
                <div class="rounded-xl bg-canvas p-3">
                    <p class="text-xs text-ink-500">Sisa tagihan</p>
                    <p class="mt-1 font-display text-lg font-semibold text-ink-950">
                        {{ formatRupiah(order.payment_summary.outstanding_rupiah) }}
                    </p>
                </div>
            </div>

            <p
                v-if="message || error"
                class="rounded-xl border px-4 py-3 text-sm"
                :class="error ? 'border-red-600/20 bg-red-600/5 text-red-600' : 'border-mint-500/20 bg-mint-500/10 text-mint-500'"
                role="status"
                aria-live="polite"
            >
                {{ error ?? message }}
            </p>

            <PaymentPendingPanel
                v-if="latestPendingPayment"
                :payment="latestPendingPayment"
                :busy="isBusy"
                @reconcile="reconcile"
            />
            <PaymentEntryForm
                v-else-if="order.payment_summary.outstanding_rupiah > 0"
                v-model:method="method"
                v-model:amount-input="amountInput"
                v-model:note="note"
                :order="order"
                :busy="isBusy"
                :can-submit="canCreatePayment"
                @submit="submitPayment"
            />
            <section
                v-else
                class="rounded-2xl border border-mint-500/20 bg-mint-500/10 p-4 text-mint-500"
            >
                <div class="flex items-center gap-2 font-semibold">
                    <CheckCircle2 class="size-5" aria-hidden="true" />
                    Pesanan sudah lunas
                </div>
            </section>

            <PaymentHistory v-if="order.payments.length" :payments="order.payments" />
            <DigitalReceiptPanel
                :receipt="receipt"
                :busy="isBusy"
                :printer-configured="printerConfigured"
                @load="loadReceipt"
                @print="printReceipt"
            />
        </DialogScrollContent>
    </Dialog>
</template>
