import { describe, expect, it } from 'vitest';
import { makeOrder } from '@/composables/mobileOrderTestFixtures';
import { filterCustomerOrders } from '@/lib/mobileOrderFilters';
import type { MobileOrder } from '@/types/mobile';

const orderWithService = (
    id: number,
    createdAt: string,
    branchName: string,
    branchCode: string,
    serviceName: string,
    serviceCode: string,
): MobileOrder => ({
    ...makeOrder(id),
    created_at: createdAt,
    branch: { ...makeOrder(id).branch, name: branchName, code: branchCode },
    items: [
        {
            id,
            service: { id, name: serviceName, code: serviceCode },
            price_option: {
                id,
                name: 'Reguler',
                type: 'per_kilogram',
                pricing_snapshot: {
                    package_weight_grams: null,
                    base_price_rupiah: null,
                    price_per_kilogram_rupiah: 12000,
                    minimum_chargeable_grams: 1000,
                },
            },
            calculation: {
                requested_weight_grams: 1000,
                chargeable_weight_grams: 1000,
                base_price_rupiah: 12000,
                excess_weight_grams: 0,
                excess_price_rupiah: 0,
                subtotal_rupiah: 12000,
            },
        },
    ],
});

describe('customer order filters', () => {
    const orders = [
        orderWithService(
            1,
            '2026-09-01T00:00:00+07:00',
            'Cabang Kemang',
            'KMG01',
            'Cuci Kering Lipat',
            'CKL',
        ),
        orderWithService(
            2,
            '2026-09-15T23:59:59+07:00',
            'Cabang Dago',
            'DGO01',
            'Setrika Saja',
            'STR',
        ),
        orderWithService(
            3,
            '2026-09-16T00:00:00+07:00',
            'Cabang Depok',
            'DPK01',
            'Cuci Sepatu',
            'CSP',
        ),
    ];

    it.each([
        ['LDR-20260913-00000001', 1],
        ['kemang', 1],
        ['DGO01', 2],
        ['cuci kering', 1],
        ['STR', 2],
    ])('matches %s across order, branch, and service fields', (search, expectedId) => {
        expect(
            filterCustomerOrders(orders, { search, date_from: '', date_to: '' }).map(
                (order) => order.id,
            ),
        ).toEqual([expectedId]);
    });

    it('includes both typed date boundaries and excludes the next day', () => {
        expect(
            filterCustomerOrders(orders, {
                search: '',
                date_from: '2026-09-01',
                date_to: '2026-09-15',
            }).map((order) => order.id),
        ).toEqual([1, 2]);
    });

    it('filters 1,000 orders deterministically without mutating the source', () => {
        const largeOrderList = Array.from({ length: 1000 }, (_, index) =>
            orderWithService(
                index + 1,
                '2026-09-15T12:00:00+07:00',
                index === 777 ? 'Cabang Target' : `Cabang ${index + 1}`,
                `BR${index + 1}`,
                'Cuci Reguler',
                'REG',
            ),
        );

        expect(
            filterCustomerOrders(largeOrderList, {
                search: 'Cabang Target',
                date_from: '',
                date_to: '',
            }).map((order) => order.id),
        ).toEqual([778]);
        expect(largeOrderList).toHaveLength(1000);
    });
});
