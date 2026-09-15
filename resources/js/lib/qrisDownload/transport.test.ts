import { describe, expect, it, vi } from 'vitest';
import type { NativeQrisDownloadPlugin } from '@/lib/qrisDownload/native';
import { createQrisDownloadTransport } from '@/lib/qrisDownload/transport';

const dataUri = 'data:image/svg+xml;base64,PHN2Zy8+';
const filename = 'PAY-20260915-00000055.svg';

const plugin = (
    save = vi.fn().mockResolvedValue({ status: 'saved' }),
): NativeQrisDownloadPlugin => ({ save });

describe('QRIS download transport', () => {
    it('uses the Android document saver when its native plugin is available', async () => {
        const save = vi.fn().mockResolvedValue({ status: 'saved' });
        const transport = createQrisDownloadTransport({
            isNativePlatform: true,
            isPluginAvailable: true,
            plugin: plugin(save),
            createAnchor: vi.fn(),
        });

        await expect(transport.save({ dataUri, filename })).resolves.toEqual({ status: 'saved' });
        expect(save).toHaveBeenCalledWith({ dataUri, filename });
    });

    it('keeps browser downloads using a temporary download anchor', async () => {
        const anchor = document.createElement('a');
        const click = vi.spyOn(anchor, 'click').mockImplementation(() => undefined);
        const transport = createQrisDownloadTransport({
            isNativePlatform: false,
            isPluginAvailable: false,
            plugin: plugin(),
            createAnchor: () => anchor,
        });

        await expect(transport.save({ dataUri, filename })).resolves.toEqual({
            status: 'download_started',
        });
        expect(anchor.href).toBe(dataUri);
        expect(anchor.download).toBe(filename);
        expect(click).toHaveBeenCalledOnce();
    });

    it('explains when an installed Android build does not contain the saver plugin', async () => {
        const transport = createQrisDownloadTransport({
            isNativePlatform: true,
            isPluginAvailable: false,
            plugin: plugin(),
            createAnchor: vi.fn(),
        });

        await expect(transport.save({ dataUri, filename })).rejects.toThrow(
            'Perbarui aplikasi untuk mengunduh QRIS di Android.',
        );
    });

    it.each([
        ['data:text/plain;base64,PHN2Zy8+', filename, 'Data QRIS tidak valid.'],
        [dataUri, '../qris.svg', 'Nama berkas QRIS tidak valid.'],
    ])(
        'rejects an unsafe QRIS download boundary',
        async (unsafeDataUri, unsafeFilename, message) => {
            const transport = createQrisDownloadTransport({
                isNativePlatform: false,
                isPluginAvailable: false,
                plugin: plugin(),
                createAnchor: vi.fn(),
            });

            await expect(
                transport.save({ dataUri: unsafeDataUri, filename: unsafeFilename }),
            ).rejects.toThrow(message);
        },
    );
});
