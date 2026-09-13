import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, nextTick } from 'vue';
import BranchFormDialog from '@/components/cms/BranchFormDialog.vue';
import CmsDeleteDialog from '@/components/cms/CmsDeleteDialog.vue';
import LocationPicker from '@/components/cms/LocationPicker.vue';
import OrganizationFormDialog from '@/components/cms/OrganizationFormDialog.vue';
import UserFormDialog from '@/components/cms/UserFormDialog.vue';
import InputError from '@/components/InputError.vue';
import type { OrganizationSummary, UserSummary } from '@/types';

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
                    options?.onSuccess?.();
                },
            };

            return reactive(form);
        },
    };
});

vi.mock('leaflet', () => {
    const map = {
        invalidateSize: vi.fn(),
        on: vi.fn((event: string, handler: (event: MapClickEvent) => void) => {
            if (event === 'click') {
                leafletHarness.registerMapClick(handler);
            }
        }),
        remove: vi.fn(),
        setView: vi.fn(),
    };
    const marker = {
        addTo: vi.fn(),
        getLatLng: vi.fn(() => leafletHarness.markerPosition()),
        on: vi.fn((event: string, handler: () => void) => {
            if (event === 'dragend') {
                leafletHarness.registerMarkerDrag(handler);
            }
        }),
        setLatLng: vi.fn(([latitude, longitude]: [number, number]) => {
            leafletHarness.setMarkerPosition(latitude, longitude);
        }),
        setOpacity: vi.fn((opacity: number) => {
            leafletHarness.setMarkerOpacity(opacity);
        }),
    };

    marker.addTo.mockReturnValue(marker);

    return {
        divIcon: vi.fn(() => ({})),
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
    leafletHarness.reset();
    vi.restoreAllMocks();
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
        const submit = wrapper.get<HTMLButtonElement>('[data-test="confirm-delete"]');

        expect(wrapper.text()).toContain('Kirei Wash');
        expect(submit.element.disabled).toBe(true);

        await input.setValue('kirei wash');
        expect(submit.element.disabled).toBe(true);

        await input.setValue('Kirei Wash');
        expect(submit.element.disabled).toBe(false);
    });

    it('prefills organization values when editing', () => {
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
        expect(wrapper.get<HTMLInputElement>('#organization-slug').element.value).toBe(
            organization.slug,
        );
    });

    it('prefills user edit values and keeps password optional', () => {
        const user: UserSummary = {
            id: 10,
            organization_id: 1,
            name: 'Admin Kemang',
            email: 'admin.kemang@example.test',
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
        expect(wrapper.get('#user-password').attributes('required')).toBeUndefined();
        expect(wrapper.text()).toContain('Password baru (opsional)');
    });

    it('announces validation errors as a polite live region', () => {
        const wrapper = mount(InputError, {
            props: { message: 'Nama wajib diisi.' },
        });

        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.attributes('aria-live')).toBe('polite');
        expect(wrapper.attributes('aria-atomic')).toBe('true');
    });

    it('explains and updates branch cities when the selected organization changes', async () => {
        const wrapper = mount(BranchFormDialog, {
            props: {
                cities: [
                    {
                        id: 1,
                        organization_id: 1,
                        name: 'Jakarta Selatan',
                        province: 'DKI Jakarta',
                        organization: { id: 1, name: 'LaundryHub Demo' },
                    },
                    {
                        id: 2,
                        organization_id: 1,
                        name: 'Bandung',
                        province: 'Jawa Barat',
                        organization: { id: 1, name: 'LaundryHub Demo' },
                    },
                    {
                        id: 3,
                        organization_id: 2,
                        name: 'Bogor',
                        province: 'Jawa Barat',
                        organization: { id: 2, name: 'Laundry Bandung' },
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

        expect(wrapper.text()).toContain('Kota mengikuti organisasi yang dipilih.');
        expect(wrapper.get('#branch-city').text()).toContain('Jakarta Selatan');
        expect(wrapper.get('#branch-city').text()).toContain('Bandung');
        expect(wrapper.get('#branch-city').text()).not.toContain('Bogor');

        await wrapper.get('#branch-organization').setValue('2');

        expect(wrapper.get('#branch-city').text()).toContain('Bogor');
        expect(wrapper.get('#branch-city').text()).not.toContain('Bandung');
        expect(wrapper.get('#branch-city').text()).not.toContain('Jakarta Selatan');
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
