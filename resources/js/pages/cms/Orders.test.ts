import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Orders from '@/pages/cms/Orders.vue';
import type { CmsOrderSummary } from '@/types';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent } = await import('vue');

    return {
        Head: defineComponent({ template: '<div><slot /></div>' }),
        usePage: () => ({
            props: {
                auth: {
                    user: { organization_id: 1, roles: ['superadmin'] },
                },
            },
        }),
    };
});

describe('CMS Orders Queue page', () => {
    const sampleOrders: CmsOrderSummary[] = [
        {
            id: 1,
            order_number: 'ORD-001',
            status: 'washing',
            organization_id: 1,
            organization: { id: 1, name: 'Laundry Satu', slug: 'laundry-satu' },
            branch: { id: 10, name: 'Kemang', code: 'KMG01', qris_enabled: true },
            customer: { id: 100, name: 'Budi Santoso', whatsapp_phone: '628123456789' },
            items: [
                {
                    id: 1,
                    service: { id: 1, name: 'Cuci Kering', code: 'CK' },
                    price_option: {
                        id: 1,
                        name: 'Reguler',
                        type: 'per_kilogram',
                        pricing_snapshot: {
                            package_weight_grams: null,
                            base_price_rupiah: null,
                            price_per_kilogram_rupiah: 10000,
                            min_price_per_kilogram_rupiah: 8000,
                            minimum_chargeable_grams: 3000,
                        },
                    },
                    calculation: {
                        requested_weight_grams: 4000,
                        chargeable_weight_grams: 4000,
                        base_price_rupiah: 40000,
                        excess_weight_grams: 0,
                        excess_price_rupiah: 0,
                        subtotal_rupiah: 40000,
                    },
                },
            ],
            pricing: {
                confirmed: true,
                confirmed_at: '2026-09-21T10:00:00.000Z',
                items_subtotal_rupiah: 40000,
                delivery_fee_rupiah: 0,
                total_rupiah: 40000,
            },
            delivery: null,
            status_history: [
                {
                    id: 1,
                    status: 'received',
                    actor: { id: 2, name: 'Kasir Kemang' },
                    note: 'Pakaian diterima',
                    occurred_at: '2026-09-21T09:00:00.000Z',
                },
            ],
            payments: [
                {
                    id: 1,
                    payment_number: 'PAY-001',
                    method: 'cash',
                    method_label: 'Tunai',
                    status: 'paid',
                    status_label: 'Lunas',
                    amount_rupiah: 40000,
                    qris_payload: null,
                    qris_image_data_uri: null,
                    note: null,
                    settled_at: '2026-09-21T09:05:00.000Z',
                    created_at: '2026-09-21T09:05:00.000Z',
                },
            ],
            payment_summary: {
                paid_rupiah: 40000,
                outstanding_rupiah: 0,
                is_paid: true,
            },
            created_at: '2026-09-21T09:00:00.000Z',
        },
        {
            id: 2,
            order_number: 'ORD-002',
            status: 'ready_for_pickup',
            organization_id: 2,
            organization: { id: 2, name: 'Laundry Dua', slug: 'laundry-dua' },
            branch: { id: 20, name: 'Dago', code: 'DGO01', qris_enabled: true },
            customer: { id: 101, name: 'Siti Rahma', whatsapp_phone: '628987654321' },
            items: [],
            pricing: {
                confirmed: true,
                confirmed_at: '2026-09-21T11:00:00.000Z',
                items_subtotal_rupiah: 25000,
                delivery_fee_rupiah: 5000,
                total_rupiah: 30000,
            },
            delivery: null,
            status_history: [],
            payments: [],
            payment_summary: {
                paid_rupiah: 0,
                outstanding_rupiah: 30000,
                is_paid: false,
            },
            created_at: '2026-09-21T10:30:00.000Z',
        },
    ];

    const branches = [
        { id: 10, organization_id: 1, name: 'Kemang', code: 'KMG01', is_active: true },
        { id: 20, organization_id: 2, name: 'Dago', code: 'DGO01', is_active: true },
    ];

    const organizations = [
        { id: 1, name: 'Laundry Satu' },
        { id: 2, name: 'Laundry Dua' },
    ];

    const orderStatuses = [
        { value: 'washing', label: 'Dicuci' },
        { value: 'ready_for_pickup', label: 'Siap diambil' },
        { value: 'completed', label: 'Selesai' },
    ];

    it('renders orders table and filters by organization, branch, status, and search query', async () => {
        const wrapper = mount(Orders, {
            props: {
                orders: sampleOrders,
                branches,
                organizations,
                orderStatuses,
            },
            global: {
                stubs: {
                    CmsPageHeader: true,
                    CmsMetricCard: true,
                    CmsEmptyState: true,
                    Badge: true,
                    OrderDetailDialog: true,
                },
            },
        });

        // Initially both orders are displayed
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);

        // Filter by organization 1
        const orgSelect = wrapper.find('#order-organization-filter');
        await orgSelect.setValue('1');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);

        // Reset organization filter
        await orgSelect.setValue('all');
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);

        // Filter by status 'ready_for_pickup'
        const statusSelect = wrapper.find('#order-status-filter');
        await statusSelect.setValue('ready_for_pickup');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);

        // Reset status filter
        await statusSelect.setValue('all');
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);

        // Filter by search query 'Budi'
        const searchInput = wrapper.find('#order-search');
        await searchInput.setValue('Budi');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);

        // Filter by search query non-existent
        await searchInput.setValue('NonExistent');
        expect(wrapper.findAll('tbody tr')).toHaveLength(0);
    });

    it('renders safely when order items are empty, null, or wrapped in a data object', () => {
        const defensiveOrders = [
            {
                ...sampleOrders[0],
                id: 10,
                order_number: 'ORD-DATA-WRAPPED',
                items: {
                    data: [
                        {
                            id: 99,
                            service: { id: 1, name: 'Cuci Satuan', code: 'CS' },
                            price_option: {
                                id: 1,
                                name: 'Satuan',
                                type: 'fixed_package',
                                pricing_snapshot: {
                                    package_weight_grams: null,
                                    base_price_rupiah: 50000,
                                    price_per_kilogram_rupiah: null,
                                    min_price_per_kilogram_rupiah: null,
                                    minimum_chargeable_grams: null,
                                },
                            },
                            calculation: {
                                requested_weight_grams: 0,
                                chargeable_weight_grams: 0,
                                base_price_rupiah: 50000,
                                excess_weight_grams: 0,
                                excess_price_rupiah: 0,
                                subtotal_rupiah: 50000,
                            },
                        },
                    ],
                } as unknown as CmsOrderSummary['items'],
            },
            {
                ...sampleOrders[0],
                id: 11,
                order_number: 'ORD-EMPTY-ITEMS',
                items: [] as CmsOrderSummary['items'],
            },
        ];

        const wrapper = mount(Orders, {
            props: {
                orders: defensiveOrders,
                branches,
                organizations,
                orderStatuses,
            },
            global: {
                stubs: {
                    CmsPageHeader: true,
                    CmsMetricCard: true,
                    CmsEmptyState: true,
                    Badge: true,
                    OrderDetailDialog: true,
                },
            },
        });

        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.text()).toContain('Cuci Satuan');
        expect(wrapper.text()).toContain('Tanpa paket');
    });
});
