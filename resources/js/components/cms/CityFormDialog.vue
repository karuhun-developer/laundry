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
import type { BranchesPageProps, CitySummary } from '@/types';

const props = defineProps<{
    city?: CitySummary;
}>();

const open = ref(false);
const isEditing = computed(() => props.city !== undefined);
const form = useForm({
    name: props.city?.name ?? '',
    province: props.city?.province ?? '',
});

const restoreValues = (): void => {
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
        name: form.name,
        province: form.province,
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
        description="Master kota ini dapat dipakai oleh semua organisasi."
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
