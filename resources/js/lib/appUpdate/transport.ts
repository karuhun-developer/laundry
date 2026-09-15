import { Capacitor } from '@capacitor/core';
import type { NativeAppUpdatePlugin } from '@/lib/appUpdate/native';
import { nativeAppUpdate } from '@/lib/appUpdate/native';

export const LEGACY_ANDROID_VERSION = '1.0.13';

export type AppUpdateRuntime = {
    installedVersion: string | null;
    nativeInstallerAvailable: boolean;
};

export type AppUpdateInstallResult =
    | { status: 'download_opened' }
    | { status: 'install_started' }
    | { status: 'permission_required' };

export type AppUpdateTransport = {
    runtime: () => Promise<AppUpdateRuntime>;
    install: (options: {
        readonly url: string;
        readonly filename: string;
        readonly sha256: string;
    }) => Promise<AppUpdateInstallResult>;
};

type BrowserWindowOpener = (url: string, target: string, features: string) => Window | null;

type AppUpdateTransportOptions = {
    isNativePlatform: boolean;
    isPluginAvailable: boolean;
    plugin: NativeAppUpdatePlugin;
    openWindow: BrowserWindowOpener;
};

const semanticVersionPattern = /^[0-9]+\.[0-9]+\.[0-9]+$/;

export const createAppUpdateTransport = ({
    isNativePlatform,
    isPluginAvailable,
    plugin,
    openWindow,
}: AppUpdateTransportOptions): AppUpdateTransport => ({
    async runtime(): Promise<AppUpdateRuntime> {
        if (!isNativePlatform) {
            return { installedVersion: null, nativeInstallerAvailable: false };
        }

        if (!isPluginAvailable) {
            return {
                installedVersion: LEGACY_ANDROID_VERSION,
                nativeInstallerAvailable: false,
            };
        }

        const { version } = await plugin.getInstalledVersion();
        if (!semanticVersionPattern.test(version)) {
            throw new Error('Versi aplikasi terpasang tidak valid.');
        }

        return { installedVersion: version, nativeInstallerAvailable: true };
    },

    async install(options): Promise<AppUpdateInstallResult> {
        if (isNativePlatform && isPluginAvailable) {
            return plugin.downloadAndInstall(options);
        }

        const openedWindow = openWindow(options.url, '_blank', 'noopener,noreferrer');
        if (!openedWindow) {
            throw new Error('Unduhan APK diblokir. Izinkan pop-up lalu coba lagi.');
        }

        return { status: 'download_opened' };
    },
});

export const appUpdateTransport = createAppUpdateTransport({
    isNativePlatform: Capacitor.isNativePlatform(),
    isPluginAvailable: Capacitor.isPluginAvailable('AppUpdate'),
    plugin: nativeAppUpdate,
    openWindow: (url, target, features) => window.open(url, target, features),
});
