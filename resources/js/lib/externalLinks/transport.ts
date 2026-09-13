import { Capacitor } from '@capacitor/core';
import type { NativeExternalLinkPlugin } from '@/lib/externalLinks/native';
import { nativeExternalLink } from '@/lib/externalLinks/native';

export type ExternalLinkTransport = {
    open: (url: string) => Promise<void>;
};

type BrowserWindowOpener = (url: string, target: string, features: string) => Window | null;

export const isAllowedWhatsAppUrl = (value: string): boolean => {
    try {
        const url = new URL(value);

        return (
            url.protocol === 'https:' &&
            url.hostname === 'wa.me' &&
            url.port === '' &&
            url.username === '' &&
            url.password === ''
        );
    } catch {
        return false;
    }
};

const assertAllowedWhatsAppUrl = (url: string): void => {
    if (!isAllowedWhatsAppUrl(url)) {
        throw new Error('Tautan WhatsApp tidak valid.');
    }
};

export const createNativeExternalLinkTransport = (
    plugin: NativeExternalLinkPlugin,
): ExternalLinkTransport => ({
    async open(url: string): Promise<void> {
        assertAllowedWhatsAppUrl(url);
        await plugin.open({ url });
    },
});

export const createBrowserExternalLinkTransport = (
    openWindow: BrowserWindowOpener = (url, target, features) => window.open(url, target, features),
): ExternalLinkTransport => ({
    async open(url: string): Promise<void> {
        assertAllowedWhatsAppUrl(url);
        const openedWindow = openWindow(url, '_blank', 'noopener,noreferrer');

        if (!openedWindow) {
            throw new Error('WhatsApp diblokir browser. Izinkan pop-up lalu coba lagi.');
        }
    },
});

export const externalLinkTransport = Capacitor.isNativePlatform()
    ? createNativeExternalLinkTransport(nativeExternalLink)
    : createBrowserExternalLinkTransport();
