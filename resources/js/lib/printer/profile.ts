import { PrinterProfileError } from '@/lib/printer/errors';
import type { PaperWidth, PrinterConnection, PrinterProfile } from '@/lib/printer/types';

export const PRINTER_PROFILE_STORAGE_KEY = 'laundryhub.printer-profile';

export type PrinterProfileStorage = Pick<Storage, 'getItem' | 'removeItem' | 'setItem'>;

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const isConnection = (value: unknown): value is PrinterConnection =>
    value === 'bluetooth' || value === 'usb';

const isPaperWidth = (value: unknown): value is PaperWidth => value === '58mm' || value === '80mm';

const parsePrinterProfile = (value: unknown): PrinterProfile => {
    if (
        !isRecord(value) ||
        typeof value.id !== 'string' ||
        typeof value.name !== 'string' ||
        !isConnection(value.connection) ||
        !isPaperWidth(value.paperWidth)
    ) {
        throw new PrinterProfileError('invalid_profile');
    }

    return {
        id: value.id,
        name: value.name,
        connection: value.connection,
        paperWidth: value.paperWidth,
    };
};

export const loadPrinterProfile = (
    storage: PrinterProfileStorage = localStorage,
): PrinterProfile | null => {
    const serializedProfile = storage.getItem(PRINTER_PROFILE_STORAGE_KEY);

    if (serializedProfile === null) {
        return null;
    }

    try {
        const decodedProfile: unknown = JSON.parse(serializedProfile);

        return parsePrinterProfile(decodedProfile);
    } catch (error) {
        if (error instanceof PrinterProfileError) {
            throw error;
        }

        throw new PrinterProfileError('invalid_profile');
    }
};

export const savePrinterProfile = (
    profile: PrinterProfile,
    storage: PrinterProfileStorage = localStorage,
): void => {
    storage.setItem(PRINTER_PROFILE_STORAGE_KEY, JSON.stringify(profile));
};

export const clearPrinterProfile = (storage: PrinterProfileStorage = localStorage): void => {
    storage.removeItem(PRINTER_PROFILE_STORAGE_KEY);
};
