<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { BadgeDollarSign, Pencil, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CmsFormDialog from '@/components/cms/CmsFormDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/cms/price-options';
import { store } from '@/routes/cms/services/price-options';
import type {
    CatalogBranchSummary,
    PriceOptionSummary,
    PriceTypeValue,
    ServiceSummary,
    ServicesPageProps,
} from '@/types';

const props = defineProps<{
    service: ServiceSummary;
    branches: CatalogBranchSummary[];
    priceOption?: PriceOptionSummary;
}>();

const open = ref(false);
const isEditing = computed(() => props.priceOption !== undefined);
const availableBranches = computed(() =>
    props.branches.filter(
        (branch) =>
            branch.organization_id === props.service.organization_id &&
            (branch.is_active || branch.id === props.priceOption?.branch_id),
    ),
);
const defaultBranchId = (): number | null => props.priceOption?.branch_id ?? null;
const form = useForm({
    branch_id: defaultBranchId(),
    type: (props.priceOption?.type ?? 'per_kilogram') as PriceTypeValue,
    name: props.priceOption?.name ?? '',
    package_weight_grams: props.priceOption?.package_weight_grams?.toString() ?? '',
    base_price_rupiah: props.priceOption?.base_price_rupiah?.toString() ?? '',
    price_per_kilogram_rupiah: props.priceOption?.price_per_kilogram_rupiah?.toString() ?? '',
    minimum_chargeable_grams: props.priceOption?.minimum_chargeable_grams?.toString() ?? '',
    is_active: props.priceOption?.is_active ?? true,
});

const gramsAsKilograms = (grams: string): string =>
    grams === '' ? '' : (Number(grams) / 1000).toString();
const kilogramsAsGrams = (kilograms: string): string =>
    kilograms === '' ? '' : Math.round(Number(kilograms) * 1000).toString();
const optionalNumber = (value: string): number | null => (value === '' ? null : Number(value));
const packageWeightKilograms = computed<string>({
    get: () => gramsAsKilograms(form.package_weight_grams),
    set: (value) => {
        form.package_weight_grams = kilogramsAsGrams(value);
    },
});
const minimumChargeableKilograms = computed<string>({
    get: () => gramsAsKilograms(form.minimum_chargeable_grams),
    set: (value) => {
        form.minimum_chargeable_grams = kilogramsAsGrams(value);
    },
});

const restoreValues = (): void => {
    form.branch_id = defaultBranchId();
    form.type = props.priceOption?.type ?? 'per_kilogram';
    form.name = props.priceOption?.name ?? '';
    form.package_weight_grams = props.priceOption?.package_weight_grams?.toString() ?? '';
    form.base_price_rupiah = props.priceOption?.base_price_rupiah?.toString() ?? '';
    form.price_per_kilogram_rupiah = props.priceOption?.price_per_kilogram_rupiah?.toString() ?? '';
    form.minimum_chargeable_grams = props.priceOption?.minimum_chargeable_grams?.toString() ?? '';
    form.is_active = props.priceOption?.is_active ?? true;
    form.clearErrors();
};

watch(open, (isOpen) => {
    if (isOpen) {
        restoreValues();
    }
});

const clearUnusedPricingFields = (): void => {
    if (form.type === 'fixed_package') {
        form.minimum_chargeable_grams = '';

        return;
    }

    form.package_weight_grams = '';
    form.base_price_rupiah = '';
};

const submit = (): void => {
    const branch = availableBranches.value.find((item) => item.id === form.branch_id) ?? null;

    if (form.branch_id !== null && !branch) {
        form.setError('branch_id', 'Pilih cabang yang tersedia.');
        return;
    }

    const pending: PriceOptionSummary = {
        id: props.priceOption?.id ?? -Date.now(),
        service_id: props.service.id,
        branch_id: form.branch_id,
        type: form.type,
        name: form.name,
        package_weight_grams: optionalNumber(form.package_weight_grams),
        base_price_rupiah: optionalNumber(form.base_price_rupiah),
        price_per_kilogram_rupiah: optionalNumber(form.price_per_kilogram_rupiah),
        minimum_chargeable_grams: optionalNumber(form.minimum_chargeable_grams),
        is_active: form.is_active,
        branch,
        pending: true,
    };

    if (props.priceOption) {
        form.optimistic<ServicesPageProps>((page) => ({
            services: page.services.map((service) =>
                service.id === props.service.id
                    ? {
                          ...service,
                          price_options: service.price_options.map((priceOption) =>
                              priceOption.id === props.priceOption?.id ? pending : priceOption,
                          ),
                      }
                    : service,
            ),
        })).submit(update(props.priceOption.id), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });

        return;
    }

    form.optimistic<ServicesPageProps>((page) => ({
        services: page.services.map((service) =>
            service.id === props.service.id
                ? { ...service, price_options: [...service.price_options, pending] }
                : service,
        ),
    })).submit(store(props.service.id), {
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
        form-test="price-option-form"
        :title="isEditing ? 'Edit paket' : 'Paket baru'"
        :description="`Atur tarif ${service.name} untuk semua cabang atau satu cabang tertentu.`"
        :processing="form.processing"
        :submit-label="isEditing ? 'Simpan perubahan' : 'Tambah paket'"
        content-class="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl"
        @submit="submit"
    >
        <template #trigger>
            <Button
                type="button"
                :variant="isEditing ? 'ghost' : 'secondary'"
                :size="isEditing ? 'sm' : 'default'"
                :class="isEditing ? 'min-h-11 min-w-11' : undefined"
                :disabled="service.pending || priceOption?.pending"
                :aria-label="isEditing ? `Edit ${priceOption?.name}` : undefined"
                data-test="open-price-option-dialog"
            >
                <Pencil v-if="isEditing" />
                <Plus v-else />
                <span :class="isEditing ? 'hidden xl:inline' : ''">
                    {{ isEditing ? 'Edit' : 'Tambah paket' }}
                </span>
            </Button>
        </template>
        <template #icon><BadgeDollarSign class="size-5" /></template>

        <div class="space-y-2">
            <Label for="price-option-branch">Cabang</Label>
            <select
                id="price-option-branch"
                v-model="form.branch_id"
                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3 disabled:opacity-50"
                :aria-invalid="Boolean(form.errors.branch_id)"
                aria-describedby="price-option-branch-error"
            >
                <option :value="null">Semua cabang</option>
                <option
                    v-for="branch in availableBranches"
                    :key="branch.id"
                    :value="branch.id"
                >
                    {{ branch.name }} · {{ branch.code }}
                </option>
            </select>
            <p class="text-ink-500 text-xs">
                Paket semua cabang otomatis tersedia di cabang baru organisasi ini.
            </p>
            <InputError id="price-option-branch-error" :message="form.errors.branch_id" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="price-option-name">Nama paket</Label>
                <Input
                    id="price-option-name"
                    v-model="form.name"
                    placeholder="Kiloan reguler"
                    required
                    :aria-invalid="Boolean(form.errors.name)"
                    aria-describedby="price-option-name-error"
                />
                <InputError id="price-option-name-error" :message="form.errors.name" />
            </div>
            <div class="space-y-2">
                <Label for="price-option-type">Model harga</Label>
                <select
                    id="price-option-type"
                    v-model="form.type"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    required
                    :aria-invalid="Boolean(form.errors.type)"
                    aria-describedby="price-option-type-error"
                    @change="clearUnusedPricingFields"
                >
                    <option value="per_kilogram">Harga per kilogram</option>
                    <option value="fixed_package">Paket berat tetap</option>
                </select>
                <InputError id="price-option-type-error" :message="form.errors.type" />
            </div>
        </div>

        <div v-if="form.type === 'fixed_package'" class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="package-weight">Berat paket (kg)</Label>
                <Input
                    id="package-weight"
                    v-model="packageWeightKilograms"
                    type="number"
                    min="0.001"
                    step="0.001"
                    required
                    :aria-invalid="Boolean(form.errors.package_weight_grams)"
                    aria-describedby="package-weight-error"
                />
                <InputError id="package-weight-error" :message="form.errors.package_weight_grams" />
            </div>
            <div class="space-y-2">
                <Label for="package-base-price">Harga paket (Rp)</Label>
                <Input
                    id="package-base-price"
                    v-model="form.base_price_rupiah"
                    type="number"
                    min="0"
                    step="1"
                    required
                    :aria-invalid="Boolean(form.errors.base_price_rupiah)"
                    aria-describedby="package-base-price-error"
                />
                <InputError id="package-base-price-error" :message="form.errors.base_price_rupiah" />
            </div>
            <div class="space-y-2 sm:col-span-2">
                <Label for="package-excess-price">Harga kelebihan per kg (Rp)</Label>
                <Input
                    id="package-excess-price"
                    v-model="form.price_per_kilogram_rupiah"
                    type="number"
                    min="0"
                    step="1"
                    placeholder="Opsional jika tidak menerima kelebihan berat"
                    :aria-invalid="Boolean(form.errors.price_per_kilogram_rupiah)"
                    aria-describedby="package-excess-price-error"
                />
                <InputError
                    id="package-excess-price-error"
                    :message="form.errors.price_per_kilogram_rupiah"
                />
            </div>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="kilogram-price">Harga per kg (Rp)</Label>
                <Input
                    id="kilogram-price"
                    v-model="form.price_per_kilogram_rupiah"
                    type="number"
                    min="0"
                    step="1"
                    required
                    :aria-invalid="Boolean(form.errors.price_per_kilogram_rupiah)"
                    aria-describedby="kilogram-price-error"
                />
                <InputError id="kilogram-price-error" :message="form.errors.price_per_kilogram_rupiah" />
            </div>
            <div class="space-y-2">
                <Label for="minimum-weight">Minimum tagihan (kg)</Label>
                <Input
                    id="minimum-weight"
                    v-model="minimumChargeableKilograms"
                    type="number"
                    min="0.001"
                    step="0.001"
                    placeholder="Opsional"
                    :aria-invalid="Boolean(form.errors.minimum_chargeable_grams)"
                    aria-describedby="minimum-weight-error"
                />
                <InputError id="minimum-weight-error" :message="form.errors.minimum_chargeable_grams" />
            </div>
        </div>

        <label class="border-line flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2">
            <Checkbox v-model="form.is_active" />
            <span class="text-sm font-medium">Paket aktif dan dapat dipilih</span>
        </label>
    </CmsFormDialog>
</template>
