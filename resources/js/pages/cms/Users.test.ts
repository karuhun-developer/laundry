import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Users from '@/pages/cms/Users.vue';
import type { UserSummary } from '@/types';

const visits = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent } = await import('vue');

    return {
        router: visits,
        Head: defineComponent({ template: '<div><slot /></div>' }),
        usePage: () => ({
            props: {
                auth: {
                    user: { id: 99, organization_id: 1, roles: ['superadmin'] },
                },
            },
        }),
    };
});

describe('CMS users page', () => {
    afterEach(() => {
        vi.useRealTimers();
        vi.clearAllMocks();
    });

    it('debounces server search and preserves it during pagination', async () => {
        vi.useFakeTimers();
        const user: UserSummary = {
            id: 1,
            organization_id: 1,
            name: 'Budi Santoso',
            email: 'budi@example.com',
            whatsapp_phone: '628123456789',
            roles: [{ id: 1, name: 'branch_admin' }],
            branches: [{ id: 10, name: 'Kemang' }],
            organization: { id: 1, name: 'Laundry Satu' },
        };
        const wrapper = mount(Users, {
            props: {
                users: [user],
                filters: { search: '' },
                pagination: { current_page: 1, last_page: 3, total: 51 },
                statistics: { total: 51, branch_admins: 10 },
                branches: [{ id: 10, organization_id: 1, name: 'Kemang' }],
                organizations: [{ id: 1, name: 'Laundry Satu' }],
            },
            global: {
                stubs: {
                    CmsPageHeader: true,
                    CmsMetricCard: true,
                    CmsEmptyState: true,
                    UserFormDialog: true,
                    CmsDeleteDialog: true,
                    Badge: true,
                },
            },
        });

        await wrapper.get('#user-search').setValue(' Budi ');
        expect(visits.get).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(300);
        expect(visits.get).toHaveBeenCalledWith(
            '/cms/users',
            { page: 1, search: 'Budi' },
            expect.objectContaining({ preserveState: true, replace: true }),
        );

        const pageTwo = wrapper.findAll('nav button').find((button) => button.text() === '2');
        expect(pageTwo).toBeDefined();
        await pageTwo?.trigger('click');
        expect(visits.get).toHaveBeenLastCalledWith(
            '/cms/users',
            { page: 2, search: 'Budi' },
            expect.any(Object),
        );

        await wrapper.get('[data-test="clear-user-search"]').trigger('click');
        await vi.advanceTimersByTimeAsync(300);
        expect(visits.get).toHaveBeenLastCalledWith('/cms/users', { page: 1 }, expect.any(Object));
        wrapper.unmount();
    });
});
