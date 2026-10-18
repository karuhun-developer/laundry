import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, nextTick } from 'vue';
import BranchFormDialog from '@/components/cms/BranchFormDialog.vue';
import CityFormDialog from '@/components/cms/CityFormDialog.vue';
import CmsDeleteDialog from '@/components/cms/CmsDeleteDialog.vue';
import LocationPicker from '@/components/cms/LocationPicker.vue';
import MessageTemplateFormDialog from '@/components/cms/MessageTemplateFormDialog.vue';
import OrganizationFormDialog from '@/components/cms/OrganizationFormDialog.vue';
import UserFormDialog from '@/components/cms/UserFormDialog.vue';
import InputError from '@/components/InputError.vue';
import type {
    BranchSummary,
    MessageTemplateSummary,
    OrganizationSummary,
    UserSummary,
} from '@/types';

type SubmitOptions = {
    onSuccess?: () => void;
};

type MockForm = Record<string, unknown> & {
    errors: Record<string, string>;
    processing: boolean;
    clearErrors: () => void;
    optimistic: () => MockForm;
    reset: () => void;
    setError: (field: string, message: string) => void;
    submit: (route: unknown, options?: SubmitOptions) => void;
};

type MapClickEvent = {
    latlng: {
        lat: number;
        lng: number;
    };
};

const inertiaHarness = vi.hoisted(() => {
    let submittedData: Record<string, unknown> | undefined;

    return {
        record(data: Record<string, unknown>): void {
            submittedData = data;
        },
        reset(): void {
            submittedData = undefined;
        },
        submittedData(): Record<string, unknown> | undefined {
            return submittedData;
        },
    };
});

const leafletHarness = vi.hoisted(() => {
    let mapClickHandler: ((event: MapClickEvent) => void) | undefined;
    let markerDragHandler: (() => void) | undefined;
    let markerPosition = { lat: -6.2, lng: 106.8 };
    let markerOpacity: number | undefined;

    return {
        registerMapClick(handler: (event: MapClickEvent) => void): void {
            mapClickHandler = handler;
        },
        registerMarkerDrag(handler: () => void): void {
            markerDragHandler = handler;
        },
        reset(): void {
            mapClickHandler = undefined;
            markerDragHandler = undefined;
            markerPosition = { lat: -6.2, lng: 106.8 };
            markerOpacity = undefined;
        },
        setMarkerPosition(latitude: number, longitude: number): void {
            markerPosition = { lat: latitude, lng: longitude };
        },
        setMarkerOpacity(opacity: number): void {
            markerOpacity = opacity;
        },
        markerOpacity(): number | undefined {
            return markerOpacity;
        },
        markerPosition(): { lat: number; lng: number } {
            return markerPosition;
        },
        triggerMapClick(latitude: number, longitude: number): void {
            mapClickHandler?.({ latlng: { lat: latitude, lng: longitude } });
        },
        triggerMarkerDrag(): void {
            markerDragHandler?.();
        },
    };
});

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        useForm: (values: Record<string, unknown>): MockForm => {
            const defaults = structuredClone(values);
            const fieldNames = Object.keys(values);
            const form: MockForm = {
                ...structuredClone(values),
                errors: {},
                processing: false,
                clearErrors: () => {
                    form.errors = {};
                },
                optimistic: () => form,
                reset: () => {
                    Object.assign(form, structuredClone(defaults));
                },
                setError: (field, message) => {
                    form.errors[field] = message;
                },
                submit: (_route, options) => {
                    inertiaHarness.record(
                        Object.fromEntries(fieldNames.map((field) => [field, form[field]])),
                    );
                    options?.onSuccess?.();
                },
            };

            return reactive(form);
        },
    };
});

vi.mock('leaflet', () => {
    const map = {
        fitBounds: vi.fn(),
        invalidateSize: vi.fn(),
        on: vi.fn((event: string, handler: (event: MapClickEvent) => void) => {
            if (event === 'click') {
                leafletHarness.registerMapClick(handler);
            }
        }),
        remove: vi.fn(),
        setView: vi.fn(),
    };
    const layerGroup = {
        addTo: vi.fn(),
        clearLayers: vi.fn(),
    };
    const marker = {
        addTo: vi.fn(),
        bindTooltip: vi.fn(),
        getLatLng: vi.fn(() => leafletHarness.markerPosition()),
        on: vi.fn((event: string, handler: () => void) => {
            if (event === 'dragend') {
                leafletHarness.registerMarkerDrag(handler);
            }
        }),
        setLatLng: vi.fn((coordinates: [number, number] | MapClickEvent['latlng']) => {
            const latitude = Array.isArray(coordinates) ? coordinates[0] : coordinates.lat;
            const longitude = Array.isArray(coordinates) ? coordinates[1] : coordinates.lng;

            leafletHarness.setMarkerPosition(latitude, longitude);
        }),
        setOpacity: vi.fn((opacity: number) => {
            leafletHarness.setMarkerOpacity(opacity);
        }),
    };
    const circle = {
        addTo: vi.fn(),
        on: vi.fn(),
    };

    circle.addTo.mockReturnValue(circle);
    layerGroup.addTo.mockReturnValue(layerGroup);
    marker.addTo.mockReturnValue(marker);

    return {
        circle: vi.fn(() => circle),
        divIcon: vi.fn(() => ({})),
        latLngBounds: vi.fn(() => ({})),
        layerGroup: vi.fn(() => layerGroup),
        map: vi.fn(() => map),
        marker: vi.fn((_coordinates: [number, number], options: { opacity: number }) => {
            leafletHarness.setMarkerOpacity(options.opacity);

            return marker;
        }),
        tileLayer: vi.fn(() => ({ addTo: vi.fn() })),
    };
});

const passThrough = defineComponent({
    template: '<div><slot /></div>',
});
const dialogStubs = {
    Dialog: passThrough,
    DialogClose: passThrough,
    DialogContent: passThrough,
    DialogDescription: passThrough,
    DialogFooter: passThrough,
    DialogHeader: passThrough,
    DialogTitle: passThrough,
    DialogTrigger: passThrough,
};

afterEach(() => {
    document.body.innerHTML = '';
    inertiaHarness.reset();
    leafletHarness.reset();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('CMS dialogs', () => {
    it('requires exact typed confirmation before enabling deletion', async () => {
        const wrapper = mount(CmsDeleteDialog, {
            props: {
                title: 'Hapus organisasi?',
                description: 'Organisasi akan dihapus permanen.',
                confirmation: 'Kirei Wash',
                confirmationLabel: 'Nama organisasi',
                route: { method: 'delete', url: '/cms/organizations/1' },
            },
            global: { stubs: dialogStubs },
        });

        const input = wrapper.get('[data-test="delete-confirmation"]');
        const trigger = wrapper.get('[data-test="open-delete-dialog"]');
        const submit = wrapper.get<HTMLButtonElement>('[data-test="confirm-delete"]');

        expect(wrapper.text()).toContain('Kirei Wash');
        expect(trigger.classes()).toEqual(expect.arrayContaining(['min-h-11', 'min-w-11']));
        expect(submit.element.disabled).toBe(true);

        await input.setValue('kirei wash');
        expect(submit.element.disabled).toBe(true);

        await input.setValue('Kirei Wash');
        expect(submit.element.disabled).toBe(false);
    });

    it('prefills organization values without exposing or submitting its slug', async () => {
        const organization: OrganizationSummary = {
            id: 1,
            name: 'Kirei Wash Jakarta',
            slug: 'kirei-wash-jakarta',
            is_active: true,
            branches_count: 12,
            users_count: 30,
        };
        const wrapper = mount(OrganizationFormDialog, {
            props: { organization },
            global: { stubs: dialogStubs },
        });

        expect(wrapper.get<HTMLInputElement>('#organization-name').element.value).toBe(
            organization.name,
        );
        expect(wrapper.find('#organization-slug').exists()).toBe(false);

        await wrapper.get('[data-test="organization-form"]').trigger('submit');

        expect(inertiaHarness.submittedData()).toEqual({
            name: organization.name,
            is_active: organization.is_active,
        });
    });

    it('prefills user edit values and keeps password optional', () => {
        const user: UserSummary = {
            id: 10,
            organization_id: 1,
            name: 'Admin Kemang',
            email: 'admin.kemang@example.test',
            whatsapp_phone: '6281234567890',
            roles: [{ id: 2, name: 'branch_admin' }],
            branches: [{ id: 7, name: 'Kemang' }],
            organization: { id: 1, name: 'Kirei Wash Jakarta' },
        };
        const wrapper = mount(UserFormDialog, {
            props: {
                user,
                branches: [{ id: 7, organization_id: 1, name: 'Kemang' }],
                organizations: [{ id: 1, name: 'Kirei Wash Jakarta' }],
                currentOrganizationId: null,
                isSuperadmin: true,
            },
            global: { stubs: dialogStubs },
        });

        expect(wrapper.get<HTMLInputElement>('#user-name').element.value).toBe(user.name);
        expect(wrapper.get<HTMLInputElement>('#user-email').element.value).toBe(user.email);
        expect(wrapper.get<HTMLInputElement>('#user-whatsapp-phone').element.value).toBe(
            user.whatsapp_phone,
        );
        expect(wrapper.get('#user-password').attributes('required')).toBeUndefined();
        expect(wrapper.text()).toContain('Password baru (opsional)');
    });

    it('submits owner accounts with empty branch_ids and explains organization-wide access', async () => {
        const wrapper = mount(UserFormDialog, {
            props: {
                branches: [{ id: 7, organization_id: 1, name: 'Kemang' }],
                organizations: [{ id: 1, name: 'Kirei Wash Jakarta' }],
                currentOrganizationId: 1,
                isSuperadmin: true,
            },
            global: { stubs: dialogStubs },
        });

        await wrapper.get<HTMLSelectElement>('#user-role').setValue('owner');

        expect(wrapper.text()).toContain('Owner dapat mengakses seluruh cabang dalam organisasi.');
        expect(wrapper.find('fieldset').exists()).toBe(false);

        await wrapper.get<HTMLInputElement>('#user-name').setValue('Budi Owner');
        await wrapper.get<HTMLInputElement>('#user-email').setValue('owner.budi@example.test');
        await wrapper.get<HTMLInputElement>('#user-password').setValue('password123');
        await wrapper.get<HTMLInputElement>('#user-password-confirmation').setValue('password123');

        await wrapper.get('[data-test="user-form"]').trigger('submit');

        expect(inertiaHarness.submittedData()).toMatchObject({
            role: 'owner',
            branch_ids: [],
        });
    });

    it('prefills a message template and renders its variable preview', () => {
        const messageTemplate: MessageTemplateSummary = {
            id: 20,
            row_key: 'branch:7:ready_for_pickup',
            organization_id: 1,
            branch_id: 7,
            scope_key: 'branch:7',
            order_status: 'ready_for_pickup',
            body: 'Halo {customer_name}, pesanan {order_number} sudah {status}. Berat {weight}, total {total}.',
            is_active: true,
            organization: { id: 1, name: 'Kirei Wash Jakarta' },
            branch: { id: 7, name: 'Kemang' },
            is_inherited: false,
        };
        const wrapper = mount(MessageTemplateFormDialog, {
            props: {
                messageTemplate,
                branches: [{ id: 7, organization_id: 1, name: 'Kemang' }],
                organizations: [{ id: 1, name: 'Kirei Wash Jakarta' }],
                orderStatuses: [{ value: 'ready_for_pickup', label: 'Siap diambil' }],
                supportedVariables: [
                    '{customer_name}',
                    '{order_number}',
                    '{status}',
                    '{weight}',
                    '{total}',
                ],
                currentOrganizationId: 1,
                isSuperadmin: false,
            },
            global: { stubs: dialogStubs },
        });

        expect(wrapper.get<HTMLSelectElement>('#message-template-branch').element.value).toBe('7');
        expect(wrapper.get<HTMLSelectElement>('#message-template-branch').element.disabled).toBe(
            true,
        );
        expect(wrapper.find('option[value=""]').exists()).toBe(false);
        expect(wrapper.get<HTMLSelectElement>('#message-template-status').element.value).toBe(
            messageTemplate.order_status,
        );
        expect(wrapper.get<HTMLTextAreaElement>('#message-template-body').element.value).toBe(
            messageTemplate.body,
        );
        expect(wrapper.text()).toContain(
            'Halo Budi Santoso, pesanan LDR-20260913-00000001 sudah Siap diambil. Berat 3,5 kg, total Rp48.000.',
        );
    });

    it('inserts a supported variable at the current message cursor', async () => {
        const wrapper = mount(MessageTemplateFormDialog, {
            attachTo: document.body,
            props: {
                branches: [],
                organizations: [{ id: 1, name: 'Kirei Wash Jakarta' }],
                orderStatuses: [{ value: 'received', label: 'Diterima' }],
                supportedVariables: ['{customer_name}'],
                currentOrganizationId: 1,
                isSuperadmin: false,
            },
            global: { stubs: dialogStubs },
        });
        const body = wrapper.get<HTMLTextAreaElement>('#message-template-body');

        await body.setValue('Halo !');
        body.element.setSelectionRange(5, 5);
        await wrapper.get('button[type="button"][class*="font-mono"]').trigger('click');

        expect(body.element.value).toBe('Halo {customer_name}!');
        expect(document.activeElement).toBe(body.element);
        expect(body.element.selectionStart).toBe(20);
    });

    it('announces validation errors as a polite live region', () => {
        const wrapper = mount(InputError, {
            props: { message: 'Nama wajib diisi.' },
        });

        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.attributes('aria-live')).toBe('polite');
        expect(wrapper.attributes('aria-atomic')).toBe('true');
    });

    it('shows every global city and keeps the selection when organization changes', async () => {
        const wrapper = mount(BranchFormDialog, {
            props: {
                cities: [
                    {
                        id: 1,
                        name: 'Jakarta Selatan',
                        province: 'DKI Jakarta',
                    },
                    {
                        id: 2,
                        name: 'Bandung',
                        province: 'Jawa Barat',
                    },
                    {
                        id: 3,
                        name: 'Bogor',
                        province: 'Jawa Barat',
                    },
                ],
                organizations: [
                    { id: 1, name: 'LaundryHub Demo' },
                    { id: 2, name: 'Laundry Bandung' },
                ],
                currentOrganizationId: null,
                isSuperadmin: true,
            },
            global: { stubs: dialogStubs },
        });

        expect(wrapper.text()).toContain('Semua kota dapat dipakai oleh setiap organisasi.');
        expect(wrapper.get('#branch-city').text()).toContain('Jakarta Selatan');
        expect(wrapper.get('#branch-city').text()).toContain('Bandung');
        expect(wrapper.get('#branch-city').text()).toContain('Bogor');

        await wrapper.get('#branch-city').setValue('2');

        await wrapper.get('#branch-organization').setValue('2');

        expect(wrapper.get<HTMLSelectElement>('#branch-city').element.value).toBe('2');
        expect(wrapper.get('#branch-city').text()).toContain('Bogor');
        expect(wrapper.get('#branch-city').text()).toContain('Bandung');
        expect(wrapper.get('#branch-city').text()).toContain('Jakarta Selatan');
    });

    it('prefills and submits branch QRIS settings', async () => {
        const qrisPayload =
            '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';
        const branch: BranchSummary = {
            id: 7,
            organization_id: 1,
            city_id: 1,
            name: 'Cabang Kemang',
            code: 'KMG01',
            phone: '081234567890',
            address: 'Jl. Kemang Raya No. 10',
            latitude: -6.2607,
            longitude: 106.8136,
            free_delivery_radius_meters: 10000,
            delivery_fee_per_km_rupiah: 3000,
            qris_enabled: true,
            qris_static_payload: qrisPayload,
            is_active: true,
            city: { id: 1, name: 'Jakarta Selatan', province: 'DKI Jakarta' },
            organization: { id: 1, name: 'LaundryHub Demo' },
        };
        const wrapper = mount(BranchFormDialog, {
            props: {
                branch,
                cities: [branch.city],
                organizations: [],
                currentOrganizationId: 1,
                isSuperadmin: false,
            },
            global: { stubs: dialogStubs },
        });

        expect(wrapper.text()).toContain('QRIS dinamis');
        expect(wrapper.get<HTMLTextAreaElement>('#branch-qris-payload').element.value).toBe(
            qrisPayload,
        );
        expect(wrapper.get('#branch-qris-payload').attributes('required')).toBeDefined();

        await wrapper.get('[data-test="branch-form"]').trigger('submit');

        expect(inertiaHarness.submittedData()).toMatchObject({
            qris_enabled: true,
            qris_static_payload: qrisPayload,
        });
    });

    it('imports a QRIS payload from an uploaded image', async () => {
        const qrisPayload =
            '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';

        vi.stubGlobal('createImageBitmap', vi.fn().mockResolvedValue({ close: vi.fn() }));
        vi.stubGlobal(
            'BarcodeDetector',
            class {
                detect = vi.fn().mockResolvedValue([{ rawValue: qrisPayload }]);
            },
        );

        const wrapper = mount(BranchFormDialog, {
            props: {
                cities: [{ id: 1, name: 'Jakarta Selatan', province: 'DKI Jakarta' }],
                organizations: [],
                currentOrganizationId: 1,
                isSuperadmin: false,
            },
            global: { stubs: dialogStubs },
        });
        const imageInput = wrapper.get<HTMLInputElement>('#branch-qris-image');

        Object.defineProperty(imageInput.element, 'files', {
            configurable: true,
            value: [new File(['qris'], 'merchant-qris.png', { type: 'image/png' })],
        });
        await imageInput.trigger('change');
        await flushPromises();

        expect(wrapper.get<HTMLTextAreaElement>('#branch-qris-payload').element.value).toBe(
            qrisPayload,
        );
        expect(wrapper.text()).toContain('Payload QRIS berhasil dibaca dari gambar.');
        expect(wrapper.get('#branch-qris-payload').attributes('required')).toBeDefined();
    });

    it('does not submit organization ownership from the global city form', () => {
        const wrapper = mount(CityFormDialog, {
            global: { stubs: dialogStubs },
        });

        expect(wrapper.find('#city-organization').exists()).toBe(false);
        expect(wrapper.text()).toContain('Master kota ini dapat dipakai oleh semua organisasi.');
    });

    it('keeps blank coordinates unselected when the map opens', async () => {
        mount(LocationPicker, {
            props: { latitude: '', longitude: '' },
        });

        await flushPromises();

        expect(leafletHarness.markerOpacity()).toBe(0);
    });

    it('updates coordinates when the map is clicked', async () => {
        const wrapper = mount(LocationPicker, {
            props: { latitude: '', longitude: '' },
        });

        await flushPromises();
        leafletHarness.triggerMapClick(-6.9175, 107.6191);

        expect(wrapper.emitted('update:latitude')?.at(-1)).toEqual(['-6.917500']);
        expect(wrapper.emitted('update:longitude')?.at(-1)).toEqual(['107.619100']);
        expect(leafletHarness.markerOpacity()).toBe(1);
    });

    it('allows keyboard users to enter coordinates directly', async () => {
        const wrapper = mount(LocationPicker, {
            props: { latitude: '', longitude: '' },
        });

        await flushPromises();
        await wrapper.get('#branch-latitude').setValue('-6.917500');
        await wrapper.get('#branch-longitude').setValue('107.619100');

        expect(wrapper.emitted('update:latitude')?.at(-1)).toEqual(['-6.9175']);
        expect(wrapper.emitted('update:longitude')?.at(-1)).toEqual(['107.6191']);
        expect(wrapper.get('[data-test="use-current-location"]').classes()).toContain('min-h-11');
    });

    it('updates coordinates when the map marker is dragged', async () => {
        const wrapper = mount(LocationPicker, {
            props: { latitude: '-6.200000', longitude: '106.800000' },
        });

        await flushPromises();
        leafletHarness.setMarkerPosition(-6.9147, 107.6098);
        leafletHarness.triggerMarkerDrag();

        expect(wrapper.emitted('update:latitude')?.at(-1)).toEqual(['-6.914700']);
        expect(wrapper.emitted('update:longitude')?.at(-1)).toEqual(['107.609800']);
    });

    it('uses the device location to update map coordinates', async () => {
        const getCurrentPosition: Geolocation['getCurrentPosition'] = (success) => {
            success({
                coords: {
                    accuracy: 5,
                    altitude: null,
                    altitudeAccuracy: null,
                    heading: null,
                    latitude: -6.2607,
                    longitude: 106.8136,
                    speed: null,
                    toJSON: () => ({}),
                },
                timestamp: Date.now(),
                toJSON: () => ({}),
            });
        };
        Object.defineProperty(navigator, 'geolocation', {
            configurable: true,
            value: { getCurrentPosition },
        });
        const wrapper = mount(LocationPicker, {
            props: { latitude: '', longitude: '' },
        });

        await flushPromises();
        await nextTick();
        await wrapper.get('button').trigger('click');

        expect(wrapper.emitted('update:latitude')?.at(-1)).toEqual(['-6.260700']);
        expect(wrapper.emitted('update:longitude')?.at(-1)).toEqual(['106.813600']);
    });
});
