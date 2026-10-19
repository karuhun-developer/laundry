<script setup lang="ts">
import { KeyRound, Mail, Pencil, Phone, Search, UserPlus, UserRound, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import type { MobileCustomer } from '@/types/mobile';

const props = defineProps<{ customers: readonly MobileCustomer[] }>();

const emit = defineEmits<{
    create: [];
    edit: [customer: MobileCustomer];
    'reset-pin': [customer: MobileCustomer];
}>();

const pageSize = 10;
const searchQuery = ref('');
const visibleCount = ref(pageSize);
const filteredCustomers = computed(() => {
    const search = searchQuery.value.trim().toLocaleLowerCase('id-ID');

    if (!search) {
        return props.customers;
    }

    return props.customers.filter((customer) =>
        [customer.name, customer.email, customer.whatsapp_phone].some((value) =>
            value?.toLocaleLowerCase('id-ID').includes(search),
        ),
    );
});
const visibleCustomers = computed(() => filteredCustomers.value.slice(0, visibleCount.value));
const hasMoreCustomers = computed(() => visibleCount.value < filteredCustomers.value.length);

watch([searchQuery, () => props.customers], () => {
    visibleCount.value = pageSize;
});

const loadMore = (): void => {
    visibleCount.value += pageSize;
};

const formatExpiry = (expiresAt: string): string =>
    new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(expiresAt));
</script>

<template>
    <section aria-labelledby="customer-directory-title">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-brand-600 uppercase">Pelanggan</p>
                <h2
                    id="customer-directory-title"
                    class="mt-1 font-display text-2xl font-semibold text-ink-950"
                    tabindex="-1"
                >
                    Direktori pelanggan
                </h2>
            </div>
            <Button
                type="button"
                class="shrink-0 rounded-xl"
                data-test="create-customer-from-directory"
                @click="emit('create')"
            >
                <UserPlus class="size-4" aria-hidden="true" />
                <span class="hidden sm:inline">Pelanggan baru</span>
                <span class="sm:hidden">Tambah</span>
            </Button>
        </div>
        <p class="mt-2 text-sm leading-6 text-ink-500">
            Kelola pelanggan organisasi dan pelanggan umum yang sudah dihubungkan ke organisasi ini.
        </p>

        <div class="mt-5 space-y-2">
            <label for="customer-directory-search" class="text-sm font-medium text-ink-700">
                Cari pelanggan
            </label>
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-500"
                    aria-hidden="true"
                />
                <input
                    id="customer-directory-search"
                    v-model="searchQuery"
                    type="search"
                    class="min-h-11 w-full rounded-xl border border-line bg-surface pr-11 pl-10 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20"
                    placeholder="Nama, email, atau WhatsApp"
                    data-test="customer-directory-search"
                />
                <button
                    v-if="searchQuery"
                    type="button"
                    class="absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-lg text-ink-500 hover:bg-canvas hover:text-ink-950"
                    aria-label="Hapus pencarian pelanggan"
                    data-test="clear-customer-directory-search"
                    @click="searchQuery = ''"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>
            <p v-if="customers.length > 0" class="text-xs text-ink-500" aria-live="polite">
                Menampilkan {{ visibleCustomers.length }} dari {{ filteredCustomers.length }} pelanggan
            </p>
        </div>

        <div class="mt-4 space-y-3">
            <article
                v-for="customer in visibleCustomers"
                :key="customer.id"
                class="rounded-2xl border border-line bg-surface p-4 shadow-low"
                :class="customer.pending ? 'border-brand-600/30' : ''"
                :aria-busy="customer.pending"
            >
                <div class="flex items-start gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-100 text-brand-700">
                        <UserRound class="size-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate font-semibold text-ink-950">{{ customer.name }}</h3>
                            <span
                                v-if="customer.pending"
                                class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-semibold text-brand-700"
                            >
                                Menyimpan…
                            </span>
                            <span
                                v-else-if="customer.must_change_pin"
                                class="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-700"
                            >
                                PIN sementara
                            </span>
                            <span
                                v-else
                                class="rounded-full bg-mint-500/10 px-2 py-0.5 text-xs font-semibold text-mint-500"
                            >
                                PIN aktif
                            </span>
                        </div>
                        <p class="mt-1 flex items-center gap-1.5 truncate text-sm text-ink-500">
                            <Mail class="size-3.5 shrink-0" aria-hidden="true" />
                            {{ customer.email ?? 'Tanpa email' }}
                        </p>
                        <p class="mt-1 flex items-center gap-1.5 truncate text-sm text-ink-500">
                            <Phone class="size-3.5 shrink-0" aria-hidden="true" />
                            {{ customer.whatsapp_phone ?? 'Tanpa nomor WhatsApp' }}
                        </p>
                        <p
                            v-if="!customer.pending && customer.must_change_pin && customer.pin_expires_at"
                            class="mt-2 text-xs font-medium text-amber-700"
                        >
                            PIN sementara kedaluwarsa {{ formatExpiry(customer.pin_expires_at) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="!customer.pending"
                    class="mt-4 grid grid-cols-2 gap-2 border-t border-line pt-3"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        class="min-h-11 rounded-xl px-2 text-ink-700"
                        :data-test="`edit-customer-${customer.id}`"
                        :aria-label="`Edit ${customer.name}`"
                        @click="emit('edit', customer)"
                    >
                        <Pencil class="size-4" aria-hidden="true" />
                        Edit
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        class="min-h-11 rounded-xl px-2 text-ink-700"
                        :data-test="`reset-customer-pin-${customer.id}`"
                        :aria-label="`Reset PIN ${customer.name}`"
                        @click="emit('reset-pin', customer)"
                    >
                        <KeyRound class="size-4" aria-hidden="true" />
                        Reset PIN
                    </Button>
                </div>
            </article>
            <p
                v-if="customers.length === 0"
                class="rounded-2xl border border-dashed border-line bg-surface p-6 text-center text-sm text-ink-500"
            >
                Belum ada pelanggan yang tersedia untuk organisasi ini.
            </p>
            <p
                v-else-if="filteredCustomers.length === 0"
                class="rounded-2xl border border-dashed border-line bg-surface p-6 text-center text-sm text-ink-500"
                data-test="customer-directory-no-results"
            >
                Tidak ada pelanggan yang cocok dengan pencarian.
            </p>
            <Button
                v-if="hasMoreCustomers"
                type="button"
                variant="outline"
                class="min-h-11 w-full rounded-xl"
                data-test="load-more-customers"
                @click="loadMore"
            >
                Muat lainnya
            </Button>
        </div>
    </section>
</template>
