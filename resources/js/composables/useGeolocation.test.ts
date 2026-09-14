import { describe, expect, it, vi } from 'vitest';
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
    it('requests native permission and returns Capacitor coordinates', async () => {
        const checkPermissions = vi.fn().mockResolvedValue({
            location: 'prompt',
            coarseLocation: 'prompt',
        });
        const requestPermissions = vi.fn().mockResolvedValue({
            location: 'granted',
            coarseLocation: 'granted',
        });
        const getCurrentPosition = vi.fn().mockResolvedValue(position(-6.2, 106.8));
        const geolocation = useGeolocation({
            isNativePlatform: () => true,
            native: { checkPermissions, getCurrentPosition, requestPermissions },
        });

        await expect(geolocation.locate()).resolves.toEqual({
            latitude: -6.2,
            longitude: 106.8,
        });
        expect(checkPermissions).toHaveBeenCalledOnce();
        expect(requestPermissions).toHaveBeenCalledWith({ permissions: ['location'] });
        expect(getCurrentPosition).toHaveBeenCalledWith({
            enableHighAccuracy: true,
            maximumAge: 60000,
            timeout: 10000,
        });
    });

    it('uses navigator geolocation as the browser fallback', async () => {
        const browser: Pick<Geolocation, 'getCurrentPosition'> = {
            getCurrentPosition: (success) => {
                success(position(-6.2, 106.8));
            },
        };
        const nativeGetCurrentPosition = vi.fn();
        const geolocation = useGeolocation({
            isNativePlatform: () => false,
            native: {
                checkPermissions: vi.fn(),
                getCurrentPosition: nativeGetCurrentPosition,
                requestPermissions: vi.fn(),
            },
            browser,
        });

        await expect(geolocation.locate()).resolves.toEqual({
            latitude: -6.2,
            longitude: 106.8,
        });
        expect(geolocation.coordinates.value).toEqual({
            latitude: -6.2,
            longitude: 106.8,
        });
        expect(geolocation.error.value).toBeNull();
        expect(nativeGetCurrentPosition).not.toHaveBeenCalled();
    });

    it('keeps permission denial recoverable without asking for a position', async () => {
        const getCurrentPosition = vi.fn();
        const geolocation = useGeolocation({
            isNativePlatform: () => true,
            native: {
                checkPermissions: vi.fn().mockResolvedValue({
                    location: 'prompt',
                    coarseLocation: 'prompt',
                }),
                requestPermissions: vi.fn().mockResolvedValue({
                    location: 'denied',
                    coarseLocation: 'denied',
                }),
                getCurrentPosition,
            },
        });

        await expect(geolocation.locate()).resolves.toBeNull();
        expect(getCurrentPosition).not.toHaveBeenCalled();
        expect(geolocation.error.value).toContain('pilih cabang secara manual');
        expect(geolocation.locating.value).toBe(false);
    });

    it('keeps unavailable browser GPS and timeout errors recoverable', async () => {
        const browser: Pick<Geolocation, 'getCurrentPosition'> = {
            getCurrentPosition: (_success, failure) => {
                failure?.({
                    code: 3,
                    message: 'Position unavailable',
                    PERMISSION_DENIED: 1,
                    POSITION_UNAVAILABLE: 2,
                    TIMEOUT: 3,
                });
            },
        };
        const geolocation = useGeolocation({
            isNativePlatform: () => false,
            native: {
                checkPermissions: vi.fn(),
                getCurrentPosition: vi.fn(),
                requestPermissions: vi.fn(),
            },
            browser,
        });

        await expect(geolocation.locate()).resolves.toBeNull();
        expect(geolocation.error.value).toContain('pilih cabang secara manual');
        expect(geolocation.locating.value).toBe(false);
    });

    it('rejects malformed coordinates from a location provider', async () => {
        const browser: Pick<Geolocation, 'getCurrentPosition'> = {
            getCurrentPosition: (success) => {
                success(position(95, Number.NaN));
            },
        };
        const geolocation = useGeolocation({
            isNativePlatform: () => false,
            native: {
                checkPermissions: vi.fn(),
                getCurrentPosition: vi.fn(),
                requestPermissions: vi.fn(),
            },
            browser,
        });

        await expect(geolocation.locate()).resolves.toBeNull();
        expect(geolocation.coordinates.value).toBeNull();
        expect(geolocation.error.value).toContain('pilih cabang secara manual');
    });
});
