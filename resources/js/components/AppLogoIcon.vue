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
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
        fill="none"
        aria-hidden="true"
    >
        <rect
            x="6"
            y="3"
            width="28"
            height="34"
            rx="6"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
        />
        <circle cx="12" cy="10" r="2" fill="currentColor" />
        <path
            d="M18 10h9"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
        />
        <circle
            cx="20"
            cy="24"
            r="8.5"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
        />
        <path
            d="M13.4 25.2c2.1-2.1 4.2-2.1 6.4 0 2.2 2.1 4.4 2.1 6.8-.3"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
        />
    </svg>
</template>
