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
    organization_id: number;
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

export type UserSummary = {
    id: number;
    organization_id: number;
    name: string;
    email: string;
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
