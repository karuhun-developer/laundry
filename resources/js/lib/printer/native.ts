import { registerPlugin } from '@capacitor/core';
import type { PrinterConnection } from '@/lib/printer/types';

export type NativePrinter = {
    readonly id: string;
    readonly name: string;
};

export interface NativeThermalPrinterPlugin {
    listBluetooth(): Promise<{ readonly printers: readonly NativePrinter[] }>;
    listUsb(): Promise<{ readonly printers: readonly NativePrinter[] }>;
    print(options: {
        readonly connection: PrinterConnection;
        readonly id: string;
        readonly data: string;
    }): Promise<void>;
}

export const nativeThermalPrinter = registerPlugin<NativeThermalPrinterPlugin>('ThermalPrinter');
