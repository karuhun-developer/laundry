import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import Landing from '@/pages/Landing.vue';

const platform = vi.hoisted(() => ({ isNativePlatform: vi.fn() }));
const router = vi.hoisted(() => ({ visit: vi.fn() }));
const loginRoute = { url: '/sentinel/login', method: 'get' };
const registerRoute = { url: '/sentinel/register', method: 'get' };
const mobileAppRoute = { url: '/sentinel/app', method: 'get' };

vi.mock('@capacitor/core', () => ({ Capacitor: platform }));
vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        name: 'InertiaLink',
        props: { href: { type: [String, Object], required: true } },
        template: '<a><slot /></a>',
    }),
    router,
}));
vi.mock('@/routes', () => ({
    login: () => loginRoute,
    register: () => registerRoute,
}));
vi.mock('@/routes/mobile', () => ({ app: () => mobileAppRoute }));

beforeEach(() => {
    vi.clearAllMocks();
    platform.isNativePlatform.mockReturnValue(false);
});

describe('public landing page', () => {
    it('stays visible in a browser and presents the product landing content', async () => {
        const wrapper = mount(Landing);
        await flushPromises();

        expect(router.visit).not.toHaveBeenCalled();
        expect(wrapper.get('h1').text()).toContain('Kelola laundry lebih cepat');
        expect(wrapper.text()).toContain('QRIS dinamis');
        expect(wrapper.classes()).toContain('bg-[#f4f8ff]');
    });

    it('redirects the native Android shell directly to the authenticated mobile app', async () => {
        platform.isNativePlatform.mockReturnValue(true);

        mount(Landing);
        await flushPromises();

        expect(router.visit).toHaveBeenCalledOnce();
        expect(router.visit).toHaveBeenCalledWith(mobileAppRoute);
    });

    it('uses Wayfinder destinations for every login and registration call to action', () => {
        const wrapper = mount(Landing);
        const destinations = wrapper
            .findAllComponents({ name: 'InertiaLink' })
            .map((link) => link.props('href'));

        expect(destinations.filter((destination) => destination === loginRoute)).toHaveLength(3);
        expect(destinations.filter((destination) => destination === registerRoute)).toHaveLength(3);
    });
});
