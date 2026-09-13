import type {
    LaundryReceiptData,
    PaperWidth,
    ReceiptDocument,
    ReceiptLine,
} from '@/lib/printer/types';

const PAPER_COLUMNS: Record<PaperWidth, number> = {
    '58mm': 32,
    '80mm': 48,
};

const rupiahFormatter = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
});

const weightFormatter = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 2,
});

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: 'Asia/Jakarta',
});

const formatRupiah = (value: number): string => `Rp${rupiahFormatter.format(value)}`;

const fitRow = (left: string, right: string, width: number): string => {
    const availableLeftWidth = Math.max(1, width - right.length - 1);
    const fittedLeft = left.slice(0, availableLeftWidth);
    const gap = Math.max(1, width - fittedLeft.length - right.length);

    return `${fittedLeft}${' '.repeat(gap)}${right}`;
};

const wrapText = (value: string, width: number): readonly string[] => {
    const words = value.trim().split(/\s+/u);
    const lines: string[] = [];
    let currentLine = '';

    for (const word of words) {
        const chunks = word.match(new RegExp(`.{1,${width}}`, 'gu')) ?? [''];

        for (const chunk of chunks) {
            const candidate = currentLine.length === 0 ? chunk : `${currentLine} ${chunk}`;

            if (candidate.length <= width) {
                currentLine = candidate;
                continue;
            }

            lines.push(currentLine);
            currentLine = chunk;
        }
    }

    if (currentLine.length > 0) {
        lines.push(currentLine);
    }

    return lines.length > 0 ? lines : [''];
};

export const buildLaundryReceipt = (
    data: LaundryReceiptData,
    paperWidth: PaperWidth,
): ReceiptDocument => {
    const columns = PAPER_COLUMNS[paperWidth];
    const rowColumns = columns - 1;
    const divider = '-'.repeat(columns);
    const lines: ReceiptLine[] = [
        { text: data.branch.name.slice(0, columns), align: 'center', bold: true, size: 'large' },
        { text: `${data.branch.code} | ${data.branch.phone}`.slice(0, columns), align: 'center' },
    ];

    for (const branchAddressLine of wrapText(
        `${data.branch.address}, ${data.branch.city}`,
        columns,
    )) {
        lines.push({ text: branchAddressLine, align: 'center' });
    }

    lines.push(
        { text: divider },
        { text: `Struk : ${data.receiptNumber}`.slice(0, columns) },
        { text: `Order : ${data.orderNumber}`.slice(0, columns) },
        { text: `Status: ${data.orderStatus.toUpperCase()}`.slice(0, columns) },
        { text: `Waktu : ${dateFormatter.format(new Date(data.issuedAt))}`.slice(0, columns) },
        { text: `Pelanggan: ${data.customerName}`.slice(0, columns) },
        { text: divider },
    );

    for (const item of data.items) {
        for (const itemNameLine of wrapText(item.name, columns)) {
            lines.push({ text: itemNameLine });
        }

        lines.push({
            text: fitRow(
                `  ${weightFormatter.format(item.weightGrams / 1000)} kg`,
                formatRupiah(item.subtotalRupiah),
                rowColumns,
            ),
        });
    }

    lines.push(
        { text: divider },
        { text: fitRow('Subtotal', formatRupiah(data.pricing.itemsSubtotalRupiah), rowColumns) },
    );

    if (data.pricing.deliveryFeeRupiah > 0) {
        lines.push({
            text: fitRow('Antar-jemput', formatRupiah(data.pricing.deliveryFeeRupiah), rowColumns),
        });
    }

    lines.push(
        { text: fitRow('TOTAL', formatRupiah(data.pricing.totalRupiah), rowColumns), bold: true },
        { text: fitRow('Dibayar', formatRupiah(data.pricing.paidRupiah), rowColumns) },
    );

    if (data.pricing.outstandingRupiah > 0) {
        lines.push({
            text: fitRow('Sisa', formatRupiah(data.pricing.outstandingRupiah), rowColumns),
            bold: true,
        });
    }

    for (const payment of data.payments) {
        lines.push({
            text: fitRow(payment.methodLabel, formatRupiah(payment.amountRupiah), rowColumns),
        });
    }

    lines.push({ text: divider }, { text: 'Terima kasih', align: 'center' });

    return {
        title: `Struk ${data.receiptNumber}`,
        lines,
    };
};
