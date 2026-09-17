import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const appearance = ref<Appearance>('light');

export function updateTheme(_value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    // biome-ignore lint/suspicious/noDocumentCookie: This SSR preference must also support browsers without the Cookie Store API.
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    appearance.value = 'light';
    updateTheme('light');
    setCookie('appearance', 'light');

    try {
        localStorage.setItem('appearance', 'light');
    } catch {
        return;
    }
}

export function updateWorkspaceStyle(component: string): void {
    if (typeof document === 'undefined') {
        return;
    }

    const isWorkspace =
        component === 'Dashboard' ||
        ['cms/', 'mobile/', 'settings/', 'auth/'].some((prefix) => component.startsWith(prefix));

    document.documentElement.dataset.workspaceStyle = isWorkspace ? 'stitch' : 'default';
    document
        .querySelector('meta[name="theme-color"]')
        ?.setAttribute('content', isWorkspace ? '#f8f9fb' : '#103d2e');
}

export function useAppearance(): UseAppearanceReturn {
    onMounted(initializeTheme);

    function updateAppearance(_value: Appearance): void {
        initializeTheme();
    }

    return {
        appearance,
        resolvedAppearance: computed<ResolvedAppearance>(() => 'light'),
        updateAppearance,
    };
}
