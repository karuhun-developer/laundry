<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description: string;
    icon: Component;
}>();
</script>

<template>
    <div
        class="border-line bg-canvas/70 flex min-h-48 flex-col items-center justify-center rounded-xl border border-dashed px-6 py-10 text-center"
    >
        <span
            class="bg-surface-blue text-brand-700 grid size-12 place-items-center rounded-xl"
        >
            <component :is="icon" class="size-6" />
        </span>
        <h2 class="text-ink-950 mt-4 font-semibold">{{ title }}</h2>
        <p class="text-ink-500 mt-1 max-w-sm text-sm leading-6">
            {{ description }}
        </p>
    </div>
</template>
