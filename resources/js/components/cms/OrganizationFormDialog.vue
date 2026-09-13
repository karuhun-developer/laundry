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
    slug: props.organization?.slug ?? '',
    is_active: props.organization?.is_active ?? true,
});

const slugify = (value: string): string =>
    value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

const restoreValues = (): void => {
    form.name = props.organization?.name ?? '';
    form.slug = props.organization?.slug ?? '';
    form.is_active = props.organization?.is_active ?? true;
    form.clearErrors();
};

watch(open, (isOpen) => {
    if (isOpen) {
        restoreValues();
    }
});

const submit = (): void => {
    const normalizedSlug = form.slug === '' ? slugify(form.name) : slugify(form.slug);

    if (props.organization) {
        const pending = {
            ...props.organization,
            name: form.name,
            slug: normalizedSlug,
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
        slug: normalizedSlug,
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
                    <Label for="organization-slug">Slug</Label>
                    <Input
                        id="organization-slug"
                        v-model="form.slug"
                        autocomplete="off"
                        placeholder="Otomatis dari nama"
                        :aria-invalid="Boolean(form.errors.slug)"
                        aria-describedby="organization-slug-hint organization-slug-error"
                    />
                    <p id="organization-slug-hint" class="text-ink-500 text-xs">
                        Digunakan sebagai identitas stabil tenant.
                    </p>
                    <InputError id="organization-slug-error" :message="form.errors.slug" />
                </div>

                <label class="border-line flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2">
                    <Checkbox v-model="form.is_active" />
                    <span class="text-sm font-medium">Organisasi aktif</span>
                </label>

    </CmsFormDialog>
</template>
