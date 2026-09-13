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
        class="border-line bg-surface flex min-h-28 items-start justify-between gap-4 rounded-xl border p-5 shadow-sm"
    >
        <div>
            <p class="text-ink-500 text-sm font-medium">{{ label }}</p>
            <p class="font-display text-ink-950 mt-2 text-2xl font-semibold">
                {{ value }}
            </p>
            <p class="text-ink-500 mt-1 text-xs">{{ detail }}</p>
        </div>
        <span
            class="bg-surface-blue text-brand-700 grid size-10 shrink-0 place-items-center rounded-lg"
        >
            <component :is="icon" class="size-5" />
        </span>
    </article>
</template>
