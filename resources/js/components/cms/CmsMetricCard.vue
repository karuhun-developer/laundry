<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    label: string;
    value: string | number;
    detail: string;
    icon: Component;
}>();
</script>

<template>
    <article
        class="group border-line bg-surface flex min-h-32 items-start justify-between gap-4 rounded-3xl border p-5 shadow-low"
    >
        <div class="min-w-0">
            <p class="text-ink-500 text-[11px] font-semibold tracking-[0.08em] uppercase">{{ label }}</p>
            <p class="font-display text-ink-950 mt-2 text-2xl font-semibold tabular-nums tracking-tight [overflow-wrap:anywhere] xl:text-3xl">
                {{ value }}
            </p>
            <p class="text-ink-500 mt-1 text-xs">{{ detail }}</p>
        </div>
        <span
            class="bg-sky-400 text-brand-700 grid size-11 shrink-0 place-items-center rounded-xl"
        >
            <component :is="icon" class="size-5" />
        </span>
    </article>
</template>
