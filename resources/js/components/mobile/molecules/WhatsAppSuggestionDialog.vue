<script setup lang="ts">
import { ExternalLink, MessageCircle } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { externalLinkTransport } from '@/lib/externalLinks/transport';
import type { WhatsAppSuggestion } from '@/types/mobile';

defineProps<{
    suggestion: WhatsAppSuggestion | null;
    loadError: string | null;
}>();

const open = defineModel<boolean>('open', { required: true });
const opening = ref(false);
const openError = ref<string | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        openError.value = null;
    }
});

const openWhatsApp = async (suggestion: WhatsAppSuggestion): Promise<void> => {
    if (!suggestion.available || opening.value) {
        return;
    }

    opening.value = true;
    openError.value = null;

    try {
        await externalLinkTransport.open(suggestion.url);
    } catch (error: unknown) {
        openError.value = error instanceof Error ? error.message : 'WhatsApp tidak dapat dibuka.';
    } finally {
        opening.value = false;
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-lg rounded-2xl bg-surface">
            <DialogHeader>
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-xl bg-mint-500/10 text-mint-500"
                >
                    <MessageCircle class="size-5" aria-hidden="true" />
                </span>
                <DialogTitle>Preview pesan WhatsApp</DialogTitle>
                <DialogDescription>
                    Status pesanan sudah tersimpan. Periksa pesannya sebelum membuka WhatsApp.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="suggestion?.available"
                class="space-y-3 rounded-2xl border border-line bg-canvas p-4"
            >
                <p class="text-xs font-semibold tracking-wide text-ink-500 uppercase">
                    Tujuan {{ suggestion.phone }}
                </p>
                <p class="whitespace-pre-wrap text-sm leading-6 text-ink-950" data-test="whatsapp-message">
                    {{ suggestion.message }}
                </p>
            </div>
            <p
                v-else
                class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm leading-6 text-amber-800"
                role="status"
            >
                {{ loadError ?? suggestion?.unavailable_reason ?? 'Preview pesan belum tersedia.' }}
            </p>

            <p
                v-if="openError"
                class="rounded-xl border border-red-600/20 bg-red-600/5 px-4 py-3 text-sm text-red-600"
                role="alert"
            >
                {{ openError }}
            </p>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="secondary">Tutup</Button>
                </DialogClose>
                <Button
                    v-if="suggestion?.available"
                    type="button"
                    :disabled="opening"
                    data-test="open-whatsapp"
                    @click="openWhatsApp(suggestion)"
                >
                    <ExternalLink class="size-4" aria-hidden="true" />
                    {{ opening ? 'Membuka...' : 'Buka WhatsApp' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
