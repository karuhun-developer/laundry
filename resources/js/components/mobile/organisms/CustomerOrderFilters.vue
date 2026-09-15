<script setup lang="ts">
import { CalendarDays, RefreshCw, Search, X } from '@lucide/vue';
import { computed } from 'vue';
import type { CustomerOrderFilters } from '@/lib/mobileOrderFilters';
import { emptyCustomerOrderFilters, hasCustomerOrderFilters } from '@/lib/mobileOrderFilters';

const props = withDefaults(
    defineProps<{
        modelValue: CustomerOrderFilters;
        loading?: boolean;
    }>(),
    { loading: false },
);

const emit = defineEmits<{
    'update:modelValue': [filters: CustomerOrderFilters];
}>();

const active = computed(() => hasCustomerOrderFilters(props.modelValue));

const update = (field: keyof CustomerOrderFilters, event: Event): void => {
    if (event.target instanceof HTMLInputElement) {
        emit('update:modelValue', { ...props.modelValue, [field]: event.target.value });
    }
};
</script>

<template>
    <section class="mb-5 space-y-3 rounded-2xl border border-line bg-surface p-4 shadow-low" aria-label="Filter pesanan">
        <label class="relative block">
            <span class="sr-only">Cari pesanan pelanggan</span>
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-500" aria-hidden="true" />
            <input
                :value="modelValue.search"
                type="search"
                autocomplete="off"
                placeholder="Cari nomor pesanan, cabang, atau layanan"
                class="min-h-11 w-full rounded-xl border border-line bg-canvas px-10 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                data-test="customer-order-search"
                @input="update('search', $event)"
            />
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 flex items-center gap-2 text-xs font-semibold text-ink-700"><CalendarDays class="size-4" aria-hidden="true" />Dari tanggal</span>
                <input :value="modelValue.date_from" type="date" class="min-h-11 w-full rounded-xl border border-line bg-canvas px-3 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100" data-test="customer-order-date-from" @input="update('date_from', $event)" />
            </label>
            <label class="block">
                <span class="mb-1 flex items-center gap-2 text-xs font-semibold text-ink-700"><CalendarDays class="size-4" aria-hidden="true" />Sampai tanggal (inklusif)</span>
                <input :value="modelValue.date_to" type="date" class="min-h-11 w-full rounded-xl border border-line bg-canvas px-3 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100" data-test="customer-order-date-to" @input="update('date_to', $event)" />
            </label>
        </div>
        <div class="flex min-h-11 items-center justify-between gap-3">
            <p class="text-xs text-ink-500">Tanggal awal dan akhir sama-sama termasuk hasil.</p>
            <button v-if="active" type="button" class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-brand-700 hover:bg-brand-100" data-test="clear-customer-order-filters" @click="emit('update:modelValue', emptyCustomerOrderFilters())">
                <X class="size-4" aria-hidden="true" /> Bersihkan
            </button>
        </div>
        <p v-if="loading" class="flex items-center gap-2 text-xs text-ink-500" role="status">
            <RefreshCw class="size-3.5 animate-spin" aria-hidden="true" /> Memuat hasil pesanan…
        </p>
    </section>
</template>
