<script setup lang="ts">
import { Check, Clipboard, KeyRound } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { MobileCustomerCredential } from '@/types/mobile';

const props = defineProps<{
    customerName: string;
    credential: MobileCustomerCredential | null;
}>();

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ dismiss: [] }>();
const visibleCredential = ref<MobileCustomerCredential | null>(null);
const copyStatus = ref<'idle' | 'copied' | 'failed'>('idle');

watch(
    open,
    (isOpen) => {
        visibleCredential.value = isOpen ? props.credential : null;
        copyStatus.value = 'idle';
    },
    { immediate: true },
);

watch(
    () => props.credential,
    (credential) => {
        if (open.value) {
            visibleCredential.value = credential;
        }
    },
);

const formattedExpiry = computed(() => {
    if (!visibleCredential.value) {
        return '';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(visibleCredential.value.expires_at));
});

const copyPin = async (): Promise<void> => {
    if (!visibleCredential.value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(visibleCredential.value.temporary_pin);
        copyStatus.value = 'copied';
    } catch {
        copyStatus.value = 'failed';
    }
};

const dismiss = (): void => {
    visibleCredential.value = null;
    copyStatus.value = 'idle';
    open.value = false;
    emit('dismiss');
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-lg rounded-2xl bg-surface" :show-close-button="false">
            <DialogHeader>
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-xl bg-mint-500/10 text-mint-500"
                >
                    <KeyRound class="size-5" aria-hidden="true" />
                </span>
                <DialogTitle>PIN sementara {{ customerName }}</DialogTitle>
                <DialogDescription>
                    Salin dan kirim PIN ini sekarang. Setelah dialog ditutup, PIN tidak akan ditampilkan lagi.
                </DialogDescription>
            </DialogHeader>

            <div v-if="visibleCredential" class="rounded-2xl border border-line bg-canvas p-4">
                <p class="text-xs font-semibold tracking-[0.14em] text-ink-500 uppercase">
                    PIN sementara
                </p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <code class="font-display text-3xl font-semibold tracking-[0.24em] text-ink-950">
                        {{ visibleCredential.temporary_pin }}
                    </code>
                    <Button
                        type="button"
                        variant="outline"
                        class="min-w-11"
                        data-test="copy-temporary-pin"
                        @click="copyPin"
                    >
                        <Check v-if="copyStatus === 'copied'" class="size-4" aria-hidden="true" />
                        <Clipboard v-else class="size-4" aria-hidden="true" />
                        {{ copyStatus === 'copied' ? 'Tersalin' : 'Salin' }}
                    </Button>
                </div>
                <p class="mt-3 text-sm text-ink-500">Berlaku sampai {{ formattedExpiry }} WIB.</p>
                <p v-if="copyStatus === 'failed'" class="mt-2 text-sm text-red-600" role="alert">
                    PIN tidak dapat disalin otomatis. Salin angka di atas secara manual.
                </p>
                <p v-else class="sr-only" aria-live="polite">
                    {{ copyStatus === 'copied' ? 'PIN sementara berhasil disalin.' : '' }}
                </p>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    class="w-full sm:w-auto"
                    data-test="dismiss-temporary-pin"
                    @click="dismiss"
                >
                    Selesai, hapus dari layar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
