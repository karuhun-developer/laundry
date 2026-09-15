import type { ComputedRef } from 'vue';
import { onBeforeUnmount, onMounted, watch } from 'vue';

type CustomerOrderPollingOptions = {
    enabled: ComputedRef<boolean>;
    onRefresh: () => Promise<void>;
    intervalMilliseconds?: number;
};

export const useCustomerOrderPolling = ({
    enabled,
    onRefresh,
    intervalMilliseconds = 15_000,
}: CustomerOrderPollingOptions): void => {
    let interval: ReturnType<typeof setInterval> | null = null;
    let refreshInFlight = false;

    const isVisible = (): boolean => document.visibilityState === 'visible';

    const refresh = async (): Promise<void> => {
        if (!enabled.value || !isVisible() || refreshInFlight) {
            return;
        }

        refreshInFlight = true;

        try {
            await onRefresh();
        } catch {
            // Background synchronization is intentionally silent.
        } finally {
            refreshInFlight = false;
        }
    };

    const stop = (): void => {
        if (interval) {
            clearInterval(interval);
            interval = null;
        }
    };

    const synchronize = (): void => {
        stop();

        if (enabled.value && isVisible()) {
            interval = setInterval(() => void refresh(), intervalMilliseconds);
        }
    };

    const handleVisibilityChange = (): void => {
        synchronize();

        if (isVisible()) {
            void refresh();
        }
    };

    watch(enabled, synchronize);

    onMounted(() => {
        document.addEventListener('visibilitychange', handleVisibilityChange);
        synchronize();
    });

    onBeforeUnmount(() => {
        stop();
        document.removeEventListener('visibilitychange', handleVisibilityChange);
    });
};
