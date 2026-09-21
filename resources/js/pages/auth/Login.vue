<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store as customerLoginStore } from '@/routes/customer-login';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { AuthMode } from '@/types/auth';

defineOptions({
    layout: {
        title: 'Masuk ke LaundryHub',
        description: 'Kelola cucian dari pelanggan sampai siap diambil.',
    },
});

type DemoAccount = {
    label: string;
    email: string | null;
    role: string;
    whatsapp_phone?: string;
    pin?: string;
};

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    demoAccounts: DemoAccount[];
    demoPassword: string | null;
    demoCustomerPin: string | null;
}>();

const mode = ref<AuthMode>('customer');
const whatsappPhone = ref('');
const pin = ref('');
const email = ref('');
const password = ref('');
const visibleDemoAccounts = computed(() =>
    props.demoAccounts.filter((account) =>
        mode.value === 'customer' ? account.role === 'customer' : account.role !== 'customer',
    ),
);

const useDemoAccount = (account: DemoAccount): void => {
    if (account.role === 'customer') {
        mode.value = 'customer';
        whatsappPhone.value = account.whatsapp_phone ?? '';
        pin.value = account.pin ?? props.demoCustomerPin ?? '';

        return;
    }

    mode.value = 'staff';
    email.value = account.email ?? '';
    password.value = props.demoPassword ?? '';
};

const transformCustomerLogin = (
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> => ({
    ...data,
    whatsapp_phone:
        typeof data.whatsapp_phone === 'string' ? data.whatsapp_phone.trim() : data.whatsapp_phone,
});
</script>

<template>
    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div
        v-if="demoAccounts.length > 0"
        class="border-brand-100 bg-surface-blue rounded-2xl border p-4"
    >
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <p class="text-ink-950 text-sm font-semibold">Akun demo</p>
                <p class="text-ink-500 text-xs">
                    Pilih role untuk mengisi form otomatis.
                </p>
            </div>
            <span
                class="bg-surface text-brand-700 rounded-full px-2.5 py-1 font-mono text-xs font-medium shadow-sm"
            >
                {{ mode === 'customer' ? demoCustomerPin : demoPassword }}
            </span>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <button
                v-for="account in visibleDemoAccounts"
                :key="account.role"
                type="button"
                class="border-brand-100 bg-surface text-ink-700 hover:border-brand-600 hover:text-brand-700 focus-visible:outline-brand-600 min-h-11 rounded-xl border px-3 py-2 text-left text-xs font-semibold shadow-sm transition focus-visible:outline-2 focus-visible:outline-offset-2"
                @click="useDemoAccount(account)"
            >
                {{ account.label }}
            </button>
        </div>
    </div>

    <Form
        v-if="mode === 'customer'"
        v-bind="customerLoginStore.form()"
        :transform="transformCustomerLogin"
        :reset-on-success="['pin']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        data-test="customer-login-form"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="whatsapp_phone">Nomor WhatsApp</Label>
                <Input
                    id="whatsapp_phone"
                    v-model="whatsappPhone"
                    type="tel"
                    name="whatsapp_phone"
                    required
                    autofocus
                    autocomplete="tel"
                    inputmode="tel"
                    placeholder="08xxxxxxxxxx atau 628xxxxxxxxxx"
                />
                <InputError :message="errors.whatsapp_phone" />
            </div>

            <div class="grid gap-2">
                <Label for="pin">PIN 6 digit</Label>
                <Input
                    id="pin"
                    v-model="pin"
                    type="password"
                    name="pin"
                    required
                    maxlength="6"
                    autocomplete="current-password"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    placeholder="••••••"
                />
                <InputError :message="errors.pin" />
            </div>

            <Label
                for="customer-remember"
                class="flex min-h-11 cursor-pointer items-center space-x-3"
            >
                <Checkbox id="customer-remember" name="remember" />
                <span>Ingat saya</span>
            </Label>

            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Masuk
            </Button>
        </div>

        <div class="text-muted-foreground text-center text-sm">
            Belum punya akun?
            <TextLink :href="register()">Daftar</TextLink>
        </div>
    </Form>

    <div v-else data-test="staff-login-panel">
        <PasskeyVerify separator="Atau masuk dengan email" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Alamat email</Label>
                    <Input
                        id="email"
                        v-model="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="nama@email.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Kata sandi</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                        >
                            Lupa kata sandi?
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        v-model="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Kata sandi"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label
                        for="remember"
                        class="flex min-h-11 cursor-pointer items-center space-x-3"
                    >
                        <Checkbox id="remember" name="remember" />
                        <span>Ingat saya</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Masuk
                </Button>
            </div>
        </Form>
    </div>

    <Button
        type="button"
        variant="ghost"
        class="w-full"
        data-test="auth-mode-switch"
        @click="mode = mode === 'customer' ? 'staff' : 'customer'"
    >
        {{ mode === 'customer' ? 'Masuk sebagai staf' : 'Masuk sebagai pelanggan' }}
    </Button>
</template>
