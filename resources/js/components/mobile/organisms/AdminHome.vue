<script setup lang="ts">
import {
    ChevronRight,
    ClipboardList,
    MessageCircle,
    PackagePlus,
    Printer,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import type { MobileBranch, MobileOrder } from '@/types/mobile';

const props = defineProps<{
    userName: string;
    branch: MobileBranch | null;
    orders: readonly MobileOrder[];
    customerCount: number;
    printerConfigured: boolean;
    whatsAppAvailable: boolean;
    statistics?: { active: number; unweighed: number; ready: number };
}>();

const emit = defineEmits<{
    'create-order': [];
    'view-queue': [];
    'view-customers': [];
    'view-whatsapp': [];
    'view-more': [];
    'view-summary': [];
}>();

const firstName = computed(() => props.userName.trim().split(/\s+/)[0] || props.userName);
const activeCount = computed(
    () =>
        props.statistics?.active ??
        props.orders.filter((order) => !['completed', 'cancelled'].includes(order.status)).length,
);
const pricingPendingCount = computed(
    () =>
        props.statistics?.unweighed ??
        props.orders.filter((order) => !order.pricing.confirmed && order.status !== 'cancelled')
            .length,
);
const readyCount = computed(
    () =>
        props.statistics?.ready ??
        props.orders.filter((order) => order.status === 'ready_for_pickup').length,
);
</script>

<template>
    <section class="space-y-5" aria-labelledby="admin-home-title">
        <div class="mobile-hero rounded-3xl p-5 text-white sm:p-7">
            <p class="text-xs font-semibold tracking-[0.16em] text-brand-100 uppercase">{{ branch?.name ?? 'Cabang aktif' }}</p>
            <h1 id="admin-home-title" class="mt-3 font-display text-2xl font-semibold tracking-tight sm:text-3xl">Selamat bekerja, {{ firstName }}.</h1>
            <p class="mt-2 max-w-sm text-sm leading-6 text-white/80">Semua kebutuhan cabang, satu tempat.</p>
            <button type="button" class="mt-6 inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-mint-accent px-5 text-sm font-semibold text-ink-950 transition hover:bg-mint-accent/90" data-test="open-order-composer-home" @click="emit('create-order')">
                <PackagePlus class="size-5" aria-hidden="true" />Catat pesanan baru
            </button>
        </div>
        <div class="mobile-bento" aria-label="Ringkasan cabang">
            <button type="button" class="mobile-card mobile-bento-feature flex flex-col items-start p-5 text-left transition hover:border-brand-600" @click="emit('view-queue')">
                <span class="grid size-11 place-items-center rounded-2xl bg-white text-brand-700"><ClipboardList class="size-5" aria-hidden="true" /></span>
                <span class="mt-6 font-display text-4xl font-semibold text-brand-700">{{ activeCount }}</span>
                <span class="mt-1 text-sm font-semibold text-ink-950">Pesanan aktif</span>
                <span class="mt-2 text-xs leading-5 text-ink-700">Pantau pengerjaan laundry.</span>
                <span class="mt-auto flex items-center gap-1 pt-5 text-sm font-semibold text-brand-700">Antrean<ChevronRight class="size-4" aria-hidden="true" /></span>
            </button>
            <article class="mobile-card p-4 sm:p-5">
                <p class="font-display text-3xl font-semibold text-brand-700">{{ pricingPendingCount }}</p>
                <p class="mt-1 text-xs leading-5 text-ink-700">Belum ditimbang</p>
            </article>
            <article class="mobile-card p-4 sm:p-5">
                <p class="font-display text-3xl font-semibold text-mint-500">{{ readyCount }}</p>
                <p class="mt-1 text-xs leading-5 text-ink-700">Siap diambil</p>
            </article>
        </div>
        <div>
            <button type="button" class="mobile-card mb-4 flex min-h-12 w-full items-center justify-between gap-3 p-4 text-left font-semibold text-brand-700" @click="emit('view-summary')">Ringkasan harian <ChevronRight class="size-4" aria-hidden="true" /></button>
            <h2 class="mb-3 font-display text-lg font-semibold text-ink-950">Menu cabang</h2>
            <div class="mobile-bento">
                <button type="button" class="mobile-card flex flex-col items-start gap-3 p-4 text-left transition hover:border-brand-600" @click="emit('view-customers')">
                    <span class="grid size-11 place-items-center rounded-2xl bg-surface-blue text-brand-700"><UsersRound class="size-5" aria-hidden="true" /></span>
                    <span><span class="block text-sm font-semibold text-ink-950">Pelanggan</span><span class="mt-1 block text-xs text-ink-500">{{ customerCount }} tersimpan</span></span>
                </button>
                <button type="button" class="mobile-card flex flex-col items-start gap-3 p-4 text-left transition hover:border-brand-600 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!whatsAppAvailable" @click="emit('view-whatsapp')">
                    <span class="grid size-11 place-items-center rounded-2xl bg-surface-blue text-brand-700"><MessageCircle class="size-5" aria-hidden="true" /></span>
                    <span><span class="block text-sm font-semibold text-ink-950">WhatsApp</span><span class="mt-1 block text-xs text-ink-500">Preview terakhir</span></span>
                </button>
            </div>
            <button type="button" class="mobile-card mt-3 flex min-h-20 w-full items-center gap-3 p-4 text-left transition hover:border-brand-600" @click="emit('view-more')">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-surface-blue text-brand-700"><Printer class="size-5" aria-hidden="true" /></span>
                <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-ink-950">Perangkat & akun</span><span class="mt-1 block text-xs text-ink-500">{{ printerConfigured ? 'Printer siap' : 'Atur printer' }}</span></span>
                <ChevronRight class="size-4 text-brand-700" aria-hidden="true" />
            </button>
        </div>
    </section>
</template>
