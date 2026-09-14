import type { Ref } from 'vue';
import { readonly, ref } from 'vue';
import { type MobileCustomerApi, useMobileCustomerApi } from '@/composables/useMobileCustomerApi';
import type {
    MobileCustomer,
    MobileCustomerCredentialResult,
    MobileCustomerMutationResult,
    MobileCustomerProfilePayload,
} from '@/types/mobile';

let optimisticCustomerId = 0;

const nextOptimisticCustomerId = (): number => {
    optimisticCustomerId += 1;

    return -optimisticCustomerId;
};

export const useMobileCustomerWorkspace = (
    customers: Ref<MobileCustomer[]>,
    customerApi: MobileCustomerApi = useMobileCustomerApi(),
) => {
    const selectedCustomerId = ref<number | null>(null);
    const operationGenerations = new Map<number, number>();

    const beginCustomerOperation = (customerId: number): number => {
        const generation = (operationGenerations.get(customerId) ?? 0) + 1;
        operationGenerations.set(customerId, generation);

        return generation;
    };

    const isCurrentCustomerOperation = (customerId: number, generation: number): boolean =>
        operationGenerations.get(customerId) === generation;

    const setCustomers = (nextCustomers: MobileCustomer[]): void => {
        customers.value = nextCustomers;

        if (
            selectedCustomerId.value !== null &&
            !nextCustomers.some((customer) => customer.id === selectedCustomerId.value)
        ) {
            selectedCustomerId.value = null;
        }
    };

    const selectCustomer = (customerId: number | null): void => {
        selectedCustomerId.value = customerId;
    };

    const reconcileCustomer = (replacedCustomerId: number, customer: MobileCustomer): void => {
        customers.value = [
            customer,
            ...customers.value.filter(
                (currentCustomer) =>
                    currentCustomer.id !== replacedCustomerId && currentCustomer.id !== customer.id,
            ),
        ];
    };

    const replaceCustomer = (customerId: number, customer: MobileCustomer): void => {
        customers.value = customers.value.map((currentCustomer) =>
            currentCustomer.id === customerId ? customer : currentCustomer,
        );
    };

    const createCustomer = async (
        payload: MobileCustomerProfilePayload,
    ): Promise<MobileCustomerMutationResult> => {
        const temporaryId = nextOptimisticCustomerId();
        const optimisticCustomer: MobileCustomer = {
            id: temporaryId,
            name: payload.name,
            email: null,
            whatsapp_phone: payload.whatsapp_phone,
            must_change_pin: false,
            pin_expires_at: null,
            pending: true,
        };
        customers.value = [optimisticCustomer, ...customers.value];

        try {
            const result = await customerApi.createCustomer(payload);
            reconcileCustomer(temporaryId, result.customer);
            selectedCustomerId.value = result.customer.id;

            return result;
        } catch (error: unknown) {
            customers.value = customers.value.filter((customer) => customer.id !== temporaryId);
            throw error;
        }
    };

    const updateCustomer = async (
        customerId: number,
        payload: MobileCustomerProfilePayload,
    ): Promise<MobileCustomerMutationResult> => {
        const generation = beginCustomerOperation(customerId);
        const customer = await customerApi.updateCustomer(customerId, payload);

        if (isCurrentCustomerOperation(customerId, generation)) {
            replaceCustomer(customerId, customer);
        }

        return { customer, credential: null };
    };

    const resetCustomerPin = async (
        customerId: number,
    ): Promise<MobileCustomerCredentialResult> => {
        const generation = beginCustomerOperation(customerId);
        const result = await customerApi.resetCustomerPin(customerId);

        if (isCurrentCustomerOperation(customerId, generation)) {
            replaceCustomer(customerId, result.customer);
        }

        return result;
    };

    const detachCustomer = async (customerId: number): Promise<void> => {
        const generation = beginCustomerOperation(customerId);
        await customerApi.detachCustomer(customerId);

        if (!isCurrentCustomerOperation(customerId, generation)) {
            return;
        }

        customers.value = customers.value.filter((customer) => customer.id !== customerId);

        if (selectedCustomerId.value === customerId) {
            selectedCustomerId.value = null;
        }
    };

    return {
        selectedCustomerId: readonly(selectedCustomerId),
        createCustomer,
        detachCustomer,
        resetCustomerPin,
        selectCustomer,
        setCustomers,
        updateCustomer,
    };
};
