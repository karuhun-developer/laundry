export class MobileHttpError extends Error {
    public constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
        this.name = 'MobileHttpError';
    }
}

export const firstRequestError = (errors: object, fallback: string): string => {
    for (const value of Object.values(errors)) {
        if (typeof value === 'string') {
            return value;
        }

        if (Array.isArray(value) && typeof value[0] === 'string') {
            return value[0];
        }
    }

    return fallback;
};

export const messageFromResponseData = (data: string, fallback: string): string => {
    try {
        const payload: unknown = JSON.parse(data);

        if (
            typeof payload === 'object' &&
            payload !== null &&
            'message' in payload &&
            typeof payload.message === 'string'
        ) {
            return payload.message;
        }
    } catch {
        return fallback;
    }

    return fallback;
};
