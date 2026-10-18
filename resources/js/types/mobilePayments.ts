export type MobilePaymentMethod = 'cash' | 'qris' | 'gateway';

export type MobilePaymentGatewayDriver = 'paywuz';

export type MobilePaymentGatewayMethod = {
    driver: MobilePaymentGatewayDriver;
    driver_label: string;
    code: string;
    name: string;
    type: string;
    fee_rupiah: number;
    minimum_rupiah: number;
    maximum_rupiah: number;
    is_default: boolean;
};

export type MobilePaymentGateway = {
    driver: MobilePaymentGatewayDriver;
    driver_label: string;
    payment_id: string;
    order_id: string;
    method: string;
    status: string;
    payment_number: string | null;
    payment_url: string | null;
    expires_at: string | null;
};

export type MobilePaymentStatus = 'pending' | 'paid' | 'failed' | 'cancelled' | 'refunded';

export type MobilePaymentStatusEvent = {
    id: number;
    status: MobilePaymentStatus;
    actor: {
        id: number;
        name: string;
    };
    note: string | null;
    occurred_at: string;
    pending?: boolean;
};

export type MobilePayment = {
    id: number;
    payment_number: string;
    method: MobilePaymentMethod;
    method_label: string;
    status: MobilePaymentStatus;
    status_label: string;
    amount_rupiah: number;
    qris_payload: string | null;
    qris_token?: string | null;
    qris_unique_code?: number | null;
    qris_expires_at?: string | null;
    qris_image_data_uri: string | null;
    gateway: MobilePaymentGateway | null;
    note: string | null;
    settled_at: string | null;
    status_history: readonly MobilePaymentStatusEvent[];
    created_at: string;
    pending?: boolean;
};

export type MobilePaymentSummary = {
    paid_rupiah: number;
    outstanding_rupiah: number;
    is_paid: boolean;
};
