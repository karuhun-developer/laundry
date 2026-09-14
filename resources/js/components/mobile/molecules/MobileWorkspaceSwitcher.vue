<script setup lang="ts">
import { ChevronDown, Store } from '@lucide/vue';
import { computed } from 'vue';
import { formatDistance, formatRupiah } from '@/lib/mobileOrders';
import type { MobileBranch, MobileContext, NearestMobileBranch } from '@/types/mobile';

type BranchOption = MobileBranch | NearestMobileBranch;

const props = defineProps<{
    context: MobileContext;
    nearestBranches: readonly NearestMobileBranch[];
    selectedBranchId: number | null;
}>();

const emit = defineEmits<{
    branchChange: [branchId: number];
}>();

const branchOptions = computed<readonly BranchOption[]>(() => {
    if (props.context.role !== 'customer' || props.nearestBranches.length === 0) {
        return props.context.branches;
    }

    const rankedBranchIds = new Set(props.nearestBranches.map((branch) => branch.id));

    return [
        ...props.nearestBranches,
        ...props.context.branches.filter((branch) => !rankedBranchIds.has(branch.id)),
    ];
});
const selectedNearestBranch = computed(() =>
    props.nearestBranches.find((branch) => branch.id === props.selectedBranchId),
);

const isNearestBranch = (branch: BranchOption): branch is NearestMobileBranch =>
    'distance_meters' in branch.delivery;

const branchOptionLabel = (branch: BranchOption): string => {
    const location = `${branch.name} · ${branch.city.name}`;

    if (!isNearestBranch(branch)) {
        return location;
    }

    const coverage = branch.delivery.within_free_radius
        ? 'Gratis jemput & antar'
        : `Tambahan ${formatRupiah(branch.delivery.fee_rupiah)}`;

    return `${location} · ${formatDistance(branch.delivery.distance_meters)} · ${coverage}`;
};

const onBranchChange = (event: Event): void => {
    if (event.target instanceof HTMLSelectElement && event.target.value !== '') {
        emit('branchChange', Number(event.target.value));
    }
};
</script>

<template>
    <div class="mb-5 space-y-2">
        <label class="relative block">
            <span class="sr-only">Pilih cabang</span>
            <Store
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-brand-700"
                aria-hidden="true"
            />
            <select
                :value="selectedBranchId ?? ''"
                class="min-h-11 w-full appearance-none rounded-xl border border-line bg-surface py-2 pr-10 pl-10 text-sm font-semibold text-ink-950 shadow-low outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                data-test="branch-select"
                @change="onBranchChange"
            >
                <option v-if="branchOptions.length === 0" value="">Belum ada cabang</option>
                <option v-for="branch in branchOptions" :key="branch.id" :value="branch.id">
                    {{ branchOptionLabel(branch) }}
                </option>
            </select>
            <ChevronDown
                class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-ink-500"
                aria-hidden="true"
            />
        </label>

        <p
            v-if="context.role === 'customer' && selectedNearestBranch"
            class="px-1 text-xs"
            :class="selectedNearestBranch.delivery.within_free_radius ? 'text-mint-500' : 'text-amber-600'"
            data-test="selected-branch-coverage"
        >
            <template v-if="selectedNearestBranch.delivery.within_free_radius">
                Gratis jemput & antar hingga
                {{ formatDistance(selectedNearestBranch.delivery.free_radius_meters) }}
            </template>
            <template v-else>
                Di luar radius gratis
                {{ formatDistance(selectedNearestBranch.delivery.free_radius_meters) }} · estimasi
                tambahan {{ formatRupiah(selectedNearestBranch.delivery.fee_rupiah) }}
            </template>
        </p>
        <p
            v-else-if="context.role === 'customer'"
            class="px-1 text-xs text-ink-500"
        >
            Tentukan titik jemput di menu Cabang untuk melihat jarak dan ongkir.
        </p>
    </div>
</template>
