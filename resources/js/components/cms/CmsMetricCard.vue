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
        class="group border-line bg-surface flex min-h-32 items-start justify-between gap-4 rounded-2xl border p-5 shadow-low transition hover:-translate-y-0.5 hover:shadow-raised"
    >
        <div>
            <p class="text-ink-500 text-sm font-medium">{{ label }}</p>
            <p class="font-display text-ink-950 mt-2 text-2xl font-semibold">
                {{ value }}
            </p>
            <p class="text-ink-500 mt-1 text-xs">{{ detail }}</p>
        </div>
        <span
            class="bg-brand-100 text-brand-700 grid size-11 shrink-0 place-items-center rounded-xl ring-1 ring-brand-600/10 transition group-hover:bg-brand-700 group-hover:text-white"
        >
            <component :is="icon" class="size-5" />
        </span>
    </article>
</template>
