import { useHttp } from '@inertiajs/vue3';
import { firstRequestError, MobileHttpError, messageFromResponseData } from '@/lib/mobileHttp';
import { context as contextRoute } from '@/routes/api/v1/mobile';
import { nearest as customerNearestRoute } from '@/routes/api/v1/mobile/branches';
import { catalog as catalogRoute } from '@/routes/api/v1/organizations/branches';
import { quote as quoteRoute } from '@/routes/api/v1/organizations/branches/catalog';
import { index as customerIndexRoute } from '@/routes/api/v1/organizations/customers';
import type {
    ApiEnvelope,
    MobileContext,
    MobileCustomer,
    MobilePriceQuote,
    MobileService,
    NearestMobileBranch,
} from '@/types/mobile';

type EmptyRequest = Record<string, never>;
type NearestRequest = { latitude: number; longitude: number };
type PriceQuoteRequest = { weight_grams: number };

export const useMobileCatalogApi = () => {
    const contextRequest = useHttp<EmptyRequest, ApiEnvelope<MobileContext>>({});
    const catalogRequest = useHttp<EmptyRequest, ApiEnvelope<MobileService[]>>({});
    const customersRequest = useHttp<EmptyRequest, ApiEnvelope<MobileCustomer[]>>({});
    const nearestRequest = useHttp<NearestRequest, ApiEnvelope<NearestMobileBranch[]>>({
        latitude: 0,
        longitude: 0,
    });
    const quoteRequest = useHttp<PriceQuoteRequest, ApiEnvelope<MobilePriceQuote>>({
        weight_grams: 1,
    });

    const loadContext = async (organizationSlug?: string): Promise<MobileContext> => {
        contextRequest.cancel();
        const response = await contextRequest.get(
            contextRoute(
                organizationSlug ? { query: { organization: organizationSlug } } : undefined,
            ).url,
        );

        if (!response) {
            throw new Error('Konteks aplikasi tidak dapat dimuat.');
        }

        return response.data;
    };

    const loadCatalog = async (
        organizationSlug: string,
        branchId: number,
    ): Promise<MobileService[]> => {
        catalogRequest.cancel();
        const response = await catalogRequest.get(
            catalogRoute({ organization: organizationSlug, branch: branchId }).url,
        );

        if (!response) {
            throw new Error('Katalog cabang tidak dapat dimuat.');
        }

        return response.data;
    };

    const loadCustomers = async (organizationSlug: string): Promise<MobileCustomer[]> => {
        customersRequest.cancel();
        const response = await customersRequest.get(customerIndexRoute(organizationSlug).url);

        if (!response) {
            throw new Error('Direktori pelanggan tidak dapat dimuat.');
        }

        return response.data;
    };

    const findNearest = async (
        latitude: number,
        longitude: number,
    ): Promise<NearestMobileBranch[]> => {
        nearestRequest.cancel();
        nearestRequest.latitude = latitude;
        nearestRequest.longitude = longitude;
        const response = await nearestRequest.get(customerNearestRoute().url);

        if (!response) {
            throw new Error('Cabang terdekat tidak dapat dihitung.');
        }

        return response.data;
    };

    const quoteOrder = async (
        organizationSlug: string,
        branchId: number,
        priceOptionId: number,
        weightGrams: number,
    ): Promise<MobilePriceQuote> => {
        quoteRequest.cancel();
        quoteRequest.weight_grams = weightGrams;
        const httpException = { value: null as MobileHttpError | null };
        let response: ApiEnvelope<MobilePriceQuote> | undefined;

        try {
            response = await quoteRequest.post(
                quoteRoute({
                    organization: organizationSlug,
                    branch: branchId,
                    priceOption: priceOptionId,
                }).url,
                {
                    onHttpException: (exceptionResponse) => {
                        httpException.value = new MobileHttpError(
                            messageFromResponseData(
                                exceptionResponse.data,
                                'Rincian harga tidak dapat dihitung.',
                            ),
                            exceptionResponse.status,
                        );
                    },
                },
            );
        } catch (error: unknown) {
            if (httpException.value) {
                throw httpException.value;
            }

            throw error;
        }

        if (!response) {
            throw new Error(
                firstRequestError(
                    quoteRequest.errors,
                    'Rincian harga tidak dapat dihitung. Periksa berat laundry.',
                ),
            );
        }

        return response.data;
    };

    return { findNearest, loadCatalog, loadContext, loadCustomers, quoteOrder };
};
