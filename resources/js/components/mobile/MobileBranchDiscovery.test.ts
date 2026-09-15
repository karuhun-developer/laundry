import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent } from 'vue';
import MobileWorkspaceSwitcher from '@/components/mobile/molecules/MobileWorkspaceSwitcher.vue';
import BranchExplorer from '@/components/mobile/organisms/BranchExplorer.vue';
import type { MobileBranch, MobileContext, NearestMobileBranch } from '@/types/mobile';

const branch: MobileBranch = {
    id: 7,
    name: 'Cabang Kemang',
    code: 'KMG01',
    phone: null,
    address: 'Jl. Kemang Raya',
    organization: { id: 1, slug: 'laundryhub-demo' },
    city: { name: 'Jakarta Selatan', province: 'DKI Jakarta' },
    coordinates: { latitude: -6.2607, longitude: 106.8136 },
    delivery: { free_radius_meters: 10000, fee_per_km_rupiah: 3000 },
    payments: { qris_enabled: true },
};

const bandungBranch: MobileBranch = {
    ...branch,
    id: 8,
    name: 'Cabang Dago',
    code: 'DGO01',
    address: 'Jl. Ir. H. Juanda',
    organization: { id: 2, slug: 'bersih-bandung' },
    city: { name: 'Bandung', province: 'Jawa Barat' },
    coordinates: { latitude: -6.8841, longitude: 107.6137 },
};

const nearestBranches: NearestMobileBranch[] = [
    {
        ...bandungBranch,
        city: { id: 2, ...bandungBranch.city },
        coordinates: { latitude: -6.8841, longitude: 107.6137 },
        delivery: {
            ...bandungBranch.delivery,
            distance_meters: 2500,
            within_free_radius: true,
            fee_rupiah: 0,
        },
    },
    {
        ...branch,
        city: { id: 1, ...branch.city },
        coordinates: { latitude: -6.2607, longitude: 106.8136 },
        delivery: {
            ...branch.delivery,
            distance_meters: 12500,
            within_free_radius: false,
            fee_rupiah: 9000,
        },
    },
];

const locationPickerStub = defineComponent({
    props: {
        latitude: { type: String, required: true },
        longitude: { type: String, required: true },
        branchLocations: { type: Array, default: () => [] },
    },
    emits: ['update:latitude', 'update:longitude', 'located', 'branchSelect'],
    template: `
        <div data-test="location-picker-stub">
            <button
                type="button"
                data-test="stub-current-location"
                @click="$emit('located', -6.2607, 106.8136)"
            >
                Current location
            </button>
            <button
                type="button"
                data-test="stub-map-point"
                @click="$emit('update:latitude', '-6.917500'); $emit('update:longitude', '107.619100')"
            >
                Map point
            </button>
            <button
                v-if="branchLocations.length > 0"
                type="button"
                data-test="stub-branch-marker"
                @click="$emit('branchSelect', branchLocations[0].id)"
            >
                Branch marker
            </button>
        </div>
    `,
});

describe('mobile branch discovery', () => {
    it('states the 10 km boundary as free and explains excess-distance fees', () => {
        const boundaryBranch: NearestMobileBranch = {
            ...nearestBranches[0],
            delivery: {
                ...nearestBranches[0].delivery,
                distance_meters: 10000,
                within_free_radius: true,
                fee_rupiah: 0,
            },
        };
        const context: MobileContext = {
            role: 'customer',
            organization: { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
            organizations: [],
            branches: [bandungBranch],
        };
        const boundaryWrapper = mount(MobileWorkspaceSwitcher, {
            props: {
                context,
                nearestBranches: [boundaryBranch],
                selectedBranchId: boundaryBranch.id,
            },
        });
        const excessWrapper = mount(MobileWorkspaceSwitcher, {
            props: {
                context: { ...context, branches: [branch] },
                nearestBranches: [nearestBranches[1]],
                selectedBranchId: branch.id,
            },
        });

        expect(boundaryWrapper.get('[data-test="selected-branch-coverage"]').text()).toBe(
            'Jarak 10 km · gratis jemput & antar dalam radius 10 km.',
        );
        expect(excessWrapper.get('[data-test="selected-branch-coverage"]').text()).toBe(
            'Jarak 12,5 km · radius gratis 10 km · estimasi biaya kelebihan Rp 9.000 (Rp 3.000/km).',
        );
    });

    it('uses the current device location from the map picker', async () => {
        const wrapper = mount(BranchExplorer, {
            props: { branches: [branch], nearestBranches: [], selectedBranchId: branch.id },
            global: { stubs: { LocationPicker: locationPickerStub } },
        });

        expect(wrapper.find('[data-test="map-location"]').exists()).toBe(true);

        await wrapper.get('[data-test="stub-current-location"]').trigger('click');

        expect(wrapper.emitted('locate')?.at(-1)).toEqual([-6.2607, 106.8136]);
    });

    it('hides organizations and ranks customer branch options by distance', () => {
        const context: MobileContext = {
            role: 'customer',
            organization: { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
            organizations: [
                { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
                { id: 2, name: 'Bersih Bandung', slug: 'bersih-bandung' },
            ],
            branches: [branch, bandungBranch],
        };
        const wrapper = mount(MobileWorkspaceSwitcher, {
            props: { context, nearestBranches, selectedBranchId: branch.id },
        });
        const options = wrapper.findAll('option');

        expect(wrapper.findAll('select')).toHaveLength(1);
        expect(options.map((option) => option.attributes('value'))).toEqual(['8', '7']);
        expect(options[0]?.text()).toContain('2,5 km · Gratis jemput & antar');
        expect(options[1]?.text()).toContain('12,5 km · Tambahan Rp 9.000');
        expect(wrapper.text()).not.toContain('Bersih Bandung');
        expect(wrapper.get('[data-test="selected-branch-coverage"]').text()).toBe(
            'Jarak 12,5 km · radius gratis 10 km · estimasi biaya kelebihan Rp 9.000 (Rp 3.000/km).',
        );
    });

    it('shows ranked branches on the map with clear delivery coverage labels', async () => {
        const wrapper = mount(BranchExplorer, {
            props: {
                branches: [branch, bandungBranch],
                nearestBranches,
                selectedBranchId: branch.id,
            },
            global: { stubs: { LocationPicker: locationPickerStub } },
        });
        const picker = wrapper.getComponent(locationPickerStub);

        expect(picker.props('branchLocations')).toEqual([
            {
                id: 8,
                name: 'Cabang Dago',
                latitude: -6.8841,
                longitude: 107.6137,
                freeRadiusMeters: 10000,
                selected: false,
            },
            {
                id: 7,
                name: 'Cabang Kemang',
                latitude: -6.2607,
                longitude: 106.8136,
                freeRadiusMeters: 10000,
                selected: true,
            },
        ]);
        expect(wrapper.get('[data-test="branch-coverage-8"]').text()).toBe(
            'Jarak 2,5 km · gratis jemput & antar dalam radius 10 km.',
        );
        expect(wrapper.get('[data-test="branch-coverage-7"]').text()).toBe(
            'Jarak 12,5 km · radius gratis 10 km · estimasi biaya kelebihan Rp 9.000 (Rp 3.000/km).',
        );

        await wrapper.get('[data-test="stub-branch-marker"]').trigger('click');

        expect(wrapper.emitted('select')?.at(-1)).toEqual([8]);
    });

    it('calculates the nearest branch from a selected map point', async () => {
        const wrapper = mount(BranchExplorer, {
            props: { branches: [branch], nearestBranches: [], selectedBranchId: branch.id },
            global: { stubs: { LocationPicker: locationPickerStub } },
        });

        await wrapper.get('[data-test="stub-map-point"]').trigger('click');
        await wrapper.get('[data-test="submit-map-location"]').trigger('submit');

        expect(wrapper.emitted('locate')?.at(-1)).toEqual([-6.9175, 107.6191]);
    });
});
