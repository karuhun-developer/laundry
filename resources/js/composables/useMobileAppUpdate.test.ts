import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { type MobileAppUpdate, useMobileAppUpdate } from '@/composables/useMobileAppUpdate';
import type { AppUpdateTransport } from '@/lib/appUpdate/transport';

const requestMocks = vi.hoisted(() => ({
    cancel: vi.fn(),
    get: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => requestMocks,
}));

vi.mock('@/lib/appUpdate/transport', () => ({
    appUpdateTransport: {
        runtime: vi.fn(),
        install: vi.fn(),
    },
}));

const makeUpdate = (overrides: Partial<MobileAppUpdate> = {}): MobileAppUpdate => ({
    current_version: '1.0.13',
    current_changelog: ['Versi stabil.'],
    latest_version: '1.0.14',
    latest_changelog: ['Pembaruan baru.'],
    update_available: true,
    check_available: true,
    unavailable_reason: null,
    download_url:
        'https://laundry.example.test/app-updates/10/laundryhub-v1.0.14.apk?signature=signed',
    apk_sha256: 'a'.repeat(64),
    apk_filename: 'laundryhub-v1.0.14.apk',
    published_at: '2026-09-15T10:00:00Z',
    ...overrides,
});

type AppUpdater = ReturnType<typeof useMobileAppUpdate>;

const mountComposable = (transport: AppUpdateTransport) => {
    let updater: AppUpdater | undefined;
    const wrapper = mount(
        defineComponent({
            setup() {
                updater = useMobileAppUpdate(transport);

                return () => h('div');
            },
        }),
    );

    if (!updater) {
        throw new Error('App updater composable did not initialize.');
    }

    return { updater, wrapper };
};

beforeEach(() => {
    vi.clearAllMocks();
});

describe('mobile app update checks', () => {
    it('omits current_version for browser checks', async () => {
        const transport: AppUpdateTransport = {
            runtime: vi.fn(async () => ({
                installedVersion: null,
                nativeInstallerAvailable: false,
            })),
            install: vi.fn(),
        };
        requestMocks.get.mockResolvedValue({ data: makeUpdate() });
        const { updater, wrapper } = mountComposable(transport);

        await updater.checkForUpdates();

        expect(requestMocks.get).toHaveBeenCalledWith(
            '/api/v1/mobile/app-update',
            expect.any(Object),
        );
        expect(updater.update.value?.current_version).toBe('1.0.13');
        wrapper.unmount();
    });

    it('sends the native installed version and invokes the verified installer', async () => {
        const install = vi.fn(async () => ({ status: 'permission_required' as const }));
        const transport: AppUpdateTransport = {
            runtime: vi.fn(async () => ({
                installedVersion: '1.0.13',
                nativeInstallerAvailable: true,
            })),
            install,
        };
        requestMocks.get.mockResolvedValue({ data: makeUpdate() });
        const { updater, wrapper } = mountComposable(transport);

        await updater.installUpdate();

        expect(requestMocks.get).toHaveBeenCalledWith(
            '/api/v1/mobile/app-update?current_version=1.0.13',
            expect.any(Object),
        );
        expect(install).toHaveBeenCalledWith({
            url: makeUpdate().download_url,
            filename: makeUpdate().apk_filename,
            sha256: makeUpdate().apk_sha256,
        });
        expect(updater.message.value).toContain('Izinkan pemasangan aplikasi');
        wrapper.unmount();
    });

    it('ignores a stale response from an older check', async () => {
        let resolveFirst: (value: { data: MobileAppUpdate }) => void = () => undefined;
        let resolveSecond: (value: { data: MobileAppUpdate }) => void = () => undefined;
        requestMocks.get
            .mockReturnValueOnce(
                new Promise((resolve) => {
                    resolveFirst = resolve;
                }),
            )
            .mockReturnValueOnce(
                new Promise((resolve) => {
                    resolveSecond = resolve;
                }),
            );
        const transport: AppUpdateTransport = {
            runtime: vi.fn(async () => ({
                installedVersion: '1.0.13',
                nativeInstallerAvailable: true,
            })),
            install: vi.fn(),
        };
        const { updater, wrapper } = mountComposable(transport);

        const firstCheck = updater.checkForUpdates();
        await flushPromises();
        const secondCheck = updater.checkForUpdates();
        await flushPromises();
        resolveSecond({ data: makeUpdate({ latest_version: '1.0.15' }) });
        await secondCheck;
        resolveFirst({ data: makeUpdate({ latest_version: '1.0.14' }) });
        await firstCheck;

        expect(updater.update.value?.latest_version).toBe('1.0.15');
        expect(requestMocks.cancel).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });
});
