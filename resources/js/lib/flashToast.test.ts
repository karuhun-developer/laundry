import { afterEach, describe, expect, it, vi } from 'vitest';
import { initializeFlashToast } from '@/lib/flashToast';

const routerHarness = vi.hoisted(() => {
    const listeners = new Map<string, (event: { detail: unknown }) => void>();

    return {
        emit(eventName: string, detail: unknown): void {
            listeners.get(eventName)?.({ detail });
        },
        on: vi.fn((eventName: string, listener: (event: { detail: unknown }) => void) => {
            listeners.set(eventName, listener);

            return () => listeners.delete(eventName);
        }),
        reset(): void {
            listeners.clear();
        },
    };
});

const toastMocks = vi.hoisted(() => ({
    error: vi.fn(),
    info: vi.fn(),
    success: vi.fn(),
    warning: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { on: routerHarness.on },
}));

vi.mock('vue-sonner', () => ({
    toast: toastMocks,
}));

afterEach(() => {
    routerHarness.reset();
    vi.clearAllMocks();
});

describe('initializeFlashToast', () => {
    it('shows dedicated Inertia flash toast messages', () => {
        initializeFlashToast();

        routerHarness.emit('flash', {
            flash: {
                toast: { type: 'success', message: 'Data berhasil disimpan.' },
            },
        });

        expect(toastMocks.success).toHaveBeenCalledWith('Data berhasil disimpan.');
    });

    it('shows successful CMS action messages from shared session props', () => {
        initializeFlashToast();

        routerHarness.emit('success', {
            page: {
                props: {
                    flash: { success: 'Cabang berhasil diperbarui.', error: null },
                },
            },
        });

        expect(toastMocks.success).toHaveBeenCalledWith('Cabang berhasil diperbarui.');
    });

    it('shows failed CMS action messages from shared session props', () => {
        initializeFlashToast();

        routerHarness.emit('success', {
            page: {
                props: {
                    flash: { success: null, error: 'Cabang gagal dihapus.' },
                },
            },
        });

        expect(toastMocks.error).toHaveBeenCalledWith('Cabang gagal dihapus.');
    });
});
