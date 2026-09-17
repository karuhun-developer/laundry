<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { login } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="auth-surface relative grid min-h-dvh bg-canvas text-ink-950 lg:grid-cols-2 lg:gap-6 lg:p-6"
    >
        <div
            class="relative hidden min-h-[calc(100dvh-3rem)] flex-col overflow-hidden rounded-3xl border border-brand-700 bg-brand-700 p-10 text-white lg:flex xl:p-12"
        >
            <Link
                :href="login()"
                class="flex min-h-11 items-center gap-3 font-display text-lg font-semibold"
            >
                <span class="flex size-11 items-center justify-center rounded-xl bg-mint-accent text-brand-700">
                    <AppLogoIcon class="size-8" />
                </span>
                {{ name }}
            </Link>

            <div class="my-auto max-w-lg py-12">
                <p
                    class="mb-5 inline-flex rounded-full border border-white/15 px-4 py-2 text-xs font-semibold tracking-wider text-mint-accent uppercase"
                >
                    Laundry multi-cabang, satu kendali
                </p>
                <h2
                    class="font-display max-w-md text-4xl leading-tight font-semibold tracking-tight text-balance xl:text-5xl"
                >
                    Operasional laundry yang terasa ringan.
                </h2>
                <p class="mt-5 max-w-md text-base leading-7 text-white/75">
                    Kelola pesanan, harga kiloan, antar jemput, dan progres
                    tiap cabang dari satu sistem yang selalu terhubung.
                </p>

                <div class="mt-10 grid max-w-md gap-3 sm:grid-cols-2">
                    <div
                        class="rounded-3xl border border-sky-400 bg-sky-400 p-5 text-ink-950"
                    >
                        <p class="font-display text-2xl font-semibold">Real-time</p>
                        <p class="mt-2 text-sm leading-5 text-ink-700">
                            Status pesanan terpantau
                        </p>
                    </div>
                    <div
                        class="rounded-3xl border border-white/15 bg-white/5 p-5"
                    >
                        <p class="font-display text-2xl font-semibold text-mint-accent">Multi-role</p>
                        <p class="mt-2 text-sm leading-5 text-white/75">
                            Customer sampai owner
                        </p>
                    </div>
                </div>
            </div>

            <p class="text-sm text-white/60">
                Dibuat untuk bisnis laundry Indonesia.
            </p>
        </div>
        <div class="flex items-center px-4 py-6 sm:px-8 lg:px-6 lg:py-8">
            <div
                class="mx-auto flex w-full max-w-md flex-col justify-center space-y-6 rounded-3xl border border-line bg-surface px-5 py-6 shadow-low sm:px-8 sm:py-8"
            >
                <Link
                    :href="login()"
                    class="mx-auto flex min-h-11 items-center gap-3 font-display text-base font-semibold text-ink-950 lg:hidden"
                >
                    <span class="flex size-11 items-center justify-center rounded-xl bg-mint-accent text-brand-700">
                        <AppLogoIcon class="size-8" />
                    </span>
                    {{ name }}
                </Link>
                <div class="flex flex-col space-y-2 text-center">
                    <h1
                        class="font-display text-2xl leading-tight font-semibold tracking-tight"
                        v-if="title"
                    >
                        {{ title }}
                    </h1>
                    <p class="text-sm leading-6 text-ink-500" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
