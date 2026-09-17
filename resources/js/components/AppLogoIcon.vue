<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
        fill="none"
        aria-hidden="true"
    >
        <rect x="5" y="5" width="31" height="38" rx="8" fill="currentColor" fill-opacity="0.14" />
        <rect x="7.5" y="7.5" width="26" height="33" rx="6" stroke="currentColor" stroke-width="2.5" />
        <circle cx="13" cy="14" r="2" fill="currentColor" />
        <path d="M19 14h8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
        <circle cx="20.5" cy="27" r="8" stroke="currentColor" stroke-width="2.5" />
        <path d="M14.3 28.2c2-2 4-2 6 0 2.1 2 4.2 2 6.4-.3" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
        <path d="M31 22.5 39.5 19l3.5 8.5-8.5 3.5z" fill="currentColor" />
        <path d="m33.4 31.2 8.4-3.4 2.2 5.5-8.4 3.4z" fill="currentColor" fill-opacity="0.72" />
    </svg>
</template>
