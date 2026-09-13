<script setup lang="ts">
import { ChevronDown, Store } from '@lucide/vue';
import type { MobileContext } from '@/types/mobile';

defineProps<{
    context: MobileContext;
    selectedBranchId: number | null;
}>();

const emit = defineEmits<{
    organizationChange: [organizationSlug: string];
    branchChange: [branchId: number];
}>();

const onOrganizationChange = (event: Event): void => {
    if (event.target instanceof HTMLSelectElement) {
        emit('organizationChange', event.target.value);
    }
};

const onBranchChange = (event: Event): void => {
    if (event.target instanceof HTMLSelectElement && event.target.value !== '') {
        emit('branchChange', Number(event.target.value));
    }
};
</script>

<template>
    <div class="mb-5 grid gap-3 sm:grid-cols-2">
        <label
            v-if="context.role === 'customer' && context.organizations.length > 1"
            class="relative block"
        >
            <span class="sr-only">Pilih organisasi laundry</span>
            <select
                :value="context.organization.slug"
                class="min-h-11 w-full appearance-none rounded-xl border border-line bg-surface py-2 pr-10 pl-3 text-sm font-semibold text-ink-950 shadow-low outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                @change="onOrganizationChange"
            >
                <option
                    v-for="organization in context.organizations"
                    :key="organization.id"
                    :value="organization.slug"
                >
                    {{ organization.name }}
                </option>
            </select>
            <ChevronDown
                class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-ink-500"
                aria-hidden="true"
            />
        </label>

        <label class="relative block" :class="{ 'sm:col-start-2': context.role === 'customer' }">
            <span class="sr-only">Pilih cabang</span>
            <Store
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-brand-700"
                aria-hidden="true"
            />
            <select
                :value="selectedBranchId ?? ''"
                class="min-h-11 w-full appearance-none rounded-xl border border-line bg-surface py-2 pr-10 pl-10 text-sm font-semibold text-ink-950 shadow-low outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                @change="onBranchChange"
            >
                <option v-if="context.branches.length === 0" value="">Belum ada cabang</option>
                <option v-for="branch in context.branches" :key="branch.id" :value="branch.id">
                    {{ branch.name }} · {{ branch.city.name }}
                </option>
            </select>
            <ChevronDown
                class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-ink-500"
                aria-hidden="true"
            />
        </label>
    </div>
</template>
