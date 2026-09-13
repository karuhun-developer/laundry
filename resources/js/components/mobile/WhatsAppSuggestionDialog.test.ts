import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import WhatsAppSuggestionDialog from '@/components/mobile/molecules/WhatsAppSuggestionDialog.vue';
import type { WhatsAppSuggestion } from '@/types/mobile';

const transportHarness = vi.hoisted(() => ({
    open: vi.fn(async (): Promise<void> => undefined),
}));

vi.mock('@/lib/externalLinks/transport', () => ({
    externalLinkTransport: transportHarness,
}));

const suggestion: WhatsAppSuggestion = {
    available: true,
    phone: '6281234567890',
    message: 'Halo Budi, pesananmu sudah siap diambil.',
    url: 'https://wa.me/6281234567890?text=Halo%20Budi',
    template_id: 12,
    unavailable_reason: null,
};

afterEach(() => {
    document.body.innerHTML = '';
    transportHarness.open.mockClear();
});

describe('WhatsApp suggestion dialog', () => {
    it('shows a preview without opening WhatsApp automatically', async () => {
        const wrapper = mount(WhatsAppSuggestionDialog, {
            props: { open: true, suggestion, loadError: null },
            attachTo: document.body,
        });

        await vi.waitFor(() => expect(document.body.textContent).toContain(suggestion.message));
        expect(transportHarness.open).not.toHaveBeenCalled();

        document.body.querySelector<HTMLButtonElement>('[data-test="open-whatsapp"]')?.click();

        await vi.waitFor(() => expect(transportHarness.open).toHaveBeenCalledWith(suggestion.url));
        wrapper.unmount();
    });

    it('can reopen and reuse the last suggestion', async () => {
        const wrapper = mount(WhatsAppSuggestionDialog, {
            props: { open: false, suggestion, loadError: null },
            attachTo: document.body,
        });

        await wrapper.setProps({ open: true });
        document.body.querySelector<HTMLButtonElement>('[data-test="open-whatsapp"]')?.click();
        await vi.waitFor(() => expect(transportHarness.open).toHaveBeenCalledTimes(1));

        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });
        document.body.querySelector<HTMLButtonElement>('[data-test="open-whatsapp"]')?.click();
        await vi.waitFor(() => expect(transportHarness.open).toHaveBeenCalledTimes(2));
        wrapper.unmount();
    });
});
