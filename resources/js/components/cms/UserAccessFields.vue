<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BranchSummary, ManagedUserRole } from '@/types';

defineProps<{
    roleOptions: { value: ManagedUserRole; label: string }[];
    availableBranches: Pick<BranchSummary, 'id' | 'organization_id' | 'name'>[];
    isEditing: boolean;
    roleError?: string;
    branchIdsError?: string;
    passwordError?: string;
}>();

const role = defineModel<ManagedUserRole>('role', { required: true });
const branchIds = defineModel<number[]>('branchIds', { required: true });
const password = defineModel<string>('password', { required: true });
const passwordConfirmation = defineModel<string>('passwordConfirmation', { required: true });

const toggleBranch = (branchId: number): void => {
    branchIds.value = branchIds.value.includes(branchId)
        ? branchIds.value.filter((id) => id !== branchId)
        : [...branchIds.value, branchId];
};
</script>

<template>
    <div class="space-y-2">
        <Label for="user-role">Role</Label>
        <select
            id="user-role"
            v-model="role"
            class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
            required
            :aria-invalid="Boolean(roleError)"
            aria-describedby="user-role-error"
        >
            <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
        <InputError id="user-role-error" :message="roleError" />
        <p v-if="role === 'owner'" class="text-ink-500 text-xs">
            Owner dapat mengakses seluruh cabang dalam organisasi.
        </p>
        <p v-else-if="role === 'customer'" class="text-ink-500 text-xs">
            Pelanggan tidak dibatasi pada cabang tertentu.
        </p>
    </div>

    <fieldset v-if="role === 'branch_admin'" class="space-y-2">
        <legend class="text-sm font-medium">Akses cabang</legend>
        <div v-if="availableBranches.length > 0" class="border-line divide-line divide-y rounded-lg border">
            <label v-for="branch in availableBranches" :key="branch.id" class="flex min-h-11 cursor-pointer items-center gap-3 px-3 py-2 text-sm">
                <input
                    type="checkbox"
                    class="border-input text-brand-700 focus:ring-brand-600 size-4 rounded"
                    :checked="branchIds.includes(branch.id)"
                    @change="toggleBranch(branch.id)"
                />
                <span>{{ branch.name }}</span>
            </label>
        </div>
        <p v-else class="text-ink-500 text-sm">Organisasi ini belum memiliki cabang.</p>
        <InputError :message="branchIdsError" />
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <Label for="user-password">Password{{ isEditing ? ' baru (opsional)' : '' }}</Label>
            <Input id="user-password" v-model="password" type="password" autocomplete="new-password" :required="!isEditing" :aria-invalid="Boolean(passwordError)" aria-describedby="user-password-error" />
            <InputError id="user-password-error" :message="passwordError" />
        </div>
        <div class="space-y-2">
            <Label for="user-password-confirmation">Ulangi password</Label>
            <Input id="user-password-confirmation" v-model="passwordConfirmation" type="password" autocomplete="new-password" :required="!isEditing" />
        </div>
    </div>
</template>
