<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Check, FolderTree, LoaderCircle, Lock, Pencil, Plus, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CmsDeleteDialog from '@/components/cms/CmsDeleteDialog.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store, update } from '@/routes/cms/cashflow/categories';
import type { CashflowCategoryOption, OrganizationOption } from '@/types';

const props = defineProps<{
    categories: CashflowCategoryOption[];
    organizations: OrganizationOption[];
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
const activeTab = ref<'all' | 'debit' | 'credit'>('all');

// Form for creating new category
const createForm = useForm({
    organization_id: defaultOrganizationId(),
    name: '',
    type: 'credit' as 'debit' | 'credit',
    group: 'rutin' as 'rutin' | 'non_rutin',
});

// Editing state
const editingCategoryId = ref<number | null>(null);
const editForm = useForm({
    name: '',
    group: 'rutin' as 'rutin' | 'non_rutin',
});

const startEditing = (category: CashflowCategoryOption): void => {
    editingCategoryId.value = category.id;
    editForm.name = category.name;
    editForm.group = category.group;
    editForm.clearErrors();
};

const cancelEditing = (): void => {
    editingCategoryId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const submitEdit = (category: CashflowCategoryOption): void => {
    editForm.submit(update(category.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingCategoryId.value = null;
        },
    });
};

const selectedOrgId = ref<string | number>(defaultOrganizationId());

watch(open, (isOpen) => {
    if (isOpen) {
        createForm.reset();
        createForm.clearErrors();
        createForm.organization_id = defaultOrganizationId();
        selectedOrgId.value = defaultOrganizationId();
        cancelEditing();
    }
});

const submitCreate = (): void => {
    createForm.organization_id = selectedOrgId.value;
    createForm.submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.name = '';
            createForm.clearErrors();
        },
    });
};

const visibleCategories = computed(() => {
    let list = props.categories;

    if (isSuperadmin.value && selectedOrgId.value) {
        list = list.filter(
            (cat) => cat.organization_id.toString() === selectedOrgId.value.toString(),
        );
    }

    if (activeTab.value !== 'all') {
        list = list.filter((cat) => cat.type === activeTab.value);
    }

    return list;
});

const isProtectedCategory = (cat: CashflowCategoryOption): boolean => {
    return cat.name === 'Pembayaran Pesanan' || Boolean(cat.is_system);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" class="gap-2">
                <FolderTree class="size-4" />
                Kelola Kategori
            </Button>
        </DialogTrigger>

        <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Kategori Cashflow</DialogTitle>
                <DialogDescription>
                    Kelola kategori pemasukan dan pengeluaran operasional laundry.
                </DialogDescription>
            </DialogHeader>

            <!-- Superadmin Organization Filter -->
            <div v-if="isSuperadmin" class="space-y-1.5 rounded-xl border border-line bg-canvas/50 p-3.5">
                <Label for="cat-org-select" class="text-xs font-medium text-ink-600">Pilih Organisasi</Label>
                <select
                    id="cat-org-select"
                    v-model="selectedOrgId"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-xs shadow-xs outline-none focus-visible:ring-2"
                >
                    <option
                        v-for="org in organizations"
                        :key="org.id"
                        :value="org.id"
                    >
                        {{ org.name }}
                    </option>
                </select>
            </div>

            <!-- Create Category Card -->
            <div class="rounded-2xl border border-line bg-surface p-4 shadow-xs">
                <h3 class="text-sm font-semibold text-ink-950 flex items-center gap-1.5 mb-3">
                    <Plus class="size-4 text-emerald-600" />
                    Tambah Kategori Baru
                </h3>
                <form class="grid gap-3 sm:grid-cols-4" @submit.prevent="submitCreate">
                    <!-- Category Name -->
                    <div class="sm:col-span-2 space-y-1">
                        <Label for="new-cat-name" class="text-xs">Nama Kategori</Label>
                        <Input
                            id="new-cat-name"
                            v-model="createForm.name"
                            placeholder="Contoh: Sabun Khusus, Bonus Staff"
                            class="h-9 text-xs"
                            :aria-invalid="Boolean(createForm.errors.name)"
                        />
                        <InputError :message="createForm.errors.name" />
                    </div>

                    <!-- Type -->
                    <div class="space-y-1">
                        <Label for="new-cat-type" class="text-xs">Tipe</Label>
                        <select
                            id="new-cat-type"
                            v-model="createForm.type"
                            class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-2.5 text-xs shadow-xs outline-none focus-visible:ring-2"
                        >
                            <option value="credit">Pengeluaran</option>
                            <option value="debit">Pemasukan</option>
                        </select>
                    </div>

                    <!-- Group -->
                    <div class="space-y-1">
                        <Label for="new-cat-group" class="text-xs">Sub-grup</Label>
                        <select
                            id="new-cat-group"
                            v-model="createForm.group"
                            class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-2.5 text-xs shadow-xs outline-none focus-visible:ring-2"
                        >
                            <option value="rutin">Rutin</option>
                            <option value="non_rutin">Non-Rutin</option>
                        </select>
                    </div>

                    <!-- Submit Button -->
                    <div class="sm:col-span-4 flex justify-end">
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="createForm.processing || !createForm.name.trim()"
                        >
                            <LoaderCircle v-if="createForm.processing" class="size-3.5 animate-spin" />
                            Simpan Kategori
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Categories List Section -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-ink-950">Daftar Kategori</h3>

                    <!-- Filter Tabs -->
                    <div class="flex gap-1 rounded-lg border border-line bg-canvas p-1 text-xs">
                        <button
                            type="button"
                            class="rounded-md px-2.5 py-1 font-medium transition"
                            :class="activeTab === 'all' ? 'bg-surface font-semibold text-ink-950 shadow-xs' : 'text-ink-500 hover:text-ink-800'"
                            @click="activeTab = 'all'"
                        >
                            Semua ({{ visibleCategories.length }})
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-2.5 py-1 font-medium transition"
                            :class="activeTab === 'debit' ? 'bg-surface font-semibold text-emerald-700 shadow-xs' : 'text-ink-500 hover:text-ink-800'"
                            @click="activeTab = 'debit'"
                        >
                            Pemasukan
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-2.5 py-1 font-medium transition"
                            :class="activeTab === 'credit' ? 'bg-surface font-semibold text-red-700 shadow-xs' : 'text-ink-500 hover:text-ink-800'"
                            @click="activeTab = 'credit'"
                        >
                            Pengeluaran
                        </button>
                    </div>
                </div>

                <!-- Categories Table / List -->
                <div class="divide-line divide-y rounded-2xl border border-line bg-surface overflow-hidden">
                    <div
                        v-for="cat in visibleCategories"
                        :key="cat.id"
                        class="flex items-center justify-between p-3.5 text-xs transition hover:bg-neutral-50/50 dark:hover:bg-neutral-800/20"
                    >
                        <!-- Normal Mode -->
                        <template v-if="editingCategoryId !== cat.id">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="font-medium text-ink-950 truncate">{{ cat.name }}</span>

                                <Badge
                                    :variant="cat.type === 'debit' ? 'default' : 'secondary'"
                                    :class="cat.type === 'debit' ? 'bg-emerald-600 text-white hover:bg-emerald-600 text-[10px]' : 'text-[10px]'"
                                >
                                    {{ cat.type === 'debit' ? 'Pemasukan' : 'Pengeluaran' }}
                                </Badge>

                                <span class="text-ink-500 text-[11px] capitalize">
                                    {{ cat.group === 'non_rutin' ? 'Non-Rutin' : 'Rutin' }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <!-- Locked / System Category Badge -->
                                <template v-if="isProtectedCategory(cat)">
                                    <span
                                        v-if="cat.name === 'Pembayaran Pesanan'"
                                        class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-800 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800"
                                        title="Kategori dilindungi oleh sistem untuk mencatat pembayaran pesanan pelanggan."
                                    >
                                        <Lock class="size-3" />
                                        Sistem (Terkunci)
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-md bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-600 border border-neutral-200 dark:bg-neutral-800 dark:text-neutral-400"
                                    >
                                        Bawaan Sistem
                                    </span>
                                </template>

                                <!-- Custom Category Actions -->
                                <template v-else>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 w-7 p-0 text-ink-600 hover:text-ink-950"
                                        title="Ubah kategori"
                                        @click="startEditing(cat)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>

                                    <CmsDeleteDialog
                                        title="Hapus Kategori Cashflow"
                                        :description="`Kategori '${cat.name}' akan dihapus secara permanen.`"
                                        :confirmation="cat.name"
                                        confirmation-label="Ketik nama kategori untuk konfirmasi"
                                        :route="destroy(cat.id)"
                                    />
                                </template>
                            </div>
                        </template>

                        <!-- Editing Mode -->
                        <template v-else>
                            <form class="flex items-center gap-2 w-full" @submit.prevent="submitEdit(cat)">
                                <Input
                                    v-model="editForm.name"
                                    class="h-8 text-xs flex-1"
                                    placeholder="Nama kategori"
                                />

                                <select
                                    v-model="editForm.group"
                                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-8 rounded-md border bg-transparent px-2 text-xs shadow-xs outline-none focus-visible:ring-2"
                                >
                                    <option value="rutin">Rutin</option>
                                    <option value="non_rutin">Non-Rutin</option>
                                </select>

                                <Button
                                    type="submit"
                                    size="sm"
                                    class="h-8 px-2.5"
                                    :disabled="editForm.processing || !editForm.name.trim()"
                                >
                                    <Check class="size-3.5" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    class="h-8 px-2.5"
                                    @click="cancelEditing"
                                >
                                    <X class="size-3.5" />
                                </Button>
                            </form>
                        </template>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
