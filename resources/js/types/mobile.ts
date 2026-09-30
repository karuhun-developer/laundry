import type {
    MobilePayment,
    MobilePaymentGatewayDriver,
    MobilePaymentGatewayMethod,
    MobilePaymentMethod,
    MobilePaymentStatus,
    MobilePaymentSummary,
} from '@/types/mobilePayments';

export type {
    MobilePayment,
    MobilePaymentGateway,
    MobilePaymentGatewayDriver,
    MobilePaymentGatewayMethod,
    MobilePaymentMethod,
    MobilePaymentStatus,
    MobilePaymentStatusEvent,
    MobilePaymentSummary,
} from '@/types/mobilePayments';

export type MobileRole = 'customer' | 'branch_admin';

export type MobileTab = 'home' | 'orders' | 'branches' | 'account' | 'queue' | 'customers' | 'more';

export type MobileOrderStatus =
    | 'pickup_requested'
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
    organization: Pick<MobileOrganization, 'id' | 'slug'>;
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
    payments: {
        qris_enabled: boolean;
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
    email: string | null;
    whatsapp_phone: string | null;
    must_change_pin: boolean;
    pin_expires_at: string | null;
    pending?: boolean;
};

export type MobileCustomerProfilePayload = {
    name: string;
    whatsapp_phone: string;
};

export type MobileCustomerAddress = {
    id: number;
    label: string;
    recipient_name: string;
    whatsapp_phone: string;
    address: string;
    notes: string | null;
    latitude: number;
    longitude: number;
    is_default: boolean;
    created_at: string | null;
    updated_at: string | null;
};

export type MobileCustomerAddressPayload = {
    label: string;
    recipient_name: string;
    whatsapp_phone: string;
    address: string;
    notes: string | null;
    latitude: number;
    longitude: number;
    is_default: boolean;
};

export type MobileCustomerCredential = {
    temporary_pin: string;
    expires_at: string;
};

export type MobileCustomerMutationResult = {
    customer: MobileCustomer;
    credential: MobileCustomerCredential | null;
};

export type MobileCustomerCredentialResult = {
    customer: MobileCustomer;
    credential: MobileCustomerCredential;
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
        min_price_per_kilogram_rupiah?: number | null;
        minimum_chargeable_grams?: number | null;
    };
};

export type MobileService = {
    completion_duration_hours?: number;
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
    effective_price_per_kilogram_rupiah?: number | null;
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
            min_price_per_kilogram_rupiah?: number | null;
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
    estimated_ready_at?: string | null;
    completion_duration_hours_snapshot?: number | null;
    is_overdue?: boolean;
    timezone?: string;
    tasks?: readonly OrderTask[];
    complaints?: readonly OrderComplaint[];
    id: number;
    order_number: string;
    status: MobileOrderStatus;
    organization_id: number;
    organization: Pick<MobileOrganization, 'id' | 'slug'>;
    branch: Pick<MobileBranch, 'id' | 'name' | 'code'> & { qris_enabled: boolean };
    customer: Pick<MobileCustomer, 'id' | 'name'>;
    items: readonly MobileOrderItem[];
    pricing: {
        confirmed: boolean;
        confirmed_at: string | null;
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
        address: null | Pick<
            MobileCustomerAddress,
            'id' | 'label' | 'recipient_name' | 'whatsapp_phone' | 'address' | 'notes'
        >;
    };
    status_history: readonly MobileOrderStatusEvent[];
    payments: readonly MobilePayment[];
    payment_summary: MobilePaymentSummary;
    created_at: string;
    pending?: boolean;
};

export type CreateOrderPayload = {
    customer_id?: number;
    customer_address_id?: number;
    items: Array<{
        price_option_id: number;
        weight_grams?: number;
        custom_price_per_kilogram_rupiah?: number;
    }>;
    delivery?: {
        latitude: number;
        longitude: number;
    };
};

export type ConfirmOrderPricingPayload = {
    price_option_id: number;
    weight_grams: number;
    custom_price_per_kilogram_rupiah?: number;
};

export type TransitionOrderPayload = {
    status: MobileOrderStatus;
    note?: string;
};

export type WhatsAppSuggestion =
    | {
          readonly available: true;
          readonly phone: string;
          readonly message: string;
          readonly url: string;
          readonly template_id: number | null;
          readonly unavailable_reason: null;
      }
    | {
          readonly available: false;
          readonly phone: null;
          readonly message: null;
          readonly url: null;
          readonly template_id: null;
          readonly unavailable_reason: string;
      };

export type CreatePaymentPayload = {
    method: MobilePaymentMethod;
    amount_rupiah: number;
    note?: string;
    gateway_driver?: MobilePaymentGatewayDriver;
    gateway_method?: string;
};

export type TransitionPaymentStatusPayload = {
    status: MobilePaymentStatus;
    note?: string;
};

export type MobileOrderReceipt = {
    receipt_number: string;
    order_id: number;
    order_status: MobileOrderStatus;
    branch: {
        id: number;
        name: string;
        code: string;
        phone: string | null;
        address: string;
        city: {
            name: string;
            province: string;
        };
    };
    customer: Pick<MobileCustomer, 'id' | 'name'>;
    items: readonly MobileOrderItem[];
    pricing: MobileOrder['pricing'] &
        Pick<MobilePaymentSummary, 'paid_rupiah' | 'outstanding_rupiah'>;
    payments: readonly MobilePayment[];
    issued_at: string;
    ordered_at: string;
    share_url: string;
};

export type MobilePaymentActions = {
    refreshOrder: (orderId: number) => Promise<MobileOrder>;
    createPayment: (orderId: number, payload: CreatePaymentPayload) => Promise<boolean>;
    loadPaymentWhatsAppSuggestion: (
        orderId: number,
        paymentId: number,
    ) => Promise<WhatsAppSuggestion>;
    loadPaymentGatewayMethods: () => Promise<readonly MobilePaymentGatewayMethod[]>;
    transitionPaymentStatus: (
        orderId: number,
        paymentId: number,
        payload: TransitionPaymentStatusPayload,
    ) => Promise<boolean>;
    loadReceipt: (orderId: number) => Promise<MobileOrderReceipt>;
    printReceipt: (orderId: number) => Promise<boolean>;
};

export type ApiEnvelope<T> = {
    data: T;
    meta?: OrderPagination;
    statistics?: { active: number; unweighed: number; ready: number };
};

export type OrderQueue = 'all' | 'active' | 'history' | 'unweighed' | 'ready' | 'overdue';
export type OrderPagination = {
    current_page: number;
    last_page: number;
    total: number;
    statistics?: { active: number; unweighed: number; ready: number };
};
export type OperationEvidence = { id: number; name: string; url: string };
export type OperationEvent = {
    id: number;
    status: string;
    actor_name: string;
    note: string | null;
    occurred_at: string;
};
export type OrderTaskStatus =
    | 'waiting'
    | 'scheduled'
    | 'en_route'
    | 'completed'
    | 'failed'
    | 'cancelled';
export type OrderTask = {
    id: number;
    kind: 'pickup' | 'delivery';
    status: OrderTaskStatus;
    operator_name: string | null;
    scheduled_at: string | null;
    address: string | null;
    recipient_name: string | null;
    note: string | null;
    completed_at: string | null;
    history: readonly OperationEvent[];
    evidence: readonly OperationEvidence[];
};
export type OrderComplaint = {
    id: number;
    category: string;
    description: string;
    status: 'new' | 'handling' | 'resolved';
    resolution_note: string | null;
    resolved_at: string | null;
    history: readonly OperationEvent[];
    evidence: readonly OperationEvidence[];
};
export type OrderOperationsOrder = Pick<
    MobileOrder,
    | 'id'
    | 'organization'
    | 'estimated_ready_at'
    | 'is_overdue'
    | 'timezone'
    | 'tasks'
    | 'complaints'
> & {
    status: string;
    pricing: { confirmed: boolean };
    delivery: { address: { address: string | null; recipient_name: string | null } | null } | null;
    branch: { id: number };
};
