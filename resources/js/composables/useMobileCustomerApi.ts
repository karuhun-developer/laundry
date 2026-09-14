import { useHttp } from '@inertiajs/vue3';
import { firstRequestError, MobileHttpError, messageFromResponseData } from '@/lib/mobileHttp';
import {
    destroy as destroyCustomerRoute,
    resetPin as resetCustomerPinRoute,
    store as storeCustomerRoute,
    update as updateCustomerRoute,
} from '@/routes/api/v1/mobile/customers';
import type {
    ApiEnvelope,
    MobileCustomer,
    MobileCustomerCredentialResult,
    MobileCustomerMutationResult,
    MobileCustomerProfilePayload,
} from '@/types/mobile';

type EmptyRequest = Record<string, never>;
type StoreCustomerResponse = ApiEnvelope<MobileCustomer | MobileCustomerCredentialResult>;
type UpdateCustomerResponse = ApiEnvelope<MobileCustomer>;
type ResetCustomerPinResponse = ApiEnvelope<MobileCustomerCredentialResult>;
type CustomerFieldErrors = Partial<Record<keyof MobileCustomerProfilePayload, string>>;

export class MobileCustomerValidationError extends Error {
    public constructor(
        message: string,
        public readonly fieldErrors: CustomerFieldErrors,
    ) {
        super(message);
        this.name = 'MobileCustomerValidationError';
    }
}

const customerWithoutCredentials = (customer: MobileCustomer): MobileCustomer => ({
    id: customer.id,
    name: customer.name,
    email: customer.email,
    whatsapp_phone: customer.whatsapp_phone,
    must_change_pin: customer.must_change_pin,
    pin_expires_at: customer.pin_expires_at,
});

const fieldErrorsFrom = (errors: object): CustomerFieldErrors => {
    const requestErrors = errors as Record<string, string | string[] | undefined>;
    const firstMessage = (field: keyof MobileCustomerProfilePayload): string | undefined => {
        const error = requestErrors[field];

        return Array.isArray(error) ? error[0] : error;
    };

    return {
        name: firstMessage('name'),
        whatsapp_phone: firstMessage('whatsapp_phone'),
    };
};

const validationError = (errors: object, fallback: string): MobileCustomerValidationError =>
    new MobileCustomerValidationError(firstRequestError(errors, fallback), fieldErrorsFrom(errors));

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

export type MobileCustomerApi = {
    createCustomer: (
        payload: MobileCustomerProfilePayload,
    ) => Promise<MobileCustomerMutationResult>;
    updateCustomer: (
        customerId: number,
        payload: MobileCustomerProfilePayload,
    ) => Promise<MobileCustomer>;
    resetCustomerPin: (customerId: number) => Promise<MobileCustomerCredentialResult>;
    detachCustomer: (customerId: number) => Promise<void>;
};

export const useMobileCustomerApi = (): MobileCustomerApi => {
    const createRequest = useHttp<MobileCustomerProfilePayload, StoreCustomerResponse>({
        name: '',
        whatsapp_phone: '',
    });
    const updateRequest = useHttp<MobileCustomerProfilePayload, UpdateCustomerResponse>({
        name: '',
        whatsapp_phone: '',
    });
    const resetPinRequest = useHttp<EmptyRequest, ResetCustomerPinResponse>({});
    const detachRequest = useHttp<EmptyRequest, unknown>({});

    const createCustomer = async (
        payload: MobileCustomerProfilePayload,
    ): Promise<MobileCustomerMutationResult> => {
        createRequest.clearErrors();
        createRequest.name = payload.name;
        createRequest.whatsapp_phone = payload.whatsapp_phone;
        const { exception, onHttpException } = requestException(
            'Pelanggan tidak dapat ditambahkan.',
        );
        try {
            const response = await createRequest.post(storeCustomerRoute().url, {
                onHttpException,
            });

            if (!response) {
                throw validationError(
                    createRequest.errors,
                    'Pelanggan tidak dapat ditambahkan. Periksa kembali datanya.',
                );
            }

            if ('customer' in response.data) {
                return {
                    customer: customerWithoutCredentials(response.data.customer),
                    credential: { ...response.data.credential },
                };
            }

            return { customer: customerWithoutCredentials(response.data), credential: null };
        } catch (error: unknown) {
            if (exception.value) {
                throw exception.value;
            }

            throw error;
        } finally {
            createRequest.response = null;
        }
    };

    const updateCustomer = async (
        customerId: number,
        payload: MobileCustomerProfilePayload,
    ): Promise<MobileCustomer> => {
        updateRequest.clearErrors();
        updateRequest.name = payload.name;
        updateRequest.whatsapp_phone = payload.whatsapp_phone;
        const { exception, onHttpException } = requestException(
            'Pelanggan tidak dapat diperbarui.',
        );
        let response: UpdateCustomerResponse;

        try {
            response = await updateRequest.patch(updateCustomerRoute(customerId).url, {
                onHttpException,
            });
        } catch (error: unknown) {
            if (exception.value) {
                throw exception.value;
            }

            throw error;
        }

        if (!response) {
            throw validationError(
                updateRequest.errors,
                'Pelanggan tidak dapat diperbarui. Periksa kembali datanya.',
            );
        }

        return customerWithoutCredentials(response.data);
    };

    const resetCustomerPin = async (
        customerId: number,
    ): Promise<MobileCustomerCredentialResult> => {
        resetPinRequest.clearErrors();
        const { exception, onHttpException } = requestException(
            'PIN sementara tidak dapat dibuat.',
        );
        try {
            const response = await resetPinRequest.post(resetCustomerPinRoute(customerId).url, {
                onHttpException,
            });

            if (!response) {
                throw new Error(
                    firstRequestError(resetPinRequest.errors, 'PIN sementara tidak dapat dibuat.'),
                );
            }

            return {
                customer: customerWithoutCredentials(response.data.customer),
                credential: { ...response.data.credential },
            };
        } catch (error: unknown) {
            if (exception.value) {
                throw exception.value;
            }

            throw error;
        } finally {
            resetPinRequest.response = null;
        }
    };

    const detachCustomer = async (customerId: number): Promise<void> => {
        detachRequest.clearErrors();
        const { exception, onHttpException } = requestException(
            'Pelanggan tidak dapat dilepas dari organisasi.',
        );

        try {
            await detachRequest.delete(destroyCustomerRoute(customerId).url, { onHttpException });
        } catch (error: unknown) {
            if (exception.value) {
                throw exception.value;
            }

            throw error;
        }

        if (detachRequest.hasErrors) {
            throw new Error(
                firstRequestError(
                    detachRequest.errors,
                    'Pelanggan tidak dapat dilepas dari organisasi.',
                ),
            );
        }
    };

    return { createCustomer, updateCustomer, resetCustomerPin, detachCustomer };
};
