<script setup lang="ts">
import { KeyRound, LoaderCircle } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { MobileCustomer, MobileCustomerCredentialResult } from '@/types/mobile';

const props = defineProps<{
    customer: MobileCustomer | null;
    reset: (customerId: number) => Promise<MobileCustomerCredentialResult>;
}>();

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ reset: [result: MobileCustomerCredentialResult] }>();
const processing = ref(false);
const requestError = ref<string | null>(null);
let operationGeneration = 0;

watch(
    [open, () => props.customer],
    ([isOpen]) => {
        operationGeneration += 1;
        processing.value = false;

        if (isOpen) {
            requestError.value = null;
        }
    },
    { immediate: true },
);

const close = (): void => {
    if (!processing.value) {
        open.value = false;
    }
};

const updateOpen = (nextOpen: boolean): void => {
    if (!processing.value) {
        open.value = nextOpen;
    }
};

const preventDismissal = (event: Event): void => {
    if (processing.value) {
        event.preventDefault();
    }
};

const resetPin = async (): Promise<void> => {
    if (!props.customer || processing.value) {
        return;
    }

    processing.value = true;
    requestError.value = null;
    const generation = operationGeneration;

    try {
        const result = await props.reset(props.customer.id);

        if (generation !== operationGeneration) {
            return;
        }

        open.value = false;
        emit('reset', result);
    } catch (error: unknown) {
        if (generation !== operationGeneration) {
            return;
        }

        requestError.value =
            error instanceof Error ? error.message : 'PIN sementara tidak dapat dibuat.';
    } finally {
        if (generation === operationGeneration) {
            processing.value = false;
        }
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="updateOpen">
        <DialogContent
            class="max-w-lg rounded-2xl bg-surface"
            :show-close-button="!processing"
            @escape-key-down="preventDismissal"
            @interact-outside="preventDismissal"
        >
            <DialogHeader>
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-700"
                >
                    <KeyRound class="size-5" aria-hidden="true" />
                </span>
                <DialogTitle>Reset PIN {{ customer?.name }}</DialogTitle>
                <DialogDescription>
                    Sesi pelanggan akan diakhiri dan sebuah PIN sementara baru akan dibuat. PIN hanya ditampilkan satu kali.
                </DialogDescription>
            </DialogHeader>

            <InputError :message="requestError ?? undefined" />

            <DialogFooter class="gap-2">
                <Button type="button" variant="secondary" :disabled="processing" @click="close">
                    Batal
                </Button>
                <Button
                    type="button"
                    :disabled="processing || !customer"
                    data-test="confirm-reset-pin"
                    @click="resetPin"
                >
                    <LoaderCircle v-if="processing" class="size-4 animate-spin" aria-hidden="true" />
                    <KeyRound v-else class="size-4" aria-hidden="true" />
                    {{ processing ? 'Membuat PIN…' : 'Reset PIN' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
