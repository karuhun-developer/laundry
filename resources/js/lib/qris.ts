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

export async function decodeQrisImage(file: File): Promise<string> {
    const BarcodeDetector = (globalThis as BarcodeDetectorGlobal).BarcodeDetector;

    if (!BarcodeDetector || typeof createImageBitmap !== 'function') {
        throw new Error(
            'Browser ini belum mendukung pembacaan gambar QR. Salin payload QRIS secara manual.',
        );
    }

    const bitmap = await createImageBitmap(file);

    try {
        const [result] = await new BarcodeDetector({ formats: ['qr_code'] }).detect(bitmap);
        const payload = result?.rawValue.trim() ?? '';

        if (!payload.startsWith('0002010') || !payload.includes('5802ID')) {
            throw new Error('Gambar tidak berisi payload QRIS Indonesia yang valid.');
        }

        return payload;
    } finally {
        bitmap.close();
    }
}
