import { DOMWrapper, enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import MobileAppUpdateSettings from '@/components/mobile/organisms/MobileAppUpdateSettings.vue';
import type { MobileAppUpdate } from '@/composables/useMobileAppUpdate';

const updaterMocks = vi.hoisted(() => ({
    checkForUpdates: vi.fn(async () => true),
    installUpdate: vi.fn(async () => undefined),
    useMobileAppUpdate: vi.fn(),
}));

enableAutoUnmount(afterEach);

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
    document.body.innerHTML = '';
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
        expect(wrapper.text()).not.toContain('Perbaikan WhatsApp Android.');
        expect(wrapper.text()).not.toContain('Pembaruan otomatis Android.');

        await wrapper.get('[data-test="install-app-update"]').trigger('click');
        expect(updaterMocks.installUpdate).toHaveBeenCalledOnce();
    });

    it('opens the current and latest changelogs only in a dismissible modal', async () => {
        setUpdaterState(makeUpdate());
        const wrapper = mount(MobileAppUpdateSettings, {
            attachTo: document.body,
        });
        const body = new DOMWrapper(document.body);

        expect(body.find('[data-test="app-changelog-dialog"]').exists()).toBe(false);
        await wrapper.get('[data-test="view-app-changelog"]').trigger('click');
        await vi.waitFor(() =>
            expect(body.find('[data-test="app-changelog-dialog"]').exists()).toBe(true),
        );

        const dialog = body.get('[data-test="app-changelog-dialog"]');
        expect(dialog.attributes('role')).toBe('dialog');
        expect(dialog.text()).toContain('Versi terpasang v1.0.13');
        expect(dialog.text()).toContain('Perbaikan WhatsApp Android.');
        expect(dialog.text()).toContain('Pembaruan v1.0.14');
        expect(dialog.text()).toContain('Pembaruan otomatis Android.');

        expect(dialog.find('[data-slot="dialog-footer"]').exists()).toBe(false);
        expect(dialog.findAll('[data-slot="dialog-close"]')).toHaveLength(1);
        const closeButton = dialog.get('[data-slot="dialog-close"]');
        expect(closeButton.text()).toBe('Tutup');
        expect(closeButton.get('span').classes()).not.toContain('sr-only');
        expect(closeButton.find('svg').exists()).toBe(true);

        await closeButton.trigger('click');
        await vi.waitFor(() =>
            expect(body.find('[data-test="app-changelog-dialog"]').exists()).toBe(false),
        );

        expect(wrapper.text()).not.toContain('Perbaikan WhatsApp Android.');
        expect(updaterMocks.installUpdate).not.toHaveBeenCalled();
        expect(updaterMocks.checkForUpdates).toHaveBeenCalledOnce();
    });

    it.each([
        { current_changelog: ['Versi stabil.'], latest_changelog: [], update_available: false },
        { current_changelog: [], latest_changelog: ['Perbaikan terbaru.'], update_available: true },
    ])('supports changelogs from just one available version', async (notes) => {
        setUpdaterState(makeUpdate(notes));
        const wrapper = mount(MobileAppUpdateSettings, {
            attachTo: document.body,
        });
        const body = new DOMWrapper(document.body);

        await wrapper.get('[data-test="view-app-changelog"]').trigger('click');
        await vi.waitFor(() =>
            expect(body.find('[data-test="app-changelog-dialog"]').exists()).toBe(true),
        );

        expect(body.get('[data-test="app-changelog-dialog"]').findAll('section')).toHaveLength(1);
    });

    it.each([
        null,
        makeUpdate({ current_changelog: [], latest_changelog: [] }),
        makeUpdate({ current_changelog: [], update_available: false }),
    ])('hides the changelog action when there are no relevant notes', (update) => {
        setUpdaterState(update);
        const wrapper = mount(MobileAppUpdateSettings);

        expect(wrapper.find('[data-test="view-app-changelog"]').exists()).toBe(false);
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
