import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import MobileAppUpdateSettings from '@/components/mobile/organisms/MobileAppUpdateSettings.vue';
import type { MobileAppUpdate } from '@/composables/useMobileAppUpdate';

const updaterMocks = vi.hoisted(() => ({
    checkForUpdates: vi.fn(async () => true),
    installUpdate: vi.fn(async () => undefined),
    useMobileAppUpdate: vi.fn(),
}));

vi.mock('@/composables/useMobileAppUpdate', () => ({
    useMobileAppUpdate: updaterMocks.useMobileAppUpdate,
}));

const makeUpdate = (overrides: Partial<MobileAppUpdate> = {}): MobileAppUpdate => ({
    current_version: '1.0.13',
    current_changelog: ['Perbaikan WhatsApp Android.'],
    latest_version: '1.0.14',
    latest_changelog: ['Pembaruan otomatis Android.'],
    update_available: true,
    check_available: true,
    unavailable_reason: null,
    download_url: 'https://example.test/app-update.apk?signature=signed',
    apk_sha256: 'a'.repeat(64),
    apk_filename: 'laundryhub-v1.0.14.apk',
    published_at: '2026-09-15T10:00:00Z',
    ...overrides,
});

const setUpdaterState = (
    update: MobileAppUpdate | null,
    options?: {
        error?: string | null;
        message?: string | null;
    },
): void => {
    updaterMocks.useMobileAppUpdate.mockReturnValue({
        checkForUpdates: updaterMocks.checkForUpdates,
        checking: ref(false),
        error: ref(options?.error ?? null),
        installUpdate: updaterMocks.installUpdate,
        installing: ref(false),
        message: ref(options?.message ?? null),
        update: ref(update),
    });
};

beforeEach(() => {
    vi.clearAllMocks();
    updaterMocks.checkForUpdates.mockResolvedValue(true);
    updaterMocks.installUpdate.mockResolvedValue(undefined);
});

describe('mobile app update settings', () => {
    it('checks automatically and displays installed and available versions', async () => {
        setUpdaterState(makeUpdate());
        const wrapper = mount(MobileAppUpdateSettings);
        await flushPromises();

        expect(updaterMocks.checkForUpdates).toHaveBeenCalledOnce();
        expect(wrapper.get('[data-test="installed-app-version"]').text()).toBe('Terpasang v1.0.13');
        expect(wrapper.get('[data-test="app-update-available"]').text()).toContain(
            'Pembaruan v1.0.14 tersedia',
        );
        expect(wrapper.text()).toContain('Perbaikan WhatsApp Android.');
        expect(wrapper.text()).toContain('Pembaruan otomatis Android.');

        await wrapper.get('[data-test="install-app-update"]').trigger('click');
        expect(updaterMocks.installUpdate).toHaveBeenCalledOnce();
    });

    it('shows current, unavailable, and permission guidance states', () => {
        setUpdaterState(
            makeUpdate({
                latest_version: '1.0.13',
                latest_changelog: [],
                update_available: false,
                unavailable_reason: 'Rilis terbaru belum memiliki APK.',
            }),
            { message: 'Izinkan pemasangan aplikasi dari sumber ini.' },
        );
        const wrapper = mount(MobileAppUpdateSettings);

        expect(wrapper.get('[data-test="app-update-current"]').text()).toContain('versi terbaru');
        expect(wrapper.get('[data-test="app-update-unavailable"]').text()).toContain(
            'belum memiliki APK',
        );
        expect(wrapper.get('[data-test="app-update-message"]').text()).toContain(
            'Izinkan pemasangan',
        );
    });

    it('shows a controlled request error', () => {
        setUpdaterState(null, { error: 'Koneksi ke server pembaruan gagal.' });
        const wrapper = mount(MobileAppUpdateSettings);

        expect(wrapper.get('[data-test="app-update-error"]').text()).toContain(
            'Koneksi ke server pembaruan gagal.',
        );
    });
});
