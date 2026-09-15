import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import CustomerCredentialDialog from '@/components/mobile/molecules/CustomerCredentialDialog.vue';
import CustomerFormDialog from '@/components/mobile/molecules/CustomerFormDialog.vue';
import CustomerResetPinDialog from '@/components/mobile/molecules/CustomerResetPinDialog.vue';
import MobileBottomNav from '@/components/mobile/molecules/MobileBottomNav.vue';
import CustomerDirectory from '@/components/mobile/organisms/CustomerDirectory.vue';
import OrderComposer from '@/components/mobile/organisms/OrderComposer.vue';
import { MobileCustomerValidationError } from '@/composables/useMobileCustomerApi';
import MobileApp from '@/pages/mobile/App.vue';
import type {
    MobileBranch,
    MobileCustomer,
    MobileCustomerMutationResult,
    MobilePriceQuote,
    MobileService,
} from '@/types/mobile';

const mobileWorkspace = vi.hoisted(() => ({
    context: {
        value: {
            role: 'branch_admin',
            organization: { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
            organizations: [],
            branches: [],
        },
    },
    loading: { value: false },
    refreshing: { value: false },
    requestError: { value: null },
    rollbackMessage: { value: null },
    orders: { value: [] },
    customers: { value: [] },
    nearestBranches: { value: [] },
    services: { value: [] },
    selectedBranch: { value: null },
    selectedBranchId: { value: null },
    selectedBranchDeliveryFee: { value: 0 },
    selectedCustomerId: { value: null },
    whatsAppSuggestion: { value: null },
    whatsAppSuggestionError: { value: null },
    createProcessing: { value: false },
    pricingProcessing: { value: false },
    transitionProcessing: { value: false },
    paymentProcessing: { value: false },
    printer: {
        available: false,
        profile: { value: null },
        devices: { value: [] },
        discovering: { value: false },
        printing: { value: false },
        message: { value: null },
        error: { value: null },
        discover: vi.fn(),
        saveProfile: vi.fn(),
        clearProfile: vi.fn(),
        testPrint: vi.fn(),
    },
    paymentActions: {},
    load: vi.fn(),
    refresh: vi.fn(),
    selectBranch: vi.fn(),
    selectCustomer: vi.fn(),
    findNearestBranches: vi.fn(),
    quoteOrder: vi.fn(),
    confirmOrderPricing: vi.fn(),
    createOrder: vi.fn(),
    transitionOrder: vi.fn(),
    createCustomer: vi.fn(),
    updateCustomer: vi.fn(),
    resetCustomerPin: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ template: '<span />' }),
    useHttp: vi.fn(),
    usePage: () => ({ props: { auth: { user: { name: 'Admin Cabang', email: null } } } }),
}));
vi.mock('@/composables/useMobileWorkspace', () => ({
    useMobileWorkspace: () => mobileWorkspace,
}));

const passThrough = defineComponent({
    template: '<div><slot /></div>',
});
const dialogRootStub = defineComponent({
    name: 'DialogRootStub',
    props: { open: Boolean },
    emits: ['update:open'],
    template: '<div><slot /></div>',
});
const dialogContentStub = defineComponent({
    name: 'DialogContentStub',
    emits: ['closeAutoFocus', 'escapeKeyDown', 'interactOutside'],
    template: '<div data-test="dialog-content"><slot /></div>',
});
const dialogStubs = {
    Dialog: dialogRootStub,
    DialogClose: passThrough,
    DialogContent: dialogContentStub,
    DialogDescription: passThrough,
    DialogFooter: passThrough,
    DialogHeader: passThrough,
    DialogTitle: passThrough,
};

const customer: MobileCustomer = {
    id: 21,
    name: 'Budi Santoso',
    email: null,
    whatsapp_phone: '6281234567890',
    must_change_pin: true,
    pin_expires_at: '2026-09-15T09:00:00+07:00',
};

const mutationResult: MobileCustomerMutationResult = {
    customer,
    credential: null,
};

afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('mobile customer management dialogs', () => {
    it('shows the required create fields and submits only profile data', async () => {
        const submit = vi.fn(async (): Promise<MobileCustomerMutationResult> => mutationResult);
        const wrapper = mount(CustomerFormDialog, {
            props: { open: true, mode: 'create', submit },
            global: { stubs: dialogStubs },
        });

        expect(wrapper.get('#customer-name').attributes('required')).toBeDefined();
        expect(wrapper.get('#customer-whatsapp-phone').attributes('required')).toBeDefined();
        expect(wrapper.find('input[type="password"]').exists()).toBe(false);

        await wrapper.get('#customer-name').setValue('Budi Santoso');
        await wrapper.get('#customer-whatsapp-phone').setValue('0812 3456 7890');
        await wrapper.get('[data-test="customer-form"]').trigger('submit');
        await flushPromises();

        expect(submit).toHaveBeenCalledWith({
            name: 'Budi Santoso',
            whatsapp_phone: '0812 3456 7890',
        });
        expect(wrapper.emitted('saved')?.[0]).toEqual([mutationResult]);
    });

    it('prefills and submits an edited customer profile', async () => {
        const updated = { ...customer, name: 'Budi Setiawan' };
        const submit = vi.fn(
            async (): Promise<MobileCustomerMutationResult> => ({
                customer: updated,
                credential: null,
            }),
        );
        const wrapper = mount(CustomerFormDialog, {
            props: { open: true, mode: 'edit', customer, submit },
            global: { stubs: dialogStubs },
        });

        expect(wrapper.get<HTMLInputElement>('#customer-name').element.value).toBe(customer.name);
        expect(wrapper.get<HTMLInputElement>('#customer-whatsapp-phone').element.value).toBe(
            customer.whatsapp_phone,
        );

        await wrapper.get('#customer-name').setValue('Budi Setiawan');
        await wrapper.get('[data-test="customer-form"]').trigger('submit');
        await flushPromises();

        expect(submit).toHaveBeenCalledWith({
            name: 'Budi Setiawan',
            whatsapp_phone: customer.whatsapp_phone,
        });
    });

    it('associates each profile field with its own validation error and the phone hint', async () => {
        const submit = vi.fn(async (): Promise<MobileCustomerMutationResult> => {
            throw new MobileCustomerValidationError('Periksa kembali data pelanggan.', {
                name: 'Nama pelanggan wajib diisi.',
                whatsapp_phone: 'Nomor WhatsApp tidak valid.',
            });
        });
        const wrapper = mount(CustomerFormDialog, {
            props: { open: true, mode: 'create', submit },
            global: { stubs: dialogStubs },
        });

        await wrapper.get('[data-test="customer-form"]').trigger('submit');
        await flushPromises();

        expect(wrapper.get('#customer-name').attributes()).toMatchObject({
            'aria-invalid': 'true',
            'aria-describedby': 'customer-name-error',
        });
        expect(wrapper.get('#customer-whatsapp-phone').attributes()).toMatchObject({
            'aria-invalid': 'true',
            'aria-describedby': 'customer-whatsapp-phone-hint customer-whatsapp-phone-error',
        });
        expect(wrapper.get('#customer-name-error').text()).toContain('wajib diisi');
        expect(wrapper.get('#customer-whatsapp-phone-error').text()).toContain('tidak valid');
    });

    it('blocks every dismissal path while a profile save is processing', async () => {
        let resolveSubmit: (result: MobileCustomerMutationResult) => void = () => undefined;
        const submit = vi.fn(
            () =>
                new Promise<MobileCustomerMutationResult>((resolve) => {
                    resolveSubmit = resolve;
                }),
        );
        const wrapper = mount(CustomerFormDialog, {
            props: { open: true, mode: 'create', submit },
            global: { stubs: dialogStubs },
        });
        await wrapper.get('[data-test="customer-form"]').trigger('submit');
        const preventEscape = vi.fn();
        const preventOutside = vi.fn();

        wrapper.findComponent(dialogRootStub).vm.$emit('update:open', false);
        wrapper
            .findComponent(dialogContentStub)
            .vm.$emit('escapeKeyDown', { preventDefault: preventEscape });
        wrapper
            .findComponent(dialogContentStub)
            .vm.$emit('interactOutside', { preventDefault: preventOutside });
        await wrapper.get('[data-test="cancel-customer-form"]').trigger('click');

        expect(wrapper.emitted('update:open')).toBeUndefined();
        expect(preventEscape).toHaveBeenCalledOnce();
        expect(preventOutside).toHaveBeenCalledOnce();
        resolveSubmit(mutationResult);
        await flushPromises();
    });

    it('does not close or emit when an older profile response finishes in a newer dialog', async () => {
        let resolveSubmit: (result: MobileCustomerMutationResult) => void = () => undefined;
        const submit = vi.fn(
            () =>
                new Promise<MobileCustomerMutationResult>((resolve) => {
                    resolveSubmit = resolve;
                }),
        );
        const wrapper = mount(CustomerFormDialog, {
            props: { open: true, mode: 'edit', customer, submit },
            global: { stubs: dialogStubs },
        });
        await wrapper.get('[data-test="customer-form"]').trigger('submit');

        await wrapper.setProps({ customer: { ...customer, id: 22, name: 'Customer Baru' } });
        resolveSubmit(mutationResult);
        await flushPromises();

        expect(wrapper.emitted('saved')).toBeUndefined();
        expect(wrapper.emitted('update:open')).toBeUndefined();
        expect(wrapper.get<HTMLInputElement>('#customer-name').element.value).toBe('Customer Baru');
    });

    it('shows a reset credential once, supports copy, and clears it when dismissed', async () => {
        const writeText = vi.fn(async (): Promise<void> => undefined);
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        });
        const wrapper = mount(CustomerCredentialDialog, {
            props: {
                open: true,
                customerName: customer.name,
                credential: {
                    temporary_pin: '483921',
                    expires_at: '2026-09-15T09:00:00+07:00',
                },
            },
            global: { stubs: dialogStubs },
        });

        expect(wrapper.text()).toContain('483921');
        expect(wrapper.text()).toContain('Berlaku sampai');
        await wrapper.get('[data-test="copy-temporary-pin"]').trigger('click');
        expect(writeText).toHaveBeenCalledWith('483921');

        await wrapper.get('[data-test="dismiss-temporary-pin"]').trigger('click');
        expect(wrapper.text()).not.toContain('483921');
        expect(wrapper.emitted('dismiss')).toHaveLength(1);
    });

    it('requests a PIN reset from an accessible confirmation dialog', async () => {
        const reset = vi.fn(async () => ({
            customer,
            credential: {
                temporary_pin: '731204',
                expires_at: '2026-09-15T11:00:00+07:00',
            },
        }));
        const wrapper = mount(CustomerResetPinDialog, {
            props: { open: true, customer, reset },
            global: { stubs: dialogStubs },
        });

        await wrapper.get('[data-test="confirm-reset-pin"]').trigger('click');
        await flushPromises();

        expect(reset).toHaveBeenCalledWith(customer.id);
        expect(wrapper.emitted('reset')?.[0]?.[0]).toMatchObject({
            customer,
            credential: { temporary_pin: '731204' },
        });
    });
});

describe('mobile customer surfaces', () => {
    it('surfaces PIN state and customer actions without rendering plaintext PIN data', async () => {
        const customerWithUnexpectedSecret = { ...customer, temporary_pin: 'do-not-render' };
        const wrapper = mount(CustomerDirectory, {
            props: { customers: [customerWithUnexpectedSecret] },
        });

        expect(wrapper.text()).toContain('PIN sementara');
        expect(wrapper.text()).toContain('15 Sep 2026');
        expect(wrapper.text()).not.toContain('do-not-render');

        await wrapper.get('[data-test="edit-customer-21"]').trigger('click');
        await wrapper.get('[data-test="reset-customer-pin-21"]').trigger('click');

        expect(wrapper.emitted('edit')?.[0]).toEqual([customerWithUnexpectedSecret]);
        expect(wrapper.emitted('reset-pin')?.[0]).toEqual([customerWithUnexpectedSecret]);
    });

    it('provides a stable focus target for destructive-action completion', () => {
        const wrapper = mount(CustomerDirectory, { props: { customers: [customer] } });

        expect(wrapper.get('#customer-directory-title').attributes('tabindex')).toBe('-1');
    });

    it('announces customer successes without exposing the temporary PIN', async () => {
        const wrapper = mount(MobileApp, {
            global: {
                stubs: {
                    BranchExplorer: true,
                    CustomerCredentialDialog: true,
                    CustomerDirectory: true,
                    CustomerFormDialog: true,
                    CustomerResetPinDialog: true,
                    MobileAccount: true,
                    MobileBottomNav: true,
                    MobileWorkspaceSwitcher: true,
                    OrderComposer: true,
                    OrderListPanel: true,
                    WhatsAppSuggestionDialog: true,
                },
            },
        });
        const credentialResult = {
            customer,
            credential: {
                temporary_pin: '731204',
                expires_at: '2026-09-15T11:00:00+07:00',
            },
        };

        wrapper.findComponent(CustomerFormDialog).vm.$emit('saved', credentialResult);
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[data-test="customer-status"]').text()).toBe(
            'Pelanggan Budi Santoso berhasil dibuat.',
        );
        expect(wrapper.get('[data-test="customer-status"]').text()).not.toContain('731204');

        wrapper.findComponent(CustomerFormDialog).vm.$emit('saved', mutationResult);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('[data-test="customer-status"]').text()).toBe(
            'Pelanggan Budi Santoso berhasil dihubungkan.',
        );

        wrapper.findComponent(MobileBottomNav).vm.$emit('update:modelValue', 'customers');
        await wrapper.vm.$nextTick();
        wrapper.findComponent(CustomerDirectory).vm.$emit('edit', customer);
        wrapper.findComponent(CustomerFormDialog).vm.$emit('saved', mutationResult);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('[data-test="customer-status"]').text()).toBe(
            'Pelanggan Budi Santoso berhasil diperbarui.',
        );

        wrapper.findComponent(CustomerResetPinDialog).vm.$emit('reset', credentialResult);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('[data-test="customer-status"]').text()).toBe(
            'PIN pelanggan Budi Santoso berhasil direset.',
        );
    });

    it('places the create action beside the branch-admin selector and accepts authoritative selection', async () => {
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
        const services: MobileService[] = [];
        const quoteOrder = vi.fn(async (): Promise<MobilePriceQuote> => {
            throw new Error('not used');
        });
        const wrapper = mount(OrderComposer, {
            props: {
                role: 'branch_admin',
                branch,
                services,
                customers: [customer],
                selectedCustomerId: null,
                deliveryCoordinates: null,
                quoteOrder,
            },
        });

        await wrapper.get('[data-test="create-customer-from-order"]').trigger('click');
        expect(wrapper.emitted('create-customer')).toHaveLength(1);

        await wrapper.setProps({ selectedCustomerId: customer.id });
        expect(wrapper.get<HTMLSelectElement>('[data-test="customer-select"]').element.value).toBe(
            String(customer.id),
        );
    });
});
