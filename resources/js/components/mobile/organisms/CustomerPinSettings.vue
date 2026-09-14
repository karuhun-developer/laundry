<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { KeyRound, LoaderCircle } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update as updateCustomerPin } from '@/routes/api/v1/mobile/account/pin';

type CustomerPinPayload = {
    current_pin: string;
    pin: string;
    pin_confirmation: string;
};

type CustomerPinResponse = {
    message: string;
    data: {
        must_change_pin: boolean;
        pin_expires_at: string | null;
    };
};

const props = withDefaults(
    defineProps<{
        mustChangePin?: boolean;
        pinExpiresAt?: string | null;
    }>(),
    {
        mustChangePin: false,
        pinExpiresAt: null,
    },
);

const pinForm = useHttp<CustomerPinPayload, CustomerPinResponse>({
    current_pin: '',
    pin: '',
    pin_confirmation: '',
});
const pinStatus = ref<string | null>(null);
const pinRequestError = ref<string | null>(null);
const temporaryPinResolved = ref(false);

const pinChangeRequired = computed(() => props.mustChangePin && !temporaryPinResolved.value);
const pinExpiryLabel = computed(() => {
    if (!props.pinExpiresAt) {
        return null;
    }

    const expiry = new Date(props.pinExpiresAt);

    if (Number.isNaN(expiry.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(expiry);
});

const submitPinChange = async (): Promise<void> => {
    pinStatus.value = null;
    pinRequestError.value = null;
    pinForm.clearErrors();

    if (!/^\d{6}$/.test(pinForm.current_pin)) {
        pinForm.setError('current_pin', 'PIN saat ini harus terdiri dari 6 angka.');
    }

    if (!/^\d{6}$/.test(pinForm.pin)) {
        pinForm.setError('pin', 'PIN baru harus terdiri dari 6 angka.');
    } else if (pinForm.pin === pinForm.current_pin) {
        pinForm.setError('pin', 'PIN baru harus berbeda dari PIN saat ini.');
    }

    if (pinForm.pin_confirmation !== pinForm.pin) {
        pinForm.setError('pin_confirmation', 'Konfirmasi PIN baru tidak cocok.');
    }

    if (pinForm.hasErrors) {
        return;
    }

    let response: CustomerPinResponse | undefined;

    try {
        response = await pinForm.put(updateCustomerPin().url, {
            onHttpException: () => {
                pinRequestError.value =
                    'PIN tidak dapat diperbarui. Silakan masuk kembali dan coba lagi.';
            },
            onNetworkError: () => {
                pinRequestError.value = 'Koneksi bermasalah. Periksa jaringan lalu coba lagi.';
            },
        });
    } catch {
        pinRequestError.value ??= 'PIN tidak dapat diperbarui. Silakan coba lagi.';

        return;
    }

    if (!response) {
        if (!pinForm.hasErrors && pinRequestError.value === null) {
            pinRequestError.value = 'PIN tidak dapat diperbarui. Silakan coba lagi.';
        }

        return;
    }

    temporaryPinResolved.value = !response.data.must_change_pin;
    pinStatus.value = response.message;
    pinForm.reset();
};
</script>

<template>
    <section
        class="mt-5 rounded-2xl border border-line bg-surface p-5 shadow-low"
        aria-labelledby="customer-pin-title"
    >
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
                <KeyRound class="size-5" aria-hidden="true" />
            </span>
            <div>
                <h3 id="customer-pin-title" class="font-display text-lg font-semibold text-ink-950">
                    Ganti PIN masuk
                </h3>
                <p class="mt-1 text-sm leading-6 text-ink-500">
                    Gunakan enam angka yang mudah Anda ingat dan tidak mudah ditebak orang lain.
                </p>
            </div>
        </div>

        <div
            v-if="pinChangeRequired"
            class="mt-4 rounded-xl border border-brand-700/20 bg-brand-100/70 px-4 py-3"
            data-test="mandatory-pin-notice"
            role="alert"
        >
            <p class="text-sm font-semibold text-brand-700">PIN sementara wajib diganti</p>
            <p class="mt-1 text-sm leading-6 text-ink-700">
                Ganti PIN sementara sebelum melanjutkan penggunaan akun.
                <span v-if="pinExpiryLabel">PIN ini berlaku sampai {{ pinExpiryLabel }}.</span>
            </p>
        </div>

        <form class="mt-5 space-y-4" data-test="customer-pin-form" @submit.prevent="submitPinChange">
            <div class="grid gap-2">
                <Label for="current-pin">PIN saat ini</Label>
                <Input
                    id="current-pin"
                    v-model="pinForm.current_pin"
                    name="current_pin"
                    type="password"
                    inputmode="numeric"
                    autocomplete="current-password"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    required
                    :aria-invalid="Boolean(pinForm.errors.current_pin)"
                    aria-describedby="current-pin-error"
                />
                <InputError id="current-pin-error" :message="pinForm.errors.current_pin" />
            </div>

            <div class="grid gap-2">
                <Label for="new-pin">PIN baru</Label>
                <Input
                    id="new-pin"
                    v-model="pinForm.pin"
                    name="pin"
                    type="password"
                    inputmode="numeric"
                    autocomplete="new-password"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    required
                    :aria-invalid="Boolean(pinForm.errors.pin)"
                    aria-describedby="new-pin-error"
                />
                <InputError id="new-pin-error" :message="pinForm.errors.pin" />
            </div>

            <div class="grid gap-2">
                <Label for="new-pin-confirmation">Konfirmasi PIN baru</Label>
                <Input
                    id="new-pin-confirmation"
                    v-model="pinForm.pin_confirmation"
                    name="pin_confirmation"
                    type="password"
                    inputmode="numeric"
                    autocomplete="new-password"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    required
                    :aria-invalid="Boolean(pinForm.errors.pin_confirmation)"
                    aria-describedby="new-pin-confirmation-error"
                />
                <InputError
                    id="new-pin-confirmation-error"
                    :message="pinForm.errors.pin_confirmation"
                />
            </div>

            <p
                v-if="pinStatus"
                class="rounded-xl border border-mint-500/30 bg-mint-500/10 px-4 py-3 text-sm font-medium text-ink-950"
                data-test="pin-success"
                role="status"
                aria-live="polite"
            >
                {{ pinStatus }}
            </p>
            <p
                v-if="pinRequestError"
                class="rounded-xl border border-red-600/20 bg-red-600/5 px-4 py-3 text-sm text-red-600"
                data-test="pin-request-error"
                role="alert"
            >
                {{ pinRequestError }}
            </p>

            <Button
                type="submit"
                class="min-h-12 w-full rounded-xl bg-brand-700 text-white hover:bg-brand-700/90"
                data-test="submit-customer-pin"
                :disabled="pinForm.processing"
            >
                <LoaderCircle v-if="pinForm.processing" class="size-4 animate-spin" aria-hidden="true" />
                {{ pinForm.processing ? 'Menyimpan...' : 'Simpan PIN baru' }}
            </Button>
        </form>
    </section>
</template>
