<script setup lang="ts">
import { X } from '@lucide/vue';
import { reactiveOmit } from '@vueuse/core';
import type { DialogContentEmits, DialogContentProps } from 'reka-ui';
import { DialogClose, DialogContent, DialogPortal, useForwardPropsEmits } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import DialogOverlay from './DialogOverlay.vue';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<
        DialogContentProps & {
            class?: HTMLAttributes['class'];
            showCloseButton?: boolean;
            closeLabel?: string;
            layer?: 'default' | 'nested';
        }
    >(),
    {
        showCloseButton: true,
        layer: 'default',
    },
);
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = reactiveOmit(props, 'class', 'showCloseButton', 'closeLabel', 'layer');

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <DialogPortal>
    <DialogOverlay :class="layer === 'nested' ? 'z-60' : undefined" />
    <DialogContent
      data-slot="dialog-content"
      :data-dialog-layer="layer"
      v-bind="{ ...$attrs, ...forwarded }"
      :class="
        cn(
          'bg-background data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-[50%] left-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-5 rounded-2xl border border-line p-5 shadow-raised duration-200 sm:max-w-lg sm:p-6',
            props.class,
            layer === 'nested' && 'z-61',
        )"
    >
      <slot />

      <DialogClose
        v-if="showCloseButton"
        data-slot="dialog-close"
        class="ring-offset-background focus:ring-ring data-[state=open]:bg-accent data-[state=open]:text-muted-foreground absolute flex items-center justify-center border border-line bg-surface text-ink-500 shadow-sm transition hover:bg-brand-100 hover:text-brand-700 focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none [&_svg]:pointer-events-none [&_svg]:shrink-0"
        :class="
          closeLabel
            ? 'top-5 right-5 min-h-11 gap-2 rounded-full px-3 text-sm font-medium sm:top-6 sm:right-6'
            : 'top-4 right-4 size-9 rounded-lg sm:top-5 sm:right-5'
        "
      >
        <X class="size-4" aria-hidden="true" />
        <span :class="closeLabel ? undefined : 'sr-only'">{{ closeLabel ?? 'Close' }}</span>
      </DialogClose>
    </DialogContent>
  </DialogPortal>
</template>
