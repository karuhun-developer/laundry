import { describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import type { MobileCustomerApi } from '@/composables/useMobileCustomerApi';
import { useMobileCustomerWorkspace } from '@/composables/useMobileCustomerWorkspace';
import type { MobileCustomer, MobileCustomerMutationResult } from '@/types/mobile';

const existingCustomer: MobileCustomer = {
    id: 9,
    name: 'Customer Lama',
    email: 'lama@example.test',
    whatsapp_phone: '628111111111',
    must_change_pin: false,
    pin_expires_at: null,
};

const authoritativeCustomer: MobileCustomer = {
    id: 21,
    name: 'Budi Santoso',
    email: null,
    whatsapp_phone: '6281234567890',
    must_change_pin: true,
    pin_expires_at: '2026-09-15T09:00:00+07:00',
};

const createApi = (): {
    api: MobileCustomerApi;
    createCustomer: ReturnType<typeof vi.fn<MobileCustomerApi['createCustomer']>>;
    updateCustomer: ReturnType<typeof vi.fn<MobileCustomerApi['updateCustomer']>>;
    resetCustomerPin: ReturnType<typeof vi.fn<MobileCustomerApi['resetCustomerPin']>>;
} => {
    const createCustomer = vi.fn<MobileCustomerApi['createCustomer']>();
    const updateCustomer = vi.fn<MobileCustomerApi['updateCustomer']>();
    const resetCustomerPin = vi.fn<MobileCustomerApi['resetCustomerPin']>();

    return {
        api: { createCustomer, updateCustomer, resetCustomerPin },
        createCustomer,
        updateCustomer,
        resetCustomerPin,
    };
};

describe('mobile customer workspace', () => {
    it('inserts an optimistic customer immediately, then replaces it and auto-selects the authoritative customer', async () => {
        const customers = ref<MobileCustomer[]>([existingCustomer]);
        const { api, createCustomer } = createApi();
        let resolveRequest: (result: MobileCustomerMutationResult) => void = () => undefined;
        createCustomer.mockReturnValue(
            new Promise((resolve) => {
                resolveRequest = resolve;
            }),
        );
        const workspace = useMobileCustomerWorkspace(customers, api);

        const request = workspace.createCustomer({
            name: 'Budi Santoso',
            whatsapp_phone: '0812 3456 7890',
        });

        expect(customers.value).toHaveLength(2);
        expect(customers.value[0]).toMatchObject({
            name: 'Budi Santoso',
            whatsapp_phone: '0812 3456 7890',
            pending: true,
        });
        expect(workspace.selectedCustomerId.value).toBeNull();

        resolveRequest({
            customer: authoritativeCustomer,
            credential: {
                temporary_pin: '483921',
                expires_at: '2026-09-15T09:00:00+07:00',
            },
        });
        await expect(request).resolves.toEqual({
            customer: authoritativeCustomer,
            credential: {
                temporary_pin: '483921',
                expires_at: '2026-09-15T09:00:00+07:00',
            },
        });

        expect(customers.value).toEqual([authoritativeCustomer, existingCustomer]);
        expect(workspace.selectedCustomerId.value).toBe(21);
        expect(customers.value.some((customer) => 'temporary_pin' in customer)).toBe(false);
    });

    it('rolls the optimistic customer back when creation fails', async () => {
        const customers = ref<MobileCustomer[]>([existingCustomer]);
        const { api, createCustomer } = createApi();
        let rejectRequest: (error: Error) => void = () => undefined;
        createCustomer.mockReturnValue(
            new Promise((_resolve, reject) => {
                rejectRequest = reject;
            }),
        );
        const workspace = useMobileCustomerWorkspace(customers, api);
        const request = workspace.createCustomer({
            name: 'Gagal Dibuat',
            whatsapp_phone: '081299999999',
        });

        expect(customers.value[0]?.pending).toBe(true);
        rejectRequest(new Error('Nomor WhatsApp tidak valid.'));

        await expect(request).rejects.toThrow('Nomor WhatsApp tidak valid.');
        expect(customers.value).toEqual([existingCustomer]);
        expect(workspace.selectedCustomerId.value).toBeNull();
    });

    it('upserts the canonical customer after a reload removes its optimistic row', async () => {
        const customers = ref<MobileCustomer[]>([existingCustomer]);
        const { api, createCustomer } = createApi();
        let resolveRequest: (result: MobileCustomerMutationResult) => void = () => undefined;
        createCustomer.mockReturnValue(
            new Promise((resolve) => {
                resolveRequest = resolve;
            }),
        );
        const workspace = useMobileCustomerWorkspace(customers, api);
        const request = workspace.createCustomer({
            name: authoritativeCustomer.name,
            whatsapp_phone: authoritativeCustomer.whatsapp_phone ?? '',
        });

        workspace.setCustomers([existingCustomer]);
        resolveRequest({ customer: authoritativeCustomer, credential: null });
        await request;

        expect(customers.value).toEqual([authoritativeCustomer, existingCustomer]);
        expect(workspace.selectedCustomerId.value).toBe(authoritativeCustomer.id);
    });

    it('deduplicates concurrent creates that resolve to the same canonical customer', async () => {
        const customers = ref<MobileCustomer[]>([existingCustomer]);
        const { api, createCustomer } = createApi();
        const resolvers: Array<(result: MobileCustomerMutationResult) => void> = [];
        createCustomer.mockImplementation(
            () =>
                new Promise((resolve) => {
                    resolvers.push(resolve);
                }),
        );
        const workspace = useMobileCustomerWorkspace(customers, api);

        const firstRequest = workspace.createCustomer({
            name: 'Nama pertama',
            whatsapp_phone: authoritativeCustomer.whatsapp_phone ?? '',
        });
        const secondRequest = workspace.createCustomer({
            name: 'Nama kedua',
            whatsapp_phone: authoritativeCustomer.whatsapp_phone ?? '',
        });
        resolvers[1]?.({ customer: authoritativeCustomer, credential: null });
        await secondRequest;
        resolvers[0]?.({ customer: authoritativeCustomer, credential: null });
        await firstRequest;

        expect(customers.value).toEqual([authoritativeCustomer, existingCustomer]);
        expect(customers.value.filter(({ id }) => id === authoritativeCustomer.id)).toHaveLength(1);
    });

    it('accepts an attach-existing response without inventing credentials or mutating its profile', async () => {
        const customers = ref<MobileCustomer[]>([]);
        const attachedCustomer: MobileCustomer = {
            ...authoritativeCustomer,
            name: 'Nama Akun Asli',
            must_change_pin: false,
            pin_expires_at: null,
        };
        const { api, createCustomer } = createApi();
        createCustomer.mockResolvedValue({ customer: attachedCustomer, credential: null });
        const workspace = useMobileCustomerWorkspace(customers, api);

        const result = await workspace.createCustomer({
            name: 'Nama Isian Berbeda',
            whatsapp_phone: attachedCustomer.whatsapp_phone ?? '',
        });

        expect(result).toEqual({ customer: attachedCustomer, credential: null });
        expect(customers.value).toEqual([attachedCustomer]);
        expect(workspace.selectedCustomerId.value).toBe(attachedCustomer.id);
        expect(customers.value.some((customer) => 'temporary_pin' in customer)).toBe(false);
    });

    it('replaces edited and reset customers with authoritative responses', async () => {
        const customers = ref<MobileCustomer[]>([existingCustomer]);
        const updatedCustomer: MobileCustomer = {
            ...existingCustomer,
            name: 'Customer Diperbarui',
            must_change_pin: true,
            pin_expires_at: '2026-09-15T10:00:00+07:00',
        };
        const { api, updateCustomer, resetCustomerPin } = createApi();
        updateCustomer.mockResolvedValue(updatedCustomer);
        resetCustomerPin.mockResolvedValue({
            customer: updatedCustomer,
            credential: {
                temporary_pin: '125678',
                expires_at: '2026-09-15T10:00:00+07:00',
            },
        });
        const workspace = useMobileCustomerWorkspace(customers, api);

        await workspace.updateCustomer(existingCustomer.id, {
            name: 'Customer Diperbarui',
            whatsapp_phone: existingCustomer.whatsapp_phone ?? '',
        });
        const resetResult = await workspace.resetCustomerPin(existingCustomer.id);

        expect(customers.value).toEqual([updatedCustomer]);
        expect(resetResult.credential.temporary_pin).toBe('125678');
        expect(customers.value.some((customer) => 'temporary_pin' in customer)).toBe(false);
    });

    it('ignores a stale update completion after a newer reset starts for the same customer', async () => {
        const customers = ref<MobileCustomer[]>([existingCustomer]);
        const staleUpdate = { ...existingCustomer, name: 'Nama lama dari respons terlambat' };
        const resetCustomer = {
            ...existingCustomer,
            must_change_pin: true,
            pin_expires_at: '2026-09-15T12:00:00+07:00',
        };
        const { api, updateCustomer, resetCustomerPin } = createApi();
        let resolveUpdate: (customer: MobileCustomer) => void = () => undefined;
        updateCustomer.mockReturnValue(
            new Promise((resolve) => {
                resolveUpdate = resolve;
            }),
        );
        resetCustomerPin.mockResolvedValue({
            customer: resetCustomer,
            credential: {
                temporary_pin: '120045',
                expires_at: '2026-09-15T12:00:00+07:00',
            },
        });
        const workspace = useMobileCustomerWorkspace(customers, api);
        const updateRequest = workspace.updateCustomer(existingCustomer.id, {
            name: staleUpdate.name,
            whatsapp_phone: staleUpdate.whatsapp_phone ?? '',
        });

        await workspace.resetCustomerPin(existingCustomer.id);
        resolveUpdate(staleUpdate);
        await updateRequest;

        expect(customers.value).toEqual([resetCustomer]);
    });
});
