import { Capacitor, type PluginListenerHandle } from '@capacitor/core';
import {
    type ActionPerformed,
    type PushNotificationSchema,
    PushNotifications,
    type Token,
} from '@capacitor/push-notifications';
import { useHttp } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, readonly, ref } from 'vue';
import { toast } from 'vue-sonner';
import { firstRequestError } from '@/lib/mobileHttp';
import {
    destroy as destroyDevice,
    store as storeDevice,
} from '@/routes/api/v1/mobile/push-notifications/devices';
import { app as mobileApp } from '@/routes/mobile';

export type PushNotificationStatus =
    | 'unavailable'
    | 'prompt'
    | 'registering'
    | 'enabled'
    | 'denied'
    | 'error';

type RegisterDevicePayload = {
    token: string;
    platform: 'android' | 'ios';
};

type DeleteDevicePayload = {
    token: string;
};

type PushNotificationOptions = {
    onRefresh: () => Promise<void>;
};

const notificationMessage = (notification: PushNotificationSchema): string =>
    notification.body ?? notification.title ?? 'Ada pembaruan baru di LaundryHub.';

export const usePushNotifications = ({ onRefresh }: PushNotificationOptions) => {
    const available = Capacitor.isNativePlatform();
    const status = ref<PushNotificationStatus>(available ? 'prompt' : 'unavailable');
    const error = ref<string | null>(null);
    const currentToken = ref<string | null>(null);
    const registerRequest = useHttp<RegisterDevicePayload, unknown>({
        token: '',
        platform: 'android',
    });
    const deleteRequest = useHttp<DeleteDevicePayload, unknown>({ token: '' });
    const listenerHandles: PluginListenerHandle[] = [];
    let listenersPromise: Promise<void> | null = null;

    const platform = (): 'android' | 'ios' | null => {
        const currentPlatform = Capacitor.getPlatform();

        return currentPlatform === 'android' || currentPlatform === 'ios' ? currentPlatform : null;
    };

    const registerDevice = async (token: Token): Promise<void> => {
        const currentPlatform = platform();

        if (!currentPlatform) {
            status.value = 'unavailable';
            return;
        }

        currentToken.value = token.value;
        registerRequest.clearErrors();
        registerRequest.token = token.value;
        registerRequest.platform = currentPlatform;

        try {
            const response = await registerRequest.post(storeDevice().url);

            if (!response) {
                throw new Error(
                    firstRequestError(
                        registerRequest.errors,
                        'Perangkat tidak dapat didaftarkan untuk notifikasi.',
                    ),
                );
            }

            status.value = 'enabled';
            error.value = null;
        } catch (registrationError: unknown) {
            status.value = 'error';
            error.value =
                registrationError instanceof Error
                    ? registrationError.message
                    : 'Perangkat tidak dapat didaftarkan untuk notifikasi.';
        } finally {
            registerRequest.response = null;
        }
    };

    const refreshFromNotification = async (): Promise<void> => {
        try {
            await onRefresh();
        } catch (_refreshError: unknown) {
            toast.error('Data aplikasi belum dapat disegarkan dari notifikasi.');
        }
    };

    const onNotificationReceived = (notification: PushNotificationSchema): void => {
        toast.info(notificationMessage(notification));
        void refreshFromNotification();
    };

    const onNotificationAction = (action: ActionPerformed): void => {
        if (window.location.pathname !== mobileApp().url) {
            window.location.assign(mobileApp().url);
            return;
        }

        toast.info(notificationMessage(action.notification));
        void refreshFromNotification();
    };

    const setupListeners = async (): Promise<void> => {
        if (listenersPromise) {
            await listenersPromise;
            return;
        }

        listenersPromise = Promise.all([
            PushNotifications.addListener('registration', (token) => void registerDevice(token)),
            PushNotifications.addListener('registrationError', (registrationError) => {
                status.value = 'error';
                error.value =
                    registrationError.error || 'Perangkat gagal terhubung ke layanan notifikasi.';
            }),
            PushNotifications.addListener('pushNotificationReceived', onNotificationReceived),
            PushNotifications.addListener('pushNotificationActionPerformed', onNotificationAction),
        ]).then((handles) => {
            listenerHandles.push(...handles);
        });

        await listenersPromise;
    };

    const registerWithGrantedPermission = async (): Promise<void> => {
        if (platform() === 'android') {
            await PushNotifications.createChannel({
                id: 'laundry_updates',
                name: 'Pembaruan Laundry',
                description: 'Status penjemputan, harga, dan proses pesanan LaundryHub.',
                importance: 4,
                sound: 'default',
                vibration: true,
            });
        }

        await PushNotifications.register();
    };

    const enable = async (): Promise<void> => {
        if (!available) {
            status.value = 'unavailable';
            return;
        }

        status.value = 'registering';
        error.value = null;

        try {
            await setupListeners();
            let permission = await PushNotifications.checkPermissions();

            if (permission.receive !== 'granted') {
                permission = await PushNotifications.requestPermissions();
            }

            if (permission.receive !== 'granted') {
                status.value = 'denied';
                return;
            }

            await registerWithGrantedPermission();
        } catch (enableError: unknown) {
            status.value = 'error';
            error.value =
                enableError instanceof Error
                    ? enableError.message
                    : 'Notifikasi perangkat tidak dapat diaktifkan.';
        }
    };

    const unregisterDevice = async (): Promise<void> => {
        if (!available) {
            return;
        }

        const token = currentToken.value;

        if (token) {
            deleteRequest.clearErrors();
            deleteRequest.token = token;

            try {
                await deleteRequest.delete(destroyDevice().url);
            } catch (deleteError: unknown) {
                error.value =
                    deleteError instanceof Error
                        ? deleteError.message
                        : 'Perangkat belum dapat dilepas dari akun.';
            }
        }

        try {
            await PushNotifications.unregister();
        } finally {
            currentToken.value = null;
            status.value = 'prompt';
        }
    };

    const initialize = async (): Promise<void> => {
        if (!available) {
            return;
        }

        try {
            await setupListeners();
            let permission = await PushNotifications.checkPermissions();

            if (platform() === 'android' && permission.receive === 'prompt') {
                permission = await PushNotifications.requestPermissions();
            }

            if (permission.receive !== 'granted') {
                status.value = permission.receive === 'denied' ? 'denied' : 'prompt';
                return;
            }

            status.value = 'registering';
            await registerWithGrantedPermission();
        } catch (initializationError: unknown) {
            status.value = 'error';
            error.value =
                initializationError instanceof Error
                    ? initializationError.message
                    : 'Status notifikasi perangkat tidak dapat diperiksa.';
        }
    };

    onMounted(() => void initialize());
    onBeforeUnmount(() => {
        for (const listenerHandle of listenerHandles) {
            void listenerHandle.remove();
        }
    });

    return {
        available,
        status: readonly(status),
        error: readonly(error),
        enable,
        unregisterDevice,
    };
};
