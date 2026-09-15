import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { usePushNotifications } from '@/composables/usePushNotifications';
import { destroy, store } from '@/routes/api/v1/mobile/push-notifications/devices';

const capacitorMocks = vi.hoisted(() => ({
    native: true,
    platform: 'android',
}));
const pushMocks = vi.hoisted(() => ({
    addListener: vi.fn(),
    checkPermissions: vi.fn(),
    createChannel: vi.fn(),
    register: vi.fn(),
    requestPermissions: vi.fn(),
    unregister: vi.fn(),
}));
const requestMocks = vi.hoisted(() => ({
    post: vi.fn(),
    delete: vi.fn(),
}));
const toastMocks = vi.hoisted(() => ({
    info: vi.fn(),
    error: vi.fn(),
}));

vi.mock('@capacitor/core', () => ({
    Capacitor: {
        isNativePlatform: () => capacitorMocks.native,
        getPlatform: () => capacitorMocks.platform,
    },
}));
vi.mock('@capacitor/push-notifications', () => ({
    PushNotifications: pushMocks,
}));
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        useHttp: (initial: Record<string, string>) =>
            reactive({
                ...initial,
                errors: {},
                response: null,
                clearErrors: vi.fn(),
                post: requestMocks.post,
                delete: requestMocks.delete,
            }),
    };
});
vi.mock('vue-sonner', () => ({ toast: toastMocks }));

type PushNotificationsComposable = ReturnType<typeof usePushNotifications>;

const mountComposable = (onRefresh = vi.fn().mockResolvedValue(undefined)) => {
    let notifications: PushNotificationsComposable | undefined;
    const wrapper = mount(
        defineComponent({
            setup() {
                notifications = usePushNotifications({ onRefresh });

                return () => h('div');
            },
        }),
    );

    if (!notifications) {
        throw new Error('Push notification composable did not initialize.');
    }

    return { notifications, onRefresh, wrapper };
};

const listener = (event: string): ((payload: unknown) => void) => {
    const callback = pushMocks.addListener.mock.calls.find(([name]) => name === event)?.[1];

    if (typeof callback !== 'function') {
        throw new Error(`Listener ${event} was not registered.`);
    }

    return callback;
};

beforeEach(() => {
    vi.clearAllMocks();
    capacitorMocks.native = true;
    capacitorMocks.platform = 'android';
    pushMocks.addListener.mockResolvedValue({ remove: vi.fn() });
    pushMocks.checkPermissions.mockResolvedValue({ receive: 'granted' });
    pushMocks.requestPermissions.mockResolvedValue({ receive: 'granted' });
    pushMocks.createChannel.mockResolvedValue(undefined);
    pushMocks.register.mockResolvedValue(undefined);
    pushMocks.unregister.mockResolvedValue(undefined);
    requestMocks.post.mockResolvedValue({ data: { id: 1 } });
    requestMocks.delete.mockResolvedValue(undefined);
});

describe('push notification registration', () => {
    it('stays unavailable in a browser without touching the native plugin', async () => {
        capacitorMocks.native = false;
        capacitorMocks.platform = 'web';

        const { notifications, wrapper } = mountComposable();
        await flushPromises();

        expect(notifications.available).toBe(false);
        expect(notifications.status.value).toBe('unavailable');
        expect(pushMocks.checkPermissions).not.toHaveBeenCalled();

        wrapper.unmount();
    });

    it('automatically registers a granted Android device through the Wayfinder endpoint', async () => {
        const { notifications, wrapper } = mountComposable();
        await flushPromises();

        expect(pushMocks.createChannel).toHaveBeenCalledWith(
            expect.objectContaining({ id: 'laundry_updates' }),
        );
        expect(pushMocks.register).toHaveBeenCalledOnce();

        listener('registration')({ value: 'fcm-device-token' });
        await flushPromises();

        expect(requestMocks.post).toHaveBeenCalledWith(store().url);
        expect(notifications.status.value).toBe('enabled');

        wrapper.unmount();
    });

    it('requests fresh Android permission on startup and registers when granted', async () => {
        pushMocks.checkPermissions.mockResolvedValue({ receive: 'prompt' });
        const { notifications, wrapper } = mountComposable();
        await flushPromises();

        expect(pushMocks.requestPermissions).toHaveBeenCalledOnce();
        expect(pushMocks.register).toHaveBeenCalledOnce();
        expect(notifications.status.value).toBe('registering');

        wrapper.unmount();
    });

    it('does not request denied permission again during startup', async () => {
        pushMocks.checkPermissions.mockResolvedValue({ receive: 'denied' });
        const { notifications, wrapper } = mountComposable();
        await flushPromises();

        expect(pushMocks.requestPermissions).not.toHaveBeenCalled();
        expect(pushMocks.register).not.toHaveBeenCalled();
        expect(notifications.status.value).toBe('denied');

        wrapper.unmount();
    });

    it('reports denied permission and native registration errors', async () => {
        pushMocks.checkPermissions.mockResolvedValue({ receive: 'denied' });
        pushMocks.requestPermissions.mockResolvedValue({ receive: 'denied' });
        const { notifications, wrapper } = mountComposable();
        await flushPromises();

        await notifications.enable();
        expect(notifications.status.value).toBe('denied');

        listener('registrationError')({ error: 'Firebase tidak tersedia.' });
        expect(notifications.status.value).toBe('error');
        expect(notifications.error.value).toBe('Firebase tidak tersedia.');

        wrapper.unmount();
    });
});

describe('push notification events and logout', () => {
    it('refreshes the workspace for foreground notifications and notification actions', async () => {
        window.history.replaceState({}, '', '/app');
        const onRefresh = vi.fn().mockResolvedValue(undefined);
        const { wrapper } = mountComposable(onRefresh);
        await flushPromises();
        const notification = { title: 'Pesanan diperbarui', body: 'Laundry sudah selesai.' };

        listener('pushNotificationReceived')(notification);
        listener('pushNotificationActionPerformed')({ notification });
        await flushPromises();

        expect(toastMocks.info).toHaveBeenCalledTimes(2);
        expect(onRefresh).toHaveBeenCalledTimes(2);

        wrapper.unmount();
    });

    it('removes the registered token before unregistering the native device', async () => {
        const { notifications, wrapper } = mountComposable();
        await flushPromises();
        listener('registration')({ value: 'token-to-remove' });
        await flushPromises();

        await notifications.unregisterDevice();

        expect(requestMocks.delete).toHaveBeenCalledWith(destroy().url);
        expect(pushMocks.unregister).toHaveBeenCalledOnce();
        expect(notifications.status.value).toBe('prompt');

        wrapper.unmount();
    });

    it('still unregisters natively when the server cannot delete the token', async () => {
        requestMocks.delete.mockRejectedValue(new Error('Server tidak dapat dijangkau.'));
        const { notifications, wrapper } = mountComposable();
        await flushPromises();
        listener('registration')({ value: 'stale-token' });
        await flushPromises();

        await notifications.unregisterDevice();

        expect(pushMocks.unregister).toHaveBeenCalledOnce();
        expect(notifications.error.value).toBe('Server tidak dapat dijangkau.');

        wrapper.unmount();
    });
});
