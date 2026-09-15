import { useHttp } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';
import { type AppUpdateTransport, appUpdateTransport } from '@/lib/appUpdate/transport';
import { show as showAppUpdateRoute } from '@/routes/api/v1/mobile/app-update';
import type { ApiEnvelope } from '@/types/mobile';

type EmptyRequest = Record<string, never>;

export type MobileAppUpdate = {
    current_version: string;
    current_changelog: readonly string[];
    latest_version: string | null;
    latest_changelog: readonly string[];
    update_available: boolean;
    check_available: boolean;
    unavailable_reason: string | null;
    download_url: string | null;
    apk_sha256: string | null;
    apk_filename: string | null;
    published_at: string | null;
};

export const useMobileAppUpdate = (transport: AppUpdateTransport = appUpdateTransport) => {
    const request = useHttp<EmptyRequest, ApiEnvelope<MobileAppUpdate>>({});
    const update = ref<MobileAppUpdate | null>(null);
    const checking = ref(false);
    const installing = ref(false);
    const error = ref<string | null>(null);
    const message = ref<string | null>(null);
    let activeRequest = 0;
    let active = true;

    const checkForUpdates = async (): Promise<boolean> => {
        const requestId = ++activeRequest;
        request.cancel();
        checking.value = true;
        error.value = null;
        message.value = null;
        let requestError: string | null = null;

        try {
            const runtime = await transport.runtime();
            if (!active || requestId !== activeRequest) {
                return false;
            }

            const response = await request.get(
                showAppUpdateRoute({
                    query: runtime.installedVersion
                        ? { current_version: runtime.installedVersion }
                        : {},
                }).url,
                {
                    onHttpException: () => {
                        requestError = 'Pembaruan aplikasi belum dapat diperiksa.';
                    },
                    onNetworkError: () => {
                        requestError = 'Koneksi ke server pembaruan gagal.';
                    },
                },
            );

            if (!active || requestId !== activeRequest) {
                return false;
            }

            if (!response) {
                error.value = requestError ?? 'Pembaruan aplikasi belum dapat diperiksa.';
                return false;
            }

            update.value = response.data;
            return true;
        } catch (checkError: unknown) {
            if (active && requestId === activeRequest) {
                error.value =
                    checkError instanceof Error
                        ? checkError.message
                        : 'Pembaruan aplikasi belum dapat diperiksa.';
            }

            return false;
        } finally {
            if (active && requestId === activeRequest) {
                checking.value = false;
            }
        }
    };

    const installUpdate = async (): Promise<void> => {
        if (installing.value) {
            return;
        }

        const refreshed = await checkForUpdates();
        const availableUpdate = update.value;
        if (
            !refreshed ||
            !availableUpdate?.update_available ||
            !availableUpdate.download_url ||
            !availableUpdate.apk_filename ||
            !availableUpdate.apk_sha256
        ) {
            return;
        }

        installing.value = true;
        error.value = null;
        message.value = null;

        try {
            const result = await transport.install({
                url: availableUpdate.download_url,
                filename: availableUpdate.apk_filename,
                sha256: availableUpdate.apk_sha256,
            });

            message.value =
                result.status === 'permission_required'
                    ? 'Izinkan pemasangan aplikasi dari sumber ini, lalu tekan Pasang pembaruan lagi.'
                    : result.status === 'download_opened'
                      ? 'Unduhan APK dibuka. Setelah selesai, buka berkas untuk memasang pembaruan.'
                      : 'Installer Android sudah dibuka. Ikuti petunjuk perangkat untuk memperbarui aplikasi.';
        } catch (installError: unknown) {
            error.value =
                installError instanceof Error
                    ? installError.message
                    : 'Pembaruan aplikasi gagal dibuka.';
        } finally {
            installing.value = false;
        }
    };

    onUnmounted(() => {
        active = false;
        activeRequest += 1;
        request.cancel();
    });

    return {
        checkForUpdates,
        checking: computed(() => checking.value),
        error: computed(() => error.value),
        installUpdate,
        installing: computed(() => installing.value),
        message: computed(() => message.value),
        update: computed(() => update.value),
    };
};
