<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { MapPinned, Pencil, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CmsFormDialog from '@/components/cms/CmsFormDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/cms/cities';
import type { BranchesPageProps, CitySummary, OrganizationOption } from '@/types';

const props = defineProps<{
    city?: CitySummary;
    organizations: OrganizationOption[];
    currentOrganizationId: number | null;
    isSuperadmin: boolean;
}>();

const open = ref(false);
const isEditing = computed(() => props.city !== undefined);
const defaultOrganizationId = (): string =>
    (
        props.city?.organization_id ??
        props.currentOrganizationId ??
        props.organizations[0]?.id
    )?.toString() ?? '';
const form = useForm({
    organization_id: defaultOrganizationId(),
    name: props.city?.name ?? '',
    province: props.city?.province ?? '',
});

const restoreValues = (): void => {
    form.organization_id = defaultOrganizationId();
    form.name = props.city?.name ?? '';
    form.province = props.city?.province ?? '';
    form.clearErrors();
};

watch(open, (isOpen) => {
    if (isOpen) {
        restoreValues();
    }
});

const submit = (): void => {
    const organizationId =
        props.city?.organization_id ?? props.currentOrganizationId ?? Number(form.organization_id);

    if (organizationId <= 0) {
        form.setError('organization_id', 'Pilih organisasi terlebih dahulu.');
        return;
    }

    if (props.city) {
        const pending = {
            ...props.city,
            name: form.name,
            province: form.province,
            pending: true,
        };

        form.optimistic<BranchesPageProps>((page) => ({
            cities: page.cities.map((item) => (item.id === props.city?.id ? pending : item)),
        })).submit(update(props.city.id), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });

        return;
    }

    const pending: CitySummary = {
        id: -Date.now(),
        organization_id: organizationId,
        name: form.name,
        province: form.province,
        organization: props.organizations.find((item) => item.id === organizationId) ?? {
            id: organizationId,
            name: 'Organisasi saya',
        },
        pending: true,
    };

    form.optimistic<BranchesPageProps>((page) => ({
        cities: [pending, ...page.cities],
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
        form-test="city-form"
        :title="isEditing ? 'Edit kota' : 'Tambah kota'"
        description="Kelompokkan cabang berdasarkan area operasional."
        :processing="form.processing"
        :submit-label="isEditing ? 'Simpan perubahan' : 'Tambah kota'"
        @submit="submit"
    >
        <template #trigger>
            <Button
                type="button"
                :variant="isEditing ? 'ghost' : 'outline'"
                :size="isEditing ? 'icon-sm' : 'default'"
                :class="isEditing ? 'min-h-11 min-w-11' : undefined"
                :disabled="city?.pending"
                :aria-label="isEditing ? `Edit kota ${city?.name}` : undefined"
                data-test="open-city-dialog"
            >
                <Pencil v-if="isEditing" />
                <Plus v-else />
                <span v-if="!isEditing">Tambah kota</span>
            </Button>
        </template>
        <template #icon><MapPinned class="size-5" /></template>

                <div v-if="isSuperadmin" class="space-y-2">
                    <Label for="city-organization">Organisasi</Label>
                    <select
                        id="city-organization"
                        v-model="form.organization_id"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3 disabled:opacity-50"
                        :disabled="isEditing"
                        required
                        :aria-invalid="Boolean(form.errors.organization_id)"
                        aria-describedby="city-organization-error"
                    >
                        <option value="" disabled>Pilih organisasi</option>
                        <option v-for="organization in organizations" :key="organization.id" :value="organization.id.toString()">
                            {{ organization.name }}
                        </option>
                    </select>
                    <InputError id="city-organization-error" :message="form.errors.organization_id" />
                </div>

                <div class="space-y-2">
                    <Label for="city-name">Kota / kabupaten</Label>
                    <Input
                        id="city-name"
                        v-model="form.name"
                        placeholder="Jakarta Selatan"
                        required
                        :aria-invalid="Boolean(form.errors.name)"
                        aria-describedby="city-name-error"
                    />
                    <InputError id="city-name-error" :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="city-province">Provinsi</Label>
                    <Input
                        id="city-province"
                        v-model="form.province"
                        placeholder="DKI Jakarta"
                        required
                        :aria-invalid="Boolean(form.errors.province)"
                        aria-describedby="city-province-error"
                    />
                    <InputError id="city-province-error" :message="form.errors.province" />
                </div>

    </CmsFormDialog>
</template>
