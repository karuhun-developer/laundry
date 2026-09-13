<script setup lang="ts">
import type { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

type Props = {
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
};

defineProps<Props>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="text-foreground focus-visible:ring-ring decoration-line inline-flex min-h-11 min-w-11 items-center justify-center rounded-sm underline underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! focus-visible:ring-[3px] focus-visible:outline-none"
    >
        <slot />
    </Link>
</template>
