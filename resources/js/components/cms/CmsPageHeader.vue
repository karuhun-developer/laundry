<script setup lang="ts">
defineProps<{
    eyebrow: string;
    title: string;
    description: string;
}>();
</script>

<template>
    <header class="relative overflow-hidden rounded-3xl border border-line bg-surface px-5 py-6 shadow-low sm:flex sm:items-end sm:justify-between sm:gap-6 sm:px-7">
        <div class="relative space-y-2">
            <p class="inline-flex rounded-md bg-brand-100 px-2 py-1 text-brand-600 text-[11px] font-semibold tracking-[0.12em] uppercase">
                {{ eyebrow }}
            </p>
            <div class="max-w-3xl space-y-2">
                <h1 class="font-display text-ink-950 text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ title }}
                </h1>
                <p class="text-ink-500 text-sm leading-6 sm:text-base">
                    {{ description }}
                </p>
            </div>
        </div>
        <div v-if="$slots.actions" class="relative mt-5 flex shrink-0 flex-wrap gap-2 sm:mt-0">
            <slot name="actions" />
        </div>
    </header>
</template>
