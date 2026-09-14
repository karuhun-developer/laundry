<script setup lang="ts">
import { MapPin, Navigation, Store } from '@lucide/vue';
import { ref, watch } from 'vue';
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

const branchDistance = (branch: BranchResult): number | null =>
    'distance_meters' in branch.delivery ? branch.delivery.distance_meters : null;

const branchFee = (branch: BranchResult): number | null =>
    'fee_rupiah' in branch.delivery ? branch.delivery.fee_rupiah : null;

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
                @located="locateCoordinates"
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
                v-for="branch in nearestBranches.length ? nearestBranches : branches"
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
                            Gratis antar-jemput hingga {{ formatDistance(branch.delivery.free_radius_meters) }} · Biaya tambahan
                            {{ formatRupiah(branch.delivery.fee_per_km_rupiah) }}/km
                        </p>
                        <p v-if="branchFee(branch) !== null" class="mt-1 text-xs font-semibold text-mint-500">
                            {{ (branchFee(branch) ?? 0) === 0 ? 'Gratis antar-jemput' : `Biaya jarak ${formatRupiah(branchFee(branch) ?? 0)}` }}
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
