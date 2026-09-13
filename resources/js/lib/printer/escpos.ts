import type { ReceiptDocument } from '@/lib/printer/types';

export type EscPosReceiptOptions = {
    readonly feedLines?: number;
    readonly cut?: boolean;
};

const ALIGNMENT_COMMAND = {
    left: 0x00,
    center: 0x01,
    right: 0x02,
} as const;

const SIZE_COMMAND = {
    normal: 0x00,
    large: 0x11,
} as const;

export const encodeEscPosReceipt = (
    receipt: ReceiptDocument,
    options: EscPosReceiptOptions = {},
): Uint8Array => {
    const bytes: number[] = [];
    const append = (...values: readonly number[]): void => {
        bytes.push(...values);
    };
    const appendText = (value: string): void => {
        for (const character of value) {
            const codePoint = character.codePointAt(0) ?? 0x3f;
            append(codePoint <= 0xff ? codePoint : 0x3f);
        }
    };

    append(0x1b, 0x40);

    let alignment = -1;
    let bold = -1;
    let size = -1;

    for (const line of receipt.lines) {
        const nextAlignment = ALIGNMENT_COMMAND[line.align ?? 'left'];
        const nextBold = line.bold === true ? 0x01 : 0x00;
        const nextSize = SIZE_COMMAND[line.size ?? 'normal'];

        if (nextAlignment !== alignment) {
            append(0x1b, 0x61, nextAlignment);
            alignment = nextAlignment;
        }

        if (nextBold !== bold) {
            append(0x1b, 0x45, nextBold);
            bold = nextBold;
        }

        if (nextSize !== size) {
            append(0x1d, 0x21, nextSize);
            size = nextSize;
        }

        appendText(line.text);
        append(0x0a);
    }

    append(0x1b, 0x61, 0x00);
    append(0x1b, 0x45, 0x00);
    append(0x1d, 0x21, 0x00);

    const feedLines = options.feedLines ?? 3;
    for (let index = 0; index < feedLines; index += 1) {
        append(0x0a);
    }

    if (options.cut ?? true) {
        append(0x1d, 0x56, 0x01);
    }

    return Uint8Array.from(bytes);
};
