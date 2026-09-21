import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import MessageTemplates from '@/pages/cms/MessageTemplates.vue';
import type { MessageTemplateSummary } from '@/types';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent } = await import('vue');

    return {
        Head: defineComponent({ template: '<div><slot /></div>' }),
        usePage: () => ({
            props: {
                auth: {
                    user: { organization_id: 1, roles: ['superadmin'] },
                },
            },
        }),
    };
});

describe('CMS MessageTemplates page', () => {
    const templates: MessageTemplateSummary[] = [
        {
            id: 1,
            row_key: 'org-1-all-received',
            organization_id: 1,
            branch_id: null,
            scope_key: 'global',
            order_status: 'received',
            body: 'Halo {{customer_name}}, pesanan laundry Anda telah diterima.',
            is_active: true,
            organization: { id: 1, name: 'Laundry Satu' },
            branch: null,
            is_inherited: false,
        },
        {
            id: 2,
            row_key: 'org-1-b1-received',
            organization_id: 1,
            branch_id: 10,
            scope_key: 'branch:10',
            order_status: 'received',
            body: 'Halo {{customer_name}}, pesanan cabang Kemang diterima.',
            is_active: true,
            organization: { id: 1, name: 'Laundry Satu' },
            branch: { id: 10, name: 'Kemang' },
            is_inherited: false,
        },
        {
            id: 3,
            row_key: 'org-2-all-received',
            organization_id: 2,
            branch_id: null,
            scope_key: 'global',
            order_status: 'received',
            body: 'Halo dari Laundry Dua, pesanan diterima.',
            is_active: true,
            organization: { id: 2, name: 'Laundry Dua' },
            branch: null,
            is_inherited: false,
        },
    ];

    const branches = [
        { id: 10, organization_id: 1, name: 'Kemang' },
        { id: 20, organization_id: 2, name: 'Dago' },
    ];

    const organizations = [
        { id: 1, name: 'Laundry Satu' },
        { id: 2, name: 'Laundry Dua' },
    ];

    const orderStatuses = [
        { value: 'received', label: 'Diterima' },
        { value: 'washing', label: 'Dicuci' },
    ];

    it('renders all templates by default and filters by organization and branch', async () => {
        const wrapper = mount(MessageTemplates, {
            props: {
                messageTemplates: templates,
                branches,
                organizations,
                orderStatuses,
                supportedVariables: ['customer_name'],
            },
            global: {
                stubs: {
                    CmsPageHeader: true,
                    MessageTemplateFormDialog: true,
                    CmsDeleteDialog: true,
                    CmsMetricCard: true,
                    CmsEmptyState: true,
                    Badge: true,
                },
            },
        });

        const orgSelect = wrapper.find('#template-organization-filter');
        const branchSelect = wrapper.find('#template-branch-filter');

        expect(orgSelect.exists()).toBe(true);
        expect(branchSelect.exists()).toBe(true);

        // Initially shows all 3 rows in the table
        expect(wrapper.findAll('tbody tr')).toHaveLength(3);

        // Filter by organization 1
        await orgSelect.setValue('1');
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);

        // Filter by branch 10 (Kemang)
        await branchSelect.setValue('10');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);

        // Filter by global templates only
        await branchSelect.setValue('global');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);

        // Reset branch filter to all
        await branchSelect.setValue('all');
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);

        // Switch to organization 2
        await orgSelect.setValue('2');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
    });

    it('supports global filter for owners with inherited templates and deduplicates statuses', async () => {
        const ownerTemplates: MessageTemplateSummary[] = [
            {
                id: 1,
                row_key: 'b1-washing',
                organization_id: 1,
                branch_id: 10,
                scope_key: 'branch:10',
                order_status: 'washing',
                body: 'Mencuci di kemang',
                is_active: true,
                organization: { id: 1, name: 'Laundry Satu' },
                branch: { id: 10, name: 'Kemang' },
                is_inherited: true,
            },
            {
                id: 1,
                row_key: 'b2-washing',
                organization_id: 1,
                branch_id: 20,
                scope_key: 'branch:20',
                order_status: 'washing',
                body: 'Mencuci di tebet',
                is_active: true,
                organization: { id: 1, name: 'Laundry Satu' },
                branch: { id: 20, name: 'Tebet' },
                is_inherited: true,
            },
            {
                id: 2,
                row_key: 'b1-completed',
                organization_id: 1,
                branch_id: 10,
                scope_key: 'branch:10',
                order_status: 'completed',
                body: 'Selesai khusus kemang',
                is_active: true,
                organization: { id: 1, name: 'Laundry Satu' },
                branch: { id: 10, name: 'Kemang' },
                is_inherited: false,
            },
        ];

        const wrapper = mount(MessageTemplates, {
            props: {
                messageTemplates: ownerTemplates,
                branches: [
                    { id: 10, organization_id: 1, name: 'Kemang' },
                    { id: 20, organization_id: 1, name: 'Tebet' },
                ],
                organizations: [],
                orderStatuses: [
                    { value: 'washing', label: 'Dicuci' },
                    { value: 'completed', label: 'Selesai' },
                ],
                supportedVariables: ['customer_name'],
            },
            global: {
                stubs: {
                    CmsPageHeader: true,
                    MessageTemplateFormDialog: true,
                    CmsDeleteDialog: true,
                    CmsMetricCard: true,
                    CmsEmptyState: true,
                    Badge: true,
                },
            },
        });

        expect(wrapper.findAll('tbody tr')).toHaveLength(3);

        const branchSelect = wrapper.find('#template-branch-filter');
        await branchSelect.setValue('global');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
        expect(wrapper.find('tbody tr').text()).toContain('Seluruh organisasi');
        expect(wrapper.find('tbody tr').text()).not.toContain('Mewarisi template organisasi');
    });
});
