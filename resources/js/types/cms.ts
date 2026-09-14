export type OrganizationOption = {
    id: number;
    name: string;
};

export type OrganizationSummary = OrganizationOption & {
    slug: string;
    is_active: boolean;
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
    email: string;
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
    branches: Pick<BranchSummary, 'id' | 'organization_id' | 'name'>[];
    organizations: OrganizationOption[];
};

export type OrderStatusOption = {
    value: string;
    label: string;
};

export type MessageTemplateSummary = {
    id: number;
    organization_id: number;
    branch_id: number | null;
    scope_key: string;
    order_status: string;
    body: string;
    is_active: boolean;
    organization: OrganizationOption;
    branch: Pick<BranchSummary, 'id' | 'name'> | null;
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
    branch_id: number;
    type: PriceTypeValue;
    name: string;
    package_weight_grams: number | null;
    base_price_rupiah: number | null;
    price_per_kilogram_rupiah: number | null;
    minimum_chargeable_grams: number | null;
    is_active: boolean;
    branch: Pick<CatalogBranchSummary, 'id' | 'organization_id' | 'name' | 'code'>;
    pending?: boolean;
};

export type ServiceSummary = {
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
