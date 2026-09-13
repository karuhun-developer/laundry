import { describe, expect, it, vi } from 'vitest';
import { useMobilePrinter } from '@/composables/useMobilePrinter';
import type { PrinterProfileStorage } from '@/lib/printer/profile';
import type { LaundryReceiptData, PrinterDevice, PrinterTransport } from '@/lib/printer/types';

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

const receipt: LaundryReceiptData = {
    receiptNumber: 'LDR-20260913-00000021',
    orderNumber: 'LDR-20260913-00000021',
    orderStatus: 'washing',
    branch: {
        name: 'Cabang Kemang',
        code: 'KMG01',
        phone: '081234567890',
        address: 'Jl. Kemang Raya',
        city: 'Jakarta Selatan',
    },
    customerName: 'Customer Demo',
    items: [],
    pricing: {
        itemsSubtotalRupiah: 30000,
        deliveryFeeRupiah: 0,
        totalRupiah: 30000,
        paidRupiah: 30000,
        outstandingRupiah: 0,
    },
    payments: [{ methodLabel: 'Tunai', amountRupiah: 30000 }],
    issuedAt: '2026-09-13T10:30:00.000Z',
};

describe('useMobilePrinter', () => {
    it('discovers, saves, tests, and reuses a Bluetooth printer profile', async () => {
        const print = vi.fn(async () => undefined);
        const devices: readonly PrinterDevice[] = [
            { id: '00:11:22:33:44:55', name: 'RPP02N', connection: 'bluetooth' },
        ];
        const transport: PrinterTransport = {
            available: true,
            connections: ['bluetooth', 'usb'],
            list: vi.fn(async () => devices),
            print,
        };
        const printer = useMobilePrinter({ transport, storage: createStorage() });

        await expect(printer.discover('bluetooth')).resolves.toBe(true);
        expect(printer.devices.value).toHaveLength(1);

        printer.saveProfile({
            id: '00:11:22:33:44:55',
            name: 'RPP02N',
            connection: 'bluetooth',
            paperWidth: '58mm',
        });

        await expect(printer.testPrint()).resolves.toBe(true);
        await expect(printer.printReceipt(receipt)).resolves.toBe(true);
        expect(print).toHaveBeenCalledTimes(2);
        expect(printer.message.value).toContain('Struk berhasil dikirim');
    });

    it('keeps a visible failure message when Android printer transport is unavailable', async () => {
        const transport: PrinterTransport = {
            available: false,
            connections: [],
            list: vi.fn(async () => []),
            print: vi.fn(async () => {
                throw new Error('Tidak tersedia');
            }),
        };
        const printer = useMobilePrinter({ transport, storage: createStorage() });

        await expect(printer.discover('usb')).resolves.toBe(false);
        expect(printer.error.value).toContain('aplikasi Android');
    });
});
