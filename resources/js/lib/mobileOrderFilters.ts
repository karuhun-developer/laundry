import type { MobileOrder } from '@/types/mobile';

export type CustomerOrderFilters = {
    search: string;
    date_from: string;
    date_to: string;
};

export const emptyCustomerOrderFilters = (): CustomerOrderFilters => ({
    search: '',
    date_from: '',
    date_to: '',
});

export const normalizeCustomerOrderFilters = (
    filters: CustomerOrderFilters,
): CustomerOrderFilters => ({
    search: filters.search.trim(),
    date_from: filters.date_from,
    date_to: filters.date_to,
});

export const hasCustomerOrderFilters = (filters: CustomerOrderFilters): boolean =>
    Boolean(filters.search.trim() || filters.date_from || filters.date_to);

const orderCalendarDate = (order: MobileOrder): string =>
    /^\d{4}-\d{2}-\d{2}/.exec(order.created_at)?.[0] ?? '';

export const filterCustomerOrders = (
    orders: readonly MobileOrder[],
    filters: CustomerOrderFilters,
): MobileOrder[] => {
    const normalized = normalizeCustomerOrderFilters(filters);
    const searchTerms = normalized.search.toLocaleLowerCase('id-ID').split(/\s+/).filter(Boolean);

    return orders.filter((order) => {
        const searchableText = [
            order.order_number,
            order.branch.name,
            order.branch.code,
            ...order.items.flatMap((item) => [item.service.name, item.service.code]),
        ]
            .join(' ')
            .toLocaleLowerCase('id-ID');
        const date = orderCalendarDate(order);

        return (
            searchTerms.every((term) => searchableText.includes(term)) &&
            (!normalized.date_from || date >= normalized.date_from) &&
            (!normalized.date_to || date <= normalized.date_to)
        );
    });
};
