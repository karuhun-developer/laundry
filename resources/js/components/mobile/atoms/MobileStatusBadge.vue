<script setup lang="ts">
import {
    Ban,
    CircleCheck,
    CircleDot,
    PackageCheck,
    Scale,
    Sparkles,
    Waves,
    Wind,
} from '@lucide/vue';
import { computed } from 'vue';
import { orderStatusLabels } from '@/lib/mobileOrders';
import type { MobileOrderStatus } from '@/types/mobile';

type Props = {
    status: MobileOrderStatus;
    pending?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    pending: false,
});

const badgeClass = computed(() => {
    if (props.status === 'cancelled') {
        return 'bg-red-600/10 text-red-600';
    }

    if (props.status === 'completed') {
        return 'bg-mint-500/12 text-mint-500';
    }

    if (props.status === 'ready_for_pickup') {
        return 'bg-amber-500/12 text-amber-500';
    }

    return 'bg-brand-100 text-brand-700';
});
</script>

<template>
    <span
        class="inline-flex min-h-7 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
        :class="badgeClass"
        :data-status="status"
    >
        <span
            v-if="pending"
            class="size-2.5 rounded-full bg-current animate-pulse"
            aria-hidden="true"
        />
        <Ban v-else-if="status === 'cancelled'" class="size-3.5" aria-hidden="true" />
        <CircleCheck
            v-else-if="status === 'completed'"
            class="size-3.5"
            aria-hidden="true"
        />
        <PackageCheck
            v-else-if="status === 'ready_for_pickup'"
            class="size-3.5"
            aria-hidden="true"
        />
        <Sparkles
            v-else-if="status === 'ironing'"
            class="size-3.5"
            aria-hidden="true"
        />
        <Wind v-else-if="status === 'drying'" class="size-3.5" aria-hidden="true" />
        <Waves v-else-if="status === 'washing'" class="size-3.5" aria-hidden="true" />
        <Scale v-else-if="status === 'weighed'" class="size-3.5" aria-hidden="true" />
        <CircleDot v-else class="size-3.5" aria-hidden="true" />
        {{ orderStatusLabels[status] }}
        <span v-if="pending" class="sr-only">menunggu server</span>
    </span>
</template>
