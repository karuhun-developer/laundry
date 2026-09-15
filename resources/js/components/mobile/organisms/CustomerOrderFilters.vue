<script setup lang="ts">
import { RefreshCw, Search, X } from '@lucide/vue';
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
    <section
        class="mb-5 rounded-2xl border border-line bg-surface p-3 shadow-low"
        aria-label="Pencarian pesanan"
    >
        <label class="relative block">
            <span class="sr-only">Cari pesanan pelanggan</span>
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-500" aria-hidden="true" />
            <input
                :value="modelValue.search"
                type="search"
                autocomplete="off"
                placeholder="Cari nomor pesanan, cabang, atau layanan"
                class="min-h-11 w-full rounded-xl border border-line bg-canvas pr-11 pl-10 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                data-test="customer-order-search"
                @input="update('search', $event)"
            />
            <button
                v-if="active"
                type="button"
                class="absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-lg text-ink-500 transition hover:bg-brand-100 hover:text-brand-700"
                aria-label="Bersihkan pencarian"
                data-test="clear-customer-order-filters"
                @click="emit('update:modelValue', emptyCustomerOrderFilters())"
            >
                <X class="size-4" aria-hidden="true" />
            </button>
        </label>
        <p v-if="loading" class="mt-2 flex items-center gap-2 text-xs text-ink-500" role="status">
            <RefreshCw class="size-3.5 animate-spin" aria-hidden="true" /> Memuat hasil pesanan…
        </p>
    </section>
</template>
