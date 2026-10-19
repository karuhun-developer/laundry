<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CmsFormDialog from '@/components/cms/CmsFormDialog.vue';
import InputError from '@/components/InputError.vue';
import { RupiahInput } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/cms/cashflow';
import type {
    CashflowBranchOption,
    CashflowCategoryOption,
    CashflowEntrySummary,
    OrganizationOption,
} from '@/types';

const props = defineProps<{
    branches: CashflowBranchOption[];
    organizations: OrganizationOption[];
    categories: CashflowCategoryOption[];
    entry?: CashflowEntrySummary;
}>();

const page = usePage();
const isSuperadmin = computed(() => page.props.auth.user.roles.includes('superadmin'));
const isEditing = computed(() => props.entry !== undefined);
const isIncomeEdit = computed(() => props.entry?.type === 'debit');

const defaultOrganizationId = (): number | string => {
    if (props.entry) {
        return props.entry.organization_id;
    }
    if (isSuperadmin.value) {
        return props.organizations[0]?.id ?? '';
    }
    return page.props.auth.user.organization_id ?? '';
};

const open = ref(false);
const form = useForm({
    organization_id: defaultOrganizationId(),
    branch_id: props.entry?.branch_id ?? ('' as number | string),
    cashflow_category_id: props.entry?.cashflow_category_id ?? ('' as number | string),
    type: props.entry?.type ?? ('credit' as 'debit' | 'credit'),
    amount_rupiah: props.entry?.amount_rupiah ?? (null as number | null),
    description: props.entry?.description ?? '',
    entry_date: props.entry?.entry_date.slice(0, 10) ?? new Date().toISOString().split('T')[0],
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
        (category) => category.organization_id.toString() === form.organization_id.toString(),
    );
});

const filteredCategories = computed(() =>
    availableCategories.value.filter((category) => category.type === form.type),
);

watch(
    () => form.organization_id,
    () => {
        if (
            form.branch_id &&
            !availableBranches.value.some(
                (branch) => branch.id.toString() === form.branch_id.toString(),
            )
        ) {
            form.branch_id = '';
        }
        if (
            form.cashflow_category_id &&
            !availableCategories.value.some(
                (category) => category.id.toString() === form.cashflow_category_id.toString(),
            )
        ) {
            form.cashflow_category_id = '';
        }
    },
);

watch(
    () => form.type,
    () => {
        if (
            form.cashflow_category_id &&
            !filteredCategories.value.some(
                (category) => category.id.toString() === form.cashflow_category_id.toString(),
            )
        ) {
            form.cashflow_category_id = '';
        }
    },
);

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

const submit = (): void => {
    form.transform((data) =>
        isIncomeEdit.value
            ? { amount_rupiah: data.amount_rupiah, entry_date: data.entry_date }
            : data,
    );

    form.submit(props.entry ? update(props.entry.id) : store(), {
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
        :title="isEditing ? 'Edit entri cashflow' : 'Tambah entri cashflow'"
        :description="
            isIncomeEdit
                ? 'Perbarui jumlah dan tanggal pemasukan manual.'
                : isEditing
                  ? 'Perbarui data pengeluaran manual.'
                  : 'Catat pemasukan atau pengeluaran baru ke dalam cashflow.'
        "
        form-test="cashflow-entry-form"
        :processing="form.processing"
        :submit-label="isEditing ? 'Simpan Perubahan' : 'Simpan'"
        @submit="submit"
    >
        <template #trigger>
            <slot name="trigger">
                <button
                    v-if="isEditing"
                    type="button"
                    class="text-ink-700 hover:bg-canvas inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-lg px-3 text-sm font-medium"
                    :aria-label="'Edit entri ' + entry?.description"
                    data-test="edit-cashflow-entry"
                >
                    <Pencil class="size-4" />
                    <span class="hidden xl:inline">Edit</span>
                </button>
                <button
                    v-else
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
                >
                    <Plus class="size-4" />
                    Buat Baru
                </button>
            </slot>
        </template>
        <template #icon>
            <Pencil v-if="isEditing" class="size-5" />
            <Plus v-else class="size-5" />
        </template>

        <div class="space-y-4">
            <template v-if="!isIncomeEdit">
                <div v-if="isSuperadmin" class="space-y-2">
                    <Label for="cf-organization">Organisasi</Label>
                    <select
                        id="cf-organization"
                        v-model="form.organization_id"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                        :aria-invalid="Boolean(form.errors.organization_id)"
                    >
                        <option value="" disabled>Pilih organisasi</option>
                        <option v-for="organization in organizations" :key="organization.id" :value="organization.id">
                            {{ organization.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.organization_id" />
                </div>

                <div class="space-y-2">
                    <Label for="cf-branch">Cabang</Label>
                    <select
                        id="cf-branch"
                        v-model="form.branch_id"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                        :aria-invalid="Boolean(form.errors.branch_id)"
                    >
                        <option value="" disabled>Pilih cabang</option>
                        <option v-for="branch in availableBranches" :key="branch.id" :value="branch.id">
                            {{ branch.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.branch_id" />
                </div>

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

                <div class="space-y-2">
                    <Label for="cf-category">Kategori</Label>
                    <select
                        id="cf-category"
                        v-model="form.cashflow_category_id"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                        :aria-invalid="Boolean(form.errors.cashflow_category_id)"
                    >
                        <option value="" disabled>Pilih kategori</option>
                        <option v-for="category in filteredCategories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.cashflow_category_id" />
                </div>
            </template>

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

            <div v-if="!isIncomeEdit" class="space-y-2">
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
