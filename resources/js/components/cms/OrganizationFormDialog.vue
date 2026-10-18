<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building2, Pencil, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CmsFormDialog from '@/components/cms/CmsFormDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/cms/organizations';
import type { OrganizationSummary, OrganizationsPageProps } from '@/types';

const props = defineProps<{
    organization?: OrganizationSummary;
}>();

const open = ref(false);
const isEditing = computed(() => props.organization !== undefined);
const form = useForm({
    name: props.organization?.name ?? '',
    is_active: props.organization?.is_active ?? true,
    qris_enabled: props.organization?.qris_enabled ?? true,
    qris_api_key: props.organization?.qris_api_key ?? '',
    qris_base_url: props.organization?.qris_base_url ?? '',
});

const predictSlug = (value: string): string =>
    value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

const restoreValues = (): void => {
    form.name = props.organization?.name ?? '';
    form.is_active = props.organization?.is_active ?? true;
    form.qris_enabled = props.organization?.qris_enabled ?? true;
    form.qris_api_key = props.organization?.qris_api_key ?? '';
    form.qris_base_url = props.organization?.qris_base_url ?? '';
    form.clearErrors();
};

watch(open, (isOpen) => {
    if (isOpen) {
        restoreValues();
    }
});

const submit = (): void => {
    const pendingSlug = predictSlug(form.name);

    if (props.organization) {
        const pending = {
            ...props.organization,
            name: form.name,
            slug: pendingSlug,
            is_active: form.is_active,
            pending: true,
        };

        form.optimistic<OrganizationsPageProps>((page) => ({
            organizations: page.organizations.map((item) =>
                item.id === props.organization?.id ? pending : item,
            ),
        })).submit(update(props.organization.id), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });

        return;
    }

    const pending: OrganizationSummary = {
        id: -Date.now(),
        name: form.name,
        slug: pendingSlug,
        is_active: form.is_active,
        branches_count: 0,
        users_count: 0,
        pending: true,
    };

    form.optimistic<OrganizationsPageProps>((page) => ({
        organizations: [pending, ...page.organizations],
    })).submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <CmsFormDialog
        v-model:open="open"
        form-test="organization-form"
        :title="isEditing ? 'Edit organisasi' : 'Organisasi baru'"
        :description="
            isEditing
                ? 'Perbarui identitas dan status tenant.'
                : 'Buat tenant sebelum menambahkan kota, cabang, dan owner.'
        "
        :processing="form.processing"
        :submit-label="isEditing ? 'Simpan perubahan' : 'Tambah organisasi'"
        @submit="submit"
    >
        <template #trigger>
            <Button
                type="button"
                :variant="isEditing ? 'ghost' : 'default'"
                :size="isEditing ? 'sm' : 'default'"
                :class="isEditing ? 'min-h-11 min-w-11' : undefined"
                :disabled="organization?.pending"
                :aria-label="isEditing ? `Edit ${organization?.name}` : undefined"
                data-test="open-organization-dialog"
            >
                <Pencil v-if="isEditing" />
                <Plus v-else />
                <span :class="isEditing ? 'hidden xl:inline' : ''">
                    {{ isEditing ? 'Edit' : 'Tambah organisasi' }}
                </span>
            </Button>
        </template>
        <template #icon><Building2 class="size-5" /></template>

        <div class="space-y-2">
            <Label for="organization-name">Nama organisasi</Label>
            <Input
                id="organization-name"
                v-model="form.name"
                autocomplete="organization"
                placeholder="Contoh: Kirei Wash Jakarta"
                required
                :aria-invalid="Boolean(form.errors.name)"
                aria-describedby="organization-name-error"
            />
            <InputError id="organization-name-error" :message="form.errors.name" />
        </div>

        <div class="space-y-2">
            <Label for="organization-qris-api-key">API Key QRIS Dinamis <span class="text-destructive">*</span></Label>
            <Input
                id="organization-qris-api-key"
                v-model="form.qris_api_key"
                placeholder="qris_..."
                required
                :aria-invalid="Boolean(form.errors.qris_api_key)"
                aria-describedby="organization-qris-api-key-error"
            />
            <p class="text-xs text-ink-500">
                Wajib diisi. Cabang akan otomatis menggunakan API Key ini jika cabang tidak mengatur API Key tersendiri.
            </p>
            <InputError id="organization-qris-api-key-error" :message="form.errors.qris_api_key" />
        </div>

        <div class="space-y-2">
            <Label for="organization-qris-base-url">QRIS Base URL (Opsional)</Label>
            <Input
                id="organization-qris-base-url"
                v-model="form.qris_base_url"
                placeholder="https://qris.karuhundeveloper.com"
                :aria-invalid="Boolean(form.errors.qris_base_url)"
                aria-describedby="organization-qris-base-url-error"
            />
            <InputError id="organization-qris-base-url-error" :message="form.errors.qris_base_url" />
        </div>

        <label class="border-line flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2">
            <Checkbox v-model="form.is_active" />
            <span class="text-sm font-medium">Organisasi aktif</span>
        </label>
    </CmsFormDialog>
</template>
