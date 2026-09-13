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
        class="rounded-2xl border border-blue-100 bg-blue-50/80 p-4"
    >
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-900">Akun demo</p>
                <p class="text-xs text-slate-500">
                    Pilih role untuk mengisi form otomatis.
                </p>
            </div>
            <span
                class="rounded-full bg-white px-2.5 py-1 font-mono text-xs font-medium text-blue-700 shadow-sm"
            >
                {{ demoPassword }}
            </span>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <button
                v-for="account in demoAccounts"
                :key="account.role"
                type="button"
                class="min-h-11 rounded-xl border border-blue-100 bg-white px-3 py-2 text-left text-xs font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
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
                    :tabindex="1"
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
                        :tabindex="5"
                    >
                        Lupa kata sandi?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    v-model="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Kata sandi"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Ingat saya</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Masuk
            </Button>
        </div>

        <div class="text-muted-foreground text-center text-sm">
            Belum punya akun?
            <TextLink :href="register()" :tabindex="5">Daftar</TextLink>
        </div>
    </Form>
</template>
