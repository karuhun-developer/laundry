import { readonly, ref } from 'vue';

export type Coordinates = {
    latitude: number;
    longitude: number;
};

type GeolocationSource = Pick<Geolocation, 'getCurrentPosition'>;

export const useGeolocation = (
    source: GeolocationSource | undefined = typeof navigator === 'undefined'
        ? undefined
        : navigator.geolocation,
) => {
    const coordinates = ref<Coordinates | null>(null);
    const error = ref<string | null>(null);
    const locating = ref(false);

    const locate = async (): Promise<Coordinates | null> => {
        error.value = null;

        if (!source) {
            error.value = 'Perangkat ini tidak menyediakan akses lokasi.';
            return null;
        }

        locating.value = true;

        return new Promise((resolve) => {
            source.getCurrentPosition(
                (position) => {
                    const result = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                    };

                    coordinates.value = result;
                    locating.value = false;
                    resolve(result);
                },
                () => {
                    error.value =
                        'Lokasi tidak dapat dibaca. Aktifkan izin lokasi atau pilih cabang secara manual.';
                    locating.value = false;
                    resolve(null);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
            );
        });
    };

    return {
        coordinates: readonly(coordinates),
        error: readonly(error),
        locating: readonly(locating),
        locate,
    };
};
