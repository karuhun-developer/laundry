import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    MobileCustomerAddressValidationError,
    useMobileCustomerAddressApi,
} from '@/composables/useMobileCustomerAddressApi';
import { MobileHttpError } from '@/lib/mobileHttp';
import type { MobileCustomerAddress, MobileCustomerAddressPayload } from '@/types/mobile';

const mockedUseHttp = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({ useHttp: mockedUseHttp }));
vi.mock('@/routes/api/v1/mobile/account/addresses', () => ({
    defaultMethod: (addressId: number) => ({ url: `/sentinel/addresses/${addressId}/default` }),
    destroy: (addressId: number) => ({ url: `/sentinel/addresses/${addressId}/delete` }),
    index: () => ({ url: '/sentinel/addresses' }),
    store: () => ({ url: '/sentinel/addresses/store' }),
    update: (addressId: number) => ({ url: `/sentinel/addresses/${addressId}/update` }),
}));

const address: MobileCustomerAddress = {
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

const payload: MobileCustomerAddressPayload = {
    label: address.label,
    recipient_name: address.recipient_name,
    whatsapp_phone: address.whatsapp_phone,
    address: address.address,
    notes: address.notes,
    latitude: address.latitude,
    longitude: address.longitude,
    is_default: address.is_default,
};

const emptyRequest = () => {
    const request = {
        response: null as unknown,
        errors: {} as Record<string, string | string[]>,
        hasErrors: false,
        clearErrors: vi.fn(() => {
            request.errors = {};
            request.hasErrors = false;
        }),
        cancel: vi.fn(),
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        put: vi.fn(),
    };

    return request;
};

const payloadRequest = () => ({
    ...emptyRequest(),
    label: '',
    recipient_name: '',
    whatsapp_phone: '',
    address: '',
    notes: null as string | null,
    latitude: 0,
    longitude: 0,
    is_default: false,
});

beforeEach(() => {
    mockedUseHttp.mockReset();
});

describe('mobile customer address API', () => {
    it('uses generated Wayfinder endpoints for every address operation', async () => {
        const list = emptyRequest();
        const create = payloadRequest();
        const update = payloadRequest();
        const remove = emptyRequest();
        const makeDefault = emptyRequest();
        mockedUseHttp
            .mockReturnValueOnce(list)
            .mockReturnValueOnce(create)
            .mockReturnValueOnce(update)
            .mockReturnValueOnce(remove)
            .mockReturnValueOnce(makeDefault);
        list.get.mockResolvedValue({ data: [address] });
        create.post.mockResolvedValue({ data: address });
        update.patch.mockResolvedValue({ data: { ...address, label: 'Kantor' } });
        remove.delete.mockResolvedValue(undefined);
        makeDefault.put.mockResolvedValue({ data: address });
        const api = useMobileCustomerAddressApi();

        await expect(api.loadAddresses()).resolves.toEqual([address]);
        await expect(api.createAddress(payload)).resolves.toEqual(address);
        await expect(api.updateAddress(address.id, payload)).resolves.toMatchObject({
            label: 'Kantor',
        });
        await expect(api.deleteAddress(address.id)).resolves.toBeUndefined();
        await expect(api.setDefaultAddress(address.id)).resolves.toEqual(address);

        expect(list.cancel).toHaveBeenCalledOnce();
        expect(list.get).toHaveBeenCalledWith('/sentinel/addresses');
        expect(create.post).toHaveBeenCalledWith('/sentinel/addresses/store', {
            onHttpException: expect.any(Function),
        });
        expect(update.patch).toHaveBeenCalledWith(`/sentinel/addresses/${address.id}/update`, {
            onHttpException: expect.any(Function),
        });
        expect(remove.delete).toHaveBeenCalledWith(`/sentinel/addresses/${address.id}/delete`, {
            onHttpException: expect.any(Function),
        });
        expect(makeDefault.put).toHaveBeenCalledWith(`/sentinel/addresses/${address.id}/default`, {
            onHttpException: expect.any(Function),
        });
        expect(create).toMatchObject(payload);
    });

    it('preserves field validation details returned by the address form request', async () => {
        const list = emptyRequest();
        const create = payloadRequest();
        const update = payloadRequest();
        const remove = emptyRequest();
        const makeDefault = emptyRequest();
        create.post.mockImplementation(async () => {
            create.errors = {
                address: ['Alamat lengkap wajib diisi.'],
                latitude: 'Titik penjemputan wajib dipilih.',
            };
            create.hasErrors = true;

            return undefined;
        });
        mockedUseHttp
            .mockReturnValueOnce(list)
            .mockReturnValueOnce(create)
            .mockReturnValueOnce(update)
            .mockReturnValueOnce(remove)
            .mockReturnValueOnce(makeDefault);
        const api = useMobileCustomerAddressApi();

        const request = api.createAddress(payload);

        await expect(request).rejects.toBeInstanceOf(MobileCustomerAddressValidationError);
        await expect(request).rejects.toMatchObject({
            fieldErrors: {
                address: 'Alamat lengkap wajib diisi.',
                latitude: 'Titik penjemputan wajib dipilih.',
            },
        });
        expect(create.clearErrors).toHaveBeenCalledOnce();
    });

    it('converts non-validation HTTP responses into typed mobile errors', async () => {
        const list = emptyRequest();
        const create = payloadRequest();
        const update = payloadRequest();
        const remove = emptyRequest();
        const makeDefault = emptyRequest();
        update.patch.mockImplementation(
            async (
                _url: string,
                options: {
                    onHttpException: (response: { data: string; status: number }) => void;
                },
            ) => {
                options.onHttpException({
                    data: '{"message":"Alamat tidak ditemukan."}',
                    status: 404,
                });
                throw new Error('request rejected');
            },
        );
        mockedUseHttp
            .mockReturnValueOnce(list)
            .mockReturnValueOnce(create)
            .mockReturnValueOnce(update)
            .mockReturnValueOnce(remove)
            .mockReturnValueOnce(makeDefault);
        const api = useMobileCustomerAddressApi();

        await expect(api.updateAddress(address.id, payload)).rejects.toEqual(
            new MobileHttpError('Alamat tidak ditemukan.', 404),
        );
    });
});
