import type { LaundryReceiptData } from '@/lib/printer/types';
import type {
    CreatePaymentPayload,
    MobileOrderReceipt,
    MobilePayment,
    MobilePaymentMethod,
    MobilePaymentStatus,
    MobilePaymentSummary,
} from '@/types/mobile';

export const paymentMethodLabels: Record<MobilePaymentMethod, string> = {
    cash: 'Tunai',
    qris: 'QRIS',
    gateway: 'Payment gateway',
};

export const paymentStatusLabels: Record<MobilePaymentStatus, string> = {
    pending: 'Menunggu pembayaran',
    paid: 'Lunas',
    failed: 'Gagal',
    cancelled: 'Dibatalkan',
    refunded: 'Dikembalikan',
};

const paymentGatewayMethodLogos: Readonly<Record<string, string>> = {
    QRIS: '/images/payment-methods/qris.svg',
    MANDIRI: '/images/payment-methods/mandiri.svg',
    MANDIRIVA: '/images/payment-methods/mandiri.svg',
    BCA: '/images/payment-methods/bca.svg',
    BCAVA: '/images/payment-methods/bca.svg',
    BNI: '/images/payment-methods/bni.svg',
    BNIVA: '/images/payment-methods/bni.svg',
    CIMB: '/images/payment-methods/cimb-niaga.svg',
    CIMBVA: '/images/payment-methods/cimb-niaga.svg',
    CIMBNIAGA: '/images/payment-methods/cimb-niaga.svg',
    CIMBNIAGAVA: '/images/payment-methods/cimb-niaga.svg',
    PERMATA: '/images/payment-methods/permata.svg',
    PERMATAVA: '/images/payment-methods/permata.svg',
    BSI: '/images/payment-methods/bsi.svg',
    BSIVA: '/images/payment-methods/bsi.svg',
};

export const paymentGatewayMethodLogo = (code: string): string | null =>
    paymentGatewayMethodLogos[code.toUpperCase().replace(/[^A-Z0-9]/g, '')] ?? null;

let nextDraftPaymentId = -1;

export const buildDraftPayment = (payload: CreatePaymentPayload): MobilePayment => {
    const status: MobilePaymentStatus = payload.method === 'cash' ? 'paid' : 'pending';
    const timestamp = new Date().toISOString();
    const id = nextDraftPaymentId;
    nextDraftPaymentId -= 1;

    return {
        id,
        payment_number: 'Menunggu nomor pembayaran',
        method: payload.method,
        method_label: paymentMethodLabels[payload.method],
        status,
        status_label: paymentStatusLabels[status],
        amount_rupiah: payload.amount_rupiah,
        qris_payload: null,
        qris_image_data_uri: null,
        gateway:
            payload.method === 'gateway' && payload.gateway_driver && payload.gateway_method
                ? {
                      driver: payload.gateway_driver,
                      driver_label: payload.gateway_driver === 'paywuz' ? 'Paywuz' : 'Gateway',
                      payment_id: '',
                      order_id: '',
                      method: payload.gateway_method,
                      status: 'pending',
                      payment_number: null,
                      payment_url: null,
                      expires_at: null,
                  }
                : null,
        note: payload.note ?? null,
        settled_at: status === 'paid' ? timestamp : null,
        status_history: [
            {
                id,
                status,
                actor: { id: 0, name: 'Menunggu konfirmasi server' },
                note: payload.note ?? null,
                occurred_at: timestamp,
                pending: true,
            },
        ],
        created_at: timestamp,
        pending: true,
    };
};

export const summarizePayments = (
    totalRupiah: number,
    payments: readonly MobilePayment[],
): MobilePaymentSummary => {
    const paidRupiah = payments.reduce(
        (total, payment) => total + (payment.status === 'paid' ? payment.amount_rupiah : 0),
        0,
    );

    return {
        paid_rupiah: paidRupiah,
        outstanding_rupiah: Math.max(0, totalRupiah - paidRupiah),
        is_paid: paidRupiah >= totalRupiah,
    };
};

export const toLaundryReceiptData = (receipt: MobileOrderReceipt): LaundryReceiptData => ({
    receiptNumber: receipt.receipt_number,
    orderNumber: receipt.receipt_number,
    orderStatus: receipt.order_status,
    branch: {
        name: receipt.branch.name,
        code: receipt.branch.code,
        phone: receipt.branch.phone ?? '-',
        address: receipt.branch.address,
        city: receipt.branch.city.name,
    },
    customerName: receipt.customer.name,
    items: receipt.items.map((item) => ({
        name: item.service.name,
        weightGrams: item.calculation.requested_weight_grams,
        subtotalRupiah: item.calculation.subtotal_rupiah,
    })),
    pricing: {
        itemsSubtotalRupiah: receipt.pricing.items_subtotal_rupiah,
        deliveryFeeRupiah: receipt.pricing.delivery_fee_rupiah,
        totalRupiah: receipt.pricing.total_rupiah,
        paidRupiah: receipt.pricing.paid_rupiah,
        outstandingRupiah: receipt.pricing.outstanding_rupiah,
    },
    payments: receipt.payments
        .filter((payment) => payment.status === 'paid')
        .map((payment) => ({
            methodLabel: payment.method_label,
            amountRupiah: payment.amount_rupiah,
        })),
    issuedAt: receipt.issued_at,
});
