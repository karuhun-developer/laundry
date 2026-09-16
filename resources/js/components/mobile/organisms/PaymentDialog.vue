<script setup lang="ts">
import { CheckCircle2, QrCode, ReceiptText, RefreshCw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
import { externalLinkTransport } from '@/lib/externalLinks/transport';
import { formatRupiah } from '@/lib/mobileOrders';
import type {
    MobileOrder,
    MobileOrderReceipt,
    MobilePayment,
    MobilePaymentActions,
    MobilePaymentGatewayMethod,
    MobilePaymentMethod,
    MobileRole,
} from '@/types/mobile';

const props = withDefaults(
    defineProps<{
        order: MobileOrder;
        actions: MobilePaymentActions;
        role?: MobileRole;
        processing?: boolean;
        printerConfigured?: boolean;
    }>(),
    {
        role: 'branch_admin',
        processing: false,
        printerConfigured: false,
    },
);

const open = ref(false);
const currentOrder = ref<MobileOrder>(props.order);
const method = ref<MobilePaymentMethod>(props.role === 'customer' ? 'qris' : 'cash');
const gatewayMethods = ref<readonly MobilePaymentGatewayMethod[]>([]);
const selectedGatewayMethod = ref('');
const amountInput = ref('');
const note = ref('');
const actionProcessing = ref(false);
const orderRefreshProcessing = ref(false);
const orderRefreshError = ref<string | null>(null);
const receipt = ref<MobileOrderReceipt | null>(null);
const message = ref<string | null>(null);
const error = ref<string | null>(null);
let gatewayLoadGeneration = 0;
let orderRefreshGeneration = 0;

const latestPendingPayment = computed<MobilePayment | null>(() => {
    const payments = [...currentOrder.value.payments].reverse();

    return payments.find((payment) => payment.status === 'pending') ?? null;
});
const amountRupiah = computed(() => Number(amountInput.value));
const isBusy = computed(
    () => props.processing || actionProcessing.value || orderRefreshProcessing.value,
);
const paymentTriggerLabel = computed(() => {
    if (latestPendingPayment.value?.qris_image_data_uri) {
        return 'Lihat QRIS pembayaran';
    }

    if (props.role === 'branch_admin') {
        return 'Pembayaran & struk';
    }

    return props.order.payment_summary.is_paid ? 'Lihat pembayaran' : 'Bayar dengan QRIS';
});
const selectedGateway = computed(
    () =>
        gatewayMethods.value.find(
            (gatewayMethod) => gatewayMethod.code === selectedGatewayMethod.value,
        ) ?? null,
);
const canCreatePayment = computed(() => {
    const amount = amountRupiah.value;

    return (
        !isBusy.value &&
        !latestPendingPayment.value &&
        currentOrder.value.payment_summary.outstanding_rupiah > 0 &&
        Number.isInteger(amount) &&
        amount > 0 &&
        amount <= currentOrder.value.payment_summary.outstanding_rupiah &&
        (props.role !== 'customer' || method.value === 'qris') &&
        (method.value !== 'qris' || currentOrder.value.branch.qris_enabled) &&
        (method.value !== 'gateway' ||
            (selectedGateway.value !== null &&
                amount >= selectedGateway.value.minimum_rupiah &&
                amount <= selectedGateway.value.maximum_rupiah))
    );
});

const loadGatewayMethods = async (): Promise<void> => {
    const requestGeneration = ++gatewayLoadGeneration;

    try {
        const methods = await props.actions.loadPaymentGatewayMethods();

        if (requestGeneration !== gatewayLoadGeneration || !open.value) {
            return;
        }

        gatewayMethods.value = methods;
        selectedGatewayMethod.value =
            methods.find((gatewayMethod) => gatewayMethod.is_default)?.code ??
            methods[0]?.code ??
            '';
    } catch {
        if (requestGeneration === gatewayLoadGeneration) {
            gatewayMethods.value = [];
            selectedGatewayMethod.value = '';

            if (method.value === 'gateway') {
                method.value = 'cash';
            }
        }
    }
};

const resetAnnouncements = (): void => {
    message.value = null;
    error.value = null;
};

const refreshPaymentDialog = async (): Promise<void> => {
    const requestGeneration = ++orderRefreshGeneration;
    orderRefreshProcessing.value = true;
    orderRefreshError.value = null;
    resetAnnouncements();

    try {
        const refreshedOrder = await props.actions.refreshOrder(props.order.id);

        if (requestGeneration !== orderRefreshGeneration || !open.value) {
            return;
        }

        currentOrder.value = refreshedOrder;
        amountInput.value = String(refreshedOrder.payment_summary.outstanding_rupiah);
        method.value = props.role === 'customer' ? 'qris' : 'cash';

        if (props.role === 'branch_admin') {
            void loadGatewayMethods();
        }
    } catch (refreshError: unknown) {
        if (requestGeneration === orderRefreshGeneration && open.value) {
            orderRefreshError.value =
                refreshError instanceof Error
                    ? refreshError.message
                    : 'Detail pembayaran tidak dapat dimuat.';
        }
    } finally {
        if (requestGeneration === orderRefreshGeneration) {
            orderRefreshProcessing.value = false;
        }
    }
};

watch(
    () => props.order,
    (order) => {
        currentOrder.value = order;
    },
);

watch(
    open,
    (isOpen) => {
        if (isOpen) {
            void refreshPaymentDialog();
            return;
        }

        orderRefreshGeneration += 1;
        gatewayLoadGeneration += 1;
        method.value = props.role === 'customer' ? 'qris' : 'cash';
        gatewayMethods.value = [];
        selectedGatewayMethod.value = '';
        orderRefreshError.value = null;
    },
    { immediate: true },
);

const submitPayment = async (): Promise<void> => {
    if (!canCreatePayment.value) {
        return;
    }

    actionProcessing.value = true;
    resetAnnouncements();

    try {
        const payload = {
            method: method.value,
            amount_rupiah: amountRupiah.value,
            note: note.value.trim() || undefined,
            ...(method.value === 'gateway' && selectedGateway.value
                ? {
                      gateway_driver: selectedGateway.value.driver,
                      gateway_method: selectedGateway.value.code,
                  }
                : {}),
        };
        const saved = await props.actions.createPayment(currentOrder.value.id, payload);

        if (saved) {
            message.value =
                method.value === 'qris'
                    ? 'QRIS dinamis siap dipindai.'
                    : method.value === 'gateway'
                      ? 'Instruksi pembayaran online berhasil dibuat.'
                      : 'Pembayaran tunai berhasil dicatat.';
            toast.success(message.value);
            note.value = '';
        } else {
            error.value = 'Pembayaran belum tersimpan. Perubahan sementara sudah dibatalkan.';
            toast.error(error.value);
        }
    } finally {
        actionProcessing.value = false;
    }
};

const reconcile = async (payment: MobilePayment, status: 'paid' | 'failed'): Promise<void> => {
    actionProcessing.value = true;
    resetAnnouncements();

    try {
        const saved = await props.actions.transitionPaymentStatus(
            currentOrder.value.id,
            payment.id,
            {
                status,
                note:
                    status === 'paid'
                        ? 'Pembayaran QRIS dikonfirmasi admin cabang'
                        : 'Pembayaran QRIS ditandai gagal oleh admin cabang',
            },
        );

        if (saved) {
            message.value =
                status === 'paid' ? 'Pembayaran sudah lunas.' : 'Pembayaran ditandai gagal.';
            toast.success(message.value);
        } else {
            error.value = 'Status pembayaran gagal diperbarui dan sudah dikembalikan.';
            toast.error(error.value);
        }
    } finally {
        actionProcessing.value = false;
    }
};

const loadReceipt = async (): Promise<void> => {
    actionProcessing.value = true;
    resetAnnouncements();

    try {
        receipt.value = await props.actions.loadReceipt(currentOrder.value.id);
        message.value = 'Struk digital berhasil dimuat.';
        toast.success(message.value);
    } catch (loadError: unknown) {
        error.value =
            loadError instanceof Error ? loadError.message : 'Struk digital tidak dapat dimuat.';
        toast.error(error.value);
    } finally {
        actionProcessing.value = false;
    }
};

const printReceipt = async (): Promise<void> => {
    actionProcessing.value = true;
    resetAnnouncements();

    try {
        const printed = await props.actions.printReceipt(currentOrder.value.id);

        if (printed) {
            message.value = 'Struk dikirim ke printer.';
            toast.success(message.value);
        } else {
            error.value = 'Struk belum dapat dicetak. Periksa profil dan koneksi printer.';
            toast.error(error.value);
        }
    } finally {
        actionProcessing.value = false;
    }
};

const shareReceipt = async (): Promise<void> => {
    if (!receipt.value) {
        return;
    }

    const whatsappUrl = new URL('https://wa.me/');
    whatsappUrl.searchParams.set(
        'text',
        `Struk digital ${receipt.value.receipt_number}\n${receipt.value.share_url}`,
    );

    try {
        await externalLinkTransport.open(whatsappUrl.toString());
    } catch (openError: unknown) {
        error.value =
            openError instanceof Error ? openError.message : 'WhatsApp tidak dapat dibuka.';
        toast.error(error.value);
    }
};

const requestPaymentVerification = async (payment: MobilePayment): Promise<void> => {
    actionProcessing.value = true;
    resetAnnouncements();

    try {
        const suggestion = await props.actions.loadPaymentWhatsAppSuggestion(
            currentOrder.value.id,
            payment.id,
        );

        if (!suggestion.available) {
            error.value = suggestion.unavailable_reason;
            return;
        }

        await externalLinkTransport.open(suggestion.url);
    } catch (openError: unknown) {
        error.value =
            openError instanceof Error ? openError.message : 'WhatsApp tidak dapat dibuka.';
    } finally {
        actionProcessing.value = false;
    }
};

const sharePaymentToCustomer = async (payment: MobilePayment): Promise<void> => {
    actionProcessing.value = true;
    resetAnnouncements();

    try {
        const suggestion = await props.actions.loadPaymentWhatsAppSuggestion(
            currentOrder.value.id,
            payment.id,
        );

        if (!suggestion.available) {
            error.value = suggestion.unavailable_reason;
            toast.error(
                suggestion.unavailable_reason ?? 'Nomor WhatsApp pelanggan tidak tersedia.',
            );
            return;
        }

        await externalLinkTransport.open(suggestion.url);
    } catch (openError: unknown) {
        error.value =
            openError instanceof Error ? openError.message : 'WhatsApp tidak dapat dibuka.';
        toast.error(error.value);
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
                {{ paymentTriggerLabel }}
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
                    {{
                        role === 'customer'
                            ? 'Bayar sisa tagihan dengan QRIS dinamis sesuai nominal pesanan.'
                            : 'Catat tunai, tampilkan QRIS dinamis, rekonsiliasi pembayaran, lalu buka atau cetak struk.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <section
                v-if="orderRefreshProcessing"
                class="flex min-h-40 items-center justify-center gap-2 rounded-2xl border border-line bg-canvas text-sm text-ink-500"
                data-test="payment-detail-loading"
            >
                <RefreshCw class="size-4 animate-spin" aria-hidden="true" />
                Memuat detail pembayaran terbaru…
            </section>
            <section
                v-else-if="orderRefreshError"
                class="rounded-2xl border border-red-600/20 bg-red-600/5 p-4 text-sm text-red-600"
                data-test="payment-detail-error"
            >
                <p>{{ orderRefreshError }}</p>
                <Button
                    type="button"
                    variant="outline"
                    class="mt-3 border-red-600/25 text-red-600"
                    data-test="retry-payment-detail"
                    @click="refreshPaymentDialog"
                >
                    <RefreshCw class="size-4" aria-hidden="true" />
                    Coba lagi
                </Button>
            </section>
            <template v-else>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-surface-blue p-3">
                    <p class="text-xs text-ink-500">Sudah dibayar</p>
                    <p class="mt-1 font-display text-lg font-semibold text-brand-700">
                        {{ formatRupiah(currentOrder.payment_summary.paid_rupiah) }}
                    </p>
                </div>
                <div class="rounded-xl bg-canvas p-3">
                    <p class="text-xs text-ink-500">Sisa tagihan</p>
                    <p class="mt-1 font-display text-lg font-semibold text-ink-950">
                        {{ formatRupiah(currentOrder.payment_summary.outstanding_rupiah) }}
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
                :can-reconcile="role === 'branch_admin'"
                :can-request-verification="role === 'customer'"
                :can-share-payment="role === 'branch_admin'"
                @reconcile="reconcile"
                @request-verification="requestPaymentVerification"
                @share-payment="sharePaymentToCustomer"
            />
            <PaymentEntryForm
                v-else-if="
                    role === 'branch_admin' &&
                    currentOrder.payment_summary.outstanding_rupiah > 0
                "
                v-model:method="method"
                v-model:amount-input="amountInput"
                v-model:note="note"
                v-model:selected-gateway-method="selectedGatewayMethod"
                :order="currentOrder"
                :busy="isBusy"
                :can-submit="canCreatePayment"
                :gateway-methods="gatewayMethods"
                @submit="submitPayment"
            />
            <section
                v-else-if="currentOrder.payment_summary.outstanding_rupiah > 0"
                class="rounded-2xl border border-brand-600/20 bg-surface-blue p-4"
                data-test="customer-qris-payment"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-700 shadow-low"
                    >
                        <QrCode class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="font-semibold text-ink-950">
                            Bayar {{ formatRupiah(currentOrder.payment_summary.outstanding_rupiah) }}
                        </p>
                        <p class="mt-1 text-sm leading-5 text-ink-500">
                            QRIS dibuat khusus dengan nominal tagihan ini, jadi Anda tidak perlu
                            mengetik jumlah pembayaran.
                        </p>
                    </div>
                </div>
                <Button
                    v-if="currentOrder.branch.qris_enabled"
                    type="button"
                    class="mt-4 min-h-11 w-full bg-brand-700 text-white hover:bg-brand-700/90"
                    data-test="customer-create-qris"
                    :disabled="!canCreatePayment"
                    @click="submitPayment"
                >
                    <QrCode class="size-4" aria-hidden="true" />
                    {{ isBusy ? 'Membuat QRIS…' : 'Buat QRIS dinamis' }}
                </Button>
                <p
                    v-else
                    class="mt-4 rounded-xl border border-amber-500/25 bg-amber-500/10 px-3 py-2 text-sm text-amber-800"
                >
                    QRIS belum diaktifkan untuk cabang ini. Hubungi petugas untuk metode
                    pembayaran lain.
                </p>
            </section>
            <section
                v-else
                class="rounded-2xl border border-mint-500/20 bg-mint-500/10 p-4 text-mint-500"
            >
                <div class="flex items-center gap-2 font-semibold">
                    <CheckCircle2 class="size-5" aria-hidden="true" />
                    Pesanan sudah lunas
                </div>
            </section>

            <PaymentHistory
                v-if="currentOrder.payments.length"
                :payments="currentOrder.payments"
            />
            <DigitalReceiptPanel
                :receipt="receipt"
                :busy="isBusy"
                :printer-configured="printerConfigured && role === 'branch_admin'"
                :show-print-action="role === 'branch_admin'"
                @load="loadReceipt"
                @print="printReceipt"
                @share="shareReceipt"
            />
            </template>
        </DialogScrollContent>
    </Dialog>
</template>
