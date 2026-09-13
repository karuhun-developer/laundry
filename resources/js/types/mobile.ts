export type MobileRole = 'customer' | 'branch_admin';

export type MobileTab =
    | 'home'
    | 'orders'
    | 'branches'
    | 'account'
    | 'queue'
    | 'new-order'
    | 'customers'
    | 'more';

export type MobileOrderStatus =
    | 'received'
    | 'weighed'
    | 'washing'
    | 'drying'
    | 'ironing'
    | 'ready_for_pickup'
    | 'completed'
    | 'cancelled';

export type MobileOrganization = {
    id: number;
    name: string;
    slug: string;
};

export type MobileBranch = {
    id: number;
    name: string;
    code: string;
    phone: string | null;
    address: string;
    city: {
        name: string;
        province: string;
    };
    coordinates: {
        latitude: number | null;
        longitude: number | null;
    };
    delivery: {
        free_radius_meters: number;
        fee_per_km_rupiah: number;
    };
};

export type NearestMobileBranch = Omit<MobileBranch, 'delivery'> & {
    city: MobileBranch['city'] & { id: number };
    coordinates: {
        latitude: number;
        longitude: number;
    };
    delivery: MobileBranch['delivery'] & {
        distance_meters: number;
        within_free_radius: boolean;
        fee_rupiah: number;
    };
};

export type MobileContext = {
    role: MobileRole;
    organization: MobileOrganization;
    organizations: readonly MobileOrganization[];
    branches: readonly MobileBranch[];
};

export type MobileCustomer = {
    id: number;
    name: string;
    email: string;
};

export type MobilePriceOption = {
    id: number;
    name: string;
    type: 'fixed_package' | 'per_kilogram';
    pricing: {
        package_weight_grams?: number | null;
        base_price_rupiah?: number | null;
        excess_price_per_kilogram_rupiah?: number | null;
        price_per_kilogram_rupiah?: number | null;
        minimum_chargeable_grams?: number | null;
    };
};

export type MobileService = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    price_options: readonly MobilePriceOption[];
};

export type MobilePriceQuote = {
    price_option: MobilePriceOption;
    requested_weight_grams: number;
    chargeable_weight_grams: number;
    base_price_rupiah: number;
    excess_weight_grams: number;
    excess_price_rupiah: number;
    total_rupiah: number;
};

export type MobileOrderItem = {
    id: number;
    service: {
        id: number;
        name: string;
        code: string;
    };
    price_option: {
        id: number;
        name: string;
        type: MobilePriceOption['type'];
        pricing_snapshot: {
            package_weight_grams: number | null;
            base_price_rupiah: number | null;
            price_per_kilogram_rupiah: number | null;
            minimum_chargeable_grams: number | null;
        };
    };
    calculation: {
        requested_weight_grams: number;
        chargeable_weight_grams: number;
        base_price_rupiah: number;
        excess_weight_grams: number;
        excess_price_rupiah: number;
        subtotal_rupiah: number;
    };
};

export type MobileOrderStatusEvent = {
    id: number;
    status: MobileOrderStatus;
    actor: {
        id: number;
        name: string;
    };
    note: string | null;
    occurred_at: string;
    pending?: boolean;
};

export type MobileOrder = {
    id: number;
    order_number: string;
    status: MobileOrderStatus;
    organization_id: number;
    branch: Pick<MobileBranch, 'id' | 'name' | 'code'>;
    customer: Pick<MobileCustomer, 'id' | 'name'>;
    items: readonly MobileOrderItem[];
    pricing: {
        items_subtotal_rupiah: number;
        delivery_fee_rupiah: number;
        total_rupiah: number;
    };
    delivery: null | {
        customer_latitude: number;
        customer_longitude: number;
        distance_meters: number;
        free_radius_meters_snapshot: number;
        fee_per_km_rupiah_snapshot: number;
        fee_rupiah: number;
    };
    status_history: readonly MobileOrderStatusEvent[];
    created_at: string;
    pending?: boolean;
};

export type CreateOrderPayload = {
    customer_id?: number;
    items: Array<{
        price_option_id: number;
        weight_grams: number;
    }>;
    delivery?: {
        latitude: number;
        longitude: number;
    };
};

export type TransitionOrderPayload = {
    status: MobileOrderStatus;
    note?: string;
};

export type ApiEnvelope<T> = {
    data: T;
};
