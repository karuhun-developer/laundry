import { describe, expect, it, vi } from 'vitest';
import type { NativeAppUpdatePlugin } from '@/lib/appUpdate/native';
import { createAppUpdateTransport, LEGACY_ANDROID_VERSION } from '@/lib/appUpdate/transport';

const updateOptions = {
    url: 'https://laundry.example.test/app-updates/10/laundryhub-v1.0.14.apk?signature=signed',
    filename: 'laundryhub-v1.0.14.apk',
    sha256: 'a'.repeat(64),
};

const makePlugin = (): NativeAppUpdatePlugin => ({
    getInstalledVersion: vi.fn(async () => ({ version: '1.0.13' })),
    downloadAndInstall: vi.fn(async () => ({ status: 'install_started' as const })),
});

describe('app update transport', () => {
    it('does not report a browser version to the update API', async () => {
        const plugin = makePlugin();
        const transport = createAppUpdateTransport({
            isNativePlatform: false,
            isPluginAvailable: false,
            plugin,
            openWindow: vi.fn(() => window),
        });

        await expect(transport.runtime()).resolves.toEqual({
            installedVersion: null,
            nativeInstallerAvailable: false,
        });
        expect(plugin.getInstalledVersion).not.toHaveBeenCalled();
    });

    it('reads the installed version from the native plugin', async () => {
        const plugin = makePlugin();
        const transport = createAppUpdateTransport({
            isNativePlatform: true,
            isPluginAvailable: true,
            plugin,
            openWindow: vi.fn(() => window),
        });

        await expect(transport.runtime()).resolves.toEqual({
            installedVersion: '1.0.13',
            nativeInstallerAvailable: true,
        });
    });

    it('uses the release baseline for Android builds without the updater plugin', async () => {
        const transport = createAppUpdateTransport({
            isNativePlatform: true,
            isPluginAvailable: false,
            plugin: makePlugin(),
            openWindow: vi.fn(() => window),
        });

        await expect(transport.runtime()).resolves.toMatchObject({
            installedVersion: LEGACY_ANDROID_VERSION,
            nativeInstallerAvailable: false,
        });
    });

    it('passes signed download details to the native installer', async () => {
        const plugin = makePlugin();
        const transport = createAppUpdateTransport({
            isNativePlatform: true,
            isPluginAvailable: true,
            plugin,
            openWindow: vi.fn(() => window),
        });

        await expect(transport.install(updateOptions)).resolves.toEqual({
            status: 'install_started',
        });
        expect(plugin.downloadAndInstall).toHaveBeenCalledWith(updateOptions);
    });

    it('opens the signed APK URL for browsers and legacy Android builds', async () => {
        const openWindow = vi.fn(() => window);
        const transport = createAppUpdateTransport({
            isNativePlatform: true,
            isPluginAvailable: false,
            plugin: makePlugin(),
            openWindow,
        });

        await expect(transport.install(updateOptions)).resolves.toEqual({
            status: 'download_opened',
        });
        expect(openWindow).toHaveBeenCalledWith(updateOptions.url, '_blank', 'noopener,noreferrer');
    });
});
