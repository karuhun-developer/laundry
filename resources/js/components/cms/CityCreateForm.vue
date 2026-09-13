<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, MapPinned, Plus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/cms/cities';
import type { BranchesPageProps, CitySummary, OrganizationOption } from '@/types';

const props = defineProps<{
    organizations: OrganizationOption[];
    currentOrganizationId: number | null;
    isSuperadmin: boolean;
}>();

const form = useForm(store(), {
    organization_id:
        props.currentOrganizationId?.toString() ?? props.organizations[0]?.id.toString() ?? '',
    name: '',
    province: '',
});

const resetForm = (): void => {
    form.name = '';
    form.province = '';
    form.defaults();
};

const submit = (): void => {
    const organizationId = props.currentOrganizationId ?? Number(form.organization_id);

    if (organizationId <= 0) {
        form.setError('organization_id', 'Pilih organisasi terlebih dahulu.');
        return;
    }

    const pending: CitySummary = {
        id: -Date.now(),
        organization_id: organizationId,
        name: form.name,
        province: form.province,
        pending: true,
    };

    form.optimistic<BranchesPageProps>((page) => ({
        cities: [pending, ...page.cities],
    })).submit({
        preserveScroll: true,
        onSuccess: resetForm,
    });
};
</script>

<template>
    <Card class="border-line shadow-sm">
        <CardHeader>
            <span
                class="bg-surface-blue text-brand-700 mb-2 grid size-10 place-items-center rounded-lg"
            >
                <MapPinned class="size-5" />
            </span>
            <CardTitle class="font-display">Tambah kota</CardTitle>
            <CardDescription>Kelompokkan cabang berdasarkan area operasional.</CardDescription>
        </CardHeader>
        <CardContent>
            <form class="space-y-4" data-test="city-form" @submit.prevent="submit">
                <div v-if="isSuperadmin" class="space-y-2">
                    <Label for="city-organization">Organisasi</Label>
                    <select
                        id="city-organization"
                        v-model="form.organization_id"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                        required
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
                    <InputError :message="form.errors.organization_id" />
                </div>

                <div class="space-y-2">
                    <Label for="city-name">Kota / kabupaten</Label>
                    <Input id="city-name" v-model="form.name" placeholder="Jakarta Selatan" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="city-province">Provinsi</Label>
                    <Input id="city-province" v-model="form.province" placeholder="DKI Jakarta" required />
                    <InputError :message="form.errors.province" />
                </div>

                <Button class="w-full" type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    <Plus v-else />
                    {{ form.processing ? 'Menyimpan...' : 'Tambah kota' }}
                </Button>
            </form>
        </CardContent>
    </Card>
</template>
