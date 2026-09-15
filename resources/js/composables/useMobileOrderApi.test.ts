import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useMobileOrderApi } from '@/composables/useMobileOrderApi';
import type { CreateOrderPayload } from '@/types/mobile';

const submittedBodies = vi.hoisted(() => [] as Record<string, unknown>[]);
const requestedUrls = vi.hoisted(() => [] as string[]);

vi.mock('@inertiajs/vue3', () => ({
    useHttp: (initialData: Record<string, unknown>) => {
        const dataKeys = Object.keys(initialData);
        const form = {
            ...initialData,
            processing: false,
            errors: {},
            cancel: vi.fn(),
            get: vi.fn(async (url: string) => {
                requestedUrls.push(url);

                return { data: [] };
            }),
            post: vi.fn(async () => {
                submittedBodies.push(
                    Object.fromEntries(
                        dataKeys.map((key) => [key, form[key as keyof typeof form]]),
                    ),
                );

                return { data: { id: 1 } };
            }),
        };

        return form;
    },
}));

describe('mobile order API', () => {
    beforeEach(() => {
        submittedBodies.length = 0;
        requestedUrls.length = 0;
    });

    it('includes customer pickup coordinates in the serialized request body', async () => {
        const payload: CreateOrderPayload = {
            items: [{ price_option_id: 10 }],
            delivery: { latitude: -6.2607, longitude: 106.8136 },
        };

        await useMobileOrderApi().createOrder('laundryhub-demo', 7, payload);

        expect(submittedBodies).toEqual([
            {
                customer_id: undefined,
                customer_address_id: undefined,
                items: payload.items,
                delivery: payload.delivery,
            },
        ]);
    });

    it('loads customer orders through the typed route with trimmed inclusive filters', async () => {
        await useMobileOrderApi().loadOrders(undefined, {
            search: '  Kemang CKL  ',
            date_from: '2026-09-01',
            date_to: '2026-09-15',
        });

        expect(requestedUrls).toEqual([
            '/api/v1/mobile/orders?search=Kemang+CKL&date_from=2026-09-01&date_to=2026-09-15',
        ]);
    });
});
