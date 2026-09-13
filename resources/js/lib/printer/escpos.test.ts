import { describe, expect, it } from 'vitest';
import { encodeEscPosReceipt } from '@/lib/printer/escpos';
import { buildLaundryReceipt } from '@/lib/printer/receipt';
import type { LaundryReceiptData } from '@/lib/printer/types';

const receiptData: LaundryReceiptData = {
    receiptNumber: 'RCT-20260913-0001',
    orderNumber: 'LDR-20260913-0001',
    orderStatus: 'paid',
    branch: {
        name: 'LaundryHub Kemang',
        code: 'KMG01',
        phone: '081234567890',
        address: 'Jl. Kemang Raya No. 1',
        city: 'Jakarta Selatan',
    },
    customerName: 'Bayu',
    items: [
        {
            name: 'Paket Cuci 12 kg',
            weightGrams: 12500,
            subtotalRupiah: 14500,
        },
    ],
    pricing: {
        itemsSubtotalRupiah: 14500,
        deliveryFeeRupiah: 3000,
        totalRupiah: 17500,
        paidRupiah: 17500,
        outstandingRupiah: 0,
    },
    payments: [{ methodLabel: 'QRIS', amountRupiah: 17500 }],
    issuedAt: '2026-09-13T10:30:00+07:00',
};

describe('ESC/POS receipt encoding', () => {
    it('encodes style changes, feeds paper, and partially cuts the receipt', () => {
        const bytes = encodeEscPosReceipt(
            {
                title: 'Test receipt',
                lines: [
                    { text: 'LaundryHub', align: 'center', bold: true, size: 'large' },
                    { text: 'Lunas', align: 'right' },
                ],
            },
            { feedLines: 2 },
        );

        expect(Array.from(bytes.slice(0, 5))).toEqual([0x1b, 0x40, 0x1b, 0x61, 0x01]);
        expect(Array.from(bytes)).toContain(0x0a);
        expect(Array.from(bytes.slice(-3))).toEqual([0x1d, 0x56, 0x01]);
    });

    it('replaces unsupported unicode deterministically', () => {
        const bytes = encodeEscPosReceipt(
            { title: 'Unicode', lines: [{ text: 'Terima kasih 🙏' }] },
            { feedLines: 0, cut: false },
        );

        expect(new TextDecoder('latin1').decode(bytes)).toContain('Terima kasih ?');
    });
});

describe('laundry receipt formatting', () => {
    it('formats a complete 58 mm receipt within 32 columns', () => {
        const receipt = buildLaundryReceipt(receiptData, '58mm');

        expect(receipt.lines.map((line) => line.text)).toContain('LaundryHub Kemang');
        expect(receipt.lines.map((line) => line.text)).toContain('TOTAL                  Rp17.500');
        expect(receipt.lines.every((line) => line.text.length <= 32)).toBe(true);
    });

    it('uses 48 columns for an 80 mm printer profile', () => {
        const receipt = buildLaundryReceipt(receiptData, '80mm');
        const divider = receipt.lines.find((line) => line.text.startsWith('-'));

        expect(divider?.text).toHaveLength(48);
        expect(receipt.lines.every((line) => line.text.length <= 48)).toBe(true);
    });
});
