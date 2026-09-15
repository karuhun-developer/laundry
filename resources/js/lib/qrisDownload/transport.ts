import { Capacitor } from '@capacitor/core';
import type { NativeQrisDownloadPlugin } from '@/lib/qrisDownload/native';
import { nativeQrisDownload } from '@/lib/qrisDownload/native';

export type QrisDownloadResult = {
    status: 'saved' | 'cancelled' | 'download_started';
};

export type QrisDownloadTransport = {
    save: (options: {
        readonly dataUri: string;
        readonly filename: string;
    }) => Promise<QrisDownloadResult>;
};

type QrisDownloadTransportOptions = {
    isNativePlatform: boolean;
    isPluginAvailable: boolean;
    plugin: NativeQrisDownloadPlugin;
    createAnchor: () => HTMLAnchorElement;
};

const svgDataUriPattern = /^data:image\/svg\+xml;base64,[A-Za-z0-9+/]+={0,2}$/;
const svgFilenamePattern = /^[A-Za-z0-9][A-Za-z0-9._-]{0,119}\.svg$/;

const assertValidDownload = (dataUri: string, filename: string): void => {
    if (!svgDataUriPattern.test(dataUri)) {
        throw new Error('Data QRIS tidak valid.');
    }

    if (!svgFilenamePattern.test(filename)) {
        throw new Error('Nama berkas QRIS tidak valid.');
    }
};

export const createQrisDownloadTransport = ({
    isNativePlatform,
    isPluginAvailable,
    plugin,
    createAnchor,
}: QrisDownloadTransportOptions): QrisDownloadTransport => ({
    async save(options): Promise<QrisDownloadResult> {
        assertValidDownload(options.dataUri, options.filename);

        if (isNativePlatform) {
            if (!isPluginAvailable) {
                throw new Error('Perbarui aplikasi untuk mengunduh QRIS di Android.');
            }

            return plugin.save(options);
        }

        const anchor = createAnchor();
        anchor.href = options.dataUri;
        anchor.download = options.filename;
        anchor.rel = 'noopener';
        anchor.click();

        return { status: 'download_started' };
    },
});

export const qrisDownloadTransport = createQrisDownloadTransport({
    isNativePlatform: Capacitor.isNativePlatform(),
    isPluginAvailable: Capacitor.isPluginAvailable('QrisDownload'),
    plugin: nativeQrisDownload,
    createAnchor: () => document.createElement('a'),
});
