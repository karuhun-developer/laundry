<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    nameError?: string;
    emailError?: string;
    whatsappPhoneError?: string;
}>();

const name = defineModel<string>('name', { required: true });
const email = defineModel<string>('email', { required: true });
const whatsappPhone = defineModel<string>('whatsappPhone', { required: true });
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <Label for="user-name">Nama lengkap</Label>
            <Input
                id="user-name"
                v-model="name"
                autocomplete="name"
                placeholder="Nama pengguna"
                required
                :aria-invalid="Boolean(nameError)"
                aria-describedby="user-name-error"
            />
            <InputError id="user-name-error" :message="nameError" />
        </div>
        <div class="space-y-2">
            <Label for="user-email">Email</Label>
            <Input
                id="user-email"
                v-model="email"
                type="email"
                autocomplete="email"
                placeholder="nama@example.com"
                required
                :aria-invalid="Boolean(emailError)"
                aria-describedby="user-email-error"
            />
            <InputError id="user-email-error" :message="emailError" />
        </div>
    </div>

    <div class="space-y-2">
        <Label for="user-whatsapp-phone">Nomor WhatsApp</Label>
        <Input
            id="user-whatsapp-phone"
            v-model="whatsappPhone"
            inputmode="tel"
            autocomplete="tel"
            placeholder="0812 3456 7890"
            :aria-invalid="Boolean(whatsappPhoneError)"
            aria-describedby="user-whatsapp-phone-hint user-whatsapp-phone-error"
        />
        <p id="user-whatsapp-phone-hint" class="text-ink-500 text-xs">
            Opsional. Nomor akan dinormalisasi ke format Indonesia 62 saat disimpan.
        </p>
        <InputError id="user-whatsapp-phone-error" :message="whatsappPhoneError" />
    </div>
</template>
