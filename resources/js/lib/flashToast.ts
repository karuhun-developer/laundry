import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const data = event.detail.flash.toast;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });

    router.on('success', (event) => {
        const flash = event.detail.page.props.flash;

        if (typeof flash?.success === 'string' && flash.success.trim() !== '') {
            toast.success(flash.success);
        }

        if (typeof flash?.error === 'string' && flash.error.trim() !== '') {
            toast.error(flash.error);
        }
    });
}
