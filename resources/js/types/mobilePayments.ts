export type MobilePaymentMethod = 'cash' | 'qris';

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
    qris_image_data_uri: string | null;
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
