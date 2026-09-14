<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({
    layout: {
        title: 'Buat akun pelanggan',
        description: 'Daftar dengan nomor WhatsApp dan PIN 6 digit.',
    },
});
</script>

<template>
    <Head title="Daftar" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['pin', 'pin_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Nama lengkap</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    name="name"
                    placeholder="Nama lengkap"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="whatsapp_phone">Nomor WhatsApp</Label>
                <Input
                    id="whatsapp_phone"
                    type="tel"
                    required
                    autocomplete="tel"
                    inputmode="tel"
                    name="whatsapp_phone"
                    placeholder="08xxxxxxxxxx"
                />
                <InputError :message="errors.whatsapp_phone" />
            </div>

            <div class="grid gap-2">
                <Label for="pin">PIN 6 digit</Label>
                <Input
                    id="pin"
                    type="password"
                    required
                    autocomplete="new-password"
                    inputmode="numeric"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    name="pin"
                    placeholder="••••••"
                />
                <InputError :message="errors.pin" />
            </div>

            <div class="grid gap-2">
                <Label for="pin_confirmation">Konfirmasi PIN</Label>
                <Input
                    id="pin_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    inputmode="numeric"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    name="pin_confirmation"
                    placeholder="••••••"
                />
                <InputError :message="errors.pin_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Daftar
            </Button>
        </div>

        <div class="text-muted-foreground text-center text-sm">
            Sudah punya akun?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                >Masuk</TextLink
            >
        </div>
    </Form>
</template>
