<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CmsFormDialog from '@/components/cms/CmsFormDialog.vue';
import InputError from '@/components/InputError.vue';
import { RupiahInput } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/cms/cashflow';
import type { CashflowBranchOption, CashflowCategoryOption, OrganizationOption } from '@/types';

const props = defineProps<{
    branches: CashflowBranchOption[];
    organizations: OrganizationOption[];
    categories: CashflowCategoryOption[];
}>();

const page = usePage();
const isSuperadmin = computed(() => page.props.auth.user.roles.includes('superadmin'));

const defaultOrganizationId = (): number | string => {
    if (isSuperadmin.value) {
        return props.organizations[0]?.id ?? '';
    }
    return page.props.auth.user.organization_id ?? '';
};

const open = ref(false);

const form = useForm({
    organization_id: defaultOrganizationId(),
    branch_id: '' as number | string,
    cashflow_category_id: '' as number | string,
    type: 'credit' as 'debit' | 'credit',
    amount_rupiah: null as number | null,
    description: '',
    entry_date: new Date().toISOString().split('T')[0],
});

const availableBranches = computed(() => {
    if (!isSuperadmin.value || !form.organization_id) {
        return props.branches;
    }
    return props.branches.filter(
        (branch) => branch.organization_id.toString() === form.organization_id.toString(),
    );
});

const availableCategories = computed(() => {
    if (!isSuperadmin.value || !form.organization_id) {
        return props.categories;
    }
    return props.categories.filter(
        (cat) => cat.organization_id.toString() === form.organization_id.toString(),
    );
});

const filteredCategories = computed(() =>
    availableCategories.value.filter((cat) => cat.type === form.type),
);

watch(
    () => form.organization_id,
    () => {
        form.branch_id = '';
        form.cashflow_category_id = '';
    },
);

watch(
    () => form.type,
    () => {
        form.cashflow_category_id = '';
    },
);

const restoreValues = (): void => {
    form.reset();
    form.clearErrors();
    form.organization_id = defaultOrganizationId();
    form.entry_date = new Date().toISOString().split('T')[0];
};

watch(open, (isOpen) => {
    if (isOpen) {
        restoreValues();
    }
});

const submit = (): void => {
    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <CmsFormDialog
        v-model:open="open"
        title="Tambah entri cashflow"
        description="Catat pemasukan atau pengeluaran baru ke dalam cashflow."
        form-test="cashflow-entry-form"
        :processing="form.processing"
        submit-label="Simpan"
        @submit="submit"
    >
        <template #trigger>
            <slot name="trigger">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
                >
                    <Plus class="size-4" />
                    Buat Baru
                </button>
            </slot>
        </template>
        <template #icon>
            <Plus class="size-5" />
        </template>

        <div class="space-y-4">
            <!-- Organization (Superadmin only) -->
            <div v-if="isSuperadmin" class="space-y-2">
                <Label for="cf-organization">Organisasi</Label>
                <select
                    id="cf-organization"
                    v-model="form.organization_id"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    :aria-invalid="Boolean(form.errors.organization_id)"
                >
                    <option value="" disabled>Pilih organisasi</option>
                    <option
                        v-for="org in organizations"
                        :key="org.id"
                        :value="org.id"
                    >
                        {{ org.name }}
                    </option>
                </select>
                <InputError :message="form.errors.organization_id" />
            </div>

            <!-- Branch -->
            <div class="space-y-2">
                <Label for="cf-branch">Cabang</Label>
                <select
                    id="cf-branch"
                    v-model="form.branch_id"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    :aria-invalid="Boolean(form.errors.branch_id)"
                >
                    <option value="" disabled>Pilih cabang</option>
                    <option
                        v-for="branch in availableBranches"
                        :key="branch.id"
                        :value="branch.id"
                    >
                        {{ branch.name }}
                    </option>
                </select>
                <InputError :message="form.errors.branch_id" />
            </div>

            <!-- Type -->
            <div class="space-y-2">
                <Label for="cf-type">Tipe</Label>
                <select
                    id="cf-type"
                    v-model="form.type"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    :aria-invalid="Boolean(form.errors.type)"
                >
                    <option value="credit">Pengeluaran (Credit)</option>
                    <option value="debit">Pemasukan (Debit)</option>
                </select>
                <InputError :message="form.errors.type" />
            </div>

            <!-- Category -->
            <div class="space-y-2">
                <Label for="cf-category">Kategori</Label>
                <select
                    id="cf-category"
                    v-model="form.cashflow_category_id"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    :aria-invalid="Boolean(form.errors.cashflow_category_id)"
                >
                    <option value="" disabled>Pilih kategori</option>
                    <option
                        v-for="cat in filteredCategories"
                        :key="cat.id"
                        :value="cat.id"
                    >
                        {{ cat.name }}
                    </option>
                </select>
                <InputError :message="form.errors.cashflow_category_id" />
            </div>

            <!-- Amount -->
            <div class="space-y-2">
                <Label for="cf-amount">Jumlah (Rp)</Label>
                <RupiahInput
                    id="cf-amount"
                    v-model="form.amount_rupiah"
                    placeholder="Contoh: 500.000"
                    :aria-invalid="Boolean(form.errors.amount_rupiah)"
                />
                <InputError :message="form.errors.amount_rupiah" />
            </div>

            <!-- Description -->
            <div class="space-y-2">
                <Label for="cf-description">Deskripsi</Label>
                <input
                    id="cf-description"
                    v-model="form.description"
                    type="text"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    placeholder="Keterangan singkat..."
                    :aria-invalid="Boolean(form.errors.description)"
                />
                <InputError :message="form.errors.description" />
            </div>

            <!-- Date -->
            <div class="space-y-2">
                <Label for="cf-date">Tanggal</Label>
                <input
                    id="cf-date"
                    v-model="form.entry_date"
                    type="date"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    :aria-invalid="Boolean(form.errors.entry_date)"
                />
                <InputError :message="form.errors.entry_date" />
            </div>
        </div>
    </CmsFormDialog>
</template>
