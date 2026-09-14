<script setup lang="ts">
import { KeyRound, Mail, Pencil, Phone, UserMinus, UserPlus, UserRound } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { MobileCustomer } from '@/types/mobile';

defineProps<{ customers: readonly MobileCustomer[] }>();

const emit = defineEmits<{
    create: [];
    edit: [customer: MobileCustomer];
    'reset-pin': [customer: MobileCustomer];
    detach: [customer: MobileCustomer];
}>();

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

        <div class="mt-5 space-y-3">
            <article
                v-for="customer in customers"
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
                    class="mt-4 grid grid-cols-2 gap-2 border-t border-line pt-3 sm:grid-cols-3"
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
                    <Button
                        type="button"
                        variant="ghost"
                        class="col-span-2 min-h-11 rounded-xl px-2 text-red-600 hover:bg-red-600/5 hover:text-red-600 sm:col-span-1"
                        :data-test="`detach-customer-${customer.id}`"
                        :aria-label="`Lepas ${customer.name}`"
                        @click="emit('detach', customer)"
                    >
                        <UserMinus class="size-4" aria-hidden="true" />
                        Lepas
                    </Button>
                </div>
            </article>
            <p
                v-if="customers.length === 0"
                class="rounded-2xl border border-dashed border-line bg-surface p-6 text-center text-sm text-ink-500"
            >
                Belum ada pelanggan yang tersedia untuk organisasi ini.
            </p>
        </div>
    </section>
</template>
