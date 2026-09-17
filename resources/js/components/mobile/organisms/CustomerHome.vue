<script setup lang="ts">
import { Building2, ChevronRight, ClipboardList, MapPin, PackagePlus } from '@lucide/vue';
import { computed } from 'vue';
import MobileStatusBadge from '@/components/mobile/atoms/MobileStatusBadge.vue';
import { formatRupiah } from '@/lib/mobileOrders';
import type { MobileBranch, MobileOrder } from '@/types/mobile';

const props = defineProps<{
    userName: string;
    branch: MobileBranch | null;
    activeOrder: MobileOrder | null;
    addressLabel?: string | null;
}>();

const emit = defineEmits<{
    'create-order': [];
    'view-orders': [];
    'view-branches': [];
    'manage-addresses': [];
}>();

const firstName = computed(() => props.userName.trim().split(/\s+/)[0] || props.userName);
</script>

<template>
    <section class="space-y-5" aria-labelledby="customer-home-title">
        <div class="mobile-hero rounded-3xl p-5 text-white sm:p-7">
            <p class="text-xs font-semibold tracking-[0.16em] text-brand-100 uppercase">Laundry, tanpa repot</p>
            <h1 id="customer-home-title" class="mt-3 font-display text-2xl font-semibold tracking-tight sm:text-3xl">Halo, {{ firstName }}.</h1>
            <p class="mt-2 max-w-sm text-sm leading-6 text-white/80">Biar kami urus cucianmu. Mulai dari jemput, sampai bersih kembali.</p>
            <button type="button" class="mt-6 inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-white px-5 text-sm font-semibold text-brand-700 transition hover:bg-brand-100" data-test="open-customer-order-composer" @click="emit('create-order')">
                <PackagePlus class="size-5" aria-hidden="true" />Buat pesanan
            </button>
        </div>
        <div class="mobile-bento" aria-label="Menu cepat">
            <button type="button" class="mobile-card mobile-bento-feature flex flex-col items-start p-5 text-left transition hover:border-brand-600" @click="emit('view-orders')">
                <span class="grid size-11 place-items-center rounded-2xl bg-white text-brand-700"><ClipboardList class="size-5" aria-hidden="true" /></span>
                <span class="mt-5 font-display text-lg font-semibold text-ink-950">Pesanan saya</span>
                <template v-if="activeOrder">
                    <span class="mt-2 max-w-full break-words text-xs text-ink-700">{{ activeOrder.order_number }}</span>
                    <span class="mt-3"><MobileStatusBadge :status="activeOrder.status" :pending="activeOrder.pending" /></span>
                    <span class="mt-3 text-sm font-semibold text-brand-700">{{ activeOrder.pricing.confirmed ? formatRupiah(activeOrder.pricing.total_rupiah) : 'Harga diproses' }}</span>
                </template>
                <span v-else class="mt-2 text-xs leading-5 text-ink-700">Belum ada laundry aktif. Pesananmu nanti muncul di sini.</span>
                <span class="mt-auto flex items-center gap-1 pt-5 text-sm font-semibold text-brand-700">Lihat pesanan<ChevronRight class="size-4" aria-hidden="true" /></span>
            </button>
            <button type="button" class="mobile-card flex flex-col items-start p-4 text-left transition hover:border-brand-600" @click="emit('view-branches')">
                <span class="grid size-10 place-items-center rounded-xl bg-surface-blue text-brand-700"><Building2 class="size-5" aria-hidden="true" /></span>
                <span class="mt-3 text-sm font-semibold text-ink-950" data-test="view-branches-shortcut">Lihat daftar cabang</span>
                <span class="mt-1 text-xs leading-5 text-ink-500">Cari outlet terdekat.</span>
            </button>
            <button type="button" class="mobile-card flex flex-col items-start p-4 text-left transition hover:border-brand-600" @click="emit('manage-addresses')">
                <span class="grid size-10 place-items-center rounded-xl bg-surface-blue text-brand-700"><MapPin class="size-5" aria-hidden="true" /></span>
                <span class="mt-3 text-sm font-semibold text-ink-950">Alamat jemput</span>
                <span class="mt-1 break-words text-xs leading-5 text-ink-500">{{ addressLabel ?? 'Atur alamat utama.' }}</span>
            </button>
        </div>
        <article class="mobile-card p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-[0.12em] text-brand-600 uppercase">Cabang pilihan</p>
                    <h2 class="mt-2 font-display text-lg font-semibold text-ink-950">{{ branch?.name ?? 'Pilih cabang laundry' }}</h2>
                </div>
                <button type="button" class="grid size-11 shrink-0 place-items-center rounded-2xl bg-surface-blue text-brand-700" aria-label="Lihat daftar cabang" @click="emit('view-branches')"><ChevronRight class="size-5" aria-hidden="true" /></button>
            </div>
            <p class="mt-2 text-sm leading-6 text-ink-700">{{ branch?.address ?? 'Pilih cabang untuk melihat alamat dan layanan.' }}</p>
        </article>
    </section>
</template>
