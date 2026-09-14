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
        class="auth-surface bg-surface text-ink-950 relative grid min-h-dvh lg:grid-cols-2"
    >
        <div
            class="bg-brand-700 text-surface relative hidden min-h-dvh overflow-hidden p-12 lg:flex lg:flex-col"
        >
            <div
                class="bg-sky-400/25 absolute -top-28 -right-24 size-96 rounded-full blur-2xl"
            />
            <div
                class="bg-ink-950/35 absolute -bottom-32 -left-24 size-[28rem] rounded-full blur-3xl"
            />
            <Link
                :href="login()"
                class="relative z-20 flex min-h-11 items-center text-lg font-medium"
            >
                <AppLogoIcon class="text-surface mr-2 size-8 fill-current" />
                {{ name }}
            </Link>

            <div class="relative z-10 my-auto max-w-lg">
                <p
                    class="text-surface bg-surface/10 border-surface/20 mb-5 inline-flex rounded-full border px-4 py-2 text-sm font-medium backdrop-blur"
                >
                    Laundry multi-cabang, satu kendali
                </p>
                <h2
                    class="font-display max-w-md text-4xl leading-tight font-semibold tracking-tight text-balance xl:text-5xl"
                >
                    Operasional laundry yang terasa ringan.
                </h2>
                <p class="text-surface/85 mt-5 max-w-md text-base leading-7">
                    Kelola pesanan, harga kiloan, antar jemput, dan progres
                    tiap cabang dari satu sistem yang selalu terhubung.
                </p>

                <div class="mt-10 grid max-w-md gap-3 sm:grid-cols-2">
                    <div
                        class="bg-surface/10 border-surface/15 rounded-2xl border p-4 backdrop-blur"
                    >
                        <p class="text-2xl font-semibold">Real-time</p>
                        <p class="text-surface/85 mt-1 text-sm">
                            Status pesanan terpantau
                        </p>
                    </div>
                    <div
                        class="bg-surface/10 border-surface/15 rounded-2xl border p-4 backdrop-blur"
                    >
                        <p class="text-2xl font-semibold">Multi-role</p>
                        <p class="text-surface/85 mt-1 text-sm">
                            Customer sampai owner
                        </p>
                    </div>
                </div>
            </div>

            <p class="text-surface/75 relative z-10 text-sm">
                Dibuat untuk bisnis laundry Indonesia.
            </p>
        </div>
        <div class="flex items-center px-6 py-8 sm:px-10 lg:p-12">
            <div
                class="mx-auto flex w-full max-w-md flex-col justify-center space-y-6"
            >
                <Link
                    :href="login()"
                    class="text-ink-950 mx-auto flex min-h-11 items-center gap-2 text-base font-semibold lg:hidden"
                >
                    <AppLogoIcon class="text-brand-700 size-8 fill-current" />
                    {{ name }}
                </Link>
                <div class="flex flex-col space-y-2 text-center">
                    <h1
                        class="font-display text-xl font-semibold tracking-tight"
                        v-if="title"
                    >
                        {{ title }}
                    </h1>
                    <p class="text-muted-foreground text-sm" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
