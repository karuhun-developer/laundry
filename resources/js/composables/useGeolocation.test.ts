import { describe, expect, it } from 'vitest';
import { useGeolocation } from '@/composables/useGeolocation';

const position = (latitude: number, longitude: number): GeolocationPosition => ({
    coords: {
        accuracy: 5,
        altitude: null,
        altitudeAccuracy: null,
        heading: null,
        latitude,
        longitude,
        speed: null,
        toJSON: () => ({}),
    },
    timestamp: 1,
    toJSON: () => ({}),
});

describe('useGeolocation', () => {
    it('returns and stores device coordinates', async () => {
        const source: Pick<Geolocation, 'getCurrentPosition'> = {
            getCurrentPosition: (success) => {
                success(position(-6.2, 106.8));
            },
        };
        const geolocation = useGeolocation(source);

        await expect(geolocation.locate()).resolves.toEqual({
            latitude: -6.2,
            longitude: 106.8,
        });
        expect(geolocation.coordinates.value).toEqual({
            latitude: -6.2,
            longitude: 106.8,
        });
        expect(geolocation.error.value).toBeNull();
    });

    it('returns a recoverable message when location access fails', async () => {
        const source: Pick<Geolocation, 'getCurrentPosition'> = {
            getCurrentPosition: (_success, failure) => {
                failure?.({
                    code: 1,
                    message: 'Permission denied',
                    PERMISSION_DENIED: 1,
                    POSITION_UNAVAILABLE: 2,
                    TIMEOUT: 3,
                });
            },
        };
        const geolocation = useGeolocation(source);

        await expect(geolocation.locate()).resolves.toBeNull();
        expect(geolocation.error.value).toContain('pilih cabang secara manual');
        expect(geolocation.locating.value).toBe(false);
    });
});
