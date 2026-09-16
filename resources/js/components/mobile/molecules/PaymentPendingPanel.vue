<script setup lang="ts">
import { CheckCircle2, Copy, Download, MessageCircle, XCircle } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { formatRupiah } from '@/lib/mobileOrders';
import { qrisDownloadTransport } from '@/lib/qrisDownload/transport';
import type { MobilePayment } from '@/types/mobile';

const props = withDefaults(
    defineProps<{
        payment: MobilePayment;
        busy: boolean;
        canReconcile?: boolean;
        canRequestVerification?: boolean;
        canSharePayment?: boolean;
    }>(),
    {
        canReconcile: true,
        canRequestVerification: false,
        canSharePayment: false,
    },
);

const copied = ref(false);
const downloadProcessing = ref(false);
const isGateway = computed(() => props.payment.gateway !== null);
const isGatewayQris = computed(() => props.payment.gateway?.method === 'QRIS');

const copyPaymentNumber = async (): Promise<void> => {
    const paymentNumber = props.payment.gateway?.payment_number;

    if (!paymentNumber) {
        return;
    }

    await navigator.clipboard.writeText(paymentNumber);
    copied.value = true;
};

const downloadQris = async (): Promise<void> => {
    const dataUri = props.payment.qris_image_data_uri;
    if (!dataUri || downloadProcessing.value) {
        return;
    }

    downloadProcessing.value = true;

    try {
        const result = await qrisDownloadTransport.save({
            dataUri,
            filename: `${props.payment.payment_number}.svg`,
        });

        if (result.status === 'saved') {
            toast.success('QRIS berhasil disimpan.');
        } else if (result.status === 'download_started') {
            toast.success('Unduhan QRIS dimulai.');
        }
    } catch (downloadError: unknown) {
        toast.error(
            downloadError instanceof Error ? downloadError.message : 'QRIS belum dapat diunduh.',
        );
    } finally {
        downloadProcessing.value = false;
    }
};

const emit = defineEmits<{
    reconcile: [payment: MobilePayment, status: 'paid' | 'failed'];
    requestVerification: [payment: MobilePayment];
    sharePayment: [payment: MobilePayment];
}>();
</script>

<template>
    <section class="rounded-2xl border border-line bg-canvas p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold tracking-[0.14em] text-brand-600 uppercase">
                    {{ isGateway ? `${payment.gateway?.driver_label} aktif` : 'QRIS aktif' }}
                </p>
                <p class="mt-1 font-semibold text-ink-950">{{ payment.payment_number }}</p>
                <p class="text-sm text-ink-500">{{ formatRupiah(payment.amount_rupiah) }}</p>
            </div>
            <span class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-700">
                Menunggu
            </span>
        </div>

        <div
            v-if="payment.qris_image_data_uri && (!isGateway || isGatewayQris)"
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
            v-if="payment.qris_image_data_uri && (!isGateway || isGatewayQris)"
            class="mt-3 grid gap-2"
            :class="canSharePayment ? 'sm:grid-cols-2' : ''"
        >
            <Button
                type="button"
                variant="outline"
                class="w-full border-brand-600/25 text-brand-700"
                :disabled="downloadProcessing"
                data-test="download-qris"
                @click="downloadQris"
            >
                <Download class="size-4" aria-hidden="true" />
                {{ downloadProcessing ? 'Menyimpan QRIS...' : 'Unduh QRIS' }}
            </Button>
            <Button
                v-if="canSharePayment"
                type="button"
                class="w-full bg-mint-500 text-white hover:bg-mint-500/90"
                :disabled="busy"
                data-test="share-payment-whatsapp"
                @click="emit('sharePayment', payment)"
            >
                <MessageCircle class="size-4" aria-hidden="true" />
                Bagikan ke WhatsApp
            </Button>
        </div>
        <div
            v-else-if="isGateway && payment.gateway?.payment_number"
            class="mt-4 rounded-2xl border border-line bg-surface p-4"
        >
            <p class="text-xs font-semibold tracking-[0.12em] text-ink-500 uppercase">
                Nomor pembayaran {{ payment.gateway.method }}
            </p>
            <p class="mt-2 break-all font-mono text-lg font-semibold text-ink-950">
                {{ payment.gateway.payment_number }}
            </p>
            <div class="mt-3 grid gap-2" :class="canSharePayment ? 'sm:grid-cols-2' : ''">
                <Button
                    type="button"
                    variant="outline"
                    class="w-full border-brand-600/25 text-brand-700"
                    data-test="copy-gateway-va"
                    @click="copyPaymentNumber"
                >
                    <Copy class="size-4" aria-hidden="true" />
                    {{ copied ? 'Nomor tersalin' : 'Salin nomor pembayaran' }}
                </Button>
                <Button
                    v-if="canSharePayment"
                    type="button"
                    class="w-full bg-mint-500 text-white hover:bg-mint-500/90"
                    :disabled="busy"
                    data-test="share-payment-whatsapp"
                    @click="emit('sharePayment', payment)"
                >
                    <MessageCircle class="size-4" aria-hidden="true" />
                    Bagikan ke WhatsApp
                </Button>
            </div>
        </div>
        <div
            v-else-if="!isGateway || isGatewayQris"
            class="mt-4 flex min-h-40 items-center justify-center rounded-2xl border border-dashed border-line bg-surface text-center text-sm text-ink-500"
        >
            QR sedang dibuat oleh server.
        </div>

        <div v-if="!isGateway && canReconcile" class="mt-4 grid gap-2 sm:grid-cols-2">
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
        <Button
            v-if="canRequestVerification"
            type="button"
            variant="outline"
            class="mt-4 w-full border-mint-500/30 text-mint-500"
            :disabled="busy"
            data-test="request-payment-verification"
            @click="emit('requestVerification', payment)"
        >
            <MessageCircle class="size-4" aria-hidden="true" />
            Minta verifikasi via WhatsApp
        </Button>
    </section>
</template>
