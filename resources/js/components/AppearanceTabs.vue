<script setup lang="ts">
import { Check, Sun } from '@lucide/vue';
</script>

<template>
    <div
        class="inline-flex items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3 text-sm text-ink-950"
    >
        <Sun class="size-5 text-brand-600" aria-hidden="true" />
        <span class="font-medium">Tema terang</span>
        <span class="inline-flex items-center gap-1 rounded-full bg-brand-100 px-2 py-1 text-xs font-semibold text-brand-700">
            <Check class="size-3" aria-hidden="true" />Aktif
        </span>
    </div>
</template>
