<script setup lang="ts">
import { LoaderCircle, UserMinus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MobileCustomer } from '@/types/mobile';

const props = defineProps<{
    customer: MobileCustomer | null;
    detach: (customerId: number) => Promise<void>;
}>();

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ detached: [customerId: number] }>();
const verification = ref('');
const processing = ref(false);
const requestError = ref<string | null>(null);
const restoreDirectoryFocus = ref(false);
let operationGeneration = 0;
const verificationMatches = computed(
    () => props.customer !== null && verification.value === props.customer.name,
);

watch(
    [open, () => props.customer],
    ([isOpen]) => {
        operationGeneration += 1;
        processing.value = false;

        if (isOpen) {
            verification.value = '';
            requestError.value = null;
            restoreDirectoryFocus.value = false;
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

const restoreFocus = (event: Event): void => {
    if (!restoreDirectoryFocus.value) {
        return;
    }

    event.preventDefault();
    document.getElementById('customer-directory-title')?.focus();
    restoreDirectoryFocus.value = false;
};

const detachCustomer = async (): Promise<void> => {
    if (!props.customer || !verificationMatches.value || processing.value) {
        return;
    }

    processing.value = true;
    requestError.value = null;
    const generation = operationGeneration;

    try {
        const customerId = props.customer.id;
        await props.detach(customerId);

        if (generation !== operationGeneration) {
            return;
        }

        restoreDirectoryFocus.value = true;
        open.value = false;
        emit('detached', customerId);
    } catch (error: unknown) {
        if (generation !== operationGeneration) {
            return;
        }

        requestError.value =
            error instanceof Error
                ? error.message
                : 'Pelanggan tidak dapat dilepas dari organisasi.';
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
            @close-auto-focus="restoreFocus"
            @escape-key-down="preventDismissal"
            @interact-outside="preventDismissal"
        >
            <form class="space-y-6" data-test="customer-detach-form" @submit.prevent="detachCustomer">
                <DialogHeader>
                    <span
                        class="mb-2 flex size-11 items-center justify-center rounded-xl bg-red-600/10 text-red-600"
                    >
                        <UserMinus class="size-5" aria-hidden="true" />
                    </span>
                    <DialogTitle>Lepas {{ customer?.name }}?</DialogTitle>
                    <DialogDescription>
                        Pelanggan hanya dilepas dari organisasi ini. Akunnya tidak dihapus. Pelanggan dengan riwayat pesanan tidak dapat dilepas.
                    </DialogDescription>
                </DialogHeader>

                <div
                    id="customer-detach-instruction"
                    class="rounded-xl border border-red-600/20 bg-red-600/5 p-4 text-sm leading-6 text-red-600"
                >
                    Ketik <strong>{{ customer?.name }}</strong> untuk memverifikasi tindakan ini.
                </div>

                <div class="space-y-2">
                    <Label for="customer-detach-verification">Nama pelanggan</Label>
                    <Input
                        id="customer-detach-verification"
                        v-model="verification"
                        autocomplete="off"
                        :placeholder="customer?.name"
                        :disabled="processing"
                        :aria-invalid="Boolean(requestError)"
                        aria-describedby="customer-detach-instruction customer-detach-error"
                        data-test="customer-detach-verification"
                    />
                    <InputError id="customer-detach-error" :message="requestError ?? undefined" />
                </div>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="secondary" :disabled="processing" @click="close">
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing || !verificationMatches"
                        data-test="confirm-customer-detach"
                    >
                        <LoaderCircle v-if="processing" class="size-4 animate-spin" aria-hidden="true" />
                        <UserMinus v-else class="size-4" aria-hidden="true" />
                        {{ processing ? 'Melepas…' : 'Lepas pelanggan' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
