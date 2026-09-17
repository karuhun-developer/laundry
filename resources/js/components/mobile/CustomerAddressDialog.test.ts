import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import CustomerAddressDialog from '@/components/mobile/organisms/CustomerAddressDialog.vue';
import { MobileCustomerAddressValidationError } from '@/composables/useMobileCustomerAddressApi';
import type { MobileCustomerAddress, MobileCustomerAddressPayload } from '@/types/mobile';

const geolocation = vi.hoisted(() => ({ locate: vi.fn() }));
const toast = vi.hoisted(() => ({ error: vi.fn() }));

vi.mock('@/composables/useGeolocation', () => ({
    useGeolocation: () => ({
        coordinates: { value: null },
        error: { value: null },
        locating: { value: false },
        locate: geolocation.locate,
    }),
}));
vi.mock('vue-sonner', () => ({ toast }));

const dialogStub = defineComponent({
    name: 'DialogStub',
    props: { open: Boolean },
    emits: ['update:open'],
    template: '<div v-if="open"><slot /></div>',
});
const passThroughStub = defineComponent({ template: '<div><slot /></div>' });
const locationPickerStub = defineComponent({
    name: 'LocationPicker',
    props: { latitude: String, longitude: String },
    emits: ['update:latitude', 'update:longitude'],
    template: '<div data-test="location-picker" />',
});

const homeAddress: MobileCustomerAddress = {
    id: 41,
    label: 'Rumah',
    recipient_name: 'Budi Santoso',
    whatsapp_phone: '6281234567890',
    address: 'Jl. Melati No. 10, Jakarta Selatan',
    notes: 'Pagar biru',
    latitude: -6.261493,
    longitude: 106.8106,
    is_default: true,
    created_at: '2026-09-15T07:00:00+07:00',
    updated_at: '2026-09-15T07:00:00+07:00',
};
const officeAddress: MobileCustomerAddress = {
    ...homeAddress,
    id: 42,
    label: 'Kantor',
    address: 'Jl. Gatot Subroto No. 20, Jakarta Selatan',
    is_default: false,
};

const createAddress =
    vi.fn<(payload: MobileCustomerAddressPayload) => Promise<MobileCustomerAddress>>();
const updateAddress =
    vi.fn<
        (addressId: number, payload: MobileCustomerAddressPayload) => Promise<MobileCustomerAddress>
    >();
const deleteAddress = vi.fn<(addressId: number) => Promise<void>>();
const setDefaultAddress = vi.fn<(addressId: number) => Promise<MobileCustomerAddress>>();
const selectAddress = vi.fn<(addressId: number) => Promise<boolean>>();

const mountDialog = (addresses: readonly MobileCustomerAddress[] = []): VueWrapper =>
    mount(CustomerAddressDialog, {
        props: {
            open: true,
            addresses,
            selectedAddressId: addresses[0]?.id ?? null,
            customerName: 'Budi Santoso',
            customerPhone: '6281234567890',
            createAddress,
            updateAddress,
            deleteAddress,
            setDefaultAddress,
            selectAddress,
        },
        global: {
            stubs: {
                Dialog: dialogStub,
                DialogContent: passThroughStub,
                DialogDescription: passThroughStub,
                DialogHeader: passThroughStub,
                DialogTitle: passThroughStub,
                LocationPicker: locationPickerStub,
            },
        },
    });

beforeEach(() => {
    vi.clearAllMocks();
    geolocation.locate.mockResolvedValue({ latitude: -6.261493, longitude: 106.8106 });
    createAddress.mockResolvedValue(homeAddress);
    updateAddress.mockResolvedValue(homeAddress);
    deleteAddress.mockResolvedValue();
    setDefaultAddress.mockResolvedValue({ ...officeAddress, is_default: true });
    selectAddress.mockResolvedValue(true);
});

afterEach(() => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
});

describe('customer pickup address dialog', () => {
    it('shows the empty state and fills a new address with the current coordinates', async () => {
        const wrapper = mountDialog();

        expect(wrapper.text()).toContain('Belum ada alamat tersimpan');
        await wrapper.get('[data-test="add-address"]').trigger('click');
        await flushPromises();

        expect(geolocation.locate).toHaveBeenCalledOnce();
        expect(wrapper.findComponent(locationPickerStub).props()).toMatchObject({
            latitude: '-6.261493',
            longitude: '106.810600',
        });
    });

    it('saves the completed address and reports success to the mobile app', async () => {
        const wrapper = mountDialog();
        await wrapper.get('[data-test="add-address"]').trigger('click');
        await flushPromises();
        await wrapper.get('#address-label').setValue('Rumah');
        await wrapper.get('#address-recipient').setValue('Budi Santoso');
        await wrapper.get('#address-phone').setValue('0812 3456 7890');
        await wrapper.get('#address-text').setValue('Jl. Melati No. 10, Jakarta Selatan');
        await wrapper.get('#address-notes').setValue('Pagar biru');

        await wrapper.get('[data-test="address-form"]').trigger('submit');
        await flushPromises();

        expect(createAddress).toHaveBeenCalledWith({
            label: 'Rumah',
            recipient_name: 'Budi Santoso',
            whatsapp_phone: '0812 3456 7890',
            address: 'Jl. Melati No. 10, Jakarta Selatan',
            notes: 'Pagar biru',
            latitude: -6.261493,
            longitude: 106.8106,
            is_default: true,
        });
        expect(wrapper.emitted('success')?.[0]).toEqual([
            'Alamat Rumah berhasil disimpan dan dipilih.',
        ]);
    });

    it('keeps the device location movable before saving the first address', async () => {
        const wrapper = mountDialog();
        await wrapper.get('[data-test="add-address"]').trigger('click');
        await flushPromises();

        const picker = wrapper.findComponent(locationPickerStub);
        picker.vm.$emit('update:latitude', '-6.917500');
        picker.vm.$emit('update:longitude', '107.619100');
        await wrapper.get('#address-text').setValue('Jl. Titik Pilihan No. 8');
        await wrapper.get('[data-test="address-form"]').trigger('submit');
        await flushPromises();

        expect(createAddress).toHaveBeenCalledWith(
            expect.objectContaining({
                latitude: -6.9175,
                longitude: 107.6191,
            }),
        );
    });

    it('renders server validation next to the corresponding address fields', async () => {
        createAddress.mockRejectedValue(
            new MobileCustomerAddressValidationError('Periksa kembali alamat penjemputan.', {
                address: 'Alamat lengkap wajib diisi.',
                latitude: 'Titik penjemputan wajib dipilih.',
            }),
        );
        const wrapper = mountDialog();
        await wrapper.get('[data-test="add-address"]').trigger('click');
        await flushPromises();

        await wrapper.get('[data-test="address-form"]').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Alamat lengkap wajib diisi.');
        expect(wrapper.text()).toContain('Titik penjemputan wajib dipilih.');
        expect(toast.error).toHaveBeenCalledWith('Periksa kembali alamat penjemputan.');
    });

    it('selects an address and can promote another address to default', async () => {
        const wrapper = mountDialog([homeAddress, officeAddress]);
        const primaryCard = wrapper.get(`[data-test="address-card-${homeAddress.id}"]`);
        const primaryBadge = wrapper.get(`[data-test="primary-address-badge-${homeAddress.id}"]`);

        expect(primaryCard.classes()).toContain('border-brand-600/40');
        expect(primaryBadge.text()).toBe('Alamat utama');
        expect(primaryBadge.classes()).toContain('bg-brand-100');

        await wrapper.get(`[data-test="default-address-${officeAddress.id}"]`).trigger('click');
        await flushPromises();
        await wrapper.get(`[data-test="select-address-${officeAddress.id}"]`).trigger('click');
        await flushPromises();

        expect(selectAddress).toHaveBeenCalledWith(officeAddress.id);
        expect(setDefaultAddress).toHaveBeenCalledWith(officeAddress.id);
        expect(wrapper.emitted('update:open')).toContainEqual([false]);
        expect(wrapper.emitted('success')).toEqual([
            ['Kantor sekarang menjadi alamat utama.'],
            ['Alamat Kantor dipilih untuk penjemputan.'],
        ]);
    });

    it('uses an in-dialog verification step before deleting without window.confirm', async () => {
        const confirm = vi.fn();
        vi.stubGlobal('confirm', confirm);
        const wrapper = mountDialog([homeAddress, officeAddress]);

        await wrapper.get(`[data-test="delete-address-${officeAddress.id}"]`).trigger('click');

        expect(wrapper.get('[data-test="delete-address-verification"]').text()).toContain(
            'Hapus alamat Kantor?',
        );
        expect(deleteAddress).not.toHaveBeenCalled();
        await wrapper.get('[data-test="confirm-delete-address"]').trigger('click');
        await flushPromises();

        expect(confirm).not.toHaveBeenCalled();
        expect(deleteAddress).toHaveBeenCalledWith(officeAddress.id);
        expect(wrapper.emitted('success')?.at(-1)).toEqual(['Alamat Kantor berhasil dihapus.']);
    });
});
