import { readonly, ref } from 'vue';
import { encodeEscPosReceipt } from '@/lib/printer/escpos';
import type { PrinterProfileStorage } from '@/lib/printer/profile';
import { clearPrinterProfile, loadPrinterProfile, savePrinterProfile } from '@/lib/printer/profile';
import { buildLaundryReceipt } from '@/lib/printer/receipt';
import { printerTransport } from '@/lib/printer/transport';
import type {
    LaundryReceiptData,
    PrinterConnection,
    PrinterDevice,
    PrinterProfile,
    PrinterTransport,
    ReceiptDocument,
} from '@/lib/printer/types';

type MobilePrinterOptions = {
    transport?: PrinterTransport;
    storage?: PrinterProfileStorage | null;
};

const resolveStorage = (): PrinterProfileStorage | null =>
    typeof window === 'undefined' ? null : window.localStorage;

const errorMessage = (error: unknown, fallback: string): string =>
    error instanceof Error ? error.message : fallback;

const testReceipt = (): ReceiptDocument => ({
    title: 'Tes printer LaundryHub',
    lines: [
        { text: 'LaundryHub', align: 'center', bold: true, size: 'large' },
        { text: 'Tes printer berhasil', align: 'center' },
        { text: new Date().toLocaleString('id-ID'), align: 'center' },
    ],
});

export const useMobilePrinter = (options: MobilePrinterOptions = {}) => {
    const transport = options.transport ?? printerTransport;
    const storage = options.storage === undefined ? resolveStorage() : options.storage;
    const profile = ref<PrinterProfile | null>(null);
    const devices = ref<readonly PrinterDevice[]>([]);
    const discovering = ref(false);
    const printing = ref(false);
    const message = ref<string | null>(null);
    const error = ref<string | null>(null);

    if (storage) {
        try {
            profile.value = loadPrinterProfile(storage);
        } catch (loadError: unknown) {
            error.value = errorMessage(loadError, 'Profil printer tidak dapat dimuat.');
        }
    }

    const discover = async (connection: PrinterConnection): Promise<boolean> => {
        message.value = null;
        error.value = null;

        if (!transport.available) {
            devices.value = [];
            error.value = 'Printer thermal hanya tersedia di aplikasi Android.';
            return false;
        }

        discovering.value = true;

        try {
            devices.value = await transport.list(connection);
            message.value =
                devices.value.length > 0
                    ? `${devices.value.length} printer ditemukan.`
                    : 'Tidak ada printer ditemukan.';
            return true;
        } catch (discoveryError: unknown) {
            devices.value = [];
            error.value = errorMessage(discoveryError, 'Daftar printer tidak dapat dimuat.');
            return false;
        } finally {
            discovering.value = false;
        }
    };

    const saveProfileSelection = (nextProfile: PrinterProfile): void => {
        profile.value = nextProfile;
        message.value = `Printer ${nextProfile.name} disimpan.`;
        error.value = null;

        if (storage) {
            savePrinterProfile(nextProfile, storage);
        }
    };

    const clearProfile = (): void => {
        profile.value = null;
        message.value = 'Profil printer dihapus dari perangkat ini.';
        error.value = null;

        if (storage) {
            clearPrinterProfile(storage);
        }
    };

    const printDocument = async (
        document: ReceiptDocument,
        successMessage: string,
    ): Promise<boolean> => {
        const selectedProfile = profile.value;
        message.value = null;
        error.value = null;

        if (!selectedProfile) {
            error.value = 'Pilih dan simpan printer sebelum mencetak.';
            return false;
        }

        printing.value = true;

        try {
            await transport.print(selectedProfile, encodeEscPosReceipt(document));
            message.value = successMessage;
            return true;
        } catch (printError: unknown) {
            error.value = errorMessage(printError, 'Data gagal dikirim ke printer.');
            return false;
        } finally {
            printing.value = false;
        }
    };

    const testPrint = (): Promise<boolean> =>
        printDocument(testReceipt(), 'Tes cetak berhasil dikirim ke printer.');

    const printReceipt = (receipt: LaundryReceiptData): Promise<boolean> => {
        const selectedProfile = profile.value;

        if (!selectedProfile) {
            error.value = 'Pilih dan simpan printer sebelum mencetak.';
            return Promise.resolve(false);
        }

        return printDocument(
            buildLaundryReceipt(receipt, selectedProfile.paperWidth),
            'Struk berhasil dikirim ke printer.',
        );
    };

    return {
        available: transport.available,
        clearProfile,
        devices: readonly(devices),
        discover,
        discovering: readonly(discovering),
        error: readonly(error),
        message: readonly(message),
        printReceipt,
        printing: readonly(printing),
        profile: readonly(profile),
        saveProfile: saveProfileSelection,
        testPrint,
    };
};
