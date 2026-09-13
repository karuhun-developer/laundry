<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Pencil, Plus, UserRoundPlus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CmsFormDialog from '@/components/cms/CmsFormDialog.vue';
import UserAccessFields from '@/components/cms/UserAccessFields.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/cms/users';
import type {
    BranchSummary,
    ManagedUserRole,
    OrganizationOption,
    UserSummary,
    UsersPageProps,
} from '@/types';

type RoleOption = {
    value: ManagedUserRole;
    label: string;
};

const props = defineProps<{
    user?: UserSummary;
    branches: Pick<BranchSummary, 'id' | 'organization_id' | 'name'>[];
    organizations: OrganizationOption[];
    currentOrganizationId: number | null;
    isSuperadmin: boolean;
}>();

const open = ref(false);
const isEditing = computed(() => props.user !== undefined);
const defaultOrganizationId = (): string =>
    (
        props.user?.organization_id ??
        props.currentOrganizationId ??
        props.organizations[0]?.id
    )?.toString() ?? '';
const defaultRole = (): RoleOption['value'] => {
    const role = props.user?.roles[0]?.name;

    return role === 'owner' || role === 'branch_admin' || role === 'customer'
        ? role
        : 'branch_admin';
};
const form = useForm({
    organization_id: defaultOrganizationId(),
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    role: defaultRole(),
    branch_ids: props.user?.branches.map((branch) => branch.id) ?? [],
});

const selectedOrganizationId = computed<number | null>(() => {
    if (props.user) {
        return props.user.organization_id;
    }

    if (props.currentOrganizationId !== null) {
        return props.currentOrganizationId;
    }

    return form.organization_id === '' ? null : Number(form.organization_id);
});
const availableBranches = computed(() =>
    props.branches.filter((branch) => branch.organization_id === selectedOrganizationId.value),
);
const roleOptions = computed<RoleOption[]>(() => {
    if (props.user?.organization_id === null) {
        return [{ value: 'customer', label: 'Pelanggan' }];
    }

    if (props.isSuperadmin) {
        return [
            { value: 'owner', label: 'Owner' },
            { value: 'branch_admin', label: 'Admin cabang' },
            { value: 'customer', label: 'Pelanggan' },
        ];
    }

    return [
        { value: 'branch_admin', label: 'Admin cabang' },
        { value: 'customer', label: 'Pelanggan' },
    ];
});

const restoreValues = (): void => {
    form.organization_id = defaultOrganizationId();
    form.name = props.user?.name ?? '';
    form.email = props.user?.email ?? '';
    form.password = '';
    form.password_confirmation = '';
    form.role = defaultRole();
    form.branch_ids = props.user?.branches.map((branch) => branch.id) ?? [];
    form.clearErrors();
};

watch(open, (isOpen) => {
    if (isOpen) {
        restoreValues();
    }
});
watch(selectedOrganizationId, () => {
    if (!isEditing.value) {
        form.branch_ids = [];
    }
});
watch(
    () => form.role,
    (role) => {
        if (role !== 'branch_admin') {
            form.branch_ids = [];
        }
    },
);

const submit = (): void => {
    const organizationId = selectedOrganizationId.value;

    if (!isEditing.value && organizationId === null) {
        form.setError('organization_id', 'Pilih organisasi terlebih dahulu.');
        return;
    }

    if (form.role === 'branch_admin' && form.branch_ids.length === 0) {
        form.setError('branch_ids', 'Pilih minimal satu cabang untuk admin cabang.');
        return;
    }

    const organization =
        organizationId === null
            ? undefined
            : (props.user?.organization ??
              props.organizations.find((item) => item.id === organizationId));
    const pending: UserSummary = {
        id: props.user?.id ?? -Date.now(),
        organization_id: organizationId,
        name: form.name,
        email: form.email,
        roles: [{ id: props.user?.roles[0]?.id ?? -1, name: form.role }],
        branches: availableBranches.value.filter((branch) => form.branch_ids.includes(branch.id)),
        organization,
        pending: true,
    };

    if (props.user) {
        form.optimistic<UsersPageProps>((page) => ({
            users: page.users.map((user) => (user.id === props.user?.id ? pending : user)),
        })).submit(update(props.user.id), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });

        return;
    }

    form.optimistic<UsersPageProps>((page) => ({
        users: [pending, ...page.users],
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
        form-test="user-form"
        :title="isEditing ? 'Edit pengguna' : 'Pengguna baru'"
        description="Tetapkan role dan akses cabang untuk akun ini."
        :processing="form.processing"
        :submit-label="isEditing ? 'Simpan perubahan' : 'Tambah pengguna'"
        content-class="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl"
        @submit="submit"
    >
        <template #trigger>
            <Button
                type="button"
                :variant="isEditing ? 'ghost' : 'default'"
                :size="isEditing ? 'sm' : 'default'"
                :class="isEditing ? 'min-h-11 min-w-11' : undefined"
                :disabled="user?.pending"
                :aria-label="isEditing ? 'Edit ' + user?.name : undefined"
                data-test="open-user-dialog"
            >
                <Pencil v-if="isEditing" />
                <Plus v-else />
                <span :class="isEditing ? 'hidden xl:inline' : ''">
                    {{ isEditing ? 'Edit' : 'Tambah pengguna' }}
                </span>
            </Button>
        </template>
        <template #icon><UserRoundPlus class="size-5" /></template>

                <div v-if="isSuperadmin" class="space-y-2">
                    <Label for="user-organization">Organisasi</Label>
                    <div
                        v-if="isEditing && user?.organization_id === null"
                        id="user-organization"
                        class="border-line bg-canvas text-ink-500 flex h-11 items-center rounded-md border px-3 text-sm"
                    >
                        Tanpa organisasi
                    </div>
                    <select
                        v-else
                        id="user-organization"
                        v-model="form.organization_id"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3 disabled:opacity-50"
                        :disabled="isEditing"
                        required
                        :aria-invalid="Boolean(form.errors.organization_id)"
                        aria-describedby="user-organization-error"
                    >
                        <option value="" disabled>Pilih organisasi</option>
                        <option v-for="organization in organizations" :key="organization.id" :value="organization.id.toString()">
                            {{ organization.name }}
                        </option>
                    </select>
                    <InputError id="user-organization-error" :message="form.errors.organization_id" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="user-name">Nama lengkap</Label>
                        <Input id="user-name" v-model="form.name" autocomplete="name" placeholder="Nama pengguna" required :aria-invalid="Boolean(form.errors.name)" aria-describedby="user-name-error" />
                        <InputError id="user-name-error" :message="form.errors.name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="user-email">Email</Label>
                        <Input id="user-email" v-model="form.email" type="email" autocomplete="email" placeholder="nama@example.com" required :aria-invalid="Boolean(form.errors.email)" aria-describedby="user-email-error" />
                        <InputError id="user-email-error" :message="form.errors.email" />
                    </div>
                </div>

                <UserAccessFields
                    v-model:role="form.role"
                    v-model:branch-ids="form.branch_ids"
                    v-model:password="form.password"
                    v-model:password-confirmation="form.password_confirmation"
                    :role-options="roleOptions"
                    :available-branches="availableBranches"
                    :is-editing="isEditing"
                    :role-error="form.errors.role"
                    :branch-ids-error="form.errors.branch_ids"
                    :password-error="form.errors.password"
                />

    </CmsFormDialog>
</template>
