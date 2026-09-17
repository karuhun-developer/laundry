<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name;
</script>

<template>
    <div
        class="flex aspect-square size-10 items-center justify-center rounded-xl bg-white/12 text-brand-100 shadow-[inset_0_0_0_1px_rgb(255_255_255/0.16),0_10px_24px_rgb(0_0_0/0.18)]"
    >
        <AppLogoIcon class-name="size-7" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span class="font-display truncate text-sm leading-tight font-semibold text-white">{{
            name
        }}</span>
        <span class="truncate text-[10px] font-medium tracking-[0.12em] text-brand-100/60 uppercase">Laundry operations</span>
    </div>
</template>
