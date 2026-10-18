import { shallowMount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CmsDeleteDialog from '@/components/cms/CmsDeleteDialog.vue';
import Branches from '@/pages/cms/Branches.vue';
import type { BranchSummary } from '@/types';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent } = await import('vue');

    return {
        Head: defineComponent({ template: '<div><slot /></div>' }),
        usePage: () => ({
            props: {
                auth: {
                    user: { organization_id: null, roles: ['superadmin'] },
                },
            },
        }),
    };
});

describe('CMS branches page', () => {
    it('uses the exact branch name required by deletion confirmation', () => {
        const branch: BranchSummary = {
            id: 7,
            organization_id: 1,
            city_id: 1,
            name: 'Cabang Kemang',
            code: 'KMG01',
            phone: '6281234567890',
            address: 'Jl. Kemang Raya No. 10',
            latitude: -6.2607,
            longitude: 106.8136,
            free_delivery_radius_meters: 10000,
            delivery_fee_per_km_rupiah: 3000,
            qris_enabled: true,
            qris_api_key: null,
            qris_base_url: null,
            is_active: true,
            city: { id: 1, name: 'Jakarta Selatan', province: 'DKI Jakarta' },
            organization: { id: 1, name: 'LaundryHub Demo' },
        };
        const wrapper = shallowMount(Branches, {
            props: {
                branches: [branch],
                cities: [],
                organizations: [branch.organization],
            },
        });

        const deleteDialogs = wrapper
            .findAllComponents(CmsDeleteDialog)
            .filter((dialog) => dialog.props('title') === 'Hapus cabang?');

        expect(deleteDialogs).toHaveLength(2);
        expect(deleteDialogs.map((dialog) => dialog.props('confirmation'))).toEqual([
            branch.name,
            branch.name,
        ]);
        expect(deleteDialogs.map((dialog) => dialog.props('confirmationLabel'))).toEqual([
            'Nama cabang',
            'Nama cabang',
        ]);
    });
});
