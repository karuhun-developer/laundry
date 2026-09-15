import jsQR from 'jsqr';

type DetectedBarcode = {
    rawValue: string;
};

type BarcodeDetectorInstance = {
    detect(source: ImageBitmapSource): Promise<DetectedBarcode[]>;
};

type BarcodeDetectorConstructor = new (options: {
    formats: ['qr_code'];
}) => BarcodeDetectorInstance;

type BarcodeDetectorGlobal = typeof globalThis & {
    BarcodeDetector?: BarcodeDetectorConstructor;
};

type LoadedImage = {
    source: CanvasImageSource;
    width: number;
    height: number;
    dispose: () => void;
};

const MAX_DECODE_DIMENSION = 1800;

function normalizeQrisPayload(rawValue: string | undefined): string | null {
    const payload = rawValue?.trim() ?? '';

    return payload.startsWith('0002010') && payload.includes('5802ID') ? payload : null;
}

async function decodeWithBarcodeDetector(file: File): Promise<string | null> {
    const BarcodeDetector = (globalThis as BarcodeDetectorGlobal).BarcodeDetector;

    if (!BarcodeDetector || typeof createImageBitmap !== 'function') {
        return null;
    }

    const bitmap = await createImageBitmap(file);

    try {
        const results = await new BarcodeDetector({ formats: ['qr_code'] }).detect(bitmap);

        return results.map((result) => normalizeQrisPayload(result.rawValue)).find(Boolean) ?? null;
    } catch (error) {
        if (error instanceof Error) {
            return null;
        }

        throw error;
    } finally {
        bitmap.close();
    }
}

async function loadImage(file: File): Promise<LoadedImage> {
    if (typeof createImageBitmap === 'function') {
        const bitmap = await createImageBitmap(file);

        return {
            source: bitmap,
            width: bitmap.width,
            height: bitmap.height,
            dispose: () => bitmap.close(),
        };
    }

    const objectUrl = URL.createObjectURL(file);
    const image = new Image();

    try {
        await new Promise<void>((resolve, reject) => {
            image.onload = () => resolve();
            image.onerror = () => reject(new Error('Gambar QRIS gagal dibuka.'));
            image.src = objectUrl;
        });

        return {
            source: image,
            width: image.naturalWidth,
            height: image.naturalHeight,
            dispose: () => URL.revokeObjectURL(objectUrl),
        };
    } catch (error) {
        URL.revokeObjectURL(objectUrl);
        throw error;
    }
}

async function decodeWithCanvas(file: File): Promise<string | null> {
    const image = await loadImage(file);
    const scale = Math.min(1, MAX_DECODE_DIMENSION / Math.max(image.width, image.height));
    const width = Math.max(1, Math.round(image.width * scale));
    const height = Math.max(1, Math.round(image.height * scale));
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });

    canvas.width = width;
    canvas.height = height;

    try {
        if (!context) {
            throw new Error('Gambar QRIS gagal diproses oleh browser ini.');
        }

        context.drawImage(image.source, 0, 0, width, height);
        const pixels = context.getImageData(0, 0, width, height);
        const result = jsQR(pixels.data, width, height, { inversionAttempts: 'attemptBoth' });

        return normalizeQrisPayload(result?.data);
    } finally {
        image.dispose();
    }
}

export async function decodeQrisImage(file: File): Promise<string> {
    const payload = (await decodeWithBarcodeDetector(file)) ?? (await decodeWithCanvas(file));

    if (!payload) {
        throw new Error(
            'QRIS tidak terbaca. Gunakan gambar yang tajam, tidak terpotong, dan menampilkan seluruh kode QR.',
        );
    }

    return payload;
}
