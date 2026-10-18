<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building, Pencil, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BranchDeliveryFields from '@/components/cms/BranchDeliveryFields.vue';
import CmsFormDialog from '@/components/cms/CmsFormDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/cms/branches';
import type { BranchesPageProps, BranchSummary, CitySummary, OrganizationOption } from '@/types';

const props = defineProps<{
    branch?: BranchSummary;
    cities: CitySummary[];
    organizations: OrganizationOption[];
    currentOrganizationId: number | null;
    isSuperadmin: boolean;
}>();

const open = ref(false);
const isEditing = computed(() => props.branch !== undefined);
const defaultOrganizationId = (): string =>
    (
        props.branch?.organization_id ??
        props.currentOrganizationId ??
        props.organizations[0]?.id
    )?.toString() ?? '';
const form = useForm({
    organization_id: defaultOrganizationId(),
    city_id: props.branch?.city_id.toString() ?? '',
    name: props.branch?.name ?? '',
    code: props.branch?.code ?? '',
    phone: props.branch?.phone ?? '',
    address: props.branch?.address ?? '',
    latitude: props.branch?.latitude?.toString() ?? '',
    longitude: props.branch?.longitude?.toString() ?? '',
    free_delivery_radius_meters: props.branch?.free_delivery_radius_meters.toString() ?? '10000',
    delivery_fee_per_km_rupiah: props.branch?.delivery_fee_per_km_rupiah.toString() ?? '3000',
    qris_enabled: props.branch?.qris_enabled ?? false,
    qris_api_key: props.branch?.qris_api_key ?? '',
    qris_base_url: props.branch?.qris_base_url ?? '',
    is_active: props.branch?.is_active ?? true,
});

const selectedOrganizationId = computed(
    () =>
        props.branch?.organization_id ??
        props.currentOrganizationId ??
        Number(form.organization_id),
);
const availableCities = computed(() => props.cities.filter((city) => city.pending !== true));

const restoreValues = (): void => {
    form.organization_id = defaultOrganizationId();
    form.city_id = props.branch?.city_id.toString() ?? '';
    form.name = props.branch?.name ?? '';
    form.code = props.branch?.code ?? '';
    form.phone = props.branch?.phone ?? '';
    form.address = props.branch?.address ?? '';
    form.latitude = props.branch?.latitude?.toString() ?? '';
    form.longitude = props.branch?.longitude?.toString() ?? '';
    form.free_delivery_radius_meters =
        props.branch?.free_delivery_radius_meters.toString() ?? '10000';
    form.delivery_fee_per_km_rupiah = props.branch?.delivery_fee_per_km_rupiah.toString() ?? '3000';
    form.qris_enabled = props.branch?.qris_enabled ?? false;
    form.qris_api_key = props.branch?.qris_api_key ?? '';
    form.qris_base_url = props.branch?.qris_base_url ?? '';
    form.is_active = props.branch?.is_active ?? true;
    form.clearErrors();
};

watch(open, (isOpen) => {
    if (isOpen) {
        restoreValues();
    }
});

const submit = (): void => {
    const city = availableCities.value.find((item) => item.id.toString() === form.city_id);
    const organizationId = selectedOrganizationId.value;

    if (!city) {
        form.setError('city_id', 'Pilih kota yang tersedia.');
        return;
    }

    const organization = props.branch?.organization ??
        props.organizations.find((item) => item.id === organizationId) ?? {
            id: organizationId,
            name: 'Organisasi saya',
        };
    const pending: BranchSummary = {
        id: props.branch?.id ?? -Date.now(),
        organization_id: organizationId,
        city_id: city.id,
        name: form.name,
        code: form.code.toUpperCase(),
        phone: form.phone === '' ? null : form.phone,
        address: form.address,
        latitude: form.latitude === '' ? null : Number(form.latitude),
        longitude: form.longitude === '' ? null : Number(form.longitude),
        free_delivery_radius_meters: Number(form.free_delivery_radius_meters),
        delivery_fee_per_km_rupiah: Number(form.delivery_fee_per_km_rupiah),
        qris_enabled: form.qris_enabled,
        qris_api_key: form.qris_api_key === '' ? null : form.qris_api_key,
        qris_base_url: form.qris_base_url === '' ? null : form.qris_base_url,
        is_active: form.is_active,
        city,
        organization,
        pending: true,
    };

    if (props.branch) {
        form.optimistic<BranchesPageProps>((page) => ({
            branches: page.branches.map((item) => (item.id === props.branch?.id ? pending : item)),
        })).submit(update(props.branch.id), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });

        return;
    }

    form.optimistic<BranchesPageProps>((page) => ({
        branches: [pending, ...page.branches],
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
        form-test="branch-form"
        :title="isEditing ? 'Edit cabang' : 'Cabang baru'"
        description="Atur area layanan, tarif jarak, dan titik koordinat outlet."
        :processing="form.processing"
        :submit-label="isEditing ? 'Simpan perubahan' : 'Tambah cabang'"
        :submit-disabled="availableCities.length === 0"
        content-class="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-3xl"
        @submit="submit"
    >
        <template #trigger>
            <Button
                type="button"
                :variant="isEditing ? 'ghost' : 'default'"
                :size="isEditing ? 'sm' : 'default'"
                :class="isEditing ? 'min-h-11 min-w-11' : undefined"
                :disabled="branch?.pending"
                :aria-label="isEditing ? `Edit ${branch?.name}` : undefined"
                data-test="open-branch-dialog"
            >
                <Pencil v-if="isEditing" />
                <Plus v-else />
                <span :class="isEditing ? 'hidden xl:inline' : ''">
                    {{ isEditing ? 'Edit' : 'Tambah cabang' }}
                </span>
            </Button>
        </template>
        <template #icon><Building class="size-5" /></template>

                <div v-if="isSuperadmin" class="space-y-2">
                    <Label for="branch-organization">Organisasi</Label>
                    <select
                        id="branch-organization"
                        v-model="form.organization_id"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3 disabled:opacity-50"
                        :disabled="isEditing"
                        required
                        :aria-invalid="Boolean(form.errors.organization_id)"
                        aria-describedby="branch-organization-error"
                    >
                        <option value="" disabled>Pilih organisasi</option>
                        <option v-for="organization in organizations" :key="organization.id" :value="organization.id.toString()">
                            {{ organization.name }}
                        </option>
                    </select>
                    <InputError id="branch-organization-error" :message="form.errors.organization_id" />
                </div>

                <div class="space-y-2">
                    <Label for="branch-city">Kota</Label>
                    <p class="text-ink-500 text-xs">Semua kota dapat dipakai oleh setiap organisasi.</p>
                    <select
                        id="branch-city"
                        v-model="form.city_id"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3 disabled:opacity-50"
                        :disabled="availableCities.length === 0"
                        required
                        :aria-invalid="Boolean(form.errors.city_id)"
                        aria-describedby="branch-city-error"
                    >
                        <option value="" disabled>
                            {{ availableCities.length === 0 ? 'Tambahkan kota dahulu' : 'Pilih kota' }}
                        </option>
                        <option v-for="city in availableCities" :key="city.id" :value="city.id.toString()">
                            {{ city.name }}, {{ city.province }}
                        </option>
                    </select>
                    <InputError id="branch-city-error" :message="form.errors.city_id" />
                </div>

                <div class="grid gap-4 sm:grid-cols-[1fr_9rem]">
                    <div class="space-y-2">
                        <Label for="branch-name">Nama cabang</Label>
                        <Input id="branch-name" v-model="form.name" placeholder="Cabang Kemang" required :aria-invalid="Boolean(form.errors.name)" aria-describedby="branch-name-error" />
                        <InputError id="branch-name-error" :message="form.errors.name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="branch-code">Kode</Label>
                        <Input id="branch-code" v-model="form.code" placeholder="KMG01" required :aria-invalid="Boolean(form.errors.code)" aria-describedby="branch-code-error" />
                        <InputError id="branch-code-error" :message="form.errors.code" />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="branch-phone">Nomor telepon</Label>
                    <Input id="branch-phone" v-model="form.phone" inputmode="tel" placeholder="0812 3456 7890" :aria-invalid="Boolean(form.errors.phone)" aria-describedby="branch-phone-error" />
                    <InputError id="branch-phone-error" :message="form.errors.phone" />
                </div>

                <div class="space-y-2">
                    <Label for="branch-address">Alamat lengkap</Label>
                    <textarea
                        id="branch-address"
                        v-model="form.address"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-3"
                        placeholder="Jl. Kemang Raya No. 10"
                        required
                        :aria-invalid="Boolean(form.errors.address)"
                        aria-describedby="branch-address-error"
                    />
                    <InputError id="branch-address-error" :message="form.errors.address" />
                </div>

                <BranchDeliveryFields
                    v-model:latitude="form.latitude"
                    v-model:longitude="form.longitude"
                    v-model:radius="form.free_delivery_radius_meters"
                    v-model:fee="form.delivery_fee_per_km_rupiah"
                    :latitude-error="form.errors.latitude"
                    :longitude-error="form.errors.longitude"
                    :radius-error="form.errors.free_delivery_radius_meters"
                    :fee-error="form.errors.delivery_fee_per_km_rupiah"
                />

                <section class="border-line space-y-4 rounded-xl border p-4">
                    <div>
                        <h3 class="text-ink-950 text-sm font-semibold">QRIS Dinamis</h3>
                        <p class="text-ink-500 mt-1 text-xs leading-5">
                            Integrasi QRIS dinamis via qris.karuhundeveloper.com. Bila API Key cabang dikosongkan, cabang akan otomatis menggunakan pengaturan dari organisasi.
                        </p>
                    </div>

                    <label class="flex min-h-11 cursor-pointer items-center gap-3">
                        <Checkbox v-model="form.qris_enabled" data-test="branch-qris-enabled" />
                        <span class="text-sm font-medium">Cabang aktif pembayaran QRIS Dinamis</span>
                    </label>

                    <div v-if="form.qris_enabled" class="space-y-4 pt-1">
                        <div class="space-y-2">
                            <Label for="branch-qris-api-key">API Key QRIS Cabang (Opsional)</Label>
                            <Input
                                id="branch-qris-api-key"
                                v-model="form.qris_api_key"
                                placeholder="Kosongkan untuk menggunakan API Key organisasi"
                                :aria-invalid="Boolean(form.errors.qris_api_key)"
                                aria-describedby="branch-qris-api-key-error"
                            />
                            <p class="text-ink-500 text-xs">
                                Biarkan kosong jika ingin menggunakan kredensial QRIS milik organisasi.
                            </p>
                            <InputError
                                id="branch-qris-api-key-error"
                                :message="form.errors.qris_api_key"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="branch-qris-base-url">QRIS Base URL Cabang (Opsional)</Label>
                            <Input
                                id="branch-qris-base-url"
                                v-model="form.qris_base_url"
                                placeholder="https://qris.karuhundeveloper.com"
                                :aria-invalid="Boolean(form.errors.qris_base_url)"
                                aria-describedby="branch-qris-base-url-error"
                            />
                            <InputError
                                id="branch-qris-base-url-error"
                                :message="form.errors.qris_base_url"
                            />
                        </div>
                    </div>
                </section>

                <label class="border-line flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2">
                    <Checkbox v-model="form.is_active" />
                    <span class="text-sm font-medium">Cabang aktif menerima order</span>
                </label>

    </CmsFormDialog>
</template>
