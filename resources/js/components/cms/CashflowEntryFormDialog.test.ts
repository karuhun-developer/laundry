import { shallowMount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import CashflowEntryFormDialog from '@/components/cms/CashflowEntryFormDialog.vue';
import type { CashflowEntrySummary } from '@/types';

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/vue3')>('@inertiajs/vue3');

    return {
        ...actual,
        usePage: () => ({
            props: { auth: { user: { organization_id: 1, roles: ['superadmin'] } } },
        }),
    };
});

const formDialogStub = defineComponent({
    template: '<div><slot name="trigger" /><slot /><slot name="icon" /></div>',
});

const makeEntry = (type: 'debit' | 'credit'): CashflowEntrySummary => ({
    id: type === 'debit' ? 1 : 2,
    organization_id: 1,
    branch_id: 10,
    cashflow_category_id: type === 'debit' ? 1 : 2,
    payment_id: null,
    type,
    amount_rupiah: 50000,
    description: type === 'debit' ? 'Pemasukan manual' : 'Pengeluaran manual',
    entry_date: '2026-10-01',
    created_at: '2026-10-01T08:00:00Z',
    category: {
        id: type === 'debit' ? 1 : 2,
        name: type === 'debit' ? 'Pemasukan' : 'Operasional',
        type,
        group: 'rutin',
    },
    branch: { id: 10, name: 'Kemang' },
});

const mountDialog = (entry: CashflowEntrySummary) =>
    shallowMount(CashflowEntryFormDialog, {
        props: {
            entry,
            branches: [{ id: 10, organization_id: 1, name: 'Kemang' }],
            organizations: [{ id: 1, name: 'Laundry Satu' }],
            categories: [
                {
                    id: 1,
                    organization_id: 1,
                    name: 'Pemasukan',
                    type: 'debit' as const,
                    group: 'rutin' as const,
                },
                {
                    id: 2,
                    organization_id: 1,
                    name: 'Operasional',
                    type: 'credit' as const,
                    group: 'rutin' as const,
                },
            ],
        },
        global: { stubs: { CmsFormDialog: formDialogStub } },
    });

describe('Cashflow entry form dialog', () => {
    it('limits manual income editing to amount and date', () => {
        const wrapper = mountDialog(makeEntry('debit'));

        expect(wrapper.find('#cf-amount').exists()).toBe(true);
        expect(wrapper.find('#cf-date').exists()).toBe(true);
        expect(wrapper.find('#cf-organization').exists()).toBe(false);
        expect(wrapper.find('#cf-branch').exists()).toBe(false);
        expect(wrapper.find('#cf-type').exists()).toBe(false);
        expect(wrapper.find('#cf-category').exists()).toBe(false);
        expect(wrapper.find('#cf-description').exists()).toBe(false);
    });

    it('allows all manual expense fields to be edited', () => {
        const wrapper = mountDialog(makeEntry('credit'));

        expect(wrapper.find('#cf-organization').exists()).toBe(true);
        expect(wrapper.find('#cf-branch').exists()).toBe(true);
        expect(wrapper.find('#cf-type').exists()).toBe(true);
        expect(wrapper.find('#cf-category').exists()).toBe(true);
        expect(wrapper.find('#cf-amount').exists()).toBe(true);
        expect(wrapper.find('#cf-description').exists()).toBe(true);
        expect(wrapper.find('#cf-date').exists()).toBe(true);
    });
});
