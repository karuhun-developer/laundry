import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import OrderOperationsPanel from '@/components/mobile/organisms/OrderOperationsPanel.vue';
import type { MobileOrder } from '@/types/mobile';

const requests = vi.hoisted(() => ({
    post: vi.fn(),
    bodies: [] as Record<string, unknown>[],
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        useHttp: (initial: Record<string, unknown>) => {
            let transform = (data: Record<string, unknown>) => data;
            const form = reactive({
                ...initial,
                processing: false,
                errors: {} as Record<string, string>,
                resetAndClearErrors: () => {
                    Object.assign(form, initial);
                    form.errors = {};
                },
                transform: (callback: typeof transform) => {
                    transform = callback;
                },
                post: async (url: string) => {
                    requests.bodies.push(
                        transform(
                            Object.fromEntries(
                                Object.keys(initial).map((key) => [
                                    key,
                                    form[key as keyof typeof form],
                                ]),
                            ),
                        ),
                    );
                    return requests.post(url);
                },
            });
            return form;
        },
    };
});

const makeOrder = (): MobileOrder => ({
    id: 21,
    order_number: 'ORD-021',
    status: 'washing',
    organization_id: 1,
    organization: { id: 1, slug: 'demo' },
    branch: { id: 7, name: 'Kemang', code: 'KMG', qris_enabled: false },
    customer: { id: 9, name: 'Pelanggan' },
    items: [],
    pricing: {
        confirmed: true,
        confirmed_at: '2026-09-30T10:00:00Z',
        items_subtotal_rupiah: 12000,
        delivery_fee_rupiah: 0,
        total_rupiah: 12000,
    },
    payment_summary: { paid_rupiah: 0, outstanding_rupiah: 12000, is_paid: false },
    delivery: null,
    payments: [],
    status_history: [],
    tasks: [],
    complaints: [],
    estimated_ready_at: '2026-10-03T10:00:00Z',
    is_overdue: false,
    created_at: '2026-09-30T10:00:00Z',
});

describe('order operations', () => {
    beforeEach(() => {
        requests.post.mockReset();
        requests.bodies.length = 0;
    });

    it('retains a complaint draft when polling replaces the parent order', async () => {
        const wrapper = mount(OrderOperationsPanel, {
            props: { order: makeOrder(), staff: false },
        });
        await wrapper.get('button').trigger('click');
        await wrapper.get('textarea').setValue('Satu pakaian belum kembali');
        await wrapper.setProps({ order: { ...makeOrder(), status: 'ironing' } });
        expect(wrapper.get('textarea').element.value).toBe('Satu pakaian belum kembali');
        expect(wrapper.text()).not.toContain('Ubah estimasi');
        expect(wrapper.text()).not.toContain('Jadwalkan antar');
    });

    it('saves a customer complaint through the scoped route and emits the canonical order', async () => {
        const canonical = { ...makeOrder(), status: 'ironing' as const };
        requests.post.mockResolvedValue({ data: canonical });
        const wrapper = mount(OrderOperationsPanel, {
            props: { order: makeOrder(), staff: false },
        });
        await wrapper.get('button').trigger('click');
        await wrapper.get('textarea').setValue('Satu pakaian belum kembali');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(requests.post).toHaveBeenCalledExactlyOnceWith(
            '/api/v1/organizations/demo/branches/7/orders/21/complaints',
        );
        expect(requests.bodies[0]).toMatchObject({
            description: 'Satu pakaian belum kembali',
            status: 'new',
        });
        expect(wrapper.emitted('saved')).toEqual([[canonical]]);
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.get('[role="status"]').text()).toBe('Perubahan tersimpan.');
    });

    it('keeps the form and visible error after a failed request', async () => {
        requests.post.mockRejectedValue(new Error('Koneksi terputus'));
        const wrapper = mount(OrderOperationsPanel, {
            props: { order: makeOrder(), staff: false },
        });
        await wrapper.get('button').trigger('click');
        await wrapper.get('textarea').setValue('Laundry terlambat');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toBe('Koneksi terputus');
        expect(wrapper.get('textarea').element.value).toBe('Laundry terlambat');
        expect(wrapper.emitted('saved')).toBeUndefined();
    });

    it('requires handling before offering complaint resolution', async () => {
        const order = makeOrder();
        order.complaints = [
            {
                id: 4,
                category: 'late',
                description: 'Terlambat',
                status: 'new',
                resolution_note: null,
                resolved_at: null,
                evidence: [],
                history: [],
            },
        ];
        const wrapper = mount(OrderOperationsPanel, { props: { order, staff: true } });
        const followUp = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Tindak lanjuti');
        if (!followUp) {
            throw new Error('Follow-up action is required');
        }
        await followUp.trigger('click');
        expect(wrapper.findAll('option').map((option) => option.attributes('value'))).toEqual([
            'handling',
        ]);
        expect(wrapper.get('textarea').attributes('required')).toBeDefined();
    });

    it('closes a complaint draft without submitting it', async () => {
        const wrapper = mount(OrderOperationsPanel, {
            props: { order: makeOrder(), staff: false },
        });
        await wrapper.get('button').trigger('click');
        await wrapper.get('textarea').setValue('Satu pakaian belum kembali');
        const close = wrapper.findAll('button').find((button) => button.text() === 'Tutup');
        if (!close) {
            throw new Error('Close action is required');
        }

        expect(close.attributes('type')).toBe('button');
        await close.trigger('click');
        await flushPromises();

        expect(wrapper.find('form').exists()).toBe(false);
        expect(requests.post).not.toHaveBeenCalled();
    });

    it('does not offer delivery departure before laundry is ready', async () => {
        const order = makeOrder();
        order.tasks = [
            {
                id: 3,
                kind: 'delivery',
                status: 'scheduled',
                operator_name: 'Petugas',
                scheduled_at: '2026-10-01T10:00:00Z',
                address: 'Alamat pelanggan',
                recipient_name: null,
                note: null,
                completed_at: null,
                evidence: [],
                history: [],
            },
        ];
        const wrapper = mount(OrderOperationsPanel, { props: { order, staff: true } });
        const update = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Perbarui tugas');
        if (!update) {
            throw new Error('Task action is required');
        }
        await update.trigger('click');
        expect(wrapper.findAll('option').map((option) => option.attributes('value'))).toEqual([
            'scheduled',
            'cancelled',
        ]);
        expect(wrapper.get('input').element.value).toBe('Petugas');
    });
});
