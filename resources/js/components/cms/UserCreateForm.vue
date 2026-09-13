<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, UserRoundPlus } from '@lucide/vue';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/cms/users';
import type { BranchSummary, OrganizationOption, UserSummary, UsersPageProps } from '@/types';

const props = defineProps<{
    branches: Pick<BranchSummary, 'id' | 'organization_id' | 'name'>[];
    organizations: OrganizationOption[];
    currentOrganizationId: number | null;
    isSuperadmin: boolean;
}>();

const form = useForm(store(), {
    organization_id:
        props.currentOrganizationId?.toString() ?? props.organizations[0]?.id.toString() ?? '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'branch_admin',
    branch_ids: [] as number[],
});

const selectedOrganizationId = computed(
    () => props.currentOrganizationId ?? Number(form.organization_id),
);
const availableBranches = computed(() =>
    props.branches.filter((branch) => branch.organization_id === selectedOrganizationId.value),
);
const roleOptions = computed(() =>
    props.isSuperadmin
        ? [
              { value: 'owner', label: 'Owner' },
              { value: 'branch_admin', label: 'Admin cabang' },
              { value: 'customer', label: 'Pelanggan' },
          ]
        : [
              { value: 'branch_admin', label: 'Admin cabang' },
              { value: 'customer', label: 'Pelanggan' },
          ],
);

watch(selectedOrganizationId, () => {
    form.branch_ids = [];
});
watch(
    () => form.role,
    (role) => {
        if (role !== 'branch_admin') {
            form.branch_ids = [];
        }
    },
);

const toggleBranch = (branchId: number): void => {
    form.branch_ids = form.branch_ids.includes(branchId)
        ? form.branch_ids.filter((id) => id !== branchId)
        : [...form.branch_ids, branchId];
};

const resetForm = (): void => {
    form.name = '';
    form.email = '';
    form.password = '';
    form.password_confirmation = '';
    form.branch_ids = [];
    form.defaults();
};

const submit = (): void => {
    const organizationId = selectedOrganizationId.value;

    if (organizationId <= 0) {
        form.setError('organization_id', 'Pilih organisasi terlebih dahulu.');
        return;
    }

    if (form.role === 'branch_admin' && form.branch_ids.length === 0) {
        form.setError('branch_ids', 'Pilih minimal satu cabang untuk admin cabang.');
        return;
    }

    const organization = props.organizations.find((item) => item.id === organizationId);
    const pending: UserSummary = {
        id: -Date.now(),
        organization_id: organizationId,
        name: form.name,
        email: form.email,
        roles: [{ id: -1, name: form.role }],
        branches: availableBranches.value.filter((branch) => form.branch_ids.includes(branch.id)),
        organization,
        pending: true,
    };

    form.optimistic<UsersPageProps>((page) => ({
        users: [pending, ...page.users],
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
                <UserRoundPlus class="size-5" />
            </span>
            <CardTitle class="font-display">Pengguna baru</CardTitle>
            <CardDescription>
                Tetapkan role dan akses cabang sejak akun dibuat.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <form class="space-y-4" data-test="user-form" @submit.prevent="submit">
                <div v-if="isSuperadmin" class="space-y-2">
                    <Label for="user-organization">Organisasi</Label>
                    <select
                        id="user-organization"
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
                    <Label for="user-name">Nama lengkap</Label>
                    <Input id="user-name" v-model="form.name" autocomplete="name" placeholder="Nama pengguna" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="user-email">Email</Label>
                    <Input id="user-email" v-model="form.email" type="email" autocomplete="email" placeholder="nama@example.com" required />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="space-y-2">
                    <Label for="user-role">Role</Label>
                    <select
                        id="user-role"
                        v-model="form.role"
                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                        required
                    >
                        <option v-for="role in roleOptions" :key="role.value" :value="role.value">
                            {{ role.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.role" />
                </div>

                <fieldset v-if="form.role === 'branch_admin'" class="space-y-2">
                    <legend class="text-sm font-medium">Akses cabang</legend>
                    <div
                        v-if="availableBranches.length > 0"
                        class="border-line divide-line divide-y rounded-lg border"
                    >
                        <label
                            v-for="branch in availableBranches"
                            :key="branch.id"
                            class="flex min-h-11 cursor-pointer items-center gap-3 px-3 py-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="border-input text-brand-700 focus:ring-brand-600 size-4 rounded"
                                :checked="form.branch_ids.includes(branch.id)"
                                @change="toggleBranch(branch.id)"
                            />
                            <span>{{ branch.name }}</span>
                        </label>
                    </div>
                    <p v-else class="text-ink-500 text-sm">
                        Organisasi ini belum memiliki cabang.
                    </p>
                    <InputError :message="form.errors.branch_ids" />
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="user-password">Password</Label>
                        <Input id="user-password" v-model="form.password" type="password" autocomplete="new-password" required />
                        <InputError :message="form.errors.password" />
                    </div>
                    <div class="space-y-2">
                        <Label for="user-password-confirmation">Ulangi password</Label>
                        <Input id="user-password-confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
                    </div>
                </div>

                <Button class="w-full" type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    <Plus v-else />
                    {{ form.processing ? 'Menyimpan...' : 'Tambah pengguna' }}
                </Button>
            </form>
        </CardContent>
    </Card>
</template>
