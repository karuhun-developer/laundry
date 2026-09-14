import { describe, expect, it } from 'vitest';
import { paymentGatewayMethodLogo } from '@/lib/mobilePayments';

describe('payment gateway method logos', () => {
    it.each([
        ['QRIS', '/images/payment-methods/qris.svg'],
        ['MANDIRIVA', '/images/payment-methods/mandiri.svg'],
        ['BCAVA', '/images/payment-methods/bca.svg'],
        ['BNIVA', '/images/payment-methods/bni.svg'],
        ['CIMBNIAGAVA', '/images/payment-methods/cimb-niaga.svg'],
        ['PERMATAVA', '/images/payment-methods/permata.svg'],
        ['BSIVA', '/images/payment-methods/bsi.svg'],
    ])('maps %s to its local asset', (code, expectedPath) => {
        expect(paymentGatewayMethodLogo(code)).toBe(expectedPath);
    });

    it('normalizes provider separators and falls back for unknown methods', () => {
        expect(paymentGatewayMethodLogo('bni-va')).toBe('/images/payment-methods/bni.svg');
        expect(paymentGatewayMethodLogo('OTHER_BANK')).toBeNull();
    });
});
