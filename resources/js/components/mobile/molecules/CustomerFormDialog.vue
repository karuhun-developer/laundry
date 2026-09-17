<script setup lang="ts">
import { LoaderCircle, UserPlus, UserRoundPen } from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
import { MobileCustomerValidationError } from '@/composables/useMobileCustomerApi';
import type {
    MobileCustomer,
    MobileCustomerMutationResult,
    MobileCustomerProfilePayload,
} from '@/types/mobile';

const props = defineProps<{
    mode: 'create' | 'edit';
    customer?: MobileCustomer | null;
    submit: (payload: MobileCustomerProfilePayload) => Promise<MobileCustomerMutationResult>;
}>();

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [result: MobileCustomerMutationResult] }>();
const name = ref('');
const whatsappPhone = ref('');
const processing = ref(false);
const requestError = ref<string | null>(null);
const fieldErrors = ref<{ name?: string; whatsapp_phone?: string }>({});
let operationGeneration = 0;

const resetForm = (): void => {
    name.value = props.mode === 'edit' ? (props.customer?.name ?? '') : '';
    whatsappPhone.value = props.mode === 'edit' ? (props.customer?.whatsapp_phone ?? '') : '';
    requestError.value = null;
    fieldErrors.value = {};
};

watch(
    [open, () => props.customer, () => props.mode],
    ([isOpen]) => {
        operationGeneration += 1;
        processing.value = false;

        if (isOpen) {
            resetForm();
        }
    },
    { immediate: true },
);

const close = (): void => {
    if (processing.value) {
        return;
    }

    open.value = false;
    requestError.value = null;
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

const save = async (): Promise<void> => {
    if (processing.value) {
        return;
    }

    processing.value = true;
    requestError.value = null;
    fieldErrors.value = {};
    const generation = operationGeneration;

    try {
        const result = await props.submit({
            name: name.value.trim(),
            whatsapp_phone: whatsappPhone.value.trim(),
        });

        if (generation !== operationGeneration) {
            return;
        }

        open.value = false;
        emit('saved', result);
    } catch (error: unknown) {
        if (generation !== operationGeneration) {
            return;
        }

        requestError.value =
            error instanceof Error ? error.message : 'Data pelanggan tidak dapat disimpan.';
        toast.error(requestError.value);

        if (error instanceof MobileCustomerValidationError) {
            fieldErrors.value = error.fieldErrors;
        }
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
            class="w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-surface"
            close-label="Tutup"
            :show-close-button="!processing"
            @escape-key-down="preventDismissal"
            @interact-outside="preventDismissal"
        >
            <form class="space-y-6" data-test="customer-form" @submit.prevent="save">
                <DialogHeader class="text-left">
                    <span
                        class="mb-2 flex size-11 items-center justify-center rounded-xl bg-brand-100 text-brand-700"
                    >
                        <UserPlus v-if="mode === 'create'" class="size-5" aria-hidden="true" />
                        <UserRoundPen v-else class="size-5" aria-hidden="true" />
                    </span>
                    <DialogTitle>
                        {{ mode === 'create' ? 'Tambah pelanggan' : 'Edit pelanggan' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? 'Masukkan nama dan nomor WhatsApp. Akun yang sudah ada akan dihubungkan tanpa mengubah profil atau PIN-nya.'
                                : 'Perbarui nama dan nomor WhatsApp pelanggan untuk organisasi ini.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="customer-name">Nama pelanggan</Label>
                        <Input
                            id="customer-name"
                            v-model="name"
                            name="name"
                            autocomplete="name"
                            maxlength="255"
                            required
                            :disabled="processing"
                            :aria-invalid="Boolean(fieldErrors.name)"
                            :aria-describedby="fieldErrors.name ? 'customer-name-error' : undefined"
                        />
                        <InputError id="customer-name-error" :message="fieldErrors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="customer-whatsapp-phone">Nomor WhatsApp</Label>
                        <Input
                            id="customer-whatsapp-phone"
                            v-model="whatsappPhone"
                            name="whatsapp_phone"
                            type="tel"
                            inputmode="tel"
                            autocomplete="tel"
                            maxlength="30"
                            placeholder="0812 3456 7890"
                            required
                            :disabled="processing"
                            :aria-invalid="Boolean(fieldErrors.whatsapp_phone)"
                            :aria-describedby="
                                fieldErrors.whatsapp_phone
                                    ? 'customer-whatsapp-phone-hint customer-whatsapp-phone-error'
                                    : 'customer-whatsapp-phone-hint'
                            "
                        />
                        <p id="customer-whatsapp-phone-hint" class="text-xs leading-5 text-ink-500">
                            Gunakan nomor yang dapat menerima pesan WhatsApp pelanggan.
                        </p>
                        <InputError
                            id="customer-whatsapp-phone-error"
                            :message="fieldErrors.whatsapp_phone"
                        />
                    </div>

                    <InputError :message="requestError ?? undefined" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="processing"
                        data-test="cancel-customer-form"
                        @click="close"
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="processing">
                        <LoaderCircle v-if="processing" class="size-4 animate-spin" aria-hidden="true" />
                        {{
                            processing
                                ? 'Menyimpan…'
                                : mode === 'create'
                                  ? 'Tambah pelanggan'
                                  : 'Simpan perubahan'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
