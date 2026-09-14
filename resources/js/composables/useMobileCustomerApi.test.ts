import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    MobileCustomerValidationError,
    useMobileCustomerApi,
} from '@/composables/useMobileCustomerApi';
import { MobileHttpError } from '@/lib/mobileHttp';
import type { MobileCustomer, MobileCustomerCredentialResult } from '@/types/mobile';

const mockedUseHttp = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({ useHttp: mockedUseHttp }));
vi.mock('@/routes/api/v1/mobile/customers', () => ({
    destroy: (customerId: number) => ({ url: `/sentinel/customers/${customerId}/destroy` }),
    resetPin: (customerId: number) => ({ url: `/sentinel/customers/${customerId}/reset-pin` }),
    store: () => ({ url: '/sentinel/customers/store' }),
    update: (customerId: number) => ({ url: `/sentinel/customers/${customerId}/update` }),
}));

const customer: MobileCustomer = {
    id: 21,
    name: 'Budi Santoso',
    email: null,
    whatsapp_phone: '6281234567890',
    must_change_pin: true,
    pin_expires_at: '2026-09-15T09:00:00+07:00',
};

const credentialResult: MobileCustomerCredentialResult = {
    customer,
    credential: {
        temporary_pin: '483921',
        expires_at: '2026-09-15T09:00:00+07:00',
    },
};

const createRequest = () => {
    const request = {
        response: null as unknown,
        name: '',
        whatsapp_phone: '',
        errors: {} as Record<string, string>,
        hasErrors: false,
        clearErrors: vi.fn(() => {
            request.errors = {};
            request.hasErrors = false;
        }),
        post: vi.fn(),
    };

    return request;
};

const updateRequest = () => {
    const request = {
        response: null as unknown,
        name: '',
        whatsapp_phone: '',
        errors: {} as Record<string, string>,
        hasErrors: false,
        clearErrors: vi.fn(() => {
            request.errors = {};
            request.hasErrors = false;
        }),
        patch: vi.fn(),
    };

    return request;
};

const emptyRequest = () => {
    const request = {
        response: null as unknown,
        errors: {} as Record<string, string>,
        hasErrors: false,
        clearErrors: vi.fn(() => {
            request.errors = {};
            request.hasErrors = false;
        }),
        post: vi.fn(),
        delete: vi.fn(),
    };

    return request;
};

beforeEach(() => {
    mockedUseHttp.mockReset();
});

describe('mobile customer API', () => {
    it('normalizes create-new and attach-existing responses without inventing credentials', async () => {
        const create = createRequest();
        const update = updateRequest();
        const reset = emptyRequest();
        const detach = emptyRequest();
        mockedUseHttp
            .mockReturnValueOnce(create)
            .mockReturnValueOnce(update)
            .mockReturnValueOnce(reset)
            .mockReturnValueOnce(detach);
        create.post.mockImplementationOnce(async () => {
            const response = { data: credentialResult };
            create.response = response;

            return response;
        });
        create.post.mockImplementationOnce(async () => {
            const response = { data: customer };
            create.response = response;

            return response;
        });
        const api = useMobileCustomerApi();

        await expect(
            api.createCustomer({
                name: customer.name,
                whatsapp_phone: customer.whatsapp_phone ?? '',
            }),
        ).resolves.toEqual(credentialResult);
        await expect(
            api.createCustomer({
                name: 'Nama isian yang tidak boleh mengganti profil',
                whatsapp_phone: customer.whatsapp_phone ?? '',
            }),
        ).resolves.toEqual({ customer, credential: null });

        expect(create.post).toHaveBeenNthCalledWith(1, '/sentinel/customers/store', {
            onHttpException: expect.any(Function),
        });
        expect(create.post).toHaveBeenNthCalledWith(2, '/sentinel/customers/store', {
            onHttpException: expect.any(Function),
        });
        expect(create.response).toBeNull();
    });

    it('uses generated Wayfinder endpoints for update, reset PIN, and detach', async () => {
        const create = createRequest();
        const update = updateRequest();
        const reset = emptyRequest();
        const detach = emptyRequest();
        mockedUseHttp
            .mockReturnValueOnce(create)
            .mockReturnValueOnce(update)
            .mockReturnValueOnce(reset)
            .mockReturnValueOnce(detach);
        update.patch.mockResolvedValue({ data: customer });
        reset.post.mockImplementation(async () => {
            const response = { data: credentialResult };
            reset.response = response;

            return response;
        });
        detach.delete.mockResolvedValue(undefined);
        const api = useMobileCustomerApi();

        await api.updateCustomer(customer.id, {
            name: customer.name,
            whatsapp_phone: customer.whatsapp_phone ?? '',
        });
        await api.resetCustomerPin(customer.id);
        await api.detachCustomer(customer.id);

        expect(update.patch).toHaveBeenCalledWith(`/sentinel/customers/${customer.id}/update`, {
            onHttpException: expect.any(Function),
        });
        expect(reset.post).toHaveBeenCalledWith(`/sentinel/customers/${customer.id}/reset-pin`, {
            onHttpException: expect.any(Function),
        });
        expect(detach.delete).toHaveBeenCalledWith(`/sentinel/customers/${customer.id}/destroy`, {
            onHttpException: expect.any(Function),
        });
        expect(reset.response).toBeNull();
    });

    it('returns the backend validation message when detach is blocked by order history', async () => {
        const create = createRequest();
        const update = updateRequest();
        const reset = emptyRequest();
        const detach = emptyRequest();
        mockedUseHttp
            .mockReturnValueOnce(create)
            .mockReturnValueOnce(update)
            .mockReturnValueOnce(reset)
            .mockReturnValueOnce(detach);
        detach.delete.mockImplementation(async () => {
            detach.errors = {
                customer: 'Customer dengan riwayat pesanan tidak dapat dilepas dari organisasi.',
            };
            detach.hasErrors = true;
        });
        const api = useMobileCustomerApi();

        await expect(api.detachCustomer(customer.id)).rejects.toThrow(
            'Customer dengan riwayat pesanan tidak dapat dilepas dari organisasi.',
        );
    });

    it('preserves field validation details after clearing errors from a previous request', async () => {
        const create = createRequest();
        const update = updateRequest();
        const reset = emptyRequest();
        const detach = emptyRequest();
        create.errors = { name: 'Kesalahan lama.' };
        create.hasErrors = true;
        create.post.mockImplementation(async () => {
            create.errors = {
                name: 'Nama pelanggan wajib diisi.',
                whatsapp_phone: 'Nomor WhatsApp tidak valid.',
            };
            create.hasErrors = true;

            return undefined;
        });
        mockedUseHttp
            .mockReturnValueOnce(create)
            .mockReturnValueOnce(update)
            .mockReturnValueOnce(reset)
            .mockReturnValueOnce(detach);
        const api = useMobileCustomerApi();

        const request = api.createCustomer({ name: '', whatsapp_phone: 'invalid' });

        await expect(request).rejects.toBeInstanceOf(MobileCustomerValidationError);
        await expect(request).rejects.toMatchObject({
            fieldErrors: {
                name: 'Nama pelanggan wajib diisi.',
                whatsapp_phone: 'Nomor WhatsApp tidak valid.',
            },
        });
        expect(create.clearErrors).toHaveBeenCalledOnce();
    });

    it('converts HTTP failures and purges stale reset responses after network failure', async () => {
        const create = createRequest();
        const update = updateRequest();
        const reset = emptyRequest();
        const detach = emptyRequest();
        update.patch.mockImplementation(
            async (
                _url: string,
                options: {
                    onHttpException: (response: { data: string; status: number }) => void;
                },
            ) => {
                options.onHttpException({ data: '{"message":"Tidak diizinkan."}', status: 403 });
                throw new Error('request rejected');
            },
        );
        const networkError = new TypeError('Network unavailable');
        reset.post.mockRejectedValue(networkError);
        reset.response = { data: credentialResult };
        mockedUseHttp
            .mockReturnValueOnce(create)
            .mockReturnValueOnce(update)
            .mockReturnValueOnce(reset)
            .mockReturnValueOnce(detach);
        const api = useMobileCustomerApi();

        await expect(
            api.updateCustomer(customer.id, {
                name: customer.name,
                whatsapp_phone: customer.whatsapp_phone ?? '',
            }),
        ).rejects.toEqual(new MobileHttpError('Tidak diizinkan.', 403));
        await expect(api.resetCustomerPin(customer.id)).rejects.toBe(networkError);
        expect(reset.response).toBeNull();
    });
});
