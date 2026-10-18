export type OrganizationOption = {
    id: number;
    name: string;
};

export type OrganizationSummary = OrganizationOption & {
    slug: string;
    is_active: boolean;
    qris_enabled?: boolean;
    qris_api_key?: string | null;
    qris_base_url?: string | null;
    branches_count: number;
    users_count: number;
    pending?: boolean;
};

export type CitySummary = {
    id: number;
    name: string;
    province: string;
    pending?: boolean;
};

export type BranchSummary = {
    id: number;
    organization_id: number;
    city_id: number;
    name: string;
    code: string;
    phone: string | null;
    address: string;
    latitude: number | null;
    longitude: number | null;
    free_delivery_radius_meters: number;
    delivery_fee_per_km_rupiah: number;
    qris_enabled: boolean;
    qris_api_key?: string | null;
    qris_base_url?: string | null;
    is_active: boolean;
    city: Pick<CitySummary, 'id' | 'name' | 'province'>;
    organization: OrganizationOption;
    pending?: boolean;
};

export type RoleSummary = {
    id: number;
    name: string;
};

export type ManagedUserRole = 'owner' | 'branch_admin' | 'customer';

export type UserSummary = {
    id: number;
    organization_id: number | null;
    name: string;
    email: string | null;
    whatsapp_phone: string | null;
    roles: RoleSummary[];
    branches: Pick<BranchSummary, 'id' | 'name'>[];
    organization?: OrganizationOption;
    pending?: boolean;
};

export type OrganizationsPageProps = {
    organizations: OrganizationSummary[];
};

export type BranchesPageProps = {
    branches: BranchSummary[];
    cities: CitySummary[];
    organizations: OrganizationOption[];
};

export type UsersPageProps = {
    users: UserSummary[];
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
    };
    statistics?: {
        total: number;
        branch_admins: number;
    };
    branches: Pick<BranchSummary, 'id' | 'organization_id' | 'name'>[];
    organizations: OrganizationOption[];
};

export type OrderStatusOption = {
    value: string;
    label: string;
};

export type MessageTemplateSummary = {
    id: number;
    row_key: string;
    organization_id: number;
    branch_id: number | null;
    scope_key: string;
    order_status: string;
    body: string;
    is_active: boolean;
    organization: OrganizationOption;
    branch: Pick<BranchSummary, 'id' | 'name'> | null;
    is_inherited: boolean;
    pending?: boolean;
};

export type MessageTemplatesPageProps = {
    messageTemplates: MessageTemplateSummary[];
    branches: Pick<BranchSummary, 'id' | 'organization_id' | 'name'>[];
    organizations: OrganizationOption[];
    orderStatuses: OrderStatusOption[];
    supportedVariables: string[];
};

export type PriceTypeValue = 'fixed_package' | 'per_kilogram';

export type CatalogBranchSummary = {
    id: number;
    organization_id: number;
    name: string;
    code: string;
    is_active: boolean;
    organization: OrganizationOption;
};

export type PriceOptionSummary = {
    id: number;
    service_id: number;
    branch_id: number | null;
    type: PriceTypeValue;
    name: string;
    package_weight_grams: number | null;
    base_price_rupiah: number | null;
    price_per_kilogram_rupiah: number | null;
    min_price_per_kilogram_rupiah: number | null;
    minimum_chargeable_grams: number | null;
    is_active: boolean;
    branch: Pick<CatalogBranchSummary, 'id' | 'organization_id' | 'name' | 'code'> | null;
    pending?: boolean;
};

export type ServiceSummary = {
    completion_duration_hours?: number;
    id: number;
    organization_id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    organization: OrganizationOption;
    price_options: PriceOptionSummary[];
    pending?: boolean;
};

export type ServicesPageProps = {
    services: ServiceSummary[];
    branches: CatalogBranchSummary[];
    organizations: OrganizationOption[];
};

export type CmsOrderItemSummary = {
    id: number;
    service: {
        id: number;
        name: string;
        code: string;
    };
    price_option: {
        id: number;
        name: string;
        type: string;
        pricing_snapshot: {
            package_weight_grams: number | null;
            base_price_rupiah: number | null;
            price_per_kilogram_rupiah: number | null;
            min_price_per_kilogram_rupiah: number | null;
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

export type CmsOrderStatusEventSummary = {
    id: number;
    status: string;
    actor: {
        id: number | null;
        name: string;
    };
    note: string | null;
    occurred_at: string;
};

export type CmsOrderPaymentSummary = {
    id: number;
    payment_number: string | null;
    method: string;
    method_label: string;
    status: string;
    status_label: string;
    amount_rupiah: number;
    qris_payload: string | null;
    qris_image_data_uri: string | null;
    note: string | null;
    settled_at: string | null;
    created_at: string;
};

export type CmsOrderSummary = {
    estimated_ready_at?: string | null;
    is_overdue?: boolean;
    timezone?: string;
    tasks?: readonly import('./mobile').OrderTask[];
    complaints?: readonly import('./mobile').OrderComplaint[];
    id: number;
    order_number: string | null;
    status: string;
    organization_id: number;
    organization: OrganizationOption & { slug: string };
    branch: Pick<BranchSummary, 'id' | 'name' | 'code' | 'qris_enabled'>;
    customer: {
        id: number | null;
        name: string;
        whatsapp_phone?: string | null;
    };
    items: CmsOrderItemSummary[];
    pricing: {
        confirmed: boolean;
        confirmed_at: string | null;
        items_subtotal_rupiah: number;
        delivery_fee_rupiah: number;
        total_rupiah: number;
    };
    delivery: {
        customer_latitude: number | null;
        customer_longitude: number | null;
        address: {
            id: number | null;
            label: string | null;
            recipient_name: string | null;
            whatsapp_phone: string | null;
            address: string | null;
            notes: string | null;
        } | null;
        distance_meters: number | null;
        free_radius_meters_snapshot: number | null;
        fee_per_km_rupiah_snapshot: number | null;
        fee_rupiah: number;
    } | null;
    status_history: CmsOrderStatusEventSummary[];
    payments: CmsOrderPaymentSummary[];
    payment_summary: {
        paid_rupiah: number;
        outstanding_rupiah: number;
        is_paid: boolean;
    };
    created_at: string;
};

export type OrderBranchOption = {
    id: number;
    organization_id: number;
    name: string;
    code: string;
    is_active: boolean;
};

export type OrdersPageProps = {
    orders: CmsOrderSummary[];
    branches: OrderBranchOption[];
    organizations: OrganizationOption[];
    orderStatuses: OrderStatusOption[];
};

export type CashflowCategoryOption = {
    id: number;
    organization_id: number;
    name: string;
    type: 'debit' | 'credit';
    group: 'rutin' | 'non_rutin';
    is_system?: boolean;
};

export type CashflowBranchOption = {
    id: number;
    organization_id: number;
    name: string;
};

export type CashflowEntrySummary = {
    id: number;
    organization_id: number;
    branch_id: number;
    cashflow_category_id: number;
    payment_id: number | null;
    type: 'debit' | 'credit';
    amount_rupiah: number;
    description: string;
    entry_date: string;
    created_at: string;
    category: {
        id: number;
        name: string;
        type: 'debit' | 'credit';
        group: 'rutin' | 'non_rutin';
    };
    branch: {
        id: number;
        name: string;
    };
    pending?: boolean;
};

export type CashflowStatementCategory = {
    id: number;
    name: string;
    type: 'debit' | 'credit';
    group: 'rutin' | 'non_rutin';
    monthly: Record<number, number>;
    total: number;
};

export type CashflowStatement = {
    year: number;
    total_debit: number;
    total_credit: number;
    net_profit: number;
    months: Record<
        number,
        {
            month: number;
            debit: number;
            credit: number;
            net: number;
        }
    >;
    categories: CashflowStatementCategory[];
};

export type CashflowPageProps = {
    statement: CashflowStatement;
    entries: CashflowEntrySummary[];
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
    };
    branches: CashflowBranchOption[];
    organizations: OrganizationOption[];
    categories: CashflowCategoryOption[];
    filters: {
        year: number;
        branch_id: string | null;
        organization_id: string | null;
        tab?: string;
    };
};
