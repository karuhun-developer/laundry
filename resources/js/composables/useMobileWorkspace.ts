import { computed, onMounted, readonly, ref } from 'vue';
import { type Coordinates, useGeolocation } from '@/composables/useGeolocation';
import { useMobileCatalogApi } from '@/composables/useMobileCatalogApi';
import { useMobileCustomerAddressApi } from '@/composables/useMobileCustomerAddressApi';
import { useMobileCustomerWorkspace } from '@/composables/useMobileCustomerWorkspace';
import { useMobileOrderWorkspace } from '@/composables/useMobileOrderWorkspace';
import { useMobilePrinter } from '@/composables/useMobilePrinter';
import type { CustomerOrderFilters } from '@/lib/mobileOrderFilters';
import { toLaundryReceiptData } from '@/lib/mobilePayments';
import type {
    MobileBranch,
    MobileContext,
    MobileCustomer,
    MobileCustomerAddress,
    MobileCustomerAddressPayload,
    MobilePaymentActions,
    MobilePriceQuote,
    MobileService,
    NearestMobileBranch,
} from '@/types/mobile';

const errorMessage = (error: unknown, fallback: string): string =>
    error instanceof Error ? error.message : fallback;

export const useMobileWorkspace = () => {
    const context = ref<MobileContext | null>(null);
    const services = ref<MobileService[]>([]);
    const customers = ref<MobileCustomer[]>([]);
    const addresses = ref<MobileCustomerAddress[]>([]);
    const nearestBranches = ref<NearestMobileBranch[]>([]);
    const deliveryCoordinates = ref<Coordinates | null>(null);
    const selectedBranchId = ref<number | null>(null);
    const loading = ref(true);
    const refreshing = ref(false);
    const queueLoading = ref(false);
    const customerOrderLoading = ref(false);
    const requestError = ref<string | null>(null);
    const catalogApi = useMobileCatalogApi();
    const customerAddressApi = useMobileCustomerAddressApi();
    const geolocation = useGeolocation();
    const printer = useMobilePrinter();
    const customerWorkspace = useMobileCustomerWorkspace(customers);
    let loadGeneration = 0;
    let catalogGeneration = 0;
    let nearestGeneration = 0;
    let branchSelectionGeneration = 0;
    let orderSearchGeneration = 0;
    let customerOrderSearchGeneration = 0;
    let customerOrderRefreshPromise: Promise<boolean> | null = null;

    const selectedBranch = computed<MobileBranch | null>(
        () =>
            context.value?.branches.find((branch) => branch.id === selectedBranchId.value) ?? null,
    );
    const selectedBranchDeliveryFee = computed(
        () =>
            nearestBranches.value.find((branch) => branch.id === selectedBranchId.value)?.delivery
                .fee_rupiah ?? 0,
    );
    const selectedAddressId = ref<number | null>(null);
    const selectedAddress = computed<MobileCustomerAddress | null>(
        () => addresses.value.find((address) => address.id === selectedAddressId.value) ?? null,
    );
    const orderWorkspace = useMobileOrderWorkspace({
        context,
        selectedBranch,
        services,
        customers,
        nearestBranches,
        setRequestError: (message) => {
            requestError.value = message;
        },
    });

    const loadCatalog = async (): Promise<void> => {
        const requestGeneration = ++catalogGeneration;
        const branch = selectedBranch.value;

        if (!branch) {
            services.value = [];
            return;
        }

        try {
            const response = await catalogApi.loadCatalog(branch.organization.slug, branch.id);

            if (requestGeneration === catalogGeneration) {
                services.value = response;
            }
        } catch (error: unknown) {
            if (requestGeneration === catalogGeneration) {
                throw error;
            }
        }
    };

    const loadOrganizationData = async (requestGeneration: number): Promise<void> => {
        const currentContext = context.value;

        if (!currentContext) {
            return;
        }

        const [orders, organizationCustomers, customerAddresses] = await Promise.all([
            orderWorkspace.loadOrders(
                currentContext.role === 'branch_admin'
                    ? currentContext.organization.slug
                    : undefined,
            ),
            currentContext.role === 'branch_admin'
                ? catalogApi.loadCustomers(currentContext.organization.slug)
                : Promise.resolve([]),
            currentContext.role === 'customer'
                ? customerAddressApi.loadAddresses()
                : Promise.resolve([]),
        ]);

        if (requestGeneration !== loadGeneration) {
            return;
        }

        orderWorkspace.setOrders(orders);
        customerWorkspace.setCustomers(organizationCustomers);
        addresses.value = customerAddresses;
        selectedAddressId.value = customerAddresses.some(
            (address) => address.id === selectedAddressId.value,
        )
            ? selectedAddressId.value
            : (customerAddresses.find((address) => address.is_default)?.id ??
              customerAddresses[0]?.id ??
              null);
        await loadCatalog();
    };

    type NearestRequestContext = {
        branchSelectionGeneration: number;
        loadGeneration: number;
        nearestGeneration: number;
    };

    const findNearestBranches = async (
        latitude: number,
        longitude: number,
        requestContext?: NearestRequestContext,
        selectNearest = true,
    ): Promise<boolean> => {
        const currentContext = context.value;
        const currentRequest = requestContext ?? {
            branchSelectionGeneration,
            loadGeneration,
            nearestGeneration: ++nearestGeneration,
        };

        if (!currentContext) {
            return false;
        }

        requestError.value = null;

        try {
            const response = await catalogApi.findNearest(latitude, longitude);

            if (
                currentRequest.nearestGeneration !== nearestGeneration ||
                currentRequest.loadGeneration !== loadGeneration ||
                currentContext !== context.value
            ) {
                return false;
            }

            const branchIds = new Set(currentContext.branches.map((branch) => branch.id));
            const rankedBranches = [...response]
                .filter((branch) => branchIds.has(branch.id))
                .sort(
                    (left, right) => left.delivery.distance_meters - right.delivery.distance_meters,
                );
            nearestBranches.value = rankedBranches;
            deliveryCoordinates.value = { latitude, longitude };

            const nearestBranch = rankedBranches[0];

            if (
                selectNearest &&
                nearestBranch &&
                currentRequest.branchSelectionGeneration === branchSelectionGeneration
            ) {
                selectedBranchId.value = nearestBranch.id;
                services.value = [];

                try {
                    await loadCatalog();
                } catch (error: unknown) {
                    if (
                        currentRequest.nearestGeneration === nearestGeneration &&
                        currentRequest.loadGeneration === loadGeneration
                    ) {
                        requestError.value = errorMessage(
                            error,
                            'Katalog cabang tidak dapat dimuat.',
                        );
                    }
                }
            }

            return true;
        } catch (error: unknown) {
            if (
                currentRequest.nearestGeneration === nearestGeneration &&
                currentRequest.loadGeneration === loadGeneration
            ) {
                nearestBranches.value = [];
                requestError.value = errorMessage(error, 'Cabang terdekat tidak dapat dihitung.');
            }

            return false;
        }
    };

    const locateNearestBranchAutomatically = async (
        requestLoadGeneration: number,
    ): Promise<void> => {
        const currentContext = context.value;

        if (currentContext?.role !== 'customer') {
            return;
        }

        const requestContext: NearestRequestContext = {
            branchSelectionGeneration,
            loadGeneration: requestLoadGeneration,
            nearestGeneration: ++nearestGeneration,
        };
        const coordinates = await geolocation.locate();

        if (
            !coordinates ||
            requestContext.nearestGeneration !== nearestGeneration ||
            requestContext.loadGeneration !== loadGeneration ||
            currentContext !== context.value
        ) {
            return;
        }

        await findNearestBranches(coordinates.latitude, coordinates.longitude, requestContext);
    };

    const load = async (organizationSlug?: string): Promise<void> => {
        const requestGeneration = ++loadGeneration;
        let shouldLocateAutomatically = false;
        loading.value = true;
        requestError.value = null;

        try {
            const response = await catalogApi.loadContext(organizationSlug);

            if (requestGeneration !== loadGeneration) {
                return;
            }

            context.value = response;
            selectedBranchId.value = response.branches.some(
                (branch) => branch.id === selectedBranchId.value,
            )
                ? selectedBranchId.value
                : (response.branches[0]?.id ?? null);
            services.value = [];
            customerWorkspace.setCustomers([]);
            addresses.value = [];
            selectedAddressId.value = null;
            nearestBranches.value = [];
            deliveryCoordinates.value = null;
            orderWorkspace.setOrders([]);
            nearestGeneration += 1;
            branchSelectionGeneration += 1;
            await loadOrganizationData(requestGeneration);

            if (response.role === 'customer' && selectedAddress.value) {
                await findNearestBranches(
                    selectedAddress.value.latitude,
                    selectedAddress.value.longitude,
                );
            } else {
                shouldLocateAutomatically = response.role === 'customer';
            }
        } catch (error: unknown) {
            if (requestGeneration === loadGeneration) {
                requestError.value = errorMessage(
                    error,
                    'Aplikasi tidak dapat dimuat. Periksa koneksi lalu coba lagi.',
                );
            }
        } finally {
            if (requestGeneration === loadGeneration) {
                loading.value = false;

                if (shouldLocateAutomatically) {
                    void locateNearestBranchAutomatically(requestGeneration);
                }
            }
        }
    };

    const selectBranch = async (branchId: number): Promise<void> => {
        if (!context.value?.branches.some((branch) => branch.id === branchId)) {
            requestError.value = 'Cabang yang dipilih tidak tersedia.';
            return;
        }

        branchSelectionGeneration += 1;
        selectedBranchId.value = branchId;
        services.value = [];
        requestError.value = null;

        try {
            await loadCatalog();
        } catch (error: unknown) {
            requestError.value = errorMessage(error, 'Katalog cabang tidak dapat dimuat.');
        }
    };

    const refresh = async (): Promise<boolean> => {
        if (refreshing.value) {
            return false;
        }

        const previousContext = context.value;
        const previousBranchId = selectedBranchId.value;
        const previousAddressId = selectedAddressId.value;
        const previousCoordinates = deliveryCoordinates.value;
        const requestGeneration = ++loadGeneration;

        refreshing.value = true;
        requestError.value = null;
        orderSearchGeneration += 1;
        customerOrderSearchGeneration += 1;

        try {
            const response = await catalogApi.loadContext(
                previousContext?.role === 'branch_admin'
                    ? previousContext.organization.slug
                    : undefined,
            );

            if (requestGeneration !== loadGeneration) {
                return false;
            }

            context.value = response;
            selectedBranchId.value = response.branches.some(
                (branch) => branch.id === previousBranchId,
            )
                ? previousBranchId
                : (response.branches[0]?.id ?? null);
            selectedAddressId.value = previousAddressId;
            nearestGeneration += 1;
            await loadOrganizationData(requestGeneration);

            const coordinates = selectedAddress.value
                ? {
                      latitude: selectedAddress.value.latitude,
                      longitude: selectedAddress.value.longitude,
                  }
                : previousCoordinates;

            if (response.role === 'customer' && coordinates) {
                const nearestRefreshed = await findNearestBranches(
                    coordinates.latitude,
                    coordinates.longitude,
                    undefined,
                    false,
                );

                if (!nearestRefreshed) {
                    return false;
                }
            }

            return true;
        } catch (error: unknown) {
            if (requestGeneration === loadGeneration) {
                requestError.value = errorMessage(error, 'Data aplikasi tidak dapat disegarkan.');
            }

            return false;
        } finally {
            if (requestGeneration === loadGeneration) {
                refreshing.value = false;
            }
        }
    };

    const quoteOrder = async (
        priceOptionId: number,
        weightGrams: number,
        customPricePerKilogramRupiah?: number,
    ): Promise<MobilePriceQuote> => {
        const branch = selectedBranch.value;

        if (!branch) {
            throw new Error('Pilih cabang sebelum meminta rincian harga.');
        }

        return catalogApi.quoteOrder(
            branch.organization.slug,
            branch.id,
            priceOptionId,
            weightGrams,
            customPricePerKilogramRupiah,
        );
    };

    const selectAddress = async (addressId: number): Promise<boolean> => {
        const address = addresses.value.find((entry) => entry.id === addressId);

        if (!address) {
            requestError.value = 'Alamat penjemputan tidak tersedia.';
            return false;
        }

        selectedAddressId.value = address.id;

        return findNearestBranches(address.latitude, address.longitude);
    };

    const normalizeDefaultAddress = (address: MobileCustomerAddress): void => {
        addresses.value = addresses.value.map((entry) =>
            entry.id === address.id
                ? address
                : address.is_default
                  ? { ...entry, is_default: false }
                  : entry,
        );
    };

    const createAddress = async (
        payload: MobileCustomerAddressPayload,
    ): Promise<MobileCustomerAddress> => {
        const address = await customerAddressApi.createAddress(payload);
        addresses.value = [address, ...addresses.value.filter((entry) => entry.id !== address.id)];
        normalizeDefaultAddress(address);
        await selectAddress(address.id);

        return address;
    };

    const updateAddress = async (
        addressId: number,
        payload: MobileCustomerAddressPayload,
    ): Promise<MobileCustomerAddress> => {
        const address = await customerAddressApi.updateAddress(addressId, payload);
        normalizeDefaultAddress(address);

        if (selectedAddressId.value === address.id) {
            await selectAddress(address.id);
        }

        return address;
    };

    const deleteAddress = async (addressId: number): Promise<void> => {
        await customerAddressApi.deleteAddress(addressId);
        const wasSelected = selectedAddressId.value === addressId;
        addresses.value = addresses.value.filter((address) => address.id !== addressId);

        if (!wasSelected) {
            return;
        }

        const fallbackAddress =
            addresses.value.find((address) => address.is_default) ?? addresses.value[0] ?? null;
        selectedAddressId.value = fallbackAddress?.id ?? null;

        if (fallbackAddress) {
            await findNearestBranches(fallbackAddress.latitude, fallbackAddress.longitude);
        } else {
            deliveryCoordinates.value = null;
            nearestBranches.value = [];
        }
    };

    const setDefaultAddress = async (addressId: number): Promise<MobileCustomerAddress> => {
        const address = await customerAddressApi.setDefaultAddress(addressId);
        normalizeDefaultAddress(address);
        await selectAddress(address.id);

        return address;
    };

    const searchOrders = async (search: string): Promise<void> => {
        const currentContext = context.value;

        if (currentContext?.role !== 'branch_admin') {
            return;
        }

        const requestGeneration = ++orderSearchGeneration;
        queueLoading.value = true;
        requestError.value = null;

        try {
            const orders = await orderWorkspace.loadOrders(currentContext.organization.slug, {
                search,
            });

            if (requestGeneration === orderSearchGeneration && currentContext === context.value) {
                orderWorkspace.setOrders(orders);
            }
        } catch (error: unknown) {
            if (requestGeneration === orderSearchGeneration) {
                requestError.value = errorMessage(error, 'Antrean tidak dapat dimuat.');
            }
        } finally {
            if (requestGeneration === orderSearchGeneration) {
                queueLoading.value = false;
            }
        }
    };

    const searchCustomerOrders = async (filters: CustomerOrderFilters): Promise<void> => {
        const currentContext = context.value;

        if (currentContext?.role !== 'customer') {
            return;
        }

        const requestGeneration = ++customerOrderSearchGeneration;
        customerOrderLoading.value = true;
        requestError.value = null;

        try {
            const orders = await orderWorkspace.loadOrders(undefined, filters);

            if (
                requestGeneration === customerOrderSearchGeneration &&
                currentContext === context.value
            ) {
                orderWorkspace.setOrders(orders);
            }
        } catch (error: unknown) {
            if (requestGeneration === customerOrderSearchGeneration) {
                requestError.value = errorMessage(error, 'Pesanan pelanggan tidak dapat dimuat.');
            }
        } finally {
            if (requestGeneration === customerOrderSearchGeneration) {
                customerOrderLoading.value = false;
            }
        }
    };

    const refreshCustomerOrdersSilently = (filters: CustomerOrderFilters): Promise<boolean> => {
        const currentContext = context.value;

        if (currentContext?.role !== 'customer') {
            return Promise.resolve(false);
        }

        if (customerOrderRefreshPromise) {
            return customerOrderRefreshPromise;
        }

        const requestGeneration = ++customerOrderSearchGeneration;
        const refreshPromise = (async (): Promise<boolean> => {
            try {
                const orders = await orderWorkspace.loadOrders(undefined, filters);

                if (
                    requestGeneration !== customerOrderSearchGeneration ||
                    currentContext !== context.value
                ) {
                    return false;
                }

                orderWorkspace.setOrders(orders);

                return true;
            } catch {
                return false;
            }
        })();

        customerOrderRefreshPromise = refreshPromise;
        void refreshPromise.finally(() => {
            if (customerOrderRefreshPromise === refreshPromise) {
                customerOrderRefreshPromise = null;
            }
        });

        return refreshPromise;
    };

    const paymentActions: MobilePaymentActions = {
        refreshOrder: orderWorkspace.refreshOrder,
        createPayment: orderWorkspace.createPayment,
        loadPaymentGatewayMethods: orderWorkspace.loadPaymentGatewayMethods,
        loadPaymentWhatsAppSuggestion: orderWorkspace.loadPaymentWhatsAppSuggestion,
        transitionPaymentStatus: orderWorkspace.transitionPaymentStatus,
        loadReceipt: orderWorkspace.loadReceipt,
        printReceipt: async (orderId) => {
            requestError.value = null;

            try {
                const receipt = await orderWorkspace.loadReceipt(orderId);

                return printer.printReceipt(toLaundryReceiptData(receipt));
            } catch (error: unknown) {
                requestError.value = errorMessage(error, 'Struk tidak dapat dicetak.');
                return false;
            }
        },
    };

    onMounted(() => void load());

    return {
        context: readonly(context),
        addresses: readonly(addresses),
        services: readonly(services),
        customers: readonly(customers),
        deliveryCoordinates: readonly(deliveryCoordinates),
        selectedAddress,
        selectedAddressId: readonly(selectedAddressId),
        selectedCustomerId: customerWorkspace.selectedCustomerId,
        nearestBranches: readonly(nearestBranches),
        selectedBranch,
        selectedBranchId: readonly(selectedBranchId),
        selectedBranchDeliveryFee,
        loading: readonly(loading),
        refreshing: readonly(refreshing),
        queueLoading: readonly(queueLoading),
        customerOrderLoading: readonly(customerOrderLoading),
        requestError: readonly(requestError),
        orders: orderWorkspace.orders,
        rollbackMessage: orderWorkspace.rollbackMessage,
        whatsAppSuggestion: orderWorkspace.whatsAppSuggestion,
        whatsAppSuggestionError: orderWorkspace.whatsAppSuggestionError,
        loadStatusWhatsAppSuggestion: orderWorkspace.loadStatusWhatsAppSuggestion,
        createProcessing: orderWorkspace.createProcessing,
        pricingProcessing: orderWorkspace.pricingProcessing,
        transitionProcessing: orderWorkspace.transitionProcessing,
        paymentProcessing: orderWorkspace.paymentProcessing,
        paymentActions,
        printer,
        findNearestBranches,
        quoteOrder,
        confirmOrderPricing: orderWorkspace.confirmOrderPricing,
        createAddress,
        createCustomer: customerWorkspace.createCustomer,
        createOrder: orderWorkspace.createOrder,
        deleteAddress,
        load,
        refresh,
        refreshCustomerOrdersSilently,
        resetCustomerPin: customerWorkspace.resetCustomerPin,
        searchOrders,
        searchCustomerOrders,
        selectAddress,
        selectBranch,
        selectCustomer: customerWorkspace.selectCustomer,
        transitionOrder: orderWorkspace.transitionOrder,
        setDefaultAddress,
        updateAddress,
        updateCustomer: customerWorkspace.updateCustomer,
    };
};
