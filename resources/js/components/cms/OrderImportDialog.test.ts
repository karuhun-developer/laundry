import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import OrderImportDialog from '@/components/cms/OrderImportDialog.vue';

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

describe('OrderImportDialog', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('renders file selection and guidelines in idle state', () => {
        const wrapper = mount(OrderImportDialog, {
            global: { stubs: dialogStubs },
        });

        expect(wrapper.text()).toContain('Import Data Pesanan');
        expect(wrapper.text()).toContain('0812-8921-3041');
        expect(wrapper.find('input[type="file"]').exists()).toBe(true);
        expect(wrapper.get('[data-test="submit-order-import"]').attributes('disabled')).toBeDefined();
    });

    it('uploads file and polls progress until completion', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        // 1. Initial upload response
        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({ import_id: 'test-uuid-123', total_rows: 50 }),
        });

        // 2. First poll (in progress)
        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                id: 'test-uuid-123',
                status: 'processing',
                total_rows: 50,
                processed_rows: 25,
                imported: 25,
                errors: [],
                error_count: 0,
            }),
        });

        // 3. Second poll (completed)
        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                id: 'test-uuid-123',
                status: 'completed',
                total_rows: 50,
                processed_rows: 50,
                imported: 48,
                errors: ['Baris 5: Layanan tidak valid'],
                error_count: 1,
            }),
        });

        const wrapper = mount(OrderImportDialog, {
            global: { stubs: dialogStubs },
        });

        // Attach file
        const fileInput = wrapper.get<HTMLInputElement>('#order-csv-file');
        const fakeFile = new File(['dummy,content'], 'orders.csv', { type: 'text/csv' });
        Object.defineProperty(fileInput.element, 'files', {
            value: [fakeFile],
            writable: false,
        });
        await fileInput.trigger('change');

        // Submit
        await wrapper.get('[data-test="order-import-dialog"]').trigger('submit');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith('/cms/orders/import', expect.any(Object));

        // Advance timer for polling
        await vi.advanceTimersByTimeAsync(1000);
        await flushPromises();

        // Advance timer for completion
        await vi.advanceTimersByTimeAsync(1000);
        await flushPromises();

        expect(wrapper.text()).toContain('Impor Selesai!');
        expect(wrapper.text()).toContain('48');
        expect(wrapper.text()).toContain('Baris 5: Layanan tidak valid');
        expect(wrapper.emitted('imported')).toBeTruthy();
    });
});
