import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import CustomerPinSettings from '@/components/mobile/organisms/CustomerPinSettings.vue';
import MobileAccount from '@/components/mobile/organisms/MobileAccount.vue';
import { update as updateCustomerPin } from '@/routes/api/v1/mobile/account/pin';

type PinField = 'current_pin' | 'pin' | 'pin_confirmation';
type MockPinForm = Record<PinField, string> & {
    errors: Partial<Record<PinField, string>>;
    hasErrors: boolean;
    processing: boolean;
};

const httpMocks = vi.hoisted(() => ({
    form: null as MockPinForm | null,
    put: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h, reactive } = await import('vue');

    return {
        Link: defineComponent({
            inheritAttrs: false,
            setup(_, { attrs, slots }) {
                return () => h('button', attrs, slots.default?.());
            },
        }),
        useHttp: (initial: Record<PinField, string>) => {
            const form = reactive({
                ...initial,
                errors: {} as Partial<Record<PinField, string>>,
                hasErrors: false,
                processing: false,
                clearErrors(...fields: PinField[]) {
                    if (fields.length === 0) {
                        this.errors = {};
                    } else {
                        for (const field of fields) {
                            delete this.errors[field];
                        }
                    }

                    this.hasErrors = Object.keys(this.errors).length > 0;

                    return this;
                },
                setError(field: PinField, message: string) {
                    this.errors[field] = message;
                    this.hasErrors = true;

                    return this;
                },
                reset() {
                    Object.assign(this, initial);

                    return this;
                },
                put: httpMocks.put,
            });

            httpMocks.form = form;

            return form;
        },
    };
});

const mountPinSettings = () =>
    mount(CustomerPinSettings, {
        props: {
            mustChangePin: true,
            pinExpiresAt: '2026-09-15T08:00:00.000Z',
        },
    });

const setPinFields = async (
    wrapper: ReturnType<typeof mountPinSettings>,
    currentPin: string,
    pin: string,
    confirmation: string,
): Promise<void> => {
    await wrapper.get<HTMLInputElement>('input[name="current_pin"]').setValue(currentPin);
    await wrapper.get<HTMLInputElement>('input[name="pin"]').setValue(pin);
    await wrapper.get<HTMLInputElement>('input[name="pin_confirmation"]').setValue(confirmation);
};

beforeEach(() => {
    httpMocks.form = null;
    httpMocks.put.mockReset();
});

describe('customer PIN settings', () => {
    it('explains a mandatory temporary PIN replacement and submits through Wayfinder', async () => {
        httpMocks.put.mockResolvedValue({
            message: 'PIN berhasil diperbarui.',
            data: { must_change_pin: false, pin_expires_at: null },
        });
        const wrapper = mountPinSettings();

        expect(wrapper.get('[data-test="mandatory-pin-notice"]').text()).toContain(
            'PIN sementara wajib diganti',
        );
        expect(wrapper.get('[data-test="mandatory-pin-notice"]').text()).toContain(
            'PIN ini berlaku sampai',
        );

        await setPinFields(wrapper, '123456', '654321', '654321');
        await wrapper.get('[data-test="customer-pin-form"]').trigger('submit');
        await flushPromises();

        expect(httpMocks.put).toHaveBeenCalledWith(updateCustomerPin().url, expect.any(Object));
        expect(wrapper.get('[data-test="pin-success"]').text()).toBe('PIN berhasil diperbarui.');
        expect(wrapper.find('[data-test="mandatory-pin-notice"]').exists()).toBe(false);
        expect(httpMocks.form?.current_pin).toBe('');
        expect(httpMocks.form?.pin).toBe('');
        expect(httpMocks.form?.pin_confirmation).toBe('');
    });

    it('blocks malformed and mismatched PIN values before sending a request', async () => {
        const wrapper = mountPinSettings();

        await setPinFields(wrapper, '12345', '654321', '654320');
        await wrapper.get('[data-test="customer-pin-form"]').trigger('submit');

        expect(httpMocks.put).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('PIN saat ini harus terdiri dari 6 angka.');
        expect(wrapper.text()).toContain('Konfirmasi PIN baru tidak cocok.');
    });

    it('surfaces validation errors returned by the PIN endpoint', async () => {
        httpMocks.put.mockImplementation(async () => {
            if (httpMocks.form) {
                httpMocks.form.errors.current_pin = 'PIN saat ini tidak valid.';
                httpMocks.form.hasErrors = true;
            }

            return undefined;
        });
        const wrapper = mountPinSettings();

        await setPinFields(wrapper, '123456', '654321', '654321');
        await wrapper.get('[data-test="customer-pin-form"]').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('PIN saat ini tidak valid.');
        expect(wrapper.find('[data-test="pin-success"]').exists()).toBe(false);
    });
});

describe('branch-admin account', () => {
    it('renders identity, organization, thermal printer, and logout content together', () => {
        const wrapper = mount(MobileAccount, {
            props: {
                name: 'Admin Cabang',
                email: 'admin@example.test',
                phone: '628111222333',
                role: 'branch_admin',
                organization: { id: 1, name: 'LaundryHub Demo', slug: 'laundryhub-demo' },
                printerAvailable: true,
            },
            global: {
                stubs: {
                    MobilePrinterSettings: defineComponent({
                        template: '<section data-test="thermal-printer">Printer termal</section>',
                    }),
                },
            },
        });

        expect(wrapper.get('#mobile-account-title').text()).toBe('Admin Cabang');
        expect(wrapper.text()).toContain('admin@example.test');
        expect(wrapper.text()).toContain('628111222333');
        expect(wrapper.text()).toContain('LaundryHub Demo');
        expect(wrapper.get('[data-test="thermal-printer"]').text()).toBe('Printer termal');
        expect(wrapper.text()).toContain('Keluar');
    });

    it('keeps notification controls hidden in the browser', () => {
        const wrapper = mount(MobileAccount, {
            props: {
                name: 'Admin Cabang',
                role: 'branch_admin',
                notificationAvailable: false,
            },
        });

        expect(wrapper.find('[data-test="notification-settings"]').exists()).toBe(false);
    });

    it.each([
        ['prompt', 'Aktifkan pemberitahuan', true],
        ['denied', 'Izin notifikasi ditolak', true],
        ['enabled', 'Pembaruan pesanan akan muncul', false],
    ] as const)(
        'renders the %s notification state',
        (notificationStatus, expectedText, hasEnableButton) => {
            const wrapper = mount(MobileAccount, {
                props: {
                    name: 'Admin Cabang',
                    role: 'branch_admin',
                    notificationAvailable: true,
                    notificationStatus,
                },
            });

            expect(wrapper.get('[data-test="notification-settings"]').text()).toContain(
                expectedText,
            );
            expect(wrapper.find('[data-test="enable-notifications"]').exists()).toBe(
                hasEnableButton,
            );
        },
    );

    it('shows notification errors and emits the enable action', async () => {
        const wrapper = mount(MobileAccount, {
            props: {
                name: 'Admin Cabang',
                role: 'branch_admin',
                notificationAvailable: true,
                notificationStatus: 'error',
                notificationError: 'Firebase belum siap.',
            },
        });

        expect(wrapper.get('[data-test="notification-settings"]').text()).toContain(
            'Firebase belum siap.',
        );
        await wrapper.get('[data-test="enable-notifications"]').trigger('click');
        expect(wrapper.emitted('enableNotifications')).toHaveLength(1);
    });
});
