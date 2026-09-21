<script setup lang="ts">
import { AlertCircle, ArrowDown, ArrowUp, Scale } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input, RupiahInput } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatRupiah, formatWeight } from '@/lib/mobileOrders';
import type {
    ConfirmOrderPricingPayload,
    MobileOrder,
    MobilePriceOption,
    MobileService,
} from '@/types/mobile';

const props = withDefaults(
    defineProps<{
        order: MobileOrder;
        services?: readonly MobileService[];
        initialWeightGrams?: number;
        processing?: boolean;
    }>(),
    {
        services: () => [],
        initialWeightGrams: 0,
        processing: false,
    },
);

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    confirm: [payload: ConfirmOrderPricingPayload];
}>();

const catalogOptions = computed(() =>
    props.services.flatMap((service) =>
        service.price_options.map((option) => ({ service, option })),
    ),
);

const selectedPriceOptionId = ref<number>(
    props.order.items[0]?.price_option.id ?? catalogOptions.value[0]?.option.id ?? 0,
);
const weightKilograms = ref<number | string>(
    props.initialWeightGrams > 0 ? (props.initialWeightGrams / 1000).toString() : '1',
);
const customPricePerKg = ref<number | null>(null);

const currentCatalogEntry = computed(
    () =>
        catalogOptions.value.find((entry) => entry.option.id === selectedPriceOptionId.value) ??
        null,
);

const selectedOption = computed<MobilePriceOption | null>(() => {
    if (currentCatalogEntry.value) {
        return currentCatalogEntry.value.option;
    }
    const itemOption = props.order.items[0]?.price_option;
    if (itemOption) {
        return {
            id: itemOption.id,
            name: itemOption.name,
            type: itemOption.type,
            pricing: itemOption.pricing_snapshot,
        };
    }
    return null;
});

const isPerKilogram = computed(() => selectedOption.value?.type === 'per_kilogram');

const hargaAtas = computed(() => selectedOption.value?.pricing.price_per_kilogram_rupiah ?? 0);

const hargaBawah = computed(
    () =>
        selectedOption.value?.pricing.min_price_per_kilogram_rupiah ??
        selectedOption.value?.pricing.price_per_kilogram_rupiah ??
        0,
);

const syncPricesFromOption = (): void => {
    if (isPerKilogram.value) {
        customPricePerKg.value = hargaAtas.value;
    } else {
        customPricePerKg.value = null;
    }
};

watch(
    open,
    (isOpen) => {
        if (isOpen) {
            const currentItem = props.order.items[0];
            selectedPriceOptionId.value =
                currentItem?.price_option.id ?? catalogOptions.value[0]?.option.id ?? 0;
            weightKilograms.value =
                props.initialWeightGrams > 0
                    ? (props.initialWeightGrams / 1000).toString()
                    : currentItem?.calculation.requested_weight_grams
                      ? (currentItem.calculation.requested_weight_grams / 1000).toString()
                      : '1';
            syncPricesFromOption();
        }
    },
    { immediate: true },
);

watch(selectedPriceOptionId, () => {
    syncPricesFromOption();
});

const weightGrams = computed(() => Math.round(Number(weightKilograms.value) * 1000));

const numericPricePerKg = computed(() => {
    const val = Number(customPricePerKg.value);
    return Number.isFinite(val) ? val : 0;
});

const isPriceBelowFloor = computed(
    () => isPerKilogram.value && numericPricePerKg.value < hargaBawah.value,
);

const validationError = computed<string | null>(() => {
    if (!Number.isFinite(Number(weightKilograms.value)) || weightGrams.value < 1) {
        return 'Berat aktual laundry harus lebih besar dari 0 kg.';
    }
    if (isPerKilogram.value) {
        if (!customPricePerKg.value || numericPricePerKg.value <= 0) {
            return 'Masukkan harga per kilogram yang valid.';
        }
        if (numericPricePerKg.value < hargaBawah.value) {
            return `Harga per kg tidak boleh lebih rendah dari harga bawah (${formatRupiah(hargaBawah.value)}/kg).`;
        }
    }
    return null;
});

const chargeableWeightGrams = computed(() => {
    const minGrams = selectedOption.value?.pricing.minimum_chargeable_grams ?? 0;
    return Math.max(weightGrams.value, minGrams);
});

const subtotalLaundry = computed(() => {
    if (!selectedOption.value) {
        return 0;
    }
    if (isPerKilogram.value) {
        return Math.round((chargeableWeightGrams.value * numericPricePerKg.value) / 1000);
    }
    const basePrice = selectedOption.value.pricing.base_price_rupiah ?? 0;
    const packageWeight = selectedOption.value.pricing.package_weight_grams ?? 0;
    const excessKgRate = selectedOption.value.pricing.excess_price_per_kilogram_rupiah ?? 0;
    const excessGrams = Math.max(0, weightGrams.value - packageWeight);
    const excessPrice = excessGrams > 0 ? Math.round((excessGrams * excessKgRate) / 1000) : 0;
    return basePrice + excessPrice;
});

const deliveryFee = computed(() => props.order.delivery?.fee_rupiah ?? 0);
const totalRupiah = computed(() => subtotalLaundry.value + deliveryFee.value);

const selectHargaAtas = (): void => {
    customPricePerKg.value = hargaAtas.value;
};

const selectHargaBawah = (): void => {
    customPricePerKg.value = hargaBawah.value;
};

const close = (): void => {
    if (!props.processing) {
        open.value = false;
    }
};

const submitConfirmation = (): void => {
    if (validationError.value || props.processing) {
        return;
    }

    emit('confirm', {
        price_option_id: selectedPriceOptionId.value,
        weight_grams: weightGrams.value,
        custom_price_per_kilogram_rupiah: isPerKilogram.value ? numericPricePerKg.value : undefined,
    });
    open.value = false;
};
</script>

<template>
    <Dialog :open="open" @update:open="open = $event">
        <DialogContent
            class="max-h-[92dvh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-3xl border border-line bg-surface p-5 shadow-raised sm:p-6"
            :show-close-button="!processing"
        >
            <DialogHeader class="text-left">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-brand-100 text-brand-700">
                        <Scale class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <DialogTitle class="font-display text-lg font-semibold text-ink-950">
                            Pendetailan Berat & Harga
                        </DialogTitle>
                        <DialogDescription class="text-xs text-ink-500">
                            {{ order.order_number }} · {{ order.customer.name }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="mt-4 space-y-4 text-left">
                <!-- Input Berat Aktual -->
                <div>
                    <Label for="dialog-actual-weight" class="text-xs font-semibold text-ink-950">
                        Berat Aktual (kg)
                    </Label>
                    <div class="relative mt-1.5">
                        <Scale
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-500"
                            aria-hidden="true"
                        />
                        <Input
                            id="dialog-actual-weight"
                            v-model="weightKilograms"
                            type="number"
                            min="0.1"
                            max="1000"
                            step="any"
                            inputmode="decimal"
                            required
                            class="min-h-11 rounded-xl pr-12 pl-10 text-sm"
                            data-test="dialog-actual-weight"
                        />
                        <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-ink-500">
                            kg
                        </span>
                    </div>
                </div>

                <!-- Pilihan Paket Layanan (Bisa Diganti) -->
                <div>
                    <Label for="dialog-service-package" class="text-xs font-semibold text-ink-950">
                        Paket Layanan
                    </Label>
                    <select
                        id="dialog-service-package"
                        v-model="selectedPriceOptionId"
                        class="mt-1.5 min-h-11 w-full rounded-xl border border-line bg-surface px-3 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                        data-test="dialog-service-package"
                    >
                        <option v-for="entry in catalogOptions" :key="entry.option.id" :value="entry.option.id">
                            {{ entry.service.name }} · {{ entry.option.name }}
                        </option>
                    </select>
                </div>

                <!-- Bagian Harga Atas & Harga Bawah (Khusus Paket Kiloan) -->
                <div v-if="isPerKilogram" class="space-y-3 rounded-2xl border border-line bg-surface-blue/50 p-4">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-ink-700">Batas Tarif Paket:</span>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-1 font-medium text-ink-700">
                                <ArrowUp class="size-3 text-brand-600" aria-hidden="true" />
                                Atas: <strong>{{ formatRupiah(hargaAtas) }}</strong>/kg
                            </span>
                            <span class="inline-flex items-center gap-1 font-medium text-amber-700">
                                <ArrowDown class="size-3 text-amber-600" aria-hidden="true" />
                                Bawah: <strong>{{ formatRupiah(hargaBawah) }}</strong>/kg
                            </span>
                        </div>
                    </div>

                    <div>
                        <Label for="dialog-price-per-kg" class="text-xs font-semibold text-ink-950">
                            Harga per kg untuk Pesanan Ini (Rp)
                        </Label>
                        <div class="mt-1.5 flex gap-2">
                            <RupiahInput
                                id="dialog-price-per-kg"
                                v-model="customPricePerKg"
                                placeholder="Contoh: 15.000"
                                required
                                class="min-h-11 rounded-xl text-sm"
                                :class="isPriceBelowFloor ? 'border-red-500 focus-visible:ring-red-200' : ''"
                                data-test="dialog-price-per-kg"
                            />
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex min-h-8 items-center rounded-lg border border-brand-600/30 bg-brand-50 px-2.5 text-xs font-semibold text-brand-700 transition hover:bg-brand-100"
                                data-test="use-harga-atas"
                                @click="selectHargaAtas"
                            >
                                Gunakan Harga Atas ({{ formatRupiah(hargaAtas) }})
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-8 items-center rounded-lg border border-amber-600/30 bg-amber-50 px-2.5 text-xs font-semibold text-amber-800 transition hover:bg-amber-100"
                                data-test="use-harga-bawah"
                                @click="selectHargaBawah"
                            >
                                Gunakan Harga Bawah ({{ formatRupiah(hargaBawah) }})
                            </button>
                        </div>
                    </div>

                    <p v-if="isPriceBelowFloor" class="flex items-center gap-1.5 text-xs font-medium text-red-600" role="alert">
                        <AlertCircle class="size-3.5 shrink-0" aria-hidden="true" />
                        Harga tidak boleh lebih rendah dari harga bawah ({{ formatRupiah(hargaBawah) }}/kg).
                    </p>
                </div>

                <!-- Ringkasan Kalkulasi -->
                <div class="rounded-2xl bg-canvas p-4">
                    <p class="text-xs font-semibold tracking-[0.12em] text-ink-500 uppercase">
                        Rincian Perhitungan
                    </p>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between text-xs">
                            <dt class="text-ink-500">Berat aktual</dt>
                            <dd class="font-medium text-ink-950">{{ formatWeight(weightGrams) }}</dd>
                        </div>
                        <div v-if="isPerKilogram && selectedOption?.pricing.minimum_chargeable_grams" class="flex items-center justify-between text-xs">
                            <dt class="text-ink-500">Minimum tagihan</dt>
                            <dd class="font-medium text-ink-950">{{ formatWeight(selectedOption.pricing.minimum_chargeable_grams) }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <dt class="text-ink-500">Berat tertagih</dt>
                            <dd class="font-medium text-ink-950">{{ formatWeight(chargeableWeightGrams) }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <dt class="text-ink-500">Tarif laundry</dt>
                            <dd class="font-medium text-ink-950">
                                {{ isPerKilogram ? `${formatRupiah(numericPricePerKg)}/kg` : formatRupiah(subtotalLaundry) }}
                            </dd>
                        </div>
                        <div v-if="deliveryFee > 0" class="flex items-center justify-between text-xs">
                            <dt class="text-ink-500">Biaya antar-jemput</dt>
                            <dd class="font-medium text-ink-950">{{ formatRupiah(deliveryFee) }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-line pt-2">
                            <dt class="font-semibold text-ink-950">Total Akhir</dt>
                            <dd class="font-display text-lg font-semibold text-brand-700" data-test="dialog-total-price">
                                {{ formatRupiah(totalRupiah) }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <p v-if="validationError && !isPriceBelowFloor" class="text-xs text-red-600" role="alert">
                    {{ validationError }}
                </p>
            </div>

            <DialogFooter class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-xl"
                    :disabled="processing"
                    @click="close"
                >
                    Batal
                </Button>
                <Button
                    type="button"
                    class="rounded-xl bg-brand-700 hover:bg-brand-600"
                    :disabled="processing || Boolean(validationError)"
                    data-test="submit-pricing-confirmation"
                    @click="submitConfirmation"
                >
                    {{ processing ? 'Menyimpan…' : 'Simpan & Konfirmasi' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
