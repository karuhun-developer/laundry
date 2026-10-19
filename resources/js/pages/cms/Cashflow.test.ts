import { shallowMount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CashflowEntryDeleteDialog from '@/components/cms/CashflowEntryDeleteDialog.vue';
import CashflowEntryFormDialog from '@/components/cms/CashflowEntryFormDialog.vue';
import Cashflow from '@/pages/cms/Cashflow.vue';
import type { CashflowEntrySummary, CashflowPageProps } from '@/types';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent } = await import('vue');

    return {
        router: { get: vi.fn() },
        Head: defineComponent({ template: '<div><slot /></div>' }),
        usePage: () => ({
            props: { auth: { user: { organization_id: 1, roles: ['superadmin'] } } },
        }),
    };
});

const makeEntry = (
    id: number,
    type: 'debit' | 'credit',
    paymentId: number | null,
): CashflowEntrySummary => ({
    id,
    organization_id: 1,
    branch_id: 10,
    cashflow_category_id: type === 'debit' ? 1 : 2,
    payment_id: paymentId,
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

describe('CMS cashflow page', () => {
    it('offers edit and delete only for manual entries', () => {
        const manualIncome = makeEntry(1, 'debit', null);
        const manualExpense = makeEntry(2, 'credit', null);
        const paymentIncome = makeEntry(3, 'debit', 33);
        const props: CashflowPageProps = {
            statement: {
                year: 2026,
                total_debit: 100000,
                total_credit: 50000,
                net_profit: 50000,
                months: {},
                categories: [],
            },
            entries: [manualIncome, manualExpense, paymentIncome],
            pagination: { current_page: 1, last_page: 1, total: 3 },
            branches: [{ id: 10, organization_id: 1, name: 'Kemang' }],
            organizations: [{ id: 1, name: 'Laundry Satu' }],
            categories: [
                { id: 1, organization_id: 1, name: 'Pemasukan', type: 'debit', group: 'rutin' },
                { id: 2, organization_id: 1, name: 'Operasional', type: 'credit', group: 'rutin' },
            ],
            filters: { year: 2026, branch_id: null, organization_id: null, tab: 'tabel' },
        };
        const wrapper = shallowMount(Cashflow, { props });

        const editableEntryIds = wrapper
            .findAllComponents(CashflowEntryFormDialog)
            .map((dialog) => dialog.props('entry') as CashflowEntrySummary | undefined)
            .filter((entry): entry is CashflowEntrySummary => entry !== undefined)
            .map((entry) => entry.id);
        const deletableEntryIds = wrapper
            .findAllComponents(CashflowEntryDeleteDialog)
            .map((dialog) => (dialog.props('entry') as CashflowEntrySummary).id);

        expect(editableEntryIds).toEqual([1, 2, 1, 2]);
        expect(deletableEntryIds).toEqual([1, 2, 1, 2]);
        expect(editableEntryIds).not.toContain(3);
        expect(wrapper.text()).toContain('Otomatis');
    });
});
