<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

withDefaults(
    defineProps<{
        title: string;
        description: string;
        formTest: string;
        processing: boolean;
        submitLabel: string;
        submitDisabled?: boolean;
        contentClass?: string;
    }>(),
    {
        submitDisabled: false,
        contentClass: undefined,
    },
);

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot name="trigger" />
        </DialogTrigger>
        <DialogContent :class="contentClass">
            <form class="space-y-6" :data-test="formTest" @submit.prevent="emit('submit')">
                <DialogHeader>
                    <span class="bg-surface-blue text-brand-700 mb-2 grid size-10 place-items-center rounded-lg">
                        <slot name="icon" />
                    </span>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>{{ description }}</DialogDescription>
                </DialogHeader>

                <slot />

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing || submitDisabled">
                        <LoaderCircle v-if="processing" class="animate-spin" />
                        {{ processing ? 'Menyimpan...' : submitLabel }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
