import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import OrderDeleteDialog from '@/components/cms/OrderDeleteDialog.vue';

type DeleteOptions = {
    preserveScroll?: boolean;
    onSuccess?: () => void;
};

const deleteHarness = vi.hoisted(() => {
    let deletedUrl: string | undefined;

    return {
        record(url: string): void {
            deletedUrl = url;
        },
        reset(): void {
            deletedUrl = undefined;
        },
        deletedUrl(): string | undefined {
            return deletedUrl;
        },
    };
});

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        useForm: (_values: Record<string, unknown>) => {
            const form = {
                processing: false,
                delete: (url: string, options?: DeleteOptions) => {
                    deleteHarness.record(url);
                    options?.onSuccess?.();
                },
            };

            return reactive(form);
        },
    };
});

const passThrough = defineComponent({
    template: '<div><slot /></div>',
});
const dialogStubs = {
    Dialog: passThrough,
    DialogClose: passThrough,
    DialogContent: passThrough,
    DialogDescription: passThrough,
    DialogFooter: passThrough,
    DialogHeader: passThrough,
    DialogTitle: passThrough,
    DialogTrigger: passThrough,
};

afterEach(() => {
    deleteHarness.reset();
    vi.restoreAllMocks();
});

describe('OrderDeleteDialog', () => {
    it('renders confirmation text without requiring typed confirmation', () => {
        const wrapper = mount(OrderDeleteDialog, {
            props: {
                order: { id: 42, order_number: 'ORD-TEST-001' },
            },
            global: { stubs: dialogStubs },
        });

        expect(wrapper.text()).toContain('Hapus pesanan?');
        expect(wrapper.text()).toContain('Apakah Anda yakin ingin menghapus pesanan #ORD-TEST-001?');
        expect(wrapper.text()).toContain('Tindakan ini tidak dapat dibatalkan.');
        expect(wrapper.find('input').exists()).toBe(false);
        expect(wrapper.get('[data-test="confirm-delete-order"]').text()).toContain('Ya, Hapus');
    });

    it('submits delete request and emits deleted event on confirmation', async () => {
        const wrapper = mount(OrderDeleteDialog, {
            props: {
                order: { id: 42, order_number: 'ORD-TEST-001' },
            },
            global: { stubs: dialogStubs },
        });

        await wrapper.get('[data-test="delete-order-form"]').trigger('submit');
        await flushPromises();

        expect(deleteHarness.deletedUrl()).toBe('/cms/orders/42');
        expect(wrapper.emitted('deleted')).toBeTruthy();
    });
});
