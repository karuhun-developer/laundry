import { describe, expect, it, vi } from 'vitest';
import type { NativeExternalLinkPlugin } from '@/lib/externalLinks/native';
import {
    createBrowserExternalLinkTransport,
    createNativeExternalLinkTransport,
    isAllowedWhatsAppUrl,
} from '@/lib/externalLinks/transport';

describe('WhatsApp external-link transport', () => {
    it('allows only exact secure wa.me URLs', () => {
        expect(isAllowedWhatsAppUrl('https://wa.me/6281234567890?text=Halo')).toBe(true);
        expect(isAllowedWhatsAppUrl('http://wa.me/6281234567890')).toBe(false);
        expect(isAllowedWhatsAppUrl('https://chat.wa.me/6281234567890')).toBe(false);
        expect(isAllowedWhatsAppUrl('https://wa.me.example.com/6281234567890')).toBe(false);
        expect(isAllowedWhatsAppUrl('https://user@wa.me/6281234567890')).toBe(false);
        expect(isAllowedWhatsAppUrl('https://wa.me:8443/6281234567890')).toBe(false);
    });

    it('delegates an allowed URL to the native Capacitor plugin', async () => {
        const open = vi.fn(async (): Promise<void> => undefined);
        const plugin: NativeExternalLinkPlugin = { open };
        const transport = createNativeExternalLinkTransport(plugin);

        await transport.open('https://wa.me/6281234567890?text=Pesanan%20siap');

        expect(open).toHaveBeenCalledWith({
            url: 'https://wa.me/6281234567890?text=Pesanan%20siap',
        });
    });

    it('opens a browser tab only when the transport is explicitly invoked', async () => {
        const openWindow = vi.fn(
            (_url: string, _target: string, _features: string): Window | null => window,
        );
        const transport = createBrowserExternalLinkTransport(openWindow);

        expect(openWindow).not.toHaveBeenCalled();

        await transport.open('https://wa.me/6281234567890?text=Halo');

        expect(openWindow).toHaveBeenCalledWith(
            'https://wa.me/6281234567890?text=Halo',
            '_blank',
            'noopener,noreferrer',
        );
        await expect(transport.open('https://example.com')).rejects.toThrow(
            'Tautan WhatsApp tidak valid.',
        );
    });
});
