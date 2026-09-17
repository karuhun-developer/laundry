<script setup lang="ts">
import {
    ArrowUpRight,
    Building2,
    ChevronRight,
    ClipboardList,
    MapPin,
    PackagePlus,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import type { MobileBranch } from '@/types/mobile';

const props = defineProps<{
    userName: string;
    branch: MobileBranch | null;
    addressLabel?: string | null;
}>();

const emit = defineEmits<{
    'create-order': [];
    'view-orders': [];
    'view-branches': [];
    'manage-addresses': [];
    'view-account': [];
}>();

const firstName = computed(() => props.userName.trim().split(/\s+/)[0] || props.userName);
</script>

<template>
    <section class="space-y-6" aria-labelledby="customer-home-title">
        <header>
            <h1 id="customer-home-title" class="font-display text-2xl font-semibold tracking-tight text-ink-950">
                Halo, {{ firstName }}.
            </h1>
            <p class="mt-1 text-sm text-ink-500">Hari sibuk? Cucian biar kami yang urus.</p>
        </header>

        <div class="customer-welcome-card overflow-hidden rounded-3xl border border-brand-700 p-5 sm:p-7">
            <p class="inline-flex items-center gap-2 rounded-full border border-white/15 px-3 py-1.5 text-[11px] font-semibold tracking-wide text-white/85">
                <span class="size-1.5 rounded-full bg-mint-accent" aria-hidden="true" />
                Jemput · Cuci · Antar
            </p>
            <div class="grid grid-cols-[1.1fr_0.9fr] items-center gap-2 py-4 sm:gap-6">
                <div class="min-w-0">
                    <h2 class="font-display text-xl leading-tight font-semibold tracking-tight text-white min-[400px]:text-2xl sm:text-4xl">
                        Lebih bersih.<br />Lebih santai.
                    </h2>
                    <p class="mt-3 max-w-56 text-xs leading-5 text-white/70 sm:text-sm sm:leading-6">
                        Pesan dari rumah. Kami jemput dan antar kembali.
                    </p>
                </div>
                <svg class="w-full max-w-52 justify-self-center" viewBox="0 0 200 210" fill="none" aria-hidden="true">
                    <circle cx="105" cy="106" r="86" fill="var(--laundry-sky-400)" fill-opacity="0.08" />
                    <circle cx="105" cy="106" r="68" stroke="white" stroke-opacity="0.15" />
                    <ellipse cx="105" cy="191" rx="67" ry="9" fill="black" fill-opacity="0.2" />
                    <g transform="rotate(7 108 115)">
                        <rect x="54" y="41" width="112" height="140" rx="19" fill="var(--laundry-sky-400)" />
                        <rect x="43" y="37" width="112" height="140" rx="19" fill="white" />
                        <path d="M44 75h110" stroke="var(--laundry-line)" stroke-width="2" />
                        <rect x="57" y="50" width="35" height="11" rx="4" fill="var(--laundry-brand-100)" />
                        <circle cx="133" cy="56" r="7" fill="var(--laundry-brand-700)" />
                        <circle cx="111" cy="56" r="3" fill="var(--laundry-mint-500)" />
                        <circle cx="99" cy="120" r="37" fill="var(--laundry-brand-100)" stroke="var(--laundry-sky-400)" stroke-width="3" />
                        <circle cx="99" cy="120" r="28" fill="var(--laundry-brand-700)" />
                        <path d="M72 122c10-15 19 11 29 0s17-5 25 2a28 28 0 0 1-54-2Z" fill="var(--laundry-sky-400)" />
                        <path d="M78 137c10-9 19 4 37-2" stroke="var(--laundry-mint-accent)" stroke-width="4" stroke-linecap="round" />
                        <path d="M82 108a21 21 0 0 1 18-10" stroke="white" stroke-opacity="0.65" stroke-width="4" stroke-linecap="round" />
                    </g>
                    <rect x="18" y="151" width="58" height="17" rx="7" fill="var(--laundry-brand-600)" />
                    <rect x="13" y="168" width="65" height="18" rx="7" fill="var(--laundry-mint-accent)" />
                    <path d="M27 160h32M24 177h37" stroke="white" stroke-opacity="0.35" stroke-width="2" stroke-linecap="round" />
                    <path d="m162 20 3 9 9 3-9 3-3 9-3-9-9-3 9-3 3-9ZM30 86l2 6 6 2-6 2-2 6-2-6-6-2 6-2 2-6Z" fill="var(--laundry-mint-accent)" />
                    <circle cx="30" cy="44" r="6" fill="white" fill-opacity="0.8" />
                    <circle cx="177" cy="127" r="5" fill="white" fill-opacity="0.8" />
                </svg>
            </div>
            <button
                type="button"
                class="flex min-h-14 w-full items-center gap-3 rounded-full bg-mint-accent px-5 text-sm font-semibold text-ink-950 transition hover:bg-mint-accent/90 motion-safe:active:scale-[0.98]"
                data-test="open-customer-order-composer"
                @click="emit('create-order')"
            >
                <PackagePlus class="size-5 shrink-0" aria-hidden="true" />
                Buat pesanan
                <span class="ml-auto grid size-8 place-items-center rounded-full bg-ink-950/10">
                    <ArrowUpRight class="size-4" aria-hidden="true" />
                </span>
            </button>
        </div>

        <div aria-label="Menu cepat" class="grid grid-cols-4 gap-2">
            <button type="button" class="customer-home-shortcut" data-test="view-orders-shortcut" @click="emit('view-orders')">
                <span class="grid size-14 place-items-center rounded-[1.25rem] bg-brand-700 text-white shadow-low">
                    <ClipboardList class="size-6" aria-hidden="true" />
                </span>
                <span>Pesanan</span>
            </button>
            <button type="button" class="customer-home-shortcut" aria-label="Lihat daftar cabang" @click="emit('view-branches')">
                <span class="grid size-14 place-items-center rounded-[1.25rem] bg-sky-400 text-brand-700">
                    <Building2 class="size-6" aria-hidden="true" />
                </span>
                <span>Cabang</span>
            </button>
            <button type="button" class="customer-home-shortcut" data-test="manage-addresses-shortcut" @click="emit('manage-addresses')">
                <span class="grid size-14 place-items-center rounded-[1.25rem] bg-mint-accent/20 text-brand-700">
                    <MapPin class="size-6" aria-hidden="true" />
                </span>
                <span>Alamat</span>
            </button>
            <button type="button" class="customer-home-shortcut" data-test="view-account-shortcut" @click="emit('view-account')">
                <span class="grid size-14 place-items-center rounded-[1.25rem] border border-line bg-white text-brand-700">
                    <UserRound class="size-6" aria-hidden="true" />
                </span>
                <span>Akun</span>
            </button>
        </div>

        <article class="customer-location-card overflow-hidden rounded-3xl border border-line p-5">
            <div class="flex items-center gap-2 text-brand-600">
                <Building2 class="size-4" aria-hidden="true" />
                <p class="text-xs font-semibold">Cabang pilihanmu</p>
            </div>
            <h2 class="mt-3 font-display text-lg font-semibold text-ink-950">
                {{ branch?.name ?? 'Mau laundry di mana?' }}
            </h2>
            <p class="mt-1 text-sm leading-6 text-ink-700">
                {{ branch?.address ?? 'Temukan cabang dan layanan yang cocok untukmu.' }}
            </p>
            <button
                type="button"
                class="mt-2 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-brand-600 hover:text-brand-700"
                data-test="view-branches-shortcut"
                @click="emit('view-branches')"
            >
                Lihat daftar cabang
                <ArrowUpRight class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                class="mt-3 flex min-h-16 w-full items-center gap-3 border-t border-brand-600/10 pt-4 text-left"
                @click="emit('manage-addresses')"
            >
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-surface-blue text-brand-700">
                    <MapPin class="size-5" aria-hidden="true" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-xs text-ink-500">Jemput dari</span>
                    <span class="mt-0.5 block break-words text-sm font-semibold text-ink-950">
                        {{ addressLabel ?? 'Atur alamat penjemputan' }}
                    </span>
                </span>
                <ChevronRight class="size-4 shrink-0 text-brand-600" aria-hidden="true" />
            </button>
        </article>
    </section>
</template>

<style scoped>
.customer-welcome-card {
    background: var(--laundry-brand-700);
    box-shadow: var(--laundry-shadow-low);
}

.customer-home-shortcut {
    display: flex;
    min-height: 6rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    border-radius: 1rem;
    color: var(--laundry-ink-700);
    font-size: 0.75rem;
    font-weight: 600;
}

.customer-home-shortcut:hover {
    background: var(--laundry-brand-100);
}

.customer-location-card {
    background: var(--laundry-surface);
    box-shadow: var(--laundry-shadow-low);
}
</style>
