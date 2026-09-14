import { computed, onMounted, readonly, ref } from 'vue';
import { useMobileCatalogApi } from '@/composables/useMobileCatalogApi';
import { useMobileCustomerWorkspace } from '@/composables/useMobileCustomerWorkspace';
import { useMobileOrderWorkspace } from '@/composables/useMobileOrderWorkspace';
import { useMobilePrinter } from '@/composables/useMobilePrinter';
import { toLaundryReceiptData } from '@/lib/mobilePayments';
import type {
    MobileBranch,
    MobileContext,
    MobileCustomer,
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
    const nearestBranches = ref<NearestMobileBranch[]>([]);
    const selectedBranchId = ref<number | null>(null);
    const loading = ref(true);
    const requestError = ref<string | null>(null);
    const catalogApi = useMobileCatalogApi();
    const printer = useMobilePrinter();
    const customerWorkspace = useMobileCustomerWorkspace(customers);
    let loadGeneration = 0;
    let catalogGeneration = 0;
    let nearestGeneration = 0;

    const selectedBranch = computed<MobileBranch | null>(
        () =>
            context.value?.branches.find((branch) => branch.id === selectedBranchId.value) ?? null,
    );
    const selectedBranchDeliveryFee = computed(
        () =>
            nearestBranches.value.find((branch) => branch.id === selectedBranchId.value)?.delivery
                .fee_rupiah ?? 0,
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

        const [orders, organizationCustomers] = await Promise.all([
            orderWorkspace.loadOrders(
                currentContext.role === 'branch_admin'
                    ? currentContext.organization.slug
                    : undefined,
            ),
            currentContext.role === 'branch_admin'
                ? catalogApi.loadCustomers(currentContext.organization.slug)
                : Promise.resolve([]),
        ]);

        if (requestGeneration !== loadGeneration) {
            return;
        }

        orderWorkspace.setOrders(orders);
        customerWorkspace.setCustomers(organizationCustomers);
        await loadCatalog();
    };

    const load = async (organizationSlug?: string): Promise<void> => {
        const requestGeneration = ++loadGeneration;
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
            nearestBranches.value = [];
            orderWorkspace.setOrders([]);
            nearestGeneration += 1;
            await loadOrganizationData(requestGeneration);
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
            }
        }
    };

    const selectBranch = async (branchId: number): Promise<void> => {
        if (!context.value?.branches.some((branch) => branch.id === branchId)) {
            requestError.value = 'Cabang yang dipilih tidak tersedia.';
            return;
        }

        selectedBranchId.value = branchId;
        services.value = [];
        requestError.value = null;

        try {
            await loadCatalog();
        } catch (error: unknown) {
            requestError.value = errorMessage(error, 'Katalog cabang tidak dapat dimuat.');
        }
    };

    const findNearestBranches = async (latitude: number, longitude: number): Promise<boolean> => {
        const currentContext = context.value;
        const requestGeneration = ++nearestGeneration;

        if (!currentContext) {
            return false;
        }

        requestError.value = null;

        try {
            const response = await catalogApi.findNearest(latitude, longitude);

            if (requestGeneration !== nearestGeneration) {
                return false;
            }

            nearestBranches.value = response;
            return true;
        } catch (error: unknown) {
            if (requestGeneration === nearestGeneration) {
                nearestBranches.value = [];
                requestError.value = errorMessage(error, 'Cabang terdekat tidak dapat dihitung.');
            }

            return false;
        }
    };

    const quoteOrder = async (
        priceOptionId: number,
        weightGrams: number,
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
        );
    };

    const paymentActions: MobilePaymentActions = {
        createPayment: orderWorkspace.createPayment,
        loadPaymentGatewayMethods: orderWorkspace.loadPaymentGatewayMethods,
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
        services: readonly(services),
        customers: readonly(customers),
        selectedCustomerId: customerWorkspace.selectedCustomerId,
        nearestBranches: readonly(nearestBranches),
        selectedBranch,
        selectedBranchId: readonly(selectedBranchId),
        selectedBranchDeliveryFee,
        loading: readonly(loading),
        requestError: readonly(requestError),
        orders: orderWorkspace.orders,
        rollbackMessage: orderWorkspace.rollbackMessage,
        whatsAppSuggestion: orderWorkspace.whatsAppSuggestion,
        whatsAppSuggestionError: orderWorkspace.whatsAppSuggestionError,
        createProcessing: orderWorkspace.createProcessing,
        pricingProcessing: orderWorkspace.pricingProcessing,
        transitionProcessing: orderWorkspace.transitionProcessing,
        paymentProcessing: orderWorkspace.paymentProcessing,
        paymentActions,
        printer,
        findNearestBranches,
        quoteOrder,
        confirmOrderPricing: orderWorkspace.confirmOrderPricing,
        createCustomer: customerWorkspace.createCustomer,
        createOrder: orderWorkspace.createOrder,
        detachCustomer: customerWorkspace.detachCustomer,
        load,
        resetCustomerPin: customerWorkspace.resetCustomerPin,
        selectBranch,
        selectCustomer: customerWorkspace.selectCustomer,
        transitionOrder: orderWorkspace.transitionOrder,
        updateCustomer: customerWorkspace.updateCustomer,
    };
};
