import { afterEach, describe, expect, it, vi } from 'vitest';
import { decodeQrisImage } from '@/lib/qris';

const STATIC_QRIS =
    '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';
const jsQrMock = vi.hoisted(() => vi.fn());

vi.mock('jsqr', () => ({
    default: jsQrMock,
}));

function stubCanvasDecoder(): void {
    const context: CanvasRenderingContext2D = Object.create(null);

    context.drawImage = vi.fn();
    context.getImageData = vi.fn().mockReturnValue({
        data: new Uint8ClampedArray(4),
    });

    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(context);
}

afterEach(() => {
    vi.restoreAllMocks();
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

    it('falls back to canvas decoding when BarcodeDetector is unavailable', async () => {
        const close = vi.fn();

        vi.stubGlobal(
            'createImageBitmap',
            vi.fn().mockResolvedValue({ close, width: 100, height: 100 }),
        );
        vi.stubGlobal('BarcodeDetector', undefined);
        stubCanvasDecoder();
        jsQrMock.mockReturnValue({ data: STATIC_QRIS });

        await expect(decodeQrisImage(new File(['qris'], 'qris.png'))).resolves.toBe(STATIC_QRIS);
        expect(jsQrMock).toHaveBeenCalledOnce();
        expect(close).toHaveBeenCalledOnce();
    });

    it('rejects a decoded QR that is not QRIS', async () => {
        const close = vi.fn();

        vi.stubGlobal(
            'createImageBitmap',
            vi.fn().mockResolvedValue({ close, width: 100, height: 100 }),
        );
        vi.stubGlobal(
            'BarcodeDetector',
            class {
                detect = vi.fn().mockResolvedValue([{ rawValue: 'https://example.test' }]);
            },
        );
        stubCanvasDecoder();
        jsQrMock.mockReturnValue(null);

        await expect(decodeQrisImage(new File(['other'], 'other.png'))).rejects.toThrow(
            'QRIS tidak terbaca. Gunakan gambar yang tajam, tidak terpotong, dan menampilkan seluruh kode QR.',
        );
        expect(close).toHaveBeenCalledTimes(2);
    });
});
