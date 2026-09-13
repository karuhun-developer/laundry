<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building, LoaderCircle, Plus } from '@lucide/vue';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/cms/branches';
import type { BranchesPageProps, BranchSummary, CitySummary, OrganizationOption } from '@/types';

const props = defineProps<{
    cities: CitySummary[];
    organizations: OrganizationOption[];
    currentOrganizationId: number | null;
    isSuperadmin: boolean;
}>();

const form = useForm(store(), {
    organization_id:
        props.currentOrganizationId?.toString() ?? props.organizations[0]?.id.toString() ?? '',
    city_id: '',
    name: '',
    code: '',
    phone: '',
    address: '',
    latitude: '',
    longitude: '',
    free_delivery_radius_meters: '10000',
    delivery_fee_per_km_rupiah: '3000',
    is_active: true,
});

const selectedOrganizationId = computed(
    () => props.currentOrganizationId ?? Number(form.organization_id),
);

const availableCities = computed(() =>
    props.cities.filter(
        (city) => city.organization_id === selectedOrganizationId.value && city.pending !== true,
    ),
);

watch(selectedOrganizationId, () => {
    if (!availableCities.value.some((city) => city.id.toString() === form.city_id)) {
        form.city_id = '';
    }
});

const resetForm = (): void => {
    form.city_id = '';
    form.name = '';
    form.code = '';
    form.phone = '';
    form.address = '';
    form.latitude = '';
    form.longitude = '';
    form.defaults();
};

const submit = (): void => {
    const city = availableCities.value.find((item) => item.id.toString() === form.city_id);
    const organizationId = selectedOrganizationId.value;

    if (!city) {
        form.setError('city_id', 'Pilih kota yang tersedia.');
        return;
    }

    const organization = props.organizations.find((item) => item.id === organizationId) ?? {
        id: organizationId,
        name: 'Organisasi saya',
    };
    const latitude = form.latitude === '' ? null : Number(form.latitude);
    const longitude = form.longitude === '' ? null : Number(form.longitude);
    const pending: BranchSummary = {
        id: -Date.now(),
        organization_id: organizationId,
        city_id: city.id,
        name: form.name,
        code: form.code.toUpperCase(),
        phone: form.phone === '' ? null : form.phone,
        address: form.address,
        latitude,
        longitude,
        free_delivery_radius_meters: Number(form.free_delivery_radius_meters),
        delivery_fee_per_km_rupiah: Number(form.delivery_fee_per_km_rupiah),
        is_active: form.is_active,
        city,
        organization,
        pending: true,
    };

    form.optimistic<BranchesPageProps>((page) => ({
        branches: [pending, ...page.branches],
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
                <Building class="size-5" />
            </span>
            <CardTitle class="font-display">Cabang baru</CardTitle>
            <CardDescription>
                Atur area layanan, tarif jarak, dan titik koordinat cabang.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <form class="space-y-4" data-test="branch-form" @submit.prevent="submit">
                <div v-if="isSuperadmin" class="space-y-2">
                    <Label for="branch-organization">Organisasi</Label>
                    <select
                        id="branch-organization"
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
                    <Label for="branch-city">Kota</Label>
                    <select
                        id="branch-city"
                        v-model="form.city_id"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3 disabled:opacity-50"
                        :disabled="availableCities.length === 0"
                        required
                    >
                        <option value="" disabled>
                            {{ availableCities.length === 0 ? 'Tambahkan kota dahulu' : 'Pilih kota' }}
                        </option>
                        <option
                            v-for="city in availableCities"
                            :key="city.id"
                            :value="city.id.toString()"
                        >
                            {{ city.name }}, {{ city.province }}
                        </option>
                    </select>
                    <InputError :message="form.errors.city_id" />
                </div>

                <div class="grid gap-4 sm:grid-cols-[1fr_9rem]">
                    <div class="space-y-2">
                        <Label for="branch-name">Nama cabang</Label>
                        <Input id="branch-name" v-model="form.name" placeholder="Cabang Kemang" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="branch-code">Kode</Label>
                        <Input id="branch-code" v-model="form.code" placeholder="KMG01" required />
                        <InputError :message="form.errors.code" />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="branch-phone">Nomor telepon</Label>
                    <Input id="branch-phone" v-model="form.phone" inputmode="tel" placeholder="0812 3456 7890" />
                    <InputError :message="form.errors.phone" />
                </div>

                <div class="space-y-2">
                    <Label for="branch-address">Alamat lengkap</Label>
                    <textarea
                        id="branch-address"
                        v-model="form.address"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-3"
                        placeholder="Jl. Kemang Raya No. 10"
                        required
                    />
                    <InputError :message="form.errors.address" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="branch-latitude">Latitude</Label>
                        <Input id="branch-latitude" v-model="form.latitude" inputmode="decimal" placeholder="-6.2607" />
                        <InputError :message="form.errors.latitude" />
                    </div>
                    <div class="space-y-2">
                        <Label for="branch-longitude">Longitude</Label>
                        <Input id="branch-longitude" v-model="form.longitude" inputmode="decimal" placeholder="106.8136" />
                        <InputError :message="form.errors.longitude" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="branch-radius">Gratis antar-jemput (meter)</Label>
                        <Input id="branch-radius" v-model="form.free_delivery_radius_meters" inputmode="numeric" required />
                        <InputError :message="form.errors.free_delivery_radius_meters" />
                    </div>
                    <div class="space-y-2">
                        <Label for="branch-fee">Tarif lebih / km</Label>
                        <Input id="branch-fee" v-model="form.delivery_fee_per_km_rupiah" inputmode="numeric" required />
                        <InputError :message="form.errors.delivery_fee_per_km_rupiah" />
                    </div>
                </div>

                <label
                    class="border-line flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2"
                >
                    <Checkbox v-model="form.is_active" />
                    <span class="text-sm font-medium">Cabang aktif menerima order</span>
                </label>

                <Button
                    class="w-full"
                    type="submit"
                    :disabled="form.processing || availableCities.length === 0"
                >
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    <Plus v-else />
                    {{ form.processing ? 'Menyimpan...' : 'Tambah cabang' }}
                </Button>
            </form>
        </CardContent>
    </Card>
</template>
