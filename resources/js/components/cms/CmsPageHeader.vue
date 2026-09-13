<script setup lang="ts">
defineProps<{
    eyebrow: string;
    title: string;
    description: string;
}>();
</script>

<template>
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-2">
            <p class="text-brand-700 text-xs font-semibold tracking-[0.16em] uppercase">
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
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
