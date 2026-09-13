import { describe, expect, it } from 'vitest';
import { PrinterProfileError } from '@/lib/printer/errors';
import type { PrinterProfileStorage } from '@/lib/printer/profile';
import { loadPrinterProfile, savePrinterProfile } from '@/lib/printer/profile';
import type { PrinterProfile } from '@/lib/printer/types';

const createStorage = (): PrinterProfileStorage => {
    const values = new Map<string, string>();

    return {
        getItem: (key) => values.get(key) ?? null,
        removeItem: (key) => {
            values.delete(key);
        },
        setItem: (key, value) => {
            values.set(key, value);
        },
    };
};

describe('printer profile persistence', () => {
    it('restores the selected local printer profile', () => {
        const storage = createStorage();
        const profile: PrinterProfile = {
            id: '00:11:22:33:44:55',
            name: 'RPP02N',
            connection: 'bluetooth',
            paperWidth: '58mm',
        };

        savePrinterProfile(profile, storage);

        expect(loadPrinterProfile(storage)).toEqual(profile);
    });

    it('rejects a corrupted saved profile with a typed error', () => {
        const storage = createStorage();
        storage.setItem('laundryhub.printer-profile', '{"connection":"wifi"}');

        expect(() => loadPrinterProfile(storage)).toThrowError(PrinterProfileError);
        expect(() => loadPrinterProfile(storage)).toThrowError(
            expect.objectContaining({ code: 'invalid_profile' }),
        );
    });
});
