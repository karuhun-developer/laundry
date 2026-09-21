<script setup lang="ts">
import { Banknote, CreditCard, LoaderCircle, QrCode } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input, RupiahInput } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatRupiah } from '@/lib/mobileOrders';
import { paymentGatewayMethodLogo } from '@/lib/mobilePayments';
import type { MobileOrder, MobilePaymentGatewayMethod, MobilePaymentMethod } from '@/types/mobile';

withDefaults(
    defineProps<{
        order: MobileOrder;
        busy: boolean;
        method: MobilePaymentMethod;
        amountInput: string | number | null;
        note: string;
        canSubmit: boolean;
        gatewayMethods: readonly MobilePaymentGatewayMethod[];
        selectedGatewayMethod: string;
        hasPendingPayment?: boolean;
    }>(),
    {
        hasPendingPayment: false,
    },
);

const emit = defineEmits<{
    'update:method': [method: MobilePaymentMethod];
    'update:amountInput': [amount: string];
    'update:note': [note: string];
    'update:selectedGatewayMethod': [method: string];
    submit: [];
}>();

const gatewayMethodDescription = (method: MobilePaymentGatewayMethod): string => {
    const fee = method.fee_rupiah > 0 ? `Biaya ${formatRupiah(method.fee_rupiah)}` : 'Tanpa biaya';

    return `${fee} · ${formatRupiah(method.minimum_rupiah)}–${formatRupiah(method.maximum_rupiah)}`;
};
</script>

<template>
    <form
        class="space-y-4 rounded-2xl border border-line p-4"
        data-test="payment-form"
        @submit.prevent="emit('submit')"
    >
        <div>
            <p class="text-sm font-semibold text-ink-950">
                {{ hasPendingPayment ? 'Terima pembayaran tunai di kasir' : 'Tambah pembayaran' }}
            </p>
            <p class="mt-1 text-xs leading-5 text-ink-500">
                {{
                    hasPendingPayment
                        ? 'QRIS dinamis sedang aktif di atas. Kasir tetap dapat menerima pembayaran tunai langsung di konter.'
                        : 'Pembayaran sebagian diperbolehkan sampai sisa tagihan habis.'
                }}
            </p>
        </div>

        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-ink-950">Metode</legend>
            <div class="grid grid-cols-2 gap-2" :class="gatewayMethods.length ? 'sm:grid-cols-3' : ''">
                <button
                    type="button"
                    class="flex min-h-12 items-center justify-center gap-2 rounded-xl border px-3 text-sm font-semibold"
                    :class="method === 'cash' ? 'border-brand-600 bg-brand-100 text-brand-700' : 'border-line bg-surface text-ink-700'"
                    @click="emit('update:method', 'cash')"
                >
                    <Banknote class="size-5" aria-hidden="true" />
                    Tunai
                </button>
                <button
                    type="button"
                    class="flex min-h-12 items-center justify-center gap-2 rounded-xl border px-3 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50"
                    :class="method === 'qris' ? 'border-brand-600 bg-brand-100 text-brand-700' : 'border-line bg-surface text-ink-700'"
                    :disabled="!order.branch.qris_enabled || hasPendingPayment"
                    :title="hasPendingPayment ? 'QRIS dinamis sudah aktif di atas' : undefined"
                    @click="emit('update:method', 'qris')"
                >
                    <QrCode class="size-5" aria-hidden="true" />
                    QRIS
                </button>
                <button
                    v-if="gatewayMethods.length"
                    type="button"
                    class="flex min-h-12 items-center justify-center gap-2 rounded-xl border px-3 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50"
                    :class="method === 'gateway' ? 'border-brand-600 bg-brand-100 text-brand-700' : 'border-line bg-surface text-ink-700'"
                    :disabled="hasPendingPayment"
                    data-test="payment-method-gateway"
                    @click="emit('update:method', 'gateway')"
                >
                    <CreditCard class="size-5" aria-hidden="true" />
                    Online
                </button>
            </div>
            <p v-if="!order.branch.qris_enabled" class="mt-2 text-xs text-ink-500">
                QRIS belum diaktifkan untuk cabang ini.
            </p>
        </fieldset>

        <fieldset v-if="method === 'gateway' && gatewayMethods.length">
            <legend class="mb-2 text-sm font-semibold text-ink-950">Metode online</legend>
            <div class="space-y-2">
                <button
                    v-for="gatewayMethod in gatewayMethods"
                    :key="`${gatewayMethod.driver}:${gatewayMethod.code}`"
                    type="button"
                    class="flex min-h-14 w-full items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left"
                    :class="selectedGatewayMethod === gatewayMethod.code ? 'border-brand-600 bg-brand-100' : 'border-line bg-surface'"
                    :data-test="`gateway-method-${gatewayMethod.code}`"
                    @click="emit('update:selectedGatewayMethod', gatewayMethod.code)"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-lg border border-line bg-white p-2">
                            <img
                                v-if="paymentGatewayMethodLogo(gatewayMethod.code)"
                                :src="paymentGatewayMethodLogo(gatewayMethod.code) ?? undefined"
                                alt=""
                                class="max-h-8 max-w-full object-contain"
                                aria-hidden="true"
                                :data-test="`gateway-method-logo-${gatewayMethod.code}`"
                            />
                            <CreditCard
                                v-else
                                class="size-6 text-ink-500"
                                aria-hidden="true"
                                :data-test="`gateway-method-icon-${gatewayMethod.code}`"
                            />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-ink-950">{{ gatewayMethod.name }}</span>
                            <span class="mt-0.5 block text-xs text-ink-500">
                                {{ gatewayMethodDescription(gatewayMethod) }}
                            </span>
                        </span>
                    </span>
                    <span class="text-xs font-semibold text-brand-700">{{ gatewayMethod.driver_label }}</span>
                </button>
            </div>
        </fieldset>

        <div class="space-y-2">
            <Label :for="`payment-amount-${order.id}`">Nominal</Label>
            <RupiahInput
                :id="`payment-amount-${order.id}`"
                :model-value="amountInput"
                :min="1"
                :max="order.payment_summary.outstanding_rupiah"
                placeholder="Contoh: 15.000"
                required
                data-test="payment-amount-input"
                @update:model-value="emit('update:amountInput', $event !== null ? String($event) : '')"
            />
        </div>

        <div class="space-y-2">
            <Label :for="`payment-note-${order.id}`">Catatan opsional</Label>
            <Input
                :id="`payment-note-${order.id}`"
                :model-value="note"
                maxlength="500"
                placeholder="Contoh: uang diterima kasir"
                @update:model-value="emit('update:note', String($event))"
            />
        </div>

        <Button type="submit" class="w-full bg-brand-700 text-white" :disabled="!canSubmit">
            <LoaderCircle v-if="busy" class="size-4 animate-spin" aria-hidden="true" />
            {{
                method === 'qris'
                    ? 'Buat QRIS dinamis'
                    : method === 'gateway'
                      ? 'Buat pembayaran online'
                      : 'Catat pembayaran tunai'
            }}
        </Button>
    </form>
</template>
