export type PrinterProfileErrorCode = 'invalid_profile';
export type PrinterTransportErrorCode =
    | 'transport_unavailable'
    | 'bluetooth_disabled'
    | 'permission_denied'
    | 'bluetooth_unavailable'
    | 'discovery_failed'
    | 'print_failed';

const PROFILE_ERROR_MESSAGES: Record<PrinterProfileErrorCode, string> = {
    invalid_profile: 'Profil printer yang tersimpan tidak valid.',
};

const TRANSPORT_ERROR_MESSAGES: Record<PrinterTransportErrorCode, string> = {
    transport_unavailable: 'Printer thermal hanya tersedia di aplikasi Android.',
    bluetooth_disabled: 'Bluetooth belum aktif. Aktifkan Bluetooth lalu cari printer lagi.',
    permission_denied:
        'Izin Bluetooth atau USB ditolak. Izinkan akses printer di pengaturan Android.',
    bluetooth_unavailable: 'Perangkat ini tidak memiliki Bluetooth.',
    discovery_failed: 'Daftar printer tidak dapat dimuat.',
    print_failed: 'Data struk gagal dikirim ke printer.',
};

export class PrinterProfileError extends Error {
    readonly name = 'PrinterProfileError';

    constructor(readonly code: PrinterProfileErrorCode) {
        super(PROFILE_ERROR_MESSAGES[code]);
    }
}

export class PrinterTransportError extends Error {
    readonly name = 'PrinterTransportError';

    constructor(
        readonly code: PrinterTransportErrorCode,
        readonly cause?: Error,
    ) {
        super(TRANSPORT_ERROR_MESSAGES[code]);
    }
}
