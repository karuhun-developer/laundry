import { DOMWrapper, flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import BranchWhatsAppButton from '@/components/mobile/molecules/BranchWhatsAppButton.vue';
import OrderTicket from '@/components/mobile/molecules/OrderTicket.vue';
import { makeOrder } from '@/composables/mobileOrderTestFixtures';
import { formatOrderDate } from '@/lib/mobileOrders';
import type { MobileBranch, MobileRole, WhatsAppSuggestion } from '@/types/mobile';

const transport = vi.hoisted(() => ({ open: vi.fn<(url: string) => Promise<void>>() }));
vi.mock('@/lib/externalLinks/transport', () => ({ externalLinkTransport: transport }));

const branch: MobileBranch = {
    id: 7,
    name: 'Cabang Kemang',
    code: 'KMG01',
    phone: '081234567890',
    address: 'Jl. Kemang Raya',
    organization: { id: 1, slug: 'laundryhub-demo' },
    city: { name: 'Jakarta Selatan', province: 'DKI Jakarta' },
    coordinates: { latitude: -6.2607, longitude: 106.8136 },
    delivery: { free_radius_meters: 10000, fee_per_km_rupiah: 3000 },
    payments: { qris_enabled: true },
};
const suggestion: WhatsAppSuggestion = {
    available: true,
    phone: '6281234567890',
    message:
        'Halo, pesanan sedang dicuci. Pantau di https://example.test/pesanan/21?signature=signed',
    url: 'https://wa.me/6281234567890?text=Pesanan%20sedang%20dicuci',
    template_id: 12,
    unavailable_reason: null,
};
const unavailableSuggestion: WhatsAppSuggestion = {
    available: false,
    phone: null,
    message: null,
    url: null,
    template_id: null,
    unavailable_reason: 'Nomor WhatsApp pelanggan belum tersedia atau tidak valid.',
};
const wrappers: VueWrapper[] = [];
const body = (): DOMWrapper<HTMLElement> => new DOMWrapper(document.body);
const mountTicket = (role: MobileRole = 'branch_admin') => {
    const order = makeOrder(21, 'washing');
    order.status_history = [...makeOrder(20, 'received').status_history, ...order.status_history];
    const loadStatusWhatsAppSuggestion = vi.fn(async (): Promise<WhatsAppSuggestion> => suggestion);
    const wrapper = mount(OrderTicket, {
        props: { order, role, branches: [branch], loadStatusWhatsAppSuggestion },
        attachTo: document.body,
    });
    wrappers.push(wrapper);

    return { wrapper, order, loadStatusWhatsAppSuggestion };
};

beforeEach(() => {
    transport.open.mockReset().mockResolvedValue(undefined);
});
afterEach(() => {
    for (const wrapper of wrappers.splice(0)) {
        wrapper.unmount();
    }
    document.body.innerHTML = '';
});

describe('branch WhatsApp contact', () => {
    it.each(['0812 3456 7890', '+62 812-3456-7890', '6281234567890'])(
        'opens %s through the shared native-aware transport',
        async (phone) => {
            const wrapper = mount(BranchWhatsAppButton, { props: { phone } });
            wrappers.push(wrapper);
            expect(transport.open).not.toHaveBeenCalled();
            expect(wrapper.find('a[href^="tel:"]').exists()).toBe(false);

            await wrapper.get('[data-test="chat-branch"]').trigger('click');

            expect(transport.open).toHaveBeenCalledExactlyOnceWith('https://wa.me/6281234567890');
        },
    );

    it.each(['0211234567', 'invalid', ''])('does not open an invalid number: %s', async (phone) => {
        const wrapper = mount(BranchWhatsAppButton, { props: { phone } });
        wrappers.push(wrapper);

        expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(true);
        await wrapper.get('button').trigger('click');
        expect(transport.open).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Nomor WhatsApp cabang belum tersedia.');
    });

    it('shows opening failures and allows another attempt', async () => {
        transport.open.mockRejectedValueOnce(new Error('Tidak ada aplikasi WhatsApp.'));
        const wrapper = mount(BranchWhatsAppButton, { props: { phone: '081234567890' } });
        wrappers.push(wrapper);

        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('Tidak ada aplikasi WhatsApp.');
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(transport.open).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it.each(['customer', 'branch_admin'] as const)(
        'shows contact only to customers in %s order views',
        async (role) => {
            const { wrapper } = mountTicket(role);
            expect(wrapper.find('[data-test="chat-branch"]').exists()).toBe(role === 'customer');
            expect(wrapper.text()).not.toContain('Telepon / WA');
            expect(wrapper.text()).not.toContain(branch.phone);
            await wrapper.get('[data-test="open-order-details"]').trigger('click');

            const details = body().get('[data-test="order-details-dialog"]');
            expect(details.find('[data-test="chat-branch"]').exists()).toBe(role === 'customer');
            expect(details.find('a[href^="tel:"]').exists()).toBe(false);
            expect(transport.open).not.toHaveBeenCalled();
        },
    );
});

describe('order history WhatsApp notifications', () => {
    it('limits notification controls to admin and recorded events', () => {
        const admin = mountTicket();
        expect(admin.wrapper.findAll('[data-test="notify-order-status"]')).toHaveLength(2);
        expect(admin.wrapper.find('[data-timeline-state="future"] button').exists()).toBe(false);
        expect(admin.loadStatusWhatsAppSuggestion).not.toHaveBeenCalled();
        const customer = mountTicket('customer');
        expect(customer.wrapper.find('[data-test="notify-order-status"]').exists()).toBe(false);
        expect(customer.loadStatusWhatsAppSuggestion).not.toHaveBeenCalled();
    });

    it('previews a dated historical event and preserves the latest server message', async () => {
        const { wrapper, order, loadStatusWhatsAppSuggestion } = mountTicket();
        await wrapper.get('[aria-label="Kirim WhatsApp status Diterima"]').trigger('click');
        await flushPromises();

        expect(loadStatusWhatsAppSuggestion).toHaveBeenCalledExactlyOnceWith(order.id);
        const message = body().get('[data-test="whatsapp-message"]').text();
        expect(message).toContain(`Riwayat pesanan ${order.order_number}`);
        expect(message).toContain(`Diterima pada ${formatOrderDate(order.created_at)}`);
        expect(message).toContain(`Informasi terbaru dari cabang:\n${suggestion.message}`);
        expect(transport.open).not.toHaveBeenCalled();
        expect(wrapper.emitted('transition')).toBeUndefined();
        expect(wrapper.emitted('confirm-pricing')).toBeUndefined();

        await body().get('[data-test="open-whatsapp"]').trigger('click');
        const openedUrl = new URL(transport.open.mock.calls[0]?.[0] ?? '');
        expect(openedUrl.origin + openedUrl.pathname).toBe('https://wa.me/6281234567890');
        expect(openedUrl.searchParams.get('text')).toBe(message);
    });

    it('uses the existing template unchanged for the current status', async () => {
        const { wrapper } = mountTicket();
        await wrapper.get('[aria-label="Kirim WhatsApp status Dicuci"]').trigger('click');
        await flushPromises();
        expect(body().get('[data-test="whatsapp-message"]').text()).toBe(suggestion.message);
        await body().get('[data-test="open-whatsapp"]').trigger('click');
        expect(transport.open).toHaveBeenCalledExactlyOnceWith(suggestion.url);
    });

    it.each(['completed', 'cancelled'] as const)(
        'allows a notification for a saved %s order',
        async (status) => {
            const { wrapper, loadStatusWhatsAppSuggestion } = mountTicket();
            await wrapper.setProps({ order: makeOrder(21, status) });
            await wrapper.get('[data-test="notify-order-status"]').trigger('click');
            expect(loadStatusWhatsAppSuggestion).toHaveBeenCalledWith(21);
        },
    );

    it.each(['order', 'event'] as const)(
        'blocks notifications for a pending %s',
        async (pending) => {
            const { wrapper, order, loadStatusWhatsAppSuggestion } = mountTicket();
            await wrapper.setProps({
                order: {
                    ...order,
                    pending: pending === 'order',
                    status_history: order.status_history.map((event) => ({
                        ...event,
                        pending: pending === 'event',
                    })),
                },
            });
            for (const button of wrapper.findAll<HTMLButtonElement>(
                '[data-test="notify-order-status"]',
            )) {
                expect(button.element.disabled).toBe(true);
                await button.trigger('click');
            }
            expect(loadStatusWhatsAppSuggestion).not.toHaveBeenCalled();
        },
    );

    it.each(['unavailable', 'network'] as const)(
        'shows a recoverable %s failure without opening WhatsApp',
        async (failure) => {
            const { wrapper, loadStatusWhatsAppSuggestion } = mountTicket();
            if (failure === 'network') {
                loadStatusWhatsAppSuggestion.mockRejectedValueOnce(new Error('Jaringan terputus.'));
            } else {
                loadStatusWhatsAppSuggestion.mockResolvedValueOnce(unavailableSuggestion);
            }
            await wrapper.get('[data-test="notify-order-status"]').trigger('click');
            await flushPromises();
            expect(body().get('[role="dialog"]').text()).toContain(
                failure === 'network'
                    ? 'Jaringan terputus.'
                    : unavailableSuggestion.unavailable_reason,
            );
            expect(body().find('[data-test="open-whatsapp"]').exists()).toBe(false);
            expect(transport.open).not.toHaveBeenCalled();

            await body().get('[data-slot="dialog-close"]').trigger('click');
            await wrapper.get('[data-test="notify-order-status"]').trigger('click');
            await flushPromises();
            expect(body().find('[data-test="open-whatsapp"]').exists()).toBe(true);
        },
    );

    it('ignores an older response after closing and opening another status', async () => {
        const { wrapper, loadStatusWhatsAppSuggestion } = mountTicket();
        let resolveFirst: (value: WhatsAppSuggestion) => void = () => undefined;
        loadStatusWhatsAppSuggestion.mockReturnValueOnce(
            new Promise((resolve) => {
                resolveFirst = resolve;
            }),
        );
        await wrapper.get('[aria-label="Kirim WhatsApp status Diterima"]').trigger('click');
        expect(body().get('[role="status"]').text()).toContain('Memuat pesan');
        expect(body().find('[data-test="open-whatsapp"]').exists()).toBe(false);
        await body().get('[data-slot="dialog-close"]').trigger('click');
        await wrapper.get('[aria-label="Kirim WhatsApp status Dicuci"]').trigger('click');
        await flushPromises();
        resolveFirst(unavailableSuggestion);
        await flushPromises();
        expect(body().get('[data-test="whatsapp-message"]').text()).toBe(suggestion.message);
    });
});
