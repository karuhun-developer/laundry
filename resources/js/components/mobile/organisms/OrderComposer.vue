<script setup lang="ts">
import { AlertCircle, ArrowDown, ArrowUp, MapPin, PlusCircle, Scale, Sparkles } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CustomerCombobox from '@/components/mobile/molecules/CustomerCombobox.vue';
import type { Coordinates } from '@/composables/useGeolocation';
import { formatRupiah, formatWeight } from '@/lib/mobileOrders';
import type {
    CreateOrderPayload,
    MobileBranch,
    MobileCustomer,
    MobileCustomerAddress,
    MobilePriceQuote,
    MobileRole,
    MobileService,
} from '@/types/mobile';

type Props = {
    role: MobileRole;
    branch: MobileBranch | null;
    services: readonly MobileService[];
    customers: readonly MobileCustomer[];
    deliveryCoordinates: Coordinates | null;
    selectedAddress?: MobileCustomerAddress | null;
    activePickupOrderNumber?: string | null;
    quoteOrder: (
        priceOptionId: number,
        weightGrams: number,
        customPricePerKilogramRupiah?: number,
    ) => Promise<MobilePriceQuote>;
    deliveryFeeRupiah?: number;
    processing?: boolean;
    selectedCustomerId?: number | null;
};

const props = withDefaults(defineProps<Props>(), {
    deliveryFeeRupiah: 0,
    processing: false,
    selectedCustomerId: null,
    selectedAddress: null,
    activePickupOrderNumber: null,
});

const emit = defineEmits<{
    submit: [payload: CreateOrderPayload, quote: MobilePriceQuote | null];
    'create-customer': [];
    'manage-addresses': [];
    'update:selectedCustomerId': [customerId: number | null];
}>();

const customerId = computed({
    get: () => props.selectedCustomerId,
    set: (customerId: number | null) => emit('update:selectedCustomerId', customerId),
});
const priceOptionId = ref<number | null>(null);
const weightKilograms = ref<number | string>(1);
const customPricePerKg = ref<number | string>('');
const includeDelivery = ref(false);
const formError = ref<string | null>(null);
const quote = ref<MobilePriceQuote | null>(null);
const quoteError = ref<string | null>(null);
const quoteLoading = ref(false);
const submitPending = ref(false);
let quoteTimer: ReturnType<typeof setTimeout> | null = null;
let submitUnlockTimer: ReturnType<typeof setTimeout> | null = null;
let quoteGeneration = 0;

const catalogOptions = computed(() =>
    props.services.flatMap((service) =>
        service.price_options.map((option) => ({ service, option })),
    ),
);
const currentCatalogEntry = computed(
    () => catalogOptions.value.find((entry) => entry.option.id === priceOptionId.value) ?? null,
);
const selectedOption = computed(() => currentCatalogEntry.value?.option ?? null);
const isPerKilogram = computed(() => selectedOption.value?.type === 'per_kilogram');

const hargaAtas = computed(() => selectedOption.value?.pricing.price_per_kilogram_rupiah ?? 0);
const hargaBawah = computed(
    () =>
        selectedOption.value?.pricing.min_price_per_kilogram_rupiah ??
        selectedOption.value?.pricing.price_per_kilogram_rupiah ??
        0,
);

const numericPricePerKg = computed(() => {
    const val = Number(customPricePerKg.value);
    return Number.isFinite(val) ? val : 0;
});

const isPriceBelowFloor = computed(
    () =>
        props.role === 'branch_admin' &&
        isPerKilogram.value &&
        numericPricePerKg.value < hargaBawah.value,
);

const syncPricesFromOption = (): void => {
    if (props.role === 'branch_admin' && isPerKilogram.value) {
        customPricePerKg.value = hargaAtas.value;
    } else {
        customPricePerKg.value = '';
    }
};

const selectHargaAtas = (): void => {
    customPricePerKg.value = hargaAtas.value;
};

const selectHargaBawah = (): void => {
    customPricePerKg.value = hargaBawah.value;
};

const isCustomer = computed(() => props.role === 'customer');
const weightGrams = computed(() => Math.round(Number(weightKilograms.value) * 1000));
const deliveryFee = computed(() =>
    includeDelivery.value && props.deliveryCoordinates ? props.deliveryFeeRupiah : 0,
);
const finalTotal = computed(() => (quote.value?.total_rupiah ?? 0) + deliveryFee.value);
const hasCurrentQuote = computed(
    () =>
        quote.value !== null &&
        quote.value.price_option.id === priceOptionId.value &&
        quote.value.requested_weight_grams === weightGrams.value &&
        (!isPerKilogram.value ||
            props.role !== 'branch_admin' ||
            quote.value.effective_price_per_kilogram_rupiah === numericPricePerKg.value),
);

watch(
    catalogOptions,
    (options) => {
        if (!options.some((entry) => entry.option.id === priceOptionId.value)) {
            priceOptionId.value = options[0]?.option.id ?? null;
            syncPricesFromOption();
        }
    },
    { immediate: true },
);

watch(priceOptionId, () => {
    syncPricesFromOption();
});

watch(
    () => props.customers,
    (availableCustomers) => {
        if (!availableCustomers.some((customer) => customer.id === customerId.value)) {
            customerId.value = null;
        }
    },
);

watch(
    () => props.deliveryCoordinates,
    (coordinates) => {
        if (!isCustomer.value && coordinates) {
            includeDelivery.value = true;
        }
    },
);

watch(
    () => props.processing,
    (processing) => {
        if (!processing && submitPending.value) {
            submitPending.value = false;

            if (submitUnlockTimer) {
                clearTimeout(submitUnlockTimer);
                submitUnlockTimer = null;
            }
        }
    },
);

const scheduleQuote = (): void => {
    quoteGeneration += 1;
    const requestGeneration = quoteGeneration;

    if (quoteTimer) {
        clearTimeout(quoteTimer);
        quoteTimer = null;
    }

    quote.value = null;
    quoteError.value = null;
    quoteLoading.value = false;

    if (isCustomer.value || !props.branch || !priceOptionId.value || weightGrams.value < 1) {
        return;
    }

    const requestedPriceOptionId = priceOptionId.value;
    const requestedWeightGrams = weightGrams.value;
    const requestedCustomPrice =
        props.role === 'branch_admin' && isPerKilogram.value ? numericPricePerKg.value : undefined;

    if (props.role === 'branch_admin' && isPriceBelowFloor.value) {
        quoteError.value = `Harga per kg tidak boleh lebih rendah dari harga bawah (${formatRupiah(hargaBawah.value)}/kg).`;
        return;
    }

    quoteTimer = setTimeout(async () => {
        quoteLoading.value = true;

        try {
            const response = await props.quoteOrder(
                requestedPriceOptionId,
                requestedWeightGrams,
                requestedCustomPrice,
            );

            if (requestGeneration === quoteGeneration) {
                quote.value = response;
            }
        } catch (error: unknown) {
            if (requestGeneration === quoteGeneration) {
                quoteError.value =
                    error instanceof Error ? error.message : 'Rincian harga tidak dapat dihitung.';
            }
        } finally {
            if (requestGeneration === quoteGeneration) {
                quoteLoading.value = false;
            }
        }
    }, 250);
};

watch(
    [
        () => props.branch?.id ?? null,
        () => props.role,
        priceOptionId,
        weightGrams,
        customPricePerKg,
    ],
    scheduleQuote,
    {
        immediate: true,
    },
);

onBeforeUnmount(() => {
    quoteGeneration += 1;

    if (quoteTimer) {
        clearTimeout(quoteTimer);
    }

    if (submitUnlockTimer) {
        clearTimeout(submitUnlockTimer);
    }
});

const submit = (): void => {
    if (submitPending.value || props.processing) {
        return;
    }

    formError.value = null;

    if (!props.branch) {
        formError.value = 'Pilih cabang sebelum membuat pesanan.';
        return;
    }

    if (!priceOptionId.value) {
        formError.value = 'Pilih layanan laundry.';
        return;
    }

    if (isCustomer.value && !props.selectedAddress) {
        formError.value = 'Pilih alamat penjemputan terlebih dahulu.';
        return;
    }

    if (isCustomer.value && props.activePickupOrderNumber) {
        formError.value = `Penjemputan ${props.activePickupOrderNumber} masih diproses.`;
        return;
    }

    if (!isCustomer.value && weightGrams.value < 1) {
        formError.value = 'Masukkan berat laundry yang valid.';
        return;
    }

    if (!isCustomer.value && !customerId.value) {
        formError.value = 'Pilih pelanggan untuk pesanan ini.';
        return;
    }

    if (props.role === 'branch_admin' && isPerKilogram.value) {
        if (!customPricePerKg.value || numericPricePerKg.value <= 0) {
            formError.value = 'Masukkan harga per kilogram yang valid.';
            return;
        }
        if (numericPricePerKg.value < hargaBawah.value) {
            formError.value = `Harga per kg tidak boleh lebih rendah dari harga bawah (${formatRupiah(hargaBawah.value)}/kg).`;
            return;
        }
    }

    if (!isCustomer.value && (!quote.value || !hasCurrentQuote.value)) {
        formError.value =
            quoteError.value ?? 'Tunggu rincian harga terbaru sebelum membuat pesanan.';
        return;
    }

    const payload: CreateOrderPayload = {
        items: [
            {
                price_option_id: priceOptionId.value,
                ...(props.role === 'branch_admin' && isPerKilogram.value
                    ? { custom_price_per_kilogram_rupiah: numericPricePerKg.value }
                    : {}),
            },
        ],
    };

    if (!isCustomer.value && customerId.value) {
        payload.customer_id = customerId.value;
        payload.items[0].weight_grams = weightGrams.value;
    }

    if (isCustomer.value && props.selectedAddress) {
        payload.customer_address_id = props.selectedAddress.id;
    }

    if (!isCustomer.value && includeDelivery.value && props.deliveryCoordinates) {
        payload.delivery = props.deliveryCoordinates;
    }

    submitPending.value = true;
    emit('submit', payload, isCustomer.value ? null : quote.value);
    submitUnlockTimer = setTimeout(() => {
        submitPending.value = false;
        submitUnlockTimer = null;
    }, 1000);
};
</script>

<template>
    <section
        class="overflow-hidden rounded-3xl border border-line bg-surface shadow-raised"
        aria-labelledby="new-order-title"
    >
        <div class="mobile-hero px-5 py-6 text-white">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-[0.18em] text-white/70 uppercase">
                        {{ role === 'customer' ? 'Laundry tanpa antre' : 'Kasir cabang' }}
                    </p>
                    <h2 id="new-order-title" class="mt-1 font-display text-2xl font-semibold">
                        {{ role === 'customer' ? 'Buat pesanan baru' : 'Catat laundry masuk' }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-white/75">
                        {{ branch ? `${branch.name} · ${branch.city.name}` : 'Pilih cabang terlebih dahulu' }}
                    </p>
                </div>
                <slot name="header-action">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-white/12">
                        <Sparkles class="size-5" aria-hidden="true" />
                    </span>
                </slot>
            </div>
        </div>

        <form class="space-y-5 p-5" @submit.prevent="submit">
            <div v-if="role === 'branch_admin'" class="block">
                <div class="flex items-center justify-between gap-3">
                    <label for="order-customer" class="text-sm font-semibold text-ink-950">
                        Pelanggan
                    </label>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center rounded-xl px-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-100 motion-safe:active:scale-[0.98]"
                        data-test="create-customer-from-order"
                        @click="emit('create-customer')"
                    >
                        + Pelanggan baru
                    </button>
                </div>
                <CustomerCombobox v-model="customerId" :customers="customers" />
            </div>

            <label class="block">
                <span class="text-sm font-semibold text-ink-950">Layanan laundry</span>
                <select
                    v-model="priceOptionId"
                    required
                    class="mt-2 min-h-11 w-full rounded-xl border border-line bg-surface px-3 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                >
                    <option :value="null">Pilih layanan</option>
                    <option v-for="entry in catalogOptions" :key="entry.option.id" :value="entry.option.id">
                        {{ entry.service.name }} · {{ entry.option.name }}
                    </option>
                </select>
            </label>

            <label v-if="role === 'branch_admin'" class="block">
                <span class="text-sm font-semibold text-ink-950">Berat laundry</span>
                <span class="relative mt-2 block">
                    <Scale
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-500"
                        aria-hidden="true"
                    />
                    <input
                        v-model.number="weightKilograms"
                        type="number"
                        min="0.1"
                        max="1000"
                        step="any"
                        inputmode="decimal"
                        required
                        class="min-h-11 w-full rounded-xl border border-line bg-surface pr-12 pl-10 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                    />
                    <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-ink-500">kg</span>
                </span>
            </label>

            <!-- Bagian Harga Atas & Harga Bawah (Khusus Admin & Paket Kiloan) -->
            <div
                v-if="role === 'branch_admin' && isPerKilogram"
                class="space-y-3 rounded-2xl border border-line bg-surface-blue/50 p-4"
                data-test="pricing-bounds-panel"
            >
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
                    <label for="order-composer-price-per-kg" class="text-xs font-semibold text-ink-950">
                        Harga per kg untuk Pesanan Ini (Rp)
                    </label>
                    <div class="mt-1.5 flex gap-2">
                        <input
                            id="order-composer-price-per-kg"
                            v-model="customPricePerKg"
                            type="number"
                            :min="hargaBawah"
                            step="100"
                            required
                            class="min-h-11 w-full rounded-xl border border-line bg-surface px-3 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                            :class="isPriceBelowFloor ? 'border-red-500 focus:ring-red-200' : ''"
                            data-test="order-composer-price-per-kg"
                        />
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex min-h-8 items-center rounded-lg border border-brand-600/30 bg-brand-50 px-2.5 text-xs font-semibold text-brand-700 transition hover:bg-brand-100"
                            data-test="composer-use-harga-atas"
                            @click="selectHargaAtas"
                        >
                            Gunakan Harga Atas ({{ formatRupiah(hargaAtas) }})
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-8 items-center rounded-lg border border-amber-600/30 bg-amber-50 px-2.5 text-xs font-semibold text-amber-800 transition hover:bg-amber-100"
                            data-test="composer-use-harga-bawah"
                            @click="selectHargaBawah"
                        >
                            Gunakan Harga Bawah ({{ formatRupiah(hargaBawah) }})
                        </button>
                    </div>
                </div>

                <p
                    v-if="isPriceBelowFloor"
                    class="flex items-center gap-1.5 text-xs font-medium text-red-600"
                    role="alert"
                >
                    <AlertCircle class="size-3.5 shrink-0" aria-hidden="true" />
                    Harga tidak boleh lebih rendah dari harga bawah ({{ formatRupiah(hargaBawah) }}/kg).
                </p>
            </div>

            <div
                v-if="role === 'customer'"
                class="rounded-2xl border border-line bg-surface-blue p-4"
                data-test="pickup-location-status"
            >
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
                        <MapPin class="size-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-ink-950">
                            {{ selectedAddress?.label ?? 'Alamat penjemputan belum dipilih' }}
                        </p>
                        <p v-if="selectedAddress" class="mt-1 text-sm leading-5 text-ink-700">
                            {{ selectedAddress.recipient_name }} · {{ selectedAddress.whatsapp_phone }}
                        </p>
                        <p class="mt-1 text-sm leading-5 text-ink-500">
                            {{
                                selectedAddress
                                    ? selectedAddress.address
                                    : 'Tambahkan alamat dan tentukan titik penjemputan di peta.'
                            }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="mt-3 min-h-11 w-full rounded-xl border border-brand-600 bg-surface px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-100"
                    data-test="manage-pickup-address"
                    @click="emit('manage-addresses')"
                >
                    {{ selectedAddress ? 'Ganti alamat / titik penjemputan' : 'Tambah alamat penjemputan' }}
                </button>
            </div>

            <div
                v-if="role === 'customer' && activePickupOrderNumber"
                class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm leading-5 text-ink-700"
                data-test="active-pickup-notice"
            >
                Penjemputan <strong>{{ activePickupOrderNumber }}</strong> masih menunggu diproses.
                Buat pesanan berikutnya setelah penjemputan ini dilanjutkan petugas.
            </div>

            <label
                v-if="role === 'branch_admin' && deliveryCoordinates"
                class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-line bg-surface-blue px-4 py-3"
            >
                <input v-model="includeDelivery" type="checkbox" class="size-4 accent-brand-700" />
                <MapPin class="size-5 shrink-0 text-brand-700" aria-hidden="true" />
                <span class="text-sm text-ink-700">Tambahkan antar-jemput untuk pesanan ini</span>
            </label>

            <div
                v-if="role === 'customer'"
                class="rounded-2xl bg-canvas p-4 text-sm leading-6 text-ink-700"
                data-test="pickup-pricing-notice"
            >
                Berat aktual dan total harga akan muncul setelah laundry dijemput dan ditimbang oleh petugas cabang.
            </div>

            <div v-else class="rounded-2xl bg-canvas p-4" aria-live="polite">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.12em] text-ink-500 uppercase">Rincian harga server</p>
                        <p class="mt-1 text-xs text-ink-500">Harga mengikuti tarif cabang terpilih.</p>
                    </div>
                    <p v-if="quoteLoading" class="text-sm font-semibold text-brand-700">
                        Menghitung…
                    </p>
                </div>

                <p v-if="quoteError" class="mt-4 text-sm leading-5 text-red-600" role="status">
                    {{ quoteError }}
                </p>

                <dl v-else-if="quote && hasCurrentQuote" class="mt-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-500">Berat diminta</dt>
                        <dd class="font-medium text-ink-950">{{ formatWeight(quote.requested_weight_grams) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-500">Berat tertagih</dt>
                        <dd class="font-medium text-ink-950">{{ formatWeight(quote.chargeable_weight_grams) }}</dd>
                    </div>
                    <div v-if="role === 'branch_admin' && isPerKilogram" class="flex items-center justify-between gap-4">
                        <dt class="text-ink-500">Tarif per kg</dt>
                        <dd class="font-medium text-ink-950">{{ formatRupiah(numericPricePerKg) }}/kg</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-500">{{ quote.price_option.type === 'fixed_package' ? 'Harga paket' : 'Harga dasar' }}</dt>
                        <dd class="font-medium text-ink-950">{{ formatRupiah(quote.base_price_rupiah) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-500">Kelebihan {{ formatWeight(quote.excess_weight_grams) }}</dt>
                        <dd class="font-medium text-ink-950">{{ formatRupiah(quote.excess_price_rupiah) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-500">Biaya antar-jemput</dt>
                        <dd class="font-medium text-ink-950">{{ formatRupiah(deliveryFee) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-t border-line pt-3">
                        <dt class="font-semibold text-ink-950">Total</dt>
                        <dd class="font-display text-xl font-semibold text-brand-700">{{ formatRupiah(finalTotal) }}</dd>
                    </div>
                </dl>
            </div>

            <p v-if="formError" role="status" aria-live="polite" class="text-sm text-red-600">
                {{ formError }}
            </p>

            <button
                type="submit"
                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-5 text-sm font-semibold text-white transition hover:bg-brand-600 motion-safe:active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="
                    processing ||
                    submitPending ||
                    !branch ||
                    catalogOptions.length === 0 ||
                    isPriceBelowFloor ||
                    (role === 'customer'
                        ? !selectedAddress || Boolean(activePickupOrderNumber)
                        : quoteLoading || !hasCurrentQuote)
                "
                data-test="create-order"
            >
                <PlusCircle class="size-5" aria-hidden="true" />
                {{
                    processing || submitPending
                        ? 'Menyimpan…'
                        : role === 'customer'
                          ? 'Minta penjemputan'
                          : 'Buat pesanan'
                }}
            </button>
        </form>
    </section>
</template>
