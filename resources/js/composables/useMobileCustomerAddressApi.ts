import { useHttp } from '@inertiajs/vue3';
import { firstRequestError, MobileHttpError, messageFromResponseData } from '@/lib/mobileHttp';
import {
    index as addressIndexRoute,
    destroy as destroyAddressRoute,
    defaultMethod as setDefaultAddressRoute,
    store as storeAddressRoute,
    update as updateAddressRoute,
} from '@/routes/api/v1/mobile/account/addresses';
import type {
    ApiEnvelope,
    MobileCustomerAddress,
    MobileCustomerAddressPayload,
} from '@/types/mobile';

type EmptyRequest = Record<string, never>;
type AddressFieldErrors = Partial<Record<keyof MobileCustomerAddressPayload, string>>;

export class MobileCustomerAddressValidationError extends Error {
    public constructor(
        message: string,
        public readonly fieldErrors: AddressFieldErrors,
    ) {
        super(message);
        this.name = 'MobileCustomerAddressValidationError';
    }
}

const fieldErrorsFrom = (errors: object): AddressFieldErrors => {
    const requestErrors = errors as Record<string, string | string[] | undefined>;
    const firstMessage = (field: keyof MobileCustomerAddressPayload): string | undefined => {
        const error = requestErrors[field];

        return Array.isArray(error) ? error[0] : error;
    };

    return {
        label: firstMessage('label'),
        recipient_name: firstMessage('recipient_name'),
        whatsapp_phone: firstMessage('whatsapp_phone'),
        address: firstMessage('address'),
        notes: firstMessage('notes'),
        latitude: firstMessage('latitude'),
        longitude: firstMessage('longitude'),
        is_default: firstMessage('is_default'),
    };
};

const validationError = (errors: object, fallback: string): MobileCustomerAddressValidationError =>
    new MobileCustomerAddressValidationError(
        firstRequestError(errors, fallback),
        fieldErrorsFrom(errors),
    );

const requestException = (fallback: string) => {
    const exception = { value: null as MobileHttpError | null };

    return {
        exception,
        onHttpException: (response: { data: string; status: number }): void => {
            exception.value = new MobileHttpError(
                messageFromResponseData(response.data, fallback),
                response.status,
            );
        },
    };
};

const assignPayload = (
    request: MobileCustomerAddressPayload,
    payload: MobileCustomerAddressPayload,
): void => {
    request.label = payload.label;
    request.recipient_name = payload.recipient_name;
    request.whatsapp_phone = payload.whatsapp_phone;
    request.address = payload.address;
    request.notes = payload.notes;
    request.latitude = payload.latitude;
    request.longitude = payload.longitude;
    request.is_default = payload.is_default;
};

export const useMobileCustomerAddressApi = () => {
    const emptyPayload = (): MobileCustomerAddressPayload => ({
        label: '',
        recipient_name: '',
        whatsapp_phone: '',
        address: '',
        notes: null,
        latitude: 0,
        longitude: 0,
        is_default: false,
    });
    const listRequest = useHttp<EmptyRequest, ApiEnvelope<MobileCustomerAddress[]>>({});
    const createRequest = useHttp<MobileCustomerAddressPayload, ApiEnvelope<MobileCustomerAddress>>(
        emptyPayload(),
    );
    const updateRequest = useHttp<MobileCustomerAddressPayload, ApiEnvelope<MobileCustomerAddress>>(
        emptyPayload(),
    );
    const deleteRequest = useHttp<EmptyRequest, unknown>({});
    const defaultRequest = useHttp<EmptyRequest, ApiEnvelope<MobileCustomerAddress>>({});

    const loadAddresses = async (): Promise<MobileCustomerAddress[]> => {
        listRequest.cancel();
        const response = await listRequest.get(addressIndexRoute().url);

        if (!response) {
            throw new Error('Daftar alamat tidak dapat dimuat.');
        }

        return response.data;
    };

    const createAddress = async (
        payload: MobileCustomerAddressPayload,
    ): Promise<MobileCustomerAddress> => {
        createRequest.clearErrors();
        assignPayload(createRequest, payload);
        const { exception, onHttpException } = requestException('Alamat tidak dapat disimpan.');

        try {
            const response = await createRequest.post(storeAddressRoute().url, { onHttpException });

            if (!response) {
                throw validationError(
                    createRequest.errors,
                    'Alamat tidak dapat disimpan. Periksa kembali datanya.',
                );
            }

            return response.data;
        } catch (error: unknown) {
            if (exception.value) {
                throw exception.value;
            }

            throw error;
        } finally {
            createRequest.response = null;
        }
    };

    const updateAddress = async (
        addressId: number,
        payload: MobileCustomerAddressPayload,
    ): Promise<MobileCustomerAddress> => {
        updateRequest.clearErrors();
        assignPayload(updateRequest, payload);
        const { exception, onHttpException } = requestException('Alamat tidak dapat diperbarui.');

        try {
            const response = await updateRequest.patch(updateAddressRoute(addressId).url, {
                onHttpException,
            });

            if (!response) {
                throw validationError(
                    updateRequest.errors,
                    'Alamat tidak dapat diperbarui. Periksa kembali datanya.',
                );
            }

            return response.data;
        } catch (error: unknown) {
            if (exception.value) {
                throw exception.value;
            }

            throw error;
        } finally {
            updateRequest.response = null;
        }
    };

    const deleteAddress = async (addressId: number): Promise<void> => {
        deleteRequest.clearErrors();
        const { exception, onHttpException } = requestException('Alamat tidak dapat dihapus.');

        try {
            await deleteRequest.delete(destroyAddressRoute(addressId).url, { onHttpException });
        } catch (error: unknown) {
            if (exception.value) {
                throw exception.value;
            }

            throw error;
        }

        if (deleteRequest.hasErrors) {
            throw new Error(firstRequestError(deleteRequest.errors, 'Alamat tidak dapat dihapus.'));
        }
    };

    const setDefaultAddress = async (addressId: number): Promise<MobileCustomerAddress> => {
        defaultRequest.clearErrors();
        const { exception, onHttpException } = requestException(
            'Alamat utama tidak dapat diperbarui.',
        );

        try {
            const response = await defaultRequest.put(setDefaultAddressRoute(addressId).url, {
                onHttpException,
            });

            if (!response) {
                throw new Error(
                    firstRequestError(
                        defaultRequest.errors,
                        'Alamat utama tidak dapat diperbarui.',
                    ),
                );
            }

            return response.data;
        } catch (error: unknown) {
            if (exception.value) {
                throw exception.value;
            }

            throw error;
        } finally {
            defaultRequest.response = null;
        }
    };

    return { createAddress, deleteAddress, loadAddresses, setDefaultAddress, updateAddress };
};
