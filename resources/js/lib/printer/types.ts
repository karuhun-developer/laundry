export type PaperWidth = '58mm' | '80mm';
export type PrinterConnection = 'bluetooth' | 'usb';

export type PrinterDevice = {
    readonly id: string;
    readonly name: string;
    readonly connection: PrinterConnection;
};

export type PrinterProfile = PrinterDevice & {
    readonly paperWidth: PaperWidth;
};

export interface PrinterTransport {
    readonly available: boolean;
    readonly connections: readonly PrinterConnection[];
    list(connection: PrinterConnection): Promise<readonly PrinterDevice[]>;
    print(target: PrinterDevice, bytes: Uint8Array): Promise<void>;
}

export type ReceiptLine = {
    readonly text: string;
    readonly align?: 'left' | 'center' | 'right';
    readonly bold?: boolean;
    readonly size?: 'normal' | 'large';
};

export type ReceiptDocument = {
    readonly title: string;
    readonly lines: readonly ReceiptLine[];
};

export type LaundryReceiptData = {
    readonly receiptNumber: string;
    readonly orderNumber: string;
    readonly orderStatus: string;
    readonly branch: {
        readonly name: string;
        readonly code: string;
        readonly phone: string;
        readonly address: string;
        readonly city: string;
    };
    readonly customerName: string;
    readonly items: readonly {
        readonly name: string;
        readonly weightGrams: number;
        readonly subtotalRupiah: number;
    }[];
    readonly pricing: {
        readonly itemsSubtotalRupiah: number;
        readonly deliveryFeeRupiah: number;
        readonly totalRupiah: number;
        readonly paidRupiah: number;
        readonly outstandingRupiah: number;
    };
    readonly payments: readonly {
        readonly methodLabel: string;
        readonly amountRupiah: number;
    }[];
    readonly issuedAt: string;
};
