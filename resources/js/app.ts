import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme, updateWorkspaceStyle } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

initializeTheme();
router.on('navigate', (event) => updateWorkspaceStyle(event.detail.page.component));

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Landing':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('mobile/'):
                return null;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#00a776',
    },
});

// This will listen for flash toast data from the server...
initializeFlashToast();
