<script setup lang="ts">
import { Banknote, LoaderCircle, QrCode } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MobileOrder, MobilePaymentMethod } from '@/types/mobile';

defineProps<{
    order: MobileOrder;
    busy: boolean;
    method: MobilePaymentMethod;
    amountInput: string;
    note: string;
    canSubmit: boolean;
}>();

const emit = defineEmits<{
    'update:method': [method: MobilePaymentMethod];
    'update:amountInput': [amount: string];
    'update:note': [note: string];
    submit: [];
}>();
</script>

<template>
    <form
        class="space-y-4 rounded-2xl border border-line p-4"
        data-test="payment-form"
        @submit.prevent="emit('submit')"
    >
        <div>
            <p class="text-sm font-semibold text-ink-950">Tambah pembayaran</p>
            <p class="mt-1 text-xs leading-5 text-ink-500">
                Pembayaran sebagian diperbolehkan sampai sisa tagihan habis.
            </p>
        </div>

        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-ink-950">Metode</legend>
            <div class="grid grid-cols-2 gap-2">
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
                    :disabled="!order.branch.qris_enabled"
                    @click="emit('update:method', 'qris')"
                >
                    <QrCode class="size-5" aria-hidden="true" />
                    QRIS
                </button>
            </div>
            <p v-if="!order.branch.qris_enabled" class="mt-2 text-xs text-ink-500">
                QRIS belum diaktifkan untuk cabang ini.
            </p>
        </fieldset>

        <div class="space-y-2">
            <Label :for="`payment-amount-${order.id}`">Nominal</Label>
            <Input
                :id="`payment-amount-${order.id}`"
                :model-value="amountInput"
                type="number"
                min="1"
                :max="order.payment_summary.outstanding_rupiah"
                step="1"
                inputmode="numeric"
                @update:model-value="emit('update:amountInput', String($event))"
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
            {{ method === 'qris' ? 'Buat QRIS dinamis' : 'Catat pembayaran tunai' }}
        </Button>
    </form>
</template>
