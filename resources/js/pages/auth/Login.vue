<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
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
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Masuk ke LaundryHub',
        description: 'Kelola cucian dari pelanggan sampai siap diambil.',
    },
});

type DemoAccount = {
    label: string;
    email: string;
    role: string;
};

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    demoAccounts: DemoAccount[];
    demoPassword: string | null;
}>();

const email = ref('');
const password = ref('');

const useDemoAccount = (account: DemoAccount): void => {
    email.value = account.email;
    password.value = props.demoPassword ?? '';
};
</script>

<template>
    <Head title="Masuk" />

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
                {{ demoPassword }}
            </span>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <button
                v-for="account in demoAccounts"
                :key="account.role"
                type="button"
                class="border-brand-100 bg-surface text-ink-700 hover:border-brand-600 hover:text-brand-700 focus-visible:outline-brand-600 min-h-11 rounded-xl border px-3 py-2 text-left text-xs font-semibold shadow-sm transition focus-visible:outline-2 focus-visible:outline-offset-2"
                @click="useDemoAccount(account)"
            >
                {{ account.label }}
            </button>
        </div>
    </div>

    <PasskeyVerify />

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
                    type="email"
                    name="email"
                    v-model="email"
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
                    name="password"
                    v-model="password"
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

        <div class="text-muted-foreground text-center text-sm">
            Belum punya akun?
            <TextLink :href="register()">Daftar</TextLink>
        </div>
    </Form>
</template>
