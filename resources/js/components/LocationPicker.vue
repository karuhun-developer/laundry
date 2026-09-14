<script setup lang="ts">
import { LoaderCircle, LocateFixed, MapPin } from '@lucide/vue';
import type { Map as LeafletMap, Marker } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = withDefaults(
    defineProps<{
        latitude: string;
        longitude: string;
        title?: string;
        description?: string;
        currentLocationLabel?: string;
        selectedLabel?: string;
        mapLabel?: string;
        inputIdPrefix?: string;
    }>(),
    {
        title: 'Titik lokasi',
        description: 'Klik peta, geser pin, atau isi koordinat secara manual.',
        currentLocationLabel: 'Lokasi saya',
        selectedLabel: 'Koordinat terpilih',
        mapLabel: 'Peta pemilih lokasi',
        inputIdPrefix: 'location',
    },
);

const emit = defineEmits<{
    'update:latitude': [value: string];
    'update:longitude': [value: string];
    located: [latitude: number, longitude: number];
}>();

const mapElement = ref<HTMLDivElement | null>(null);
const locating = ref(false);
const locationError = ref('');
const helpId = computed(() => `${props.inputIdPrefix}-help`);
const latitudeId = computed(() => `${props.inputIdPrefix}-latitude`);
const longitudeId = computed(() => `${props.inputIdPrefix}-longitude`);
const latitudeModel = computed({
    get: () => props.latitude,
    set: (value: string | number) => emit('update:latitude', String(value)),
});
const longitudeModel = computed({
    get: () => props.longitude,
    set: (value: string | number) => emit('update:longitude', String(value)),
});
let map: LeafletMap | null = null;
let marker: Marker | null = null;

const parsedCoordinates = (): [number, number] | null => {
    const latitudeValue = props.latitude.trim();
    const longitudeValue = props.longitude.trim();

    if (latitudeValue === '' || longitudeValue === '') {
        return null;
    }

    const latitude = Number(latitudeValue);
    const longitude = Number(longitudeValue);

    if (
        !Number.isFinite(latitude) ||
        !Number.isFinite(longitude) ||
        latitude < -90 ||
        latitude > 90 ||
        longitude < -180 ||
        longitude > 180
    ) {
        return null;
    }

    return [latitude, longitude];
};

const updateCoordinates = (latitude: number, longitude: number): void => {
    emit('update:latitude', latitude.toFixed(6));
    emit('update:longitude', longitude.toFixed(6));
};

const setMarkerPosition = (latitude: number, longitude: number, center = false): void => {
    if (!map || !marker) {
        return;
    }

    marker.setLatLng([latitude, longitude]);

    if (center) {
        map.setView([latitude, longitude], 16);
    }
};

const initializeMap = async (): Promise<void> => {
    if (!mapElement.value) {
        return;
    }

    const leaflet = await import('leaflet');
    const coordinates = parsedCoordinates();
    const initialCenter: [number, number] = coordinates ?? [-2.5489, 118.0149];

    map = leaflet.map(mapElement.value, {
        center: initialCenter,
        zoom: coordinates ? 16 : 5,
    });
    leaflet
        .tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        })
        .addTo(map);

    const markerIcon = leaflet.divIcon({
        className: 'laundry-location-marker',
        html: '<span></span>',
        iconAnchor: [14, 28],
        iconSize: [28, 28],
    });
    marker = leaflet.marker(initialCenter, {
        draggable: true,
        icon: markerIcon,
        opacity: coordinates ? 1 : 0,
    });
    marker.addTo(map);
    marker.on('dragend', () => {
        if (!marker) {
            return;
        }

        const point = marker.getLatLng();
        updateCoordinates(point.lat, point.lng);
    });
    map.on('click', (event) => {
        marker?.setOpacity(1);
        setMarkerPosition(event.latlng.lat, event.latlng.lng);
        updateCoordinates(event.latlng.lat, event.latlng.lng);
    });

    window.setTimeout(() => map?.invalidateSize(), 0);
};

const useCurrentLocation = (): void => {
    locationError.value = '';

    if (!navigator.geolocation) {
        locationError.value = 'Perangkat ini tidak mendukung deteksi lokasi.';
        return;
    }

    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        ({ coords }) => {
            locating.value = false;
            marker?.setOpacity(1);
            setMarkerPosition(coords.latitude, coords.longitude, true);
            updateCoordinates(coords.latitude, coords.longitude);
            emit('located', coords.latitude, coords.longitude);
        },
        () => {
            locating.value = false;
            locationError.value =
                'Lokasi tidak dapat diambil. Izinkan akses lokasi lalu coba lagi.';
        },
        {
            enableHighAccuracy: true,
            maximumAge: 30000,
            timeout: 10000,
        },
    );
};

watch(
    () => [props.latitude, props.longitude] as const,
    () => {
        const coordinates = parsedCoordinates();

        if (coordinates) {
            marker?.setOpacity(1);
            setMarkerPosition(coordinates[0], coordinates[1]);
        } else {
            marker?.setOpacity(0);
        }
    },
);

onMounted(() => {
    void initializeMap();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    marker = null;
});
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm font-medium">{{ title }}</p>
                <p :id="helpId" class="mt-1 text-xs text-ink-500">
                    {{ description }}
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="min-h-11"
                :disabled="locating"
                data-test="use-current-location"
                @click="useCurrentLocation"
            >
                <LoaderCircle v-if="locating" class="animate-spin" />
                <LocateFixed v-else />
                {{ locating ? 'Mencari...' : currentLocationLabel }}
            </Button>
        </div>

        <div
            ref="mapElement"
            class="h-72 overflow-hidden rounded-lg border border-line bg-canvas"
            :aria-label="mapLabel"
            data-test="location-map"
        />

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="space-y-2">
                <Label :for="latitudeId">Latitude</Label>
                <Input
                    :id="latitudeId"
                    v-model="latitudeModel"
                    type="number"
                    inputmode="decimal"
                    min="-90"
                    max="90"
                    step="any"
                    placeholder="-6.917500"
                    :aria-describedby="helpId"
                />
            </div>
            <div class="space-y-2">
                <Label :for="longitudeId">Longitude</Label>
                <Input
                    :id="longitudeId"
                    v-model="longitudeModel"
                    type="number"
                    inputmode="decimal"
                    min="-180"
                    max="180"
                    step="any"
                    placeholder="107.619100"
                    :aria-describedby="helpId"
                />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-lg bg-surface-blue px-3 py-2 text-xs">
            <span class="inline-flex items-center gap-1.5 font-medium text-brand-700">
                <MapPin class="size-4" /> {{ selectedLabel }}
            </span>
            <span data-test="selected-latitude">Lat: {{ latitude || 'belum dipilih' }}</span>
            <span data-test="selected-longitude">Long: {{ longitude || 'belum dipilih' }}</span>
        </div>

        <p v-if="locationError" class="text-sm text-red-600" role="status" aria-live="polite">
            {{ locationError }}
        </p>
    </div>
</template>

<style scoped>
:global(.laundry-location-marker) {
    background: transparent;
    border: 0;
}

:global(.laundry-location-marker span) {
    display: block;
    width: 28px;
    height: 28px;
    border: 4px solid white;
    border-radius: 9999px 9999px 9999px 0;
    background: var(--laundry-brand-600);
    box-shadow: var(--laundry-map-marker-shadow);
    transform: rotate(-45deg);
}
</style>
