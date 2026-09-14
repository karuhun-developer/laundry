import { Capacitor } from '@capacitor/core';
import {
    Geolocation,
    type GeolocationPlugin,
    type Position as NativePosition,
    type PermissionStatus,
} from '@capacitor/geolocation';
import { readonly, ref } from 'vue';

export type Coordinates = {
    latitude: number;
    longitude: number;
};

type BrowserGeolocationSource = Pick<globalThis.Geolocation, 'getCurrentPosition'>;
type NativeGeolocationSource = Pick<
    GeolocationPlugin,
    'checkPermissions' | 'getCurrentPosition' | 'requestPermissions'
>;

export type GeolocationSources = {
    isNativePlatform: () => boolean;
    native: NativeGeolocationSource;
    browser?: BrowserGeolocationSource;
};

const positionOptions = {
    enableHighAccuracy: true,
    maximumAge: 60000,
    timeout: 10000,
} as const;

const defaultSources = (): GeolocationSources => ({
    isNativePlatform: () => Capacitor.isNativePlatform(),
    native: Geolocation,
    browser: typeof navigator === 'undefined' ? undefined : navigator.geolocation,
});

const hasLocationPermission = (permission: PermissionStatus): boolean =>
    permission.location === 'granted' || permission.coarseLocation === 'granted';

const browserPosition = (source: BrowserGeolocationSource): Promise<GeolocationPosition> =>
    new Promise((resolve, reject) => {
        source.getCurrentPosition(resolve, reject, positionOptions);
    });

const validCoordinates = (latitude: number, longitude: number): boolean =>
    Number.isFinite(latitude) &&
    latitude >= -90 &&
    latitude <= 90 &&
    Number.isFinite(longitude) &&
    longitude >= -180 &&
    longitude <= 180;

export const useGeolocation = (sources: GeolocationSources = defaultSources()) => {
    const coordinates = ref<Coordinates | null>(null);
    const error = ref<string | null>(null);
    const locating = ref(false);

    const locate = async (): Promise<Coordinates | null> => {
        error.value = null;

        if (!sources.isNativePlatform() && !sources.browser) {
            error.value = 'Perangkat ini tidak menyediakan akses lokasi.';
            return null;
        }

        locating.value = true;

        try {
            let position: NativePosition | GeolocationPosition;

            if (sources.isNativePlatform()) {
                let permission = await sources.native.checkPermissions();

                if (!hasLocationPermission(permission)) {
                    permission = await sources.native.requestPermissions({
                        permissions: ['location'],
                    });
                }

                if (!hasLocationPermission(permission)) {
                    throw new Error('Location permission denied.');
                }

                position = await sources.native.getCurrentPosition(positionOptions);
            } else {
                position = await browserPosition(sources.browser as BrowserGeolocationSource);
            }

            const result = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
            };

            if (!validCoordinates(result.latitude, result.longitude)) {
                throw new Error('Invalid location coordinates.');
            }

            coordinates.value = result;

            return result;
        } catch {
            error.value =
                'Lokasi tidak dapat dibaca. Aktifkan izin lokasi atau pilih cabang secara manual.';

            return null;
        } finally {
            locating.value = false;
        }
    };

    return {
        coordinates: readonly(coordinates),
        error: readonly(error),
        locating: readonly(locating),
        locate,
    };
};
