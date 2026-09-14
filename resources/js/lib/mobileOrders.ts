import type {
    MobileBranch,
    MobileOrder,
    MobileOrderStatus,
    MobileOrderStatusEvent,
    MobilePriceOption,
    MobilePriceQuote,
    MobileService,
} from '@/types/mobile';

export const orderStatusSequence: MobileOrderStatus[] = [
    'received',
    'weighed',
    'washing',
    'drying',
    'ironing',
    'ready_for_pickup',
    'completed',
];

const pickupOrderStatusSequence: MobileOrderStatus[] = [
    'pickup_requested',
    'weighed',
    'washing',
    'drying',
    'ironing',
    'ready_for_pickup',
    'completed',
];

export const orderStatusLabels: Record<MobileOrderStatus, string> = {
    pickup_requested: 'Menunggu dijemput',
    received: 'Diterima',
    weighed: 'Ditimbang',
    washing: 'Dicuci',
    drying: 'Dikeringkan',
    ironing: 'Disetrika',
    ready_for_pickup: 'Siap diambil',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

export const formatRupiah = (value: number): string =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);

export const formatWeight = (grams: number): string =>
    `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(grams / 1000)} kg`;

export const formatDistance = (meters: number): string =>
    meters < 1000
        ? `${meters} m`
        : `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(meters / 1000)} km`;

export const formatOrderDate = (value: string): string =>
    new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

export const nextOrderStatus = (status: MobileOrderStatus): MobileOrderStatus | null => {
    if (status === 'pickup_requested') {
        return null;
    }

    const currentIndex = orderStatusSequence.indexOf(status);

    if (currentIndex < 0 || currentIndex === orderStatusSequence.length - 1) {
        return null;
    }

    return orderStatusSequence[currentIndex + 1] ?? null;
};

export type MobileTimelineStep = {
    status: MobileOrderStatus;
    state: 'completed' | 'current' | 'future';
    event: MobileOrderStatusEvent | null;
};

export const buildOrderTimeline = (order: MobileOrder): MobileTimelineStep[] => {
    if (order.status === 'cancelled') {
        return order.status_history.map((event, index, events) => ({
            status: event.status,
            state: index === events.length - 1 ? 'current' : 'completed',
            event,
        }));
    }

    const sequence = order.status_history.some((event) => event.status === 'pickup_requested')
        ? pickupOrderStatusSequence
        : orderStatusSequence;
    const currentIndex = sequence.indexOf(order.status);

    return sequence.map((status, index) => ({
        status,
        state: index < currentIndex ? 'completed' : index === currentIndex ? 'current' : 'future',
        event: order.status_history.find((event) => event.status === status) ?? null,
    }));
};

export const estimatePrice = (option: MobilePriceOption, weightGrams: number): number => {
    if (option.type === 'per_kilogram') {
        const rate = option.pricing.price_per_kilogram_rupiah ?? 0;
        const chargeable = Math.max(weightGrams, option.pricing.minimum_chargeable_grams ?? 0);

        return Math.floor((chargeable * rate + 500) / 1000);
    }

    const packageWeight = option.pricing.package_weight_grams ?? 0;
    const basePrice = option.pricing.base_price_rupiah ?? 0;
    const excessRate = option.pricing.excess_price_per_kilogram_rupiah ?? 0;
    const excessWeight = Math.max(0, weightGrams - packageWeight);

    return basePrice + Math.floor((excessWeight * excessRate + 500) / 1000);
};

export const findCatalogOption = (
    services: readonly MobileService[],
    priceOptionId: number,
): { service: MobileService; option: MobilePriceOption } | null => {
    for (const service of services) {
        const option = service.price_options.find(
            (priceOption) => priceOption.id === priceOptionId,
        );

        if (option) {
            return { service, option };
        }
    }

    return null;
};

type DraftOrderInput = {
    branch: MobileBranch;
    customer: { id: number; name: string };
    option: MobilePriceOption;
    quote: MobilePriceQuote | null;
    service: MobileService;
    deliveryFeeRupiah: number;
};

export const buildDraftOrder = (input: DraftOrderInput): MobileOrder => {
    const option = input.quote?.price_option ?? input.option;
    const subtotal = input.quote?.total_rupiah ?? 0;
    const pricingConfirmed = input.quote !== null;
    const status: MobileOrderStatus = pricingConfirmed ? 'received' : 'pickup_requested';
    const timestamp = new Date().toISOString();

    return {
        id: -Date.now(),
        order_number: 'Menunggu nomor',
        status,
        organization_id: input.branch.organization.id,
        organization: input.branch.organization,
        branch: {
            id: input.branch.id,
            name: input.branch.name,
            code: input.branch.code,
            qris_enabled: input.branch.payments.qris_enabled,
        },
        customer: input.customer,
        items: [
            {
                id: -Date.now(),
                service: {
                    id: input.service.id,
                    name: input.service.name,
                    code: input.service.code,
                },
                price_option: {
                    id: option.id,
                    name: option.name,
                    type: option.type,
                    pricing_snapshot: {
                        package_weight_grams: option.pricing.package_weight_grams ?? null,
                        base_price_rupiah: option.pricing.base_price_rupiah ?? null,
                        price_per_kilogram_rupiah:
                            option.pricing.price_per_kilogram_rupiah ??
                            option.pricing.excess_price_per_kilogram_rupiah ??
                            null,
                        minimum_chargeable_grams: option.pricing.minimum_chargeable_grams ?? null,
                    },
                },
                calculation: {
                    requested_weight_grams: input.quote?.requested_weight_grams ?? 0,
                    chargeable_weight_grams: input.quote?.chargeable_weight_grams ?? 0,
                    base_price_rupiah: input.quote?.base_price_rupiah ?? 0,
                    excess_weight_grams: input.quote?.excess_weight_grams ?? 0,
                    excess_price_rupiah: input.quote?.excess_price_rupiah ?? 0,
                    subtotal_rupiah: subtotal,
                },
            },
        ],
        pricing: {
            confirmed: pricingConfirmed,
            confirmed_at: pricingConfirmed ? timestamp : null,
            items_subtotal_rupiah: subtotal,
            delivery_fee_rupiah: input.deliveryFeeRupiah,
            total_rupiah: pricingConfirmed ? subtotal + input.deliveryFeeRupiah : 0,
        },
        delivery: null,
        status_history: [
            {
                id: -Date.now(),
                status,
                actor: { id: 0, name: 'Menunggu konfirmasi server' },
                note: null,
                occurred_at: timestamp,
                pending: true,
            },
        ],
        payments: [],
        payment_summary: {
            paid_rupiah: 0,
            outstanding_rupiah: pricingConfirmed ? subtotal + input.deliveryFeeRupiah : 0,
            is_paid: false,
        },
        created_at: timestamp,
        pending: true,
    };
};
