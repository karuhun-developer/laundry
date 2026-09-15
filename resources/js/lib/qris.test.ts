import { afterEach, describe, expect, it, vi } from 'vitest';
import { decodeQrisImage } from '@/lib/qris';

const STATIC_QRIS =
    '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('decodeQrisImage', () => {
    it('reads a QRIS payload from an uploaded image', async () => {
        const close = vi.fn();
        const detect = vi.fn().mockResolvedValue([{ rawValue: STATIC_QRIS }]);

        vi.stubGlobal('createImageBitmap', vi.fn().mockResolvedValue({ close }));
        vi.stubGlobal(
            'BarcodeDetector',
            class {
                detect = detect;
            },
        );

        await expect(decodeQrisImage(new File(['qris'], 'qris.png'))).resolves.toBe(STATIC_QRIS);
        expect(detect).toHaveBeenCalledOnce();
        expect(close).toHaveBeenCalledOnce();
    });

    it('explains when the browser cannot decode QR images', async () => {
        vi.stubGlobal('BarcodeDetector', undefined);

        await expect(decodeQrisImage(new File(['qris'], 'qris.png'))).rejects.toThrow(
            'Browser ini belum mendukung pembacaan gambar QR.',
        );
    });

    it('rejects a decoded QR that is not QRIS', async () => {
        const close = vi.fn();

        vi.stubGlobal('createImageBitmap', vi.fn().mockResolvedValue({ close }));
        vi.stubGlobal(
            'BarcodeDetector',
            class {
                detect = vi.fn().mockResolvedValue([{ rawValue: 'https://example.test' }]);
            },
        );

        await expect(decodeQrisImage(new File(['other'], 'other.png'))).rejects.toThrow(
            'Gambar tidak berisi payload QRIS Indonesia yang valid.',
        );
        expect(close).toHaveBeenCalledOnce();
    });
});
