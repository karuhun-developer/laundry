<script setup lang="ts">
import { MapPin, Navigation, Store } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LocationPicker from '@/components/LocationPicker.vue';
import type { Coordinates } from '@/composables/useGeolocation';
import { formatDistance, formatRupiah } from '@/lib/mobileOrders';
import type { MobileBranch, NearestMobileBranch } from '@/types/mobile';

type BranchResult = MobileBranch | NearestMobileBranch;

const props = defineProps<{
    branches: readonly MobileBranch[];
    nearestBranches: readonly NearestMobileBranch[];
    selectedBranchId: number | null;
    selectedCoordinates?: Coordinates | null;
}>();

const emit = defineEmits<{
    locate: [latitude: number, longitude: number];
    select: [branchId: number];
}>();

const selectedLatitude = ref(props.selectedCoordinates?.latitude.toString() ?? '');
const selectedLongitude = ref(props.selectedCoordinates?.longitude.toString() ?? '');
const locationError = ref<string | null>(null);
const branchResults = computed<readonly BranchResult[]>(() =>
    props.nearestBranches.length > 0 ? props.nearestBranches : props.branches,
);
const mapBranchLocations = computed(() =>
    branchResults.value.flatMap((branch) => {
        const { latitude, longitude } = branch.coordinates;

        if (latitude === null || longitude === null) {
            return [];
        }

        return [
            {
                id: branch.id,
                name: branch.name,
                latitude,
                longitude,
                freeRadiusMeters: branch.delivery.free_radius_meters,
                selected: branch.id === props.selectedBranchId,
            },
        ];
    }),
);

const branchDistance = (branch: BranchResult): number | null =>
    'distance_meters' in branch.delivery ? branch.delivery.distance_meters : null;

const branchFee = (branch: BranchResult): number | null =>
    'fee_rupiah' in branch.delivery ? branch.delivery.fee_rupiah : null;

const branchCoverageLabel = (branch: BranchResult): string | null => {
    const fee = branchFee(branch);

    if (fee === null) {
        return null;
    }

    return fee === 0
        ? 'Gratis jemput & antar'
        : `Di luar radius gratis · tambahan ${formatRupiah(fee)}`;
};

watch(
    () => props.selectedCoordinates,
    (coordinates) => {
        selectedLatitude.value = coordinates?.latitude.toString() ?? '';
        selectedLongitude.value = coordinates?.longitude.toString() ?? '';
    },
);

const locateCoordinates = (latitude: number, longitude: number): void => {
    selectedLatitude.value = latitude.toFixed(6);
    selectedLongitude.value = longitude.toFixed(6);
    locationError.value = null;
    emit('locate', latitude, longitude);
};

const locateSelectedPoint = (): void => {
    const latitude = Number(selectedLatitude.value);
    const longitude = Number(selectedLongitude.value);

    if (
        !Number.isFinite(latitude) ||
        latitude < -90 ||
        latitude > 90 ||
        !Number.isFinite(longitude) ||
        longitude < -180 ||
        longitude > 180
    ) {
        locationError.value = 'Latitude harus -90 sampai 90 dan longitude -180 sampai 180.';
        return;
    }

    locationError.value = null;
    emit('locate', latitude, longitude);
};
</script>

<template>
    <section aria-labelledby="branch-explorer-title">
        <div class="rounded-3xl bg-brand-700 p-6 text-white shadow-raised">
            <span class="flex size-11 items-center justify-center rounded-2xl bg-white/12">
                <Navigation class="size-5" aria-hidden="true" />
            </span>
            <h2 id="branch-explorer-title" class="mt-5 font-display text-2xl font-semibold">
                Cari cabang terdekat
            </h2>
            <p class="mt-2 max-w-xl text-sm leading-6 text-white/75">
                Lokasi hanya dipakai untuk menghitung jarak, gratis antar-jemput, dan biaya kelebihan kilometer.
            </p>
        </div>

        <form
            class="mt-5 space-y-4 rounded-3xl border border-line bg-surface p-5 shadow-low"
            data-test="map-location"
            @submit.prevent="locateSelectedPoint"
        >
            <LocationPicker
                v-model:latitude="selectedLatitude"
                v-model:longitude="selectedLongitude"
                title="Titik antar-jemput"
                description="Gunakan lokasi perangkat, klik peta, atau geser pin sampai titiknya sesuai."
                current-location-label="Gunakan lokasi saya"
                selected-label="Titik antar-jemput"
                map-label="Peta pemilih titik antar-jemput"
                input-id-prefix="delivery"
                :branch-locations="mapBranchLocations"
                @located="locateCoordinates"
                @branch-select="emit('select', $event)"
            />
            <p v-if="locationError" class="text-sm text-red-600" role="status">
                {{ locationError }}
            </p>
            <button
                type="submit"
                class="min-h-11 w-full rounded-xl border border-brand-600 px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-100 motion-safe:active:scale-[0.98]"
                data-test="submit-map-location"
            >
                Hitung dari titik ini
            </button>
        </form>

        <div class="mt-5 space-y-3">
            <article
                v-for="branch in branchResults"
                :key="branch.id"
                class="rounded-2xl border bg-surface p-5 shadow-low transition"
                :class="selectedBranchId === branch.id ? 'border-brand-600 ring-2 ring-brand-100' : 'border-line'"
            >
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface-blue text-brand-700">
                        <Store class="size-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-display font-semibold text-ink-950">{{ branch.name }}</h3>
                                <p class="mt-0.5 text-xs text-ink-500">{{ branch.city.name }}, {{ branch.city.province }}</p>
                            </div>
                            <span v-if="branchDistance(branch) !== null" class="shrink-0 text-xs font-semibold text-brand-700">
                                {{ formatDistance(branchDistance(branch) ?? 0) }}
                            </span>
                        </div>
                        <p class="mt-3 flex items-start gap-2 text-sm leading-5 text-ink-700">
                            <MapPin class="mt-0.5 size-4 shrink-0 text-ink-500" aria-hidden="true" />
                            {{ branch.address }}
                        </p>
                        <p class="mt-2 text-xs text-ink-500">
                            Radius gratis {{ formatDistance(branch.delivery.free_radius_meters) }} · tarif kelebihan
                            {{ formatRupiah(branch.delivery.fee_per_km_rupiah) }}/km
                        </p>
                        <p
                            v-if="branchCoverageLabel(branch)"
                            class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="
                                (branchFee(branch) ?? 0) === 0
                                    ? 'bg-mint-500/10 text-mint-500'
                                    : 'bg-amber-500/10 text-amber-600'
                            "
                            :data-test="`branch-coverage-${branch.id}`"
                        >
                            {{ branchCoverageLabel(branch) }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="mt-4 min-h-11 w-full rounded-xl border border-brand-600 px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-100 motion-safe:active:scale-[0.98]"
                    @click="emit('select', branch.id)"
                >
                    {{ selectedBranchId === branch.id ? 'Cabang terpilih' : 'Pilih cabang ini' }}
                </button>
            </article>
        </div>
    </section>
</template>
