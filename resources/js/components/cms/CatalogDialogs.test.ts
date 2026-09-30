import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, reactive } from 'vue';
import PriceOptionFormDialog from '@/components/cms/PriceOptionFormDialog.vue';
import ServiceFormDialog from '@/components/cms/ServiceFormDialog.vue';
import type { CatalogBranchSummary, ServiceSummary } from '@/types';

type SubmitOptions = {
    onSuccess?: () => void;
};

type MockForm = Record<string, unknown> & {
    errors: Record<string, string>;
    processing: boolean;
    clearErrors: () => void;
    optimistic: (callback: (page: unknown) => unknown) => MockForm;
    reset: () => void;
    setError: (field: string, message: string) => void;
    submit: (route: unknown, options?: SubmitOptions) => void;
};

const inertiaHarness = vi.hoisted(() => {
    let submittedData: Record<string, unknown> | undefined;
    let optimisticUpdate: ((page: unknown) => unknown) | undefined;

    return {
        record(data: Record<string, unknown>): void {
            submittedData = data;
        },
        recordOptimistic(callback: (page: unknown) => unknown): void {
            optimisticUpdate = callback;
        },
        optimisticData(page: unknown): unknown {
            return optimisticUpdate?.(page);
        },
        reset(): void {
            submittedData = undefined;
            optimisticUpdate = undefined;
        },
        submittedData(): Record<string, unknown> | undefined {
            return submittedData;
        },
    };
});

vi.mock('@inertiajs/vue3', () => ({
    useForm: (values: Record<string, unknown>): MockForm => {
        const defaults = structuredClone(values);
        const fieldNames = Object.keys(values);
        const form: MockForm = {
            ...structuredClone(values),
            errors: {},
            processing: false,
            clearErrors: () => {
                form.errors = {};
            },
            optimistic: (callback) => {
                inertiaHarness.recordOptimistic(callback);
                return form;
            },
            reset: () => {
                Object.assign(form, structuredClone(defaults));
            },
            setError: (field, message) => {
                form.errors[field] = message;
            },
            submit: (_route, options) => {
                inertiaHarness.record(
                    Object.fromEntries(fieldNames.map((field) => [field, form[field]])),
                );
                options?.onSuccess?.();
            },
        };

        return reactive(form);
    },
}));

const passThrough = defineComponent({
    template: '<div><slot /></div>',
});
const dialogStubs = {
    Dialog: passThrough,
    DialogClose: passThrough,
    DialogContent: passThrough,
    DialogDescription: passThrough,
    DialogFooter: passThrough,
    DialogHeader: passThrough,
    DialogTitle: passThrough,
    DialogTrigger: passThrough,
};

const service: ServiceSummary = {
    id: 21,
    organization_id: 1,
    name: 'Cuci Kering Lipat',
    code: 'CKL',
    description: 'Layanan reguler',
    is_active: true,
    organization: { id: 1, name: 'LaundryHub Demo' },
    price_options: [],
};

const branches: CatalogBranchSummary[] = [
    {
        id: 11,
        organization_id: 1,
        name: 'Kemang',
        code: 'KMG',
        is_active: true,
        organization: { id: 1, name: 'LaundryHub Demo' },
    },
    {
        id: 12,
        organization_id: 1,
        name: 'Cabang Nonaktif',
        code: 'OFF',
        is_active: false,
        organization: { id: 1, name: 'LaundryHub Demo' },
    },
    {
        id: 13,
        organization_id: 2,
        name: 'Bandung',
        code: 'BDG',
        is_active: true,
        organization: { id: 2, name: 'Laundry Bandung' },
    },
];

afterEach(() => {
    inertiaHarness.reset();
});

describe('catalog dialogs', () => {
    it('updates the completion duration optimistically when editing a service', async () => {
        const existingService = { ...service, completion_duration_hours: 72 };
        const wrapper = mount(ServiceFormDialog, {
            props: {
                service: existingService,
                organizations: [{ id: 1, name: 'LaundryHub Demo' }],
                currentOrganizationId: 1,
                isSuperadmin: false,
            },
            global: { stubs: dialogStubs },
        });

        await wrapper.get('#service-duration').setValue('24');
        await wrapper.get('[data-test="service-form"]').trigger('submit');

        expect(inertiaHarness.optimisticData({ services: [existingService] })).toMatchObject({
            services: [{ id: existingService.id, completion_duration_hours: 24 }],
        });
    });

    it('shows only active branches owned by the service organization', () => {
        const wrapper = mount(PriceOptionFormDialog, {
            props: { service, branches },
            global: { stubs: dialogStubs },
        });

        const branchSelect = wrapper.get('#price-option-branch');

        expect(branchSelect.text()).toContain('Semua cabang');
        expect(branchSelect.text()).toContain('Kemang');
        expect(branchSelect.text()).not.toContain('Cabang Nonaktif');
        expect(branchSelect.text()).not.toContain('Bandung');
    });

    it('defaults to all branches and converts fixed-package kilograms to grams', async () => {
        const wrapper = mount(PriceOptionFormDialog, {
            props: { service, branches: [] },
            global: { stubs: dialogStubs },
        });

        await wrapper.get('#price-option-name').setValue('Paket Hemat 12,5 kg');
        await wrapper.get('#price-option-type').setValue('fixed_package');
        await wrapper.get('#package-weight').setValue('12.5');
        await wrapper.get('#package-base-price').setValue('12000');
        await wrapper.get('#package-excess-price').setValue('5000');
        await wrapper.get('[data-test="price-option-form"]').trigger('submit');

        expect(inertiaHarness.submittedData()).toEqual({
            branch_id: null,
            type: 'fixed_package',
            name: 'Paket Hemat 12,5 kg',
            package_weight_grams: '12500',
            base_price_rupiah: 12000,
            price_per_kilogram_rupiah: 5000,
            min_price_per_kilogram_rupiah: '',
            minimum_chargeable_grams: '',
            is_active: true,
        });
    });

    it('still supports a price for one selected branch and floor price', async () => {
        const wrapper = mount(PriceOptionFormDialog, {
            props: { service, branches },
            global: { stubs: dialogStubs },
        });

        await wrapper.get('#price-option-branch').setValue('11');
        await wrapper.get('#price-option-name').setValue('Kiloan Kemang');
        await wrapper.get('#kilogram-price').setValue('15000');
        await wrapper.get('#kilogram-min-price').setValue('12000');

        expect(wrapper.get<HTMLInputElement>('#kilogram-price').element.value).toBe('15.000');
        expect(wrapper.get<HTMLInputElement>('#kilogram-min-price').element.value).toBe('12.000');

        await wrapper.get('[data-test="price-option-form"]').trigger('submit');

        expect(inertiaHarness.submittedData()).toMatchObject({
            branch_id: 11,
            name: 'Kiloan Kemang',
            price_per_kilogram_rupiah: 15000,
            min_price_per_kilogram_rupiah: 12000,
        });
    });
});
