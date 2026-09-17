import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import {
    type Appearance,
    initializeTheme,
    updateTheme,
    updateWorkspaceStyle,
    useAppearance,
} from '@/composables/useAppearance';

beforeEach(() => {
    localStorage.clear();
    document.documentElement.classList.add('dark');
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    localStorage.clear();
    document.documentElement.classList.remove('dark');
    document.documentElement.style.removeProperty('color-scheme');
    delete document.documentElement.dataset.workspaceStyle;
    document.querySelector('meta[name="theme-color"]')?.remove();
});

describe('light-only appearance', () => {
    it.each([null, 'light', 'dark', 'system'])(
        'normalizes the saved %s preference to light on startup',
        (saved) => {
            if (saved) {
                localStorage.setItem('appearance', saved);
            }

            initializeTheme();

            expect(document.documentElement.classList.contains('dark')).toBe(false);
            expect(document.documentElement.style.colorScheme).toBe('light');
            expect(localStorage.getItem('appearance')).toBe('light');
            expect(document.cookie).toContain('appearance=light');
        },
    );

    it('stays light when the OS prefers dark and changes its preference', () => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        Object.defineProperty(media, 'matches', { value: true, configurable: true });
        vi.spyOn(window, 'matchMedia').mockReturnValue(media);
        localStorage.setItem('appearance', 'system');

        initializeTheme();
        expect(document.documentElement.classList.contains('dark')).toBe(false);

        Object.defineProperty(media, 'matches', { value: false });
        media.dispatchEvent(new Event('change'));
        Object.defineProperty(media, 'matches', { value: true });
        media.dispatchEvent(new Event('change'));

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.style.colorScheme).toBe('light');
    });

    it.each<Appearance>(['light', 'dark', 'system'])(
        'keeps the composable and DOM light when a caller requests %s',
        async (requested) => {
            const wrapper = mount(
                defineComponent({
                    setup() {
                        const theme = useAppearance();

                        return () =>
                            h(
                                'button',
                                { onClick: () => theme.updateAppearance(requested) },
                                `${theme.appearance.value}/${theme.resolvedAppearance.value}`,
                            );
                    },
                }),
            );

            updateTheme(requested);
            await wrapper.trigger('click');

            expect(wrapper.text()).toBe('light/light');
            expect(document.documentElement.classList.contains('dark')).toBe(false);
            expect(localStorage.getItem('appearance')).toBe('light');
            wrapper.unmount();
        },
    );

    it('still applies light mode when local storage is blocked', () => {
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new DOMException('Storage blocked', 'SecurityError');
        });

        expect(() => initializeTheme()).not.toThrow();
        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.style.colorScheme).toBe('light');
    });

    it('can initialize without a browser environment', () => {
        vi.stubGlobal('window', undefined);
        vi.stubGlobal('document', undefined);

        expect(() => initializeTheme()).not.toThrow();
        expect(() => updateTheme('system')).not.toThrow();
        expect(() => updateWorkspaceStyle('mobile/App')).not.toThrow();
    });

    it('shows only the active light theme without dark or system controls', () => {
        const wrapper = mount(AppearanceTabs);

        expect(wrapper.text()).toContain('Tema terang');
        expect(wrapper.text()).toContain('Aktif');
        expect(wrapper.findAll('button')).toHaveLength(0);
        wrapper.unmount();
    });
});

describe('application visual scope', () => {
    it.each([
        'Dashboard',
        'cms/Branches',
        'settings/Appearance',
        'mobile/App',
        'auth/Login',
        'auth/Register',
    ])(
        'applies the Stitch skin at the document root for %s, including portal content',
        (component) => {
            updateWorkspaceStyle(component);

            expect(document.documentElement.dataset.workspaceStyle).toBe('stitch');
        },
    );

    it('restores the public skin and chrome when navigating out to the landing page', () => {
        const themeColor = document.createElement('meta');
        themeColor.name = 'theme-color';
        document.head.append(themeColor);
        updateWorkspaceStyle('mobile/App');
        expect(themeColor.content).toBe('#f8f9fb');

        updateWorkspaceStyle('Landing');

        expect(document.documentElement.dataset.workspaceStyle).toBe('default');
        expect(themeColor.content).toBe('#103d2e');
    });
});
