<script setup lang="ts">
import { LocateFixed, MapPin, Navigation, Store } from '@lucide/vue';
import { ref } from 'vue';
import { useGeolocation } from '@/composables/useGeolocation';
import { formatDistance, formatRupiah } from '@/lib/mobileOrders';
import type { MobileBranch, NearestMobileBranch } from '@/types/mobile';

type BranchResult = MobileBranch | NearestMobileBranch;

defineProps<{
    branches: readonly MobileBranch[];
    nearestBranches: readonly NearestMobileBranch[];
    selectedBranchId: number | null;
}>();

const emit = defineEmits<{
    locate: [latitude: number, longitude: number];
    select: [branchId: number];
}>();

const geolocation = useGeolocation();
const manualLatitude = ref<number | string>('');
const manualLongitude = ref<number | string>('');
const manualError = ref<string | null>(null);

const branchDistance = (branch: BranchResult): number | null =>
    'distance_meters' in branch.delivery ? branch.delivery.distance_meters : null;

const branchFee = (branch: BranchResult): number | null =>
    'fee_rupiah' in branch.delivery ? branch.delivery.fee_rupiah : null;

const locate = async (): Promise<void> => {
    const coordinates = await geolocation.locate();

    if (coordinates) {
        manualLatitude.value = coordinates.latitude;
        manualLongitude.value = coordinates.longitude;
        emit('locate', coordinates.latitude, coordinates.longitude);
    }
};

const locateManually = (): void => {
    const latitude = Number(manualLatitude.value);
    const longitude = Number(manualLongitude.value);

    if (
        !Number.isFinite(latitude) ||
        latitude < -90 ||
        latitude > 90 ||
        !Number.isFinite(longitude) ||
        longitude < -180 ||
        longitude > 180
    ) {
        manualError.value = 'Latitude harus -90 sampai 90 dan longitude -180 sampai 180.';
        return;
    }

    manualError.value = null;
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
            <button
                type="button"
                class="mt-5 flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-4 text-sm font-semibold text-brand-700 transition motion-safe:active:scale-[0.98] disabled:opacity-60"
                :disabled="geolocation.locating.value"
                data-test="find-nearest-branch"
                @click="locate"
            >
                <LocateFixed class="size-4" aria-hidden="true" />
                {{ geolocation.locating.value ? 'Membaca lokasi…' : 'Gunakan lokasi saya' }}
            </button>
            <p v-if="geolocation.error.value" class="mt-3 text-sm text-white" role="status">
                {{ geolocation.error.value }}
            </p>

            <form
                class="mt-5 rounded-2xl bg-white/10 p-4"
                data-test="manual-location"
                @submit.prevent="locateManually"
            >
                <p class="text-sm font-semibold">Masukkan koordinat manual</p>
                <p class="mt-1 text-xs leading-5 text-white/75">Tetap bisa dipakai jika izin lokasi ditolak.</p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="text-xs font-medium text-white/85">Latitude</span>
                        <input
                            v-model="manualLatitude"
                            type="number"
                            min="-90"
                            max="90"
                            step="any"
                            inputmode="decimal"
                            required
                            class="mt-1 min-h-11 w-full rounded-xl border border-white/30 bg-white px-3 text-sm text-ink-950 outline-none"
                        />
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-white/85">Longitude</span>
                        <input
                            v-model="manualLongitude"
                            type="number"
                            min="-180"
                            max="180"
                            step="any"
                            inputmode="decimal"
                            required
                            class="mt-1 min-h-11 w-full rounded-xl border border-white/30 bg-white px-3 text-sm text-ink-950 outline-none"
                        />
                    </label>
                </div>
                <p v-if="manualError" class="mt-2 text-sm text-white" role="status">{{ manualError }}</p>
                <button
                    type="submit"
                    class="mt-3 min-h-11 w-full rounded-xl border border-white/40 px-4 text-sm font-semibold text-white transition hover:bg-white/10 motion-safe:active:scale-[0.98]"
                >
                    Hitung dari koordinat ini
                </button>
            </form>
        </div>

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
