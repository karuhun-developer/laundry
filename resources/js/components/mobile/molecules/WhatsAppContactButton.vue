<script setup lang="ts">
import { computed, ref } from 'vue';
import WhatsAppIcon from '@/components/mobile/atoms/WhatsAppIcon.vue';
import { Button } from '@/components/ui/button';
import { externalLinkTransport } from '@/lib/externalLinks/transport';

const props = withDefaults(defineProps<{ phone: string; recipient?: 'branch' | 'customer' }>(), {
    recipient: 'branch',
});
const opening = ref(false);
const openError = ref<string | null>(null);
const whatsAppUrl = computed(() => {
    const digits = props.phone.replace(/\D/g, '');
    const phone = digits.startsWith('0') ? `62${digits.slice(1)}` : digits;

    return /^628[1-9][0-9]{7,11}$/.test(phone) ? `https://wa.me/${phone}` : null;
});

const openWhatsApp = async (): Promise<void> => {
    if (!whatsAppUrl.value || opening.value) {
        return;
    }

    opening.value = true;
    openError.value = null;

    try {
        await externalLinkTransport.open(whatsAppUrl.value);
    } catch (error: unknown) {
        openError.value = error instanceof Error ? error.message : 'WhatsApp tidak dapat dibuka.';
    } finally {
        opening.value = false;
    }
};
</script>

<template>
    <div>
        <Button
            type="button"
            variant="outline"
            class="min-h-11 rounded-xl border-brand-600/20 bg-white text-brand-700 hover:bg-brand-100"
            :disabled="!whatsAppUrl || opening"
            :data-test="recipient === 'customer' ? 'chat-customer' : 'chat-branch'"
            @click="openWhatsApp"
        >
            <WhatsAppIcon class="size-4 shrink-0" />
            {{ opening ? 'Membuka WhatsApp…' : recipient === 'customer' ? 'Chat Pelanggan' : 'Chat Cabang' }}
        </Button>
        <p v-if="!whatsAppUrl" class="mt-2 text-xs text-ink-500">
            Nomor WhatsApp {{ recipient === 'customer' ? 'pelanggan' : 'cabang' }} belum tersedia.
        </p>
        <p v-if="openError" role="alert" class="mt-2 text-sm text-red-600">{{ openError }}</p>
    </div>
</template>
