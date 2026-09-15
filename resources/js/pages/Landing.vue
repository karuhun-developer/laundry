<script setup lang="ts">
import { Capacitor } from '@capacitor/core';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    Building2,
    CheckCircle2,
    MapPin,
    MessageCircle,
    Printer,
    QrCode,
    Shirt,
    Smartphone,
    Sparkles,
    Truck,
} from '@lucide/vue';
import { onMounted } from 'vue';
import { login, register } from '@/routes';
import { app as mobileApp } from '@/routes/mobile';

onMounted(() => {
    if (Capacitor.isNativePlatform()) {
        router.visit(mobileApp());
    }
});

const features = [
    {
        icon: Truck,
        title: 'Antar-jemput terukur',
        description:
            'Pelanggan menyimpan alamat, memilih titik penjemputan, dan otomatis diarahkan ke cabang terdekat.',
    },
    {
        icon: Building2,
        title: 'Operasional multi-cabang',
        description:
            'Antrean, pelanggan, layanan, dan tarif setiap cabang tetap rapi dalam satu sistem.',
    },
    {
        icon: MessageCircle,
        title: 'Update via WhatsApp',
        description:
            'Petugas mendapat template pesan sesuai status untuk memberi kabar tanpa mengetik ulang.',
    },
    {
        icon: QrCode,
        title: 'QRIS dinamis',
        description:
            'Nominal pembayaran mengikuti tagihan pesanan sehingga transaksi lebih cepat dan akurat.',
    },
    {
        icon: Printer,
        title: 'Printer thermal',
        description:
            'Cetak struk melalui Bluetooth atau USB langsung dari aplikasi Android petugas.',
    },
    {
        icon: BarChart3,
        title: 'Laporan siap pakai',
        description:
            'Owner memantau kinerja laundry dan mengekspor laporan operasional dari dashboard.',
    },
];
</script>

<template>
    <div class="min-h-screen overflow-hidden bg-[#f4f8ff] text-ink-950">
        <header class="relative z-20 border-b border-white/70 bg-white/85 backdrop-blur">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8 lg:px-10" aria-label="Navigasi utama">
                <a href="#beranda" class="flex items-center gap-3" aria-label="LaundryHub beranda">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-brand-700 text-white shadow-low">
                        <Shirt class="size-5" aria-hidden="true" />
                    </span>
                    <span>
                        <span class="block font-display text-lg font-semibold">LaundryHub</span>
                        <span class="block text-[11px] font-semibold tracking-[0.16em] text-brand-600 uppercase">Laundry operating system</span>
                    </span>
                </a>
                <div class="flex items-center gap-2">
                    <Link :href="login()" class="inline-flex min-h-11 items-center rounded-xl px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-100">
                        Masuk
                    </Link>
                    <Link :href="register()" class="hidden min-h-11 items-center gap-2 rounded-xl bg-brand-700 px-5 text-sm font-semibold text-white shadow-low transition hover:bg-brand-600 sm:inline-flex">
                        Mulai sekarang
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </nav>
        </header>

        <main id="beranda">
            <section class="relative isolate">
                <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_15%_20%,rgba(105,195,246,0.3),transparent_34%),radial-gradient(circle_at_88%_12%,rgba(21,89,199,0.18),transparent_30%)]" />
                <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-20 sm:px-8 sm:py-24 lg:grid-cols-[1.05fr_0.95fr] lg:px-10 lg:py-28">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-brand-600/15 bg-white px-3 py-2 text-xs font-semibold text-brand-700 shadow-low">
                            <Sparkles class="size-4" aria-hidden="true" />
                            Dari penjemputan sampai cucian selesai
                        </div>
                        <h1 class="mt-6 max-w-3xl font-display text-4xl leading-tight font-semibold tracking-tight sm:text-5xl lg:text-6xl">
                            Kelola laundry lebih cepat. Pelanggan selalu tahu progresnya.
                        </h1>
                        <p class="mt-6 max-w-2xl text-base leading-8 text-ink-500 sm:text-lg">
                            LaundryHub menyatukan antrean cabang, antar-jemput, pelanggan, pembayaran QRIS, WhatsApp, printer thermal, dan laporan dalam satu aplikasi.
                        </p>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link :href="register()" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-brand-700 px-6 text-sm font-semibold text-white shadow-raised transition hover:bg-brand-600">
                                Daftar sebagai pelanggan
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Link>
                            <Link :href="login()" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-brand-600/25 bg-white px-6 text-sm font-semibold text-brand-700 shadow-low transition hover:bg-brand-100">
                                <Smartphone class="size-4" aria-hidden="true" />
                                Buka aplikasi
                            </Link>
                        </div>
                        <ul class="mt-8 grid gap-3 text-sm text-ink-700 sm:grid-cols-2">
                            <li class="flex items-center gap-2"><CheckCircle2 class="size-4 text-mint-500" aria-hidden="true" /> Status cucian real-time</li>
                            <li class="flex items-center gap-2"><CheckCircle2 class="size-4 text-mint-500" aria-hidden="true" /> Cabang terdekat otomatis</li>
                            <li class="flex items-center gap-2"><CheckCircle2 class="size-4 text-mint-500" aria-hidden="true" /> Harga terhitung di server</li>
                            <li class="flex items-center gap-2"><CheckCircle2 class="size-4 text-mint-500" aria-hidden="true" /> Pembayaran sesuai nominal</li>
                        </ul>
                    </div>

                    <div class="relative mx-auto w-full max-w-xl">
                        <div class="absolute -inset-8 -z-10 rounded-[3rem] bg-brand-100/70 blur-2xl" />
                        <div class="rounded-[2rem] border border-white/80 bg-white p-4 shadow-[0_28px_80px_rgba(9,67,169,0.18)] sm:p-6">
                            <div class="rounded-3xl bg-brand-700 p-6 text-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs font-semibold tracking-[0.16em] text-white/65 uppercase">Penjemputan aktif</p>
                                        <p class="mt-1 font-display text-xl font-semibold">Laundry dijemput hari ini</p>
                                    </div>
                                    <span class="flex size-11 items-center justify-center rounded-2xl bg-white/12"><Truck class="size-5" aria-hidden="true" /></span>
                                </div>
                                <div class="mt-6 rounded-2xl bg-white/10 p-4">
                                    <div class="flex items-start gap-3">
                                        <MapPin class="mt-0.5 size-5 shrink-0 text-sky-400" aria-hidden="true" />
                                        <div>
                                            <p class="font-semibold">Rumah · Budi Santoso</p>
                                            <p class="mt-1 text-sm leading-6 text-white/70">Cabang terdekat sudah dipilih dan biaya jarak dihitung otomatis.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <div class="rounded-2xl bg-surface-blue p-4">
                                    <QrCode class="size-5 text-brand-700" aria-hidden="true" />
                                    <p class="mt-4 text-xs text-ink-500">Pembayaran</p>
                                    <p class="mt-1 font-semibold">QRIS dinamis</p>
                                </div>
                                <div class="rounded-2xl bg-[#e9f8f2] p-4">
                                    <MessageCircle class="size-5 text-mint-500" aria-hidden="true" />
                                    <p class="mt-4 text-xs text-ink-500">Pembaruan</p>
                                    <p class="mt-1 font-semibold">Siap via WhatsApp</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="fitur" class="border-y border-line bg-white">
                <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold tracking-[0.18em] text-brand-600 uppercase">Satu alur kerja</p>
                        <h2 class="mt-3 font-display text-3xl font-semibold sm:text-4xl">Semua yang dibutuhkan laundry modern</h2>
                        <p class="mt-4 leading-7 text-ink-500">Petugas fokus menyelesaikan cucian, pelanggan mendapat pengalaman yang jelas, dan owner tetap memegang kendali.</p>
                    </div>
                    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <article v-for="feature in features" :key="feature.title" class="rounded-2xl border border-line bg-[#fbfdff] p-5 shadow-low">
                            <span class="flex size-11 items-center justify-center rounded-xl bg-brand-100 text-brand-700"><component :is="feature.icon" class="size-5" aria-hidden="true" /></span>
                            <h3 class="mt-5 font-display text-lg font-semibold">{{ feature.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-ink-500">{{ feature.description }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
                <div class="rounded-[2rem] bg-ink-950 px-6 py-12 text-center text-white shadow-raised sm:px-12">
                    <p class="text-xs font-semibold tracking-[0.18em] text-sky-400 uppercase">Mulai tanpa antre</p>
                    <h2 class="mx-auto mt-3 max-w-2xl font-display text-3xl font-semibold sm:text-4xl">Laundry lebih sederhana untuk pelanggan dan tim cabang.</h2>
                    <p class="mx-auto mt-4 max-w-2xl leading-7 text-white/65">Daftar dengan nomor WhatsApp dan PIN enam digit, lalu pilih layanan dari cabang terdekat.</p>
                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <Link :href="register()" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-white px-6 text-sm font-semibold text-brand-700">Daftar pelanggan <ArrowRight class="size-4" aria-hidden="true" /></Link>
                        <Link :href="login()" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-white/20 px-6 text-sm font-semibold text-white hover:bg-white/10">Masuk ke LaundryHub</Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-line bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-8 text-sm text-ink-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
                <p class="font-semibold text-ink-700">LaundryHub</p>
                <p>Operasional laundry, penjemputan, dan pembayaran dalam satu aplikasi.</p>
            </div>
        </footer>
    </div>
</template>
