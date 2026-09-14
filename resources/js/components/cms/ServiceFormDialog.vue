<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { PackageOpen, Pencil, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CmsFormDialog from '@/components/cms/CmsFormDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/cms/services';
import type { OrganizationOption, ServiceSummary, ServicesPageProps } from '@/types';

const props = defineProps<{
    service?: ServiceSummary;
    organizations: OrganizationOption[];
    currentOrganizationId: number | null;
    isSuperadmin: boolean;
}>();

const open = ref(false);
const isEditing = computed(() => props.service !== undefined);
const defaultOrganizationId = (): string =>
    (
        props.service?.organization_id ??
        props.currentOrganizationId ??
        props.organizations[0]?.id
    )?.toString() ?? '';
const form = useForm({
    organization_id: defaultOrganizationId(),
    name: props.service?.name ?? '',
    code: props.service?.code ?? '',
    description: props.service?.description ?? '',
    is_active: props.service?.is_active ?? true,
});

const restoreValues = (): void => {
    form.organization_id = defaultOrganizationId();
    form.name = props.service?.name ?? '';
    form.code = props.service?.code ?? '';
    form.description = props.service?.description ?? '';
    form.is_active = props.service?.is_active ?? true;
    form.clearErrors();
};

watch(open, (isOpen) => {
    if (isOpen) {
        restoreValues();
    }
});

const submit = (): void => {
    const organizationId = props.service?.organization_id ?? Number(form.organization_id);
    const organization = props.service?.organization ??
        props.organizations.find((item) => item.id === organizationId) ?? {
            id: organizationId,
            name: 'Organisasi saya',
        };

    if (props.service) {
        const pending: ServiceSummary = {
            ...props.service,
            name: form.name,
            code: form.code.toUpperCase(),
            description: form.description === '' ? null : form.description,
            is_active: form.is_active,
            pending: true,
        };

        form.optimistic<ServicesPageProps>((page) => ({
            services: page.services.map((item) => (item.id === props.service?.id ? pending : item)),
        })).submit(update(props.service.id), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });

        return;
    }

    const pending: ServiceSummary = {
        id: -Date.now(),
        organization_id: organizationId,
        name: form.name,
        code: form.code.toUpperCase(),
        description: form.description === '' ? null : form.description,
        is_active: form.is_active,
        organization,
        price_options: [],
        pending: true,
    };

    form.optimistic<ServicesPageProps>((page) => ({
        services: [pending, ...page.services],
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
        form-test="service-form"
        :title="isEditing ? 'Edit layanan' : 'Layanan baru'"
        description="Kelompokkan jenis layanan sebelum menambahkan tarif paket per cabang."
        :processing="form.processing"
        :submit-label="isEditing ? 'Simpan perubahan' : 'Tambah layanan'"
        :submit-disabled="isSuperadmin && !isEditing && form.organization_id === ''"
        @submit="submit"
    >
        <template #trigger>
            <Button
                type="button"
                :variant="isEditing ? 'ghost' : 'default'"
                :size="isEditing ? 'sm' : 'default'"
                :class="isEditing ? 'min-h-11 min-w-11' : undefined"
                :disabled="service?.pending"
                :aria-label="isEditing ? `Edit ${service?.name}` : undefined"
                data-test="open-service-dialog"
            >
                <Pencil v-if="isEditing" />
                <Plus v-else />
                <span :class="isEditing ? 'hidden xl:inline' : ''">
                    {{ isEditing ? 'Edit' : 'Tambah layanan' }}
                </span>
            </Button>
        </template>
        <template #icon><PackageOpen class="size-5" /></template>

        <div v-if="isSuperadmin" class="space-y-2">
            <Label for="service-organization">Organisasi</Label>
            <select
                id="service-organization"
                v-model="form.organization_id"
                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3 disabled:opacity-50"
                :disabled="isEditing"
                required
                :aria-invalid="Boolean(form.errors.organization_id)"
                aria-describedby="service-organization-error"
            >
                <option value="" disabled>Pilih organisasi</option>
                <option
                    v-for="organization in organizations"
                    :key="organization.id"
                    :value="organization.id.toString()"
                >
                    {{ organization.name }}
                </option>
            </select>
            <InputError id="service-organization-error" :message="form.errors.organization_id" />
        </div>

        <div class="grid gap-4 sm:grid-cols-[1fr_10rem]">
            <div class="space-y-2">
                <Label for="service-name">Nama layanan</Label>
                <Input
                    id="service-name"
                    v-model="form.name"
                    placeholder="Cuci Kering Lipat"
                    required
                    :aria-invalid="Boolean(form.errors.name)"
                    aria-describedby="service-name-error"
                />
                <InputError id="service-name-error" :message="form.errors.name" />
            </div>
            <div class="space-y-2">
                <Label for="service-code">Kode</Label>
                <Input
                    id="service-code"
                    v-model="form.code"
                    placeholder="CKL"
                    required
                    :aria-invalid="Boolean(form.errors.code)"
                    aria-describedby="service-code-error"
                />
                <InputError id="service-code-error" :message="form.errors.code" />
            </div>
        </div>

        <div class="space-y-2">
            <Label for="service-description">Deskripsi</Label>
            <textarea
                id="service-description"
                v-model="form.description"
                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-3"
                placeholder="Contoh: layanan reguler selesai dalam 2 hari."
                :aria-invalid="Boolean(form.errors.description)"
                aria-describedby="service-description-error"
            />
            <InputError id="service-description-error" :message="form.errors.description" />
        </div>

        <label class="border-line flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2">
            <Checkbox v-model="form.is_active" />
            <span class="text-sm font-medium">Layanan aktif dan dapat dipilih pelanggan</span>
        </label>
    </CmsFormDialog>
</template>
