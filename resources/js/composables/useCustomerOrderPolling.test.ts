import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, defineComponent, h, ref } from 'vue';
import { useCustomerOrderPolling } from '@/composables/useCustomerOrderPolling';

const setVisibility = (visibilityState: DocumentVisibilityState): void => {
    Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        value: visibilityState,
    });
    document.dispatchEvent(new Event('visibilitychange'));
};

const mountPolling = (onRefresh: () => Promise<void>) => {
    const enabled = ref(true);
    const wrapper = mount(
        defineComponent({
            setup() {
                useCustomerOrderPolling({
                    enabled: computed(() => enabled.value),
                    onRefresh,
                });

                return () => h('div');
            },
        }),
    );

    return { enabled, wrapper };
};

beforeEach(() => {
    vi.useFakeTimers();
    setVisibility('visible');
});

afterEach(() => {
    vi.useRealTimers();
});

describe('customer order polling', () => {
    it('refreshes every 15 seconds only while enabled and visible', async () => {
        const onRefresh = vi.fn(async () => undefined);
        const { enabled, wrapper } = mountPolling(onRefresh);

        await vi.advanceTimersByTimeAsync(15_000);
        expect(onRefresh).toHaveBeenCalledOnce();

        setVisibility('hidden');
        await vi.advanceTimersByTimeAsync(30_000);
        expect(onRefresh).toHaveBeenCalledOnce();

        setVisibility('visible');
        await flushPromises();
        expect(onRefresh).toHaveBeenCalledTimes(2);

        enabled.value = false;
        await flushPromises();
        await vi.advanceTimersByTimeAsync(30_000);
        expect(onRefresh).toHaveBeenCalledTimes(2);

        wrapper.unmount();
    });

    it('does not overlap refresh requests', async () => {
        let resolveRefresh: () => void = () => undefined;
        const onRefresh = vi.fn(
            () =>
                new Promise<void>((resolve) => {
                    resolveRefresh = resolve;
                }),
        );
        const { wrapper } = mountPolling(onRefresh);

        await vi.advanceTimersByTimeAsync(45_000);
        expect(onRefresh).toHaveBeenCalledOnce();

        resolveRefresh();
        await flushPromises();
        await vi.advanceTimersByTimeAsync(15_000);
        expect(onRefresh).toHaveBeenCalledTimes(2);

        wrapper.unmount();
    });
});
