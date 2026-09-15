import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import MobileWorkspaceSwitcher from '@/components/mobile/molecules/MobileWorkspaceSwitcher.vue';
import BranchExplorer from '@/components/mobile/organisms/BranchExplorer.vue';
import { useMobileWorkspace } from '@/composables/useMobileWorkspace';
import type {
    MobileBranch,
    MobileContext,
    MobileCustomerAddress,
    MobileService,
    NearestMobileBranch,
} from '@/types/mobile';

const catalogApi = vi.hoisted(() => ({
    findNearest: vi.fn(),
    loadCatalog: vi.fn(),
    loadContext: vi.fn(),
    loadCustomers: vi.fn(),
    quoteOrder: vi.fn(),
}));
const geolocation = vi.hoisted(() => ({
    locate: vi.fn(),
}));
const orderApi = vi.hoisted(() => ({
    loadOrders: vi.fn(),
    setOrders: vi.fn(),
}));
const customerAddressApi = vi.hoisted(() => ({
    loadAddresses: vi.fn(),
    createAddress: vi.fn(),
    updateAddress: vi.fn(),
    deleteAddress: vi.fn(),
    setDefaultAddress: vi.fn(),
}));

vi.mock('@/composables/useMobileCatalogApi', () => ({
    useMobileCatalogApi: () => catalogApi,
}));
vi.mock('@/composables/useGeolocation', () => ({
    useGeolocation: () => ({
        coordinates: { value: null },
        error: { value: null },
        locating: { value: false },
        locate: geolocation.locate,
    }),
}));
vi.mock('@/composables/useMobileCustomerAddressApi', () => ({
    useMobileCustomerAddressApi: () => customerAddressApi,
}));
vi.mock('@/composables/useMobileCustomerWorkspace', async () => {
    const { ref } = await import('vue');

    return {
        useMobileCustomerWorkspace: () => ({
            selectedCustomerId: ref(null),
            createCustomer: vi.fn(),
            resetCustomerPin: vi.fn(),
            selectCustomer: vi.fn(),
            setCustomers: vi.fn(),
            updateCustomer: vi.fn(),
        }),
    };
});
vi.mock('@/composables/useMobileOrderWorkspace', async () => {
    const { ref } = await import('vue');

    return {
        useMobileOrderWorkspace: () => ({
            orders: ref([]),
            rollbackMessage: ref(null),
            whatsAppSuggestion: ref(null),
            whatsAppSuggestionError: ref(null),
            createProcessing: ref(false),
            pricingProcessing: ref(false),
            transitionProcessing: ref(false),
            paymentProcessing: ref(false),
            confirmOrderPricing: vi.fn(),
            createOrder: vi.fn(),
            createPayment: vi.fn(),
            loadPaymentWhatsAppSuggestion: vi.fn(),
            loadOrders: orderApi.loadOrders,
            loadPaymentGatewayMethods: vi.fn(),
            loadReceipt: vi.fn(),
            refreshOrder: vi.fn(),
            setOrders: orderApi.setOrders,
            transitionOrder: vi.fn(),
            transitionPaymentStatus: vi.fn(),
        }),
    };
});
vi.mock('@/composables/useMobilePrinter', async () => {
    const { ref } = await import('vue');

    return {
        useMobilePrinter: () => ({
            available: false,
            clearProfile: vi.fn(),
            devices: ref([]),
            discover: vi.fn(),
            discovering: ref(false),
            error: ref(null),
            message: ref(null),
            printReceipt: vi.fn(),
            printing: ref(false),
            profile: ref(null),
            saveProfile: vi.fn(),
            testPrint: vi.fn(),
        }),
    };
});

const kemangBranch: MobileBranch = {
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
const dagoBranch: MobileBranch = {
    ...kemangBranch,
    id: 8,
    name: 'Cabang Dago',
    code: 'DGO01',
    address: 'Jl. Ir. H. Juanda',
    organization: { id: 2, slug: 'bersih-bandung' },
    city: { name: 'Bandung', province: 'Jawa Barat' },
    coordinates: { latitude: -6.8841, longitude: 107.6137 },
};
const surabayaBranch: MobileBranch = {
    ...kemangBranch,
    id: 9,
    name: 'Cabang Surabaya',
    code: 'SBY01',
    organization: { id: 3, slug: 'bersih-surabaya' },
    city: { name: 'Surabaya', province: 'Jawa Timur' },
};
const customerContext: MobileContext = {
    role: 'customer',
    organization: { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
    organizations: [],
    branches: [kemangBranch, dagoBranch],
};
const branchAdminContext: MobileContext = {
    ...customerContext,
    role: 'branch_admin',
    organizations: [customerContext.organization],
};
const secondCustomerContext: MobileContext = {
    role: 'customer',
    organization: { id: 3, name: 'Bersih Surabaya', slug: 'bersih-surabaya' },
    organizations: [],
    branches: [surabayaBranch],
};
const nearestBranches: NearestMobileBranch[] = [
    {
        ...kemangBranch,
        city: { id: 1, ...kemangBranch.city },
        coordinates: { latitude: -6.2607, longitude: 106.8136 },
        delivery: {
            ...kemangBranch.delivery,
            distance_meters: 12500,
            within_free_radius: false,
            fee_rupiah: 9000,
        },
    },
    {
        ...dagoBranch,
        city: { id: 2, ...dagoBranch.city },
        coordinates: { latitude: -6.8841, longitude: 107.6137 },
        delivery: {
            ...dagoBranch.delivery,
            distance_meters: 2500,
            within_free_radius: true,
            fee_rupiah: 0,
        },
    },
];
const defaultCustomerAddress: MobileCustomerAddress = {
    id: 41,
    label: 'Rumah',
    recipient_name: 'Pelanggan Demo',
    whatsapp_phone: '6281234567890',
    address: 'Jl. Rumah Pelanggan No. 1',
    notes: 'Pagar biru',
    latitude: -6.91,
    longitude: 107.61,
    is_default: true,
    created_at: '2026-09-15T08:00:00+00:00',
    updated_at: '2026-09-15T08:00:00+00:00',
};
const serviceFor = (branchId: number): MobileService => ({
    id: branchId * 10,
    name: `Layanan cabang ${branchId}`,
    code: `SERVICE-${branchId}`,
    description: null,
    price_options: [],
});

type Workspace = ReturnType<typeof useMobileWorkspace>;

const mountWorkspace = (): { workspace: Workspace; unmount: () => void } => {
    let workspace: Workspace | undefined;
    const wrapper = mount(
        defineComponent({
            setup() {
                workspace = useMobileWorkspace();

                return () => h('div');
            },
        }),
    );

    if (!workspace) {
        throw new Error('Mobile workspace did not initialize.');
    }

    return { workspace, unmount: () => wrapper.unmount() };
};

const mountWorkspaceUi = (): { workspace: Workspace; wrapper: VueWrapper } => {
    let workspace: Workspace | undefined;
    const wrapper = mount(
        defineComponent({
            setup() {
                workspace = useMobileWorkspace();

                return () => {
                    const currentWorkspace = workspace;
                    const currentContext = currentWorkspace?.context.value;

                    if (!currentWorkspace || !currentContext) {
                        return h('div');
                    }

                    return h('div', [
                        h(MobileWorkspaceSwitcher, {
                            context: currentContext,
                            nearestBranches: currentWorkspace.nearestBranches.value,
                            selectedBranchId: currentWorkspace.selectedBranchId.value,
                            onBranchChange: currentWorkspace.selectBranch,
                        }),
                        currentContext.role === 'customer'
                            ? h(BranchExplorer, {
                                  branches: currentContext.branches,
                                  nearestBranches: currentWorkspace.nearestBranches.value,
                                  selectedBranchId: currentWorkspace.selectedBranchId.value,
                                  selectedCoordinates: currentWorkspace.deliveryCoordinates.value,
                                  onSelect: currentWorkspace.selectBranch,
                              })
                            : null,
                    ]);
                };
            },
        }),
        {
            global: {
                stubs: { LocationPicker: true },
            },
        },
    );

    if (!workspace) {
        throw new Error('Mobile workspace UI did not initialize.');
    }

    return { workspace, wrapper };
};

beforeEach(() => {
    vi.clearAllMocks();
    catalogApi.loadContext.mockResolvedValue(customerContext);
    catalogApi.loadCatalog.mockImplementation(
        async (_organizationSlug: string, branchId: number) => [serviceFor(branchId)],
    );
    catalogApi.loadCustomers.mockResolvedValue([]);
    catalogApi.findNearest.mockResolvedValue(nearestBranches);
    customerAddressApi.loadAddresses.mockResolvedValue([]);
    geolocation.locate.mockResolvedValue({ latitude: -6.9, longitude: 107.6 });
    orderApi.loadOrders.mockResolvedValue([]);
});

describe('mobile workspace automatic nearest branch selection', () => {
    it('uses the saved default address before requesting the current device location', async () => {
        customerAddressApi.loadAddresses.mockResolvedValue([defaultCustomerAddress]);
        const { workspace, unmount } = mountWorkspace();

        await vi.waitFor(() => expect(workspace.selectedBranchId.value).toBe(dagoBranch.id));

        expect(geolocation.locate).not.toHaveBeenCalled();
        expect(catalogApi.findNearest).toHaveBeenCalledWith(
            defaultCustomerAddress.latitude,
            defaultCustomerAddress.longitude,
        );
        expect(workspace.selectedAddress.value).toEqual(defaultCustomerAddress);
        expect(workspace.deliveryCoordinates.value).toEqual({
            latitude: defaultCustomerAddress.latitude,
            longitude: defaultCustomerAddress.longitude,
        });
        expect(workspace.services.value).toEqual([serviceFor(dagoBranch.id)]);

        unmount();
    });

    it('locates customers after readiness, sorts nearest-first, selects the nearest, and reloads its catalog', async () => {
        const { workspace, unmount } = mountWorkspace();

        await vi.waitFor(() => expect(workspace.selectedBranchId.value).toBe(dagoBranch.id));

        expect(geolocation.locate).toHaveBeenCalledOnce();
        expect(catalogApi.findNearest).toHaveBeenCalledWith(-6.9, 107.6);
        expect(workspace.loading.value).toBe(false);
        expect(workspace.deliveryCoordinates.value).toEqual({ latitude: -6.9, longitude: 107.6 });
        expect(workspace.nearestBranches.value.map(({ id }) => id)).toEqual([8, 7]);
        expect(workspace.selectedBranchDeliveryFee.value).toBe(0);
        expect(catalogApi.loadCatalog.mock.calls).toEqual([
            ['laundryhub-demo', 7],
            ['bersih-bandung', 8],
        ]);
        expect(workspace.services.value).toEqual([serviceFor(8)]);

        unmount();
    });

    it('never requests location for branch administrators', async () => {
        catalogApi.loadContext.mockResolvedValue(branchAdminContext);
        const { workspace, unmount } = mountWorkspace();

        await vi.waitFor(() => expect(workspace.loading.value).toBe(false));
        await flushPromises();

        expect(geolocation.locate).not.toHaveBeenCalled();
        expect(catalogApi.findNearest).not.toHaveBeenCalled();
        expect(workspace.selectedBranchId.value).toBe(kemangBranch.id);

        unmount();
    });

    it('keeps the workspace usable when permission or GPS access fails', async () => {
        geolocation.locate.mockResolvedValue(null);
        const { workspace, unmount } = mountWorkspace();

        await vi.waitFor(() => expect(workspace.loading.value).toBe(false));
        await workspace.selectBranch(dagoBranch.id);

        expect(workspace.requestError.value).toBeNull();
        expect(workspace.selectedBranchId.value).toBe(dagoBranch.id);
        expect(workspace.services.value).toEqual([serviceFor(8)]);
        expect(catalogApi.findNearest).not.toHaveBeenCalled();

        unmount();
    });

    it('does not overwrite a manual branch choice when automatic location resolves late', async () => {
        let resolveLocation: (coordinates: { latitude: number; longitude: number }) => void = () =>
            undefined;
        geolocation.locate.mockReturnValue(
            new Promise((resolve) => {
                resolveLocation = resolve;
            }),
        );
        const { workspace, unmount } = mountWorkspace();

        await vi.waitFor(() => expect(geolocation.locate).toHaveBeenCalledOnce());
        await workspace.selectBranch(dagoBranch.id);
        resolveLocation({ latitude: -6.9, longitude: 107.6 });
        await vi.waitFor(() => expect(catalogApi.findNearest).toHaveBeenCalledOnce());
        await flushPromises();

        expect(workspace.selectedBranchId.value).toBe(dagoBranch.id);
        expect(workspace.nearestBranches.value.map(({ id }) => id)).toEqual([8, 7]);
        expect(catalogApi.loadCatalog.mock.calls).toEqual([
            ['laundryhub-demo', 7],
            ['bersih-bandung', 8],
        ]);

        unmount();
    });

    it('ignores a nearest response from a stale workspace load', async () => {
        let resolveNearest: (branches: NearestMobileBranch[]) => void = () => undefined;
        catalogApi.findNearest.mockReturnValueOnce(
            new Promise((resolve) => {
                resolveNearest = resolve;
            }),
        );
        geolocation.locate
            .mockResolvedValueOnce({ latitude: -6.9, longitude: 107.6 })
            .mockResolvedValueOnce(null);
        catalogApi.loadContext
            .mockResolvedValueOnce(customerContext)
            .mockResolvedValueOnce(secondCustomerContext);
        const { workspace, unmount } = mountWorkspace();

        await vi.waitFor(() => expect(catalogApi.findNearest).toHaveBeenCalledOnce());
        await workspace.load('bersih-surabaya');
        resolveNearest(nearestBranches);
        await flushPromises();

        expect(workspace.context.value).toEqual(secondCustomerContext);
        expect(workspace.selectedBranchId.value).toBe(surabayaBranch.id);
        expect(workspace.nearestBranches.value).toEqual([]);
        expect(workspace.services.value).toEqual([serviceFor(9)]);

        unmount();
    });
});

describe('mobile workspace refresh', () => {
    it('refreshes only customer orders silently and prevents overlapping requests', async () => {
        const { workspace, unmount } = mountWorkspace();
        await vi.waitFor(() => expect(workspace.loading.value).toBe(false));
        vi.clearAllMocks();
        let resolveOrders: (orders: []) => void = () => undefined;
        orderApi.loadOrders.mockReturnValueOnce(
            new Promise((resolve) => {
                resolveOrders = resolve;
            }),
        );
        const filters = { search: 'KMG', date_from: '2026-09-01', date_to: '2026-09-15' };

        const firstRefresh = workspace.refreshCustomerOrdersSilently(filters);
        const overlappingRefresh = workspace.refreshCustomerOrdersSilently(filters);

        expect(firstRefresh).toBe(overlappingRefresh);
        expect(orderApi.loadOrders).toHaveBeenCalledOnce();
        expect(orderApi.loadOrders).toHaveBeenCalledWith(undefined, filters);
        expect(catalogApi.loadContext).not.toHaveBeenCalled();
        expect(catalogApi.loadCatalog).not.toHaveBeenCalled();
        expect(customerAddressApi.loadAddresses).not.toHaveBeenCalled();

        resolveOrders([]);
        await expect(firstRefresh).resolves.toBe(true);
        expect(orderApi.setOrders).toHaveBeenCalledWith([]);

        unmount();
    });

    it('keeps the last order data without exposing an error when silent refresh fails', async () => {
        const { workspace, unmount } = mountWorkspace();
        await vi.waitFor(() => expect(workspace.loading.value).toBe(false));
        vi.clearAllMocks();
        orderApi.loadOrders.mockRejectedValueOnce(new Error('Jaringan terputus.'));

        await expect(
            workspace.refreshCustomerOrdersSilently({ search: '', date_from: '', date_to: '' }),
        ).resolves.toBe(false);

        expect(orderApi.setOrders).not.toHaveBeenCalled();
        expect(workspace.requestError.value).toBeNull();

        unmount();
    });

    it('preserves the selected branch, address, and delivery coordinates', async () => {
        customerAddressApi.loadAddresses.mockResolvedValue([defaultCustomerAddress]);
        const { workspace, unmount } = mountWorkspace();

        await vi.waitFor(() => expect(workspace.selectedBranchId.value).toBe(dagoBranch.id));
        await workspace.selectBranch(kemangBranch.id);

        const refreshed = await workspace.refresh();

        expect(refreshed).toBe(true);
        expect(workspace.refreshing.value).toBe(false);
        expect(workspace.selectedBranchId.value).toBe(kemangBranch.id);
        expect(workspace.selectedAddressId.value).toBe(defaultCustomerAddress.id);
        expect(workspace.deliveryCoordinates.value).toEqual({
            latitude: defaultCustomerAddress.latitude,
            longitude: defaultCustomerAddress.longitude,
        });

        unmount();
    });

    it('resets refreshing and exposes a useful error when refresh fails', async () => {
        const { workspace, unmount } = mountWorkspace();
        await vi.waitFor(() => expect(workspace.loading.value).toBe(false));
        catalogApi.loadContext.mockRejectedValueOnce(new Error('Jaringan terputus.'));

        const refreshed = await workspace.refresh();

        expect(refreshed).toBe(false);
        expect(workspace.refreshing.value).toBe(false);
        expect(workspace.requestError.value).toBe('Jaringan terputus.');

        unmount();
    });

    it('rejects a concurrent refresh while the first request is still running', async () => {
        const { workspace, unmount } = mountWorkspace();
        await vi.waitFor(() => expect(workspace.loading.value).toBe(false));
        let resolveContext: (context: MobileContext) => void = () => undefined;
        catalogApi.loadContext.mockReturnValueOnce(
            new Promise((resolve) => {
                resolveContext = resolve;
            }),
        );

        const firstRefresh = workspace.refresh();
        expect(workspace.refreshing.value).toBe(true);
        await expect(workspace.refresh()).resolves.toBe(false);

        resolveContext(customerContext);
        await expect(firstRefresh).resolves.toBe(true);
        expect(workspace.refreshing.value).toBe(false);

        unmount();
    });
});

describe('mobile workspace customer addresses', () => {
    it('selects the latest address ID returned by create', async () => {
        const createdAddress: MobileCustomerAddress = {
            ...defaultCustomerAddress,
            id: 88,
            label: 'Kantor baru',
            latitude: -6.9175,
            longitude: 107.6191,
        };
        customerAddressApi.createAddress.mockResolvedValue(createdAddress);
        const { workspace, unmount } = mountWorkspace();

        await vi.waitFor(() => expect(workspace.loading.value).toBe(false));
        await workspace.createAddress({
            label: createdAddress.label,
            recipient_name: createdAddress.recipient_name,
            whatsapp_phone: createdAddress.whatsapp_phone,
            address: createdAddress.address,
            notes: createdAddress.notes,
            latitude: createdAddress.latitude,
            longitude: createdAddress.longitude,
            is_default: true,
        });

        expect(workspace.selectedAddressId.value).toBe(createdAddress.id);
        expect(workspace.selectedAddress.value).toEqual(createdAddress);
        expect(catalogApi.findNearest).toHaveBeenLastCalledWith(
            createdAddress.latitude,
            createdAddress.longitude,
        );

        unmount();
    });
});

describe('mobile nearest branch component render QA', () => {
    it('renders automatic nearest ordering and preserves authoritative coverage after manual selection', async () => {
        const { workspace, wrapper } = mountWorkspaceUi();

        await vi.waitFor(() => expect(workspace.selectedBranchId.value).toBe(dagoBranch.id));

        const options = wrapper.findAll('[data-test="branch-select"] option');

        expect(options.map((option) => option.attributes('value'))).toEqual(['8', '7']);
        expect(options[0]?.text()).toContain('2,5 km · Gratis jemput & antar');
        expect(wrapper.get('[data-test="selected-branch-coverage"]').text()).toBe(
            'Jarak 2,5 km · gratis jemput & antar dalam radius 10 km.',
        );

        await wrapper.findAll('article')[1]?.get('button').trigger('click');
        await vi.waitFor(() => expect(workspace.selectedBranchId.value).toBe(kemangBranch.id));

        expect(workspace.services.value).toEqual([serviceFor(7)]);
        expect(wrapper.get('[data-test="selected-branch-coverage"]').text()).toBe(
            'Jarak 12,5 km · radius gratis 10 km · estimasi biaya kelebihan Rp 9.000 (Rp 3.000/km).',
        );

        wrapper.unmount();
    });

    it('keeps real branch controls usable when automatic location is unavailable', async () => {
        geolocation.locate.mockResolvedValue(null);
        const { workspace, wrapper } = mountWorkspaceUi();

        await vi.waitFor(() => expect(workspace.loading.value).toBe(false));
        await wrapper.get('[data-test="branch-select"]').setValue(dagoBranch.id.toString());
        await vi.waitFor(() => expect(workspace.selectedBranchId.value).toBe(dagoBranch.id));

        expect(workspace.requestError.value).toBeNull();
        expect(workspace.services.value).toEqual([serviceFor(8)]);
        expect(wrapper.findComponent(BranchExplorer).exists()).toBe(true);

        wrapper.unmount();
    });

    it('does not render customer discovery or request location for branch administrators', async () => {
        catalogApi.loadContext.mockResolvedValue(branchAdminContext);
        const { workspace, wrapper } = mountWorkspaceUi();

        await vi.waitFor(() => expect(workspace.loading.value).toBe(false));
        await flushPromises();

        expect(geolocation.locate).not.toHaveBeenCalled();
        expect(catalogApi.findNearest).not.toHaveBeenCalled();
        expect(wrapper.findComponent(BranchExplorer).exists()).toBe(false);
        expect(wrapper.get('[data-test="branch-select"]').element).toHaveProperty(
            'value',
            kemangBranch.id.toString(),
        );

        wrapper.unmount();
    });
});
