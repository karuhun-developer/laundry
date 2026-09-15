import { describe, expect, it, vi } from 'vitest';
import type { PrinterTransportError } from '@/lib/printer/errors';
import type { NativeThermalPrinterPlugin } from '@/lib/printer/native';
import {
    createCapacitorPrinterTransport,
    unavailablePrinterTransport,
} from '@/lib/printer/transport';

describe('Capacitor thermal printer transport', () => {
    it('maps paired Bluetooth printers into typed devices', async () => {
        const plugin: NativeThermalPrinterPlugin = {
            listBluetooth: vi.fn().mockResolvedValue({
                printers: [{ id: '00:11:22:33:44:55', name: 'RPP02N' }],
            }),
            listUsb: vi.fn().mockResolvedValue({ printers: [] }),
            print: vi.fn().mockResolvedValue(undefined),
        };
        const transport = createCapacitorPrinterTransport(plugin);

        const printers = await transport.list('bluetooth');

        expect(printers).toEqual([
            { id: '00:11:22:33:44:55', name: 'RPP02N', connection: 'bluetooth' },
        ]);
    });

    it('sends ESC/POS bytes as base64 to the native plugin', async () => {
        const print = vi.fn().mockResolvedValue(undefined);
        const plugin: NativeThermalPrinterPlugin = {
            listBluetooth: vi.fn().mockResolvedValue({ printers: [] }),
            listUsb: vi.fn().mockResolvedValue({ printers: [] }),
            print,
        };
        const transport = createCapacitorPrinterTransport(plugin);

        await transport.print(
            { id: 'usb-device-1', name: 'USB Printer', connection: 'usb' },
            Uint8Array.from([0x01, 0x02, 0x03, 0xff]),
        );

        expect(print).toHaveBeenCalledWith({
            connection: 'usb',
            id: 'usb-device-1',
            data: 'AQID/w==',
        });
    });

    it('reports a typed error when printing is unavailable on the web', async () => {
        await expect(
            unavailablePrinterTransport.print(
                { id: 'web', name: 'Web', connection: 'usb' },
                Uint8Array.from([0x1b, 0x40]),
            ),
        ).rejects.toEqual(
            expect.objectContaining<Partial<PrinterTransportError>>({
                code: 'transport_unavailable',
            }),
        );
    });

    it.each([
        ['bluetooth_disabled', 'Bluetooth belum aktif. Aktifkan Bluetooth lalu cari printer lagi.'],
        [
            'permission_denied',
            'Izin Bluetooth atau USB ditolak. Izinkan akses printer di pengaturan Android.',
        ],
        ['bluetooth_unavailable', 'Perangkat ini tidak memiliki Bluetooth.'],
    ] as const)('preserves native discovery error %s', async (code, message) => {
        const plugin: NativeThermalPrinterPlugin = {
            listBluetooth: vi.fn().mockRejectedValue({ code }),
            listUsb: vi.fn().mockResolvedValue({ printers: [] }),
            print: vi.fn().mockResolvedValue(undefined),
        };
        const transport = createCapacitorPrinterTransport(plugin);

        await expect(transport.list('bluetooth')).rejects.toEqual(
            expect.objectContaining<Partial<PrinterTransportError>>({ code, message }),
        );
    });
});
