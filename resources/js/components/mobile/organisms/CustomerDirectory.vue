<script setup lang="ts">
import { Mail, UserRound } from '@lucide/vue';
import type { MobileCustomer } from '@/types/mobile';

defineProps<{ customers: readonly MobileCustomer[] }>();
</script>

<template>
    <section aria-labelledby="customer-directory-title">
        <p class="text-xs font-semibold tracking-[0.18em] text-brand-600 uppercase">Pelanggan</p>
        <h2 id="customer-directory-title" class="mt-1 font-display text-2xl font-semibold text-ink-950">
            Direktori pelanggan
        </h2>
        <p class="mt-1 text-sm leading-6 text-ink-500">Pelanggan organisasi dan pelanggan umum yang sudah bertransaksi di organisasi ini.</p>

        <div class="mt-5 space-y-3">
            <article
                v-for="customer in customers"
                :key="customer.id"
                class="flex items-center gap-3 rounded-2xl border border-line bg-surface p-4 shadow-low"
            >
                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-100 text-brand-700">
                    <UserRound class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h3 class="truncate font-semibold text-ink-950">{{ customer.name }}</h3>
                    <p class="mt-1 flex items-center gap-1.5 truncate text-sm text-ink-500">
                        <Mail class="size-3.5 shrink-0" aria-hidden="true" />
                        {{ customer.email }}
                    </p>
                </div>
            </article>
            <p v-if="customers.length === 0" class="rounded-2xl border border-dashed border-line bg-surface p-6 text-center text-sm text-ink-500">
                Belum ada pelanggan yang tersedia untuk organisasi ini.
            </p>
        </div>
    </section>
</template>
