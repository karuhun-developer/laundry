export const formatRupiah = (value: number): string =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);

export const formatKilometers = (meters: number): string => {
    const kilometers = meters / 1000;

    return `${new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: kilometers % 1 === 0 ? 0 : 1,
    }).format(kilometers)} km`;
};

export const roleLabel = (role: string): string =>
    ({
        superadmin: 'Superadmin',
        owner: 'Owner',
        branch_admin: 'Admin cabang',
        customer: 'Pelanggan',
    })[role] ?? role;
