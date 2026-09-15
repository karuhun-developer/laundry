<script setup lang="ts">
import { Check, ChevronDown, Search } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import type { MobileCustomer } from '@/types/mobile';

let nextComboboxId = 0;

const props = withDefaults(
    defineProps<{
        customers: readonly MobileCustomer[];
        inputId?: string;
        modelValue: number | null;
    }>(),
    { inputId: 'order-customer' },
);

const emit = defineEmits<{
    'update:modelValue': [customerId: number | null];
}>();

const root = ref<HTMLElement | null>(null);
const open = ref(false);
const search = ref('');
const activeIndex = ref(-1);
const listboxId = `customer-listbox-${++nextComboboxId}`;
const selectedCustomer = computed(() =>
    props.customers.find((customer) => customer.id === props.modelValue),
);
const filteredCustomers = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('id-ID');

    return props.customers
        .filter((customer) =>
            [customer.name, customer.email, customer.whatsapp_phone]
                .filter(Boolean)
                .join(' ')
                .toLocaleLowerCase('id-ID')
                .includes(query),
        )
        .slice(0, 50);
});
const inputValue = computed(() =>
    open.value ? search.value : (selectedCustomer.value?.name ?? ''),
);
const activeOptionId = computed(() => {
    const customer = filteredCustomers.value[activeIndex.value];

    return customer ? `customer-option-${customer.id}` : undefined;
});

const openList = (): void => {
    open.value = true;
    search.value = '';
    activeIndex.value = -1;
};

const closeList = (): void => {
    open.value = false;
    search.value = '';
    activeIndex.value = -1;
};

const onInput = (event: Event): void => {
    if (!(event.target instanceof HTMLInputElement)) {
        return;
    }

    open.value = true;
    search.value = event.target.value;
    activeIndex.value = filteredCustomers.value.length ? 0 : -1;
};

const selectCustomer = (customer: MobileCustomer): void => {
    if (customer.pending) {
        return;
    }

    emit('update:modelValue', customer.id);
    closeList();
};

const onKeydown = async (event: KeyboardEvent): Promise<void> => {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeList();
        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();

        if (!open.value) {
            openList();
            await nextTick();
        }

        const direction = event.key === 'ArrowDown' ? 1 : -1;
        const length = filteredCustomers.value.length;

        if (length) {
            activeIndex.value = (activeIndex.value + direction + length) % length;
        }

        return;
    }

    if (event.key === 'Enter' && open.value && activeIndex.value >= 0) {
        const customer = filteredCustomers.value[activeIndex.value];

        if (customer) {
            event.preventDefault();
            selectCustomer(customer);
        }
    }
};

const onDocumentPointerDown = (event: PointerEvent): void => {
    if (root.value && event.target instanceof Node && !root.value.contains(event.target)) {
        closeList();
    }
};

onMounted(() => document.addEventListener('pointerdown', onDocumentPointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onDocumentPointerDown));
</script>

<template>
    <div ref="root" class="relative mt-2" data-test="customer-select">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-ink-500"
            aria-hidden="true"
        />
        <input
            :id="inputId"
            :value="inputValue"
            type="search"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="open"
            :aria-controls="listboxId"
            :aria-activedescendant="activeOptionId"
            placeholder="Cari nama, email, atau WhatsApp"
            class="min-h-11 w-full rounded-xl border border-line bg-surface pr-10 pl-10 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
            data-test="customer-search"
            @focus="openList"
            @input="onInput"
            @keydown="onKeydown"
        />
        <ChevronDown
            class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-ink-500"
            aria-hidden="true"
        />

        <div
            v-if="open"
            :id="listboxId"
            role="listbox"
            class="absolute inset-x-0 top-full z-30 mt-2 max-h-64 overflow-y-auto rounded-xl border border-line bg-surface p-1 shadow-raised"
        >
            <button
                v-for="(customer, index) in filteredCustomers"
                :id="`customer-option-${customer.id}`"
                :key="customer.id"
                type="button"
                role="option"
                :aria-selected="customer.id === modelValue"
                :disabled="customer.pending"
                class="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm transition hover:bg-brand-100 disabled:cursor-not-allowed disabled:opacity-60"
                :class="index === activeIndex ? 'bg-brand-100' : ''"
                :data-test="`customer-option-${customer.id}`"
                @mouseenter="activeIndex = index"
                @mousedown.prevent
                @click="selectCustomer(customer)"
            >
                <span class="min-w-0">
                    <span class="block truncate font-semibold text-ink-950">{{ customer.name }}</span>
                    <span class="block truncate text-xs text-ink-500">
                        {{ customer.pending ? 'Menyimpan…' : (customer.email ?? customer.whatsapp_phone ?? 'Tanpa kontak') }}
                    </span>
                </span>
                <Check
                    v-if="customer.id === modelValue"
                    class="size-4 shrink-0 text-brand-700"
                    aria-hidden="true"
                />
            </button>
            <p v-if="filteredCustomers.length === 0" class="px-3 py-4 text-center text-sm text-ink-500">
                Pelanggan tidak ditemukan.
            </p>
        </div>
    </div>
</template>
