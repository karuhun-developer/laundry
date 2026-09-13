import { Capacitor } from '@capacitor/core';
import { PrinterTransportError } from '@/lib/printer/errors';
import type { NativeThermalPrinterPlugin } from '@/lib/printer/native';
import { nativeThermalPrinter } from '@/lib/printer/native';
import type { PrinterConnection, PrinterDevice, PrinterTransport } from '@/lib/printer/types';

const encodeBase64 = (bytes: Uint8Array): string => {
    const chunks: string[] = [];
    const chunkSize = 0x8000;

    for (let offset = 0; offset < bytes.length; offset += chunkSize) {
        chunks.push(String.fromCharCode(...bytes.subarray(offset, offset + chunkSize)));
    }

    return btoa(chunks.join(''));
};

const transportError = (code: 'discovery_failed' | 'print_failed', error: unknown): never => {
    if (error instanceof Error) {
        throw new PrinterTransportError(code, error);
    }

    throw new PrinterTransportError(code);
};

export const createCapacitorPrinterTransport = (
    plugin: NativeThermalPrinterPlugin,
): PrinterTransport => ({
    available: true,
    connections: ['bluetooth', 'usb'],
    async list(connection: PrinterConnection): Promise<readonly PrinterDevice[]> {
        try {
            const result =
                connection === 'bluetooth' ? await plugin.listBluetooth() : await plugin.listUsb();

            return result.printers.map((printer) => ({
                ...printer,
                connection,
            }));
        } catch (error) {
            return transportError('discovery_failed', error);
        }
    },
    async print(target: PrinterDevice, bytes: Uint8Array): Promise<void> {
        try {
            await plugin.print({
                connection: target.connection,
                id: target.id,
                data: encodeBase64(bytes),
            });
        } catch (error) {
            transportError('print_failed', error);
        }
    },
});

export const unavailablePrinterTransport: PrinterTransport = {
    available: false,
    connections: [],
    async list(): Promise<readonly PrinterDevice[]> {
        return [];
    },
    async print(): Promise<void> {
        throw new PrinterTransportError('transport_unavailable');
    },
};

export const printerTransport = Capacitor.isNativePlatform()
    ? createCapacitorPrinterTransport(nativeThermalPrinter)
    : unavailablePrinterTransport;
