import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h, type PropType } from 'vue';
import Login from '@/pages/auth/Login.vue';
import Register from '@/pages/auth/Register.vue';
import { login as loginPage, register as registerPage } from '@/routes';
import { store as customerLoginStore } from '@/routes/customer-login';
import { store as staffLoginStore } from '@/routes/login';
import { request as passwordRequest } from '@/routes/password';
import { store as registerStore } from '@/routes/register';

type RouteHref = {
    url: string;
};

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        Form: defineComponent({
            inheritAttrs: false,
            props: {
                action: { type: String, required: true },
                method: { type: String, required: true },
            },
            setup(props, { attrs, slots }) {
                return () =>
                    h(
                        'form',
                        { ...attrs, action: props.action, method: props.method },
                        slots.default?.({ errors: {}, processing: false }),
                    );
            },
        }),
        Head: defineComponent({
            setup() {
                return () => null;
            },
        }),
    };
});

const inputStub = defineComponent({
    inheritAttrs: false,
    props: {
        modelValue: { type: String, default: '' },
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        return () =>
            h('input', {
                ...attrs,
                value: props.modelValue,
                onInput: (event: Event) => {
                    emit('update:modelValue', (event.target as HTMLInputElement).value);
                },
            });
    },
});

const textLinkStub = defineComponent({
    inheritAttrs: false,
    props: {
        href: {
            type: [String, Object] as PropType<string | RouteHref>,
            required: true,
        },
    },
    setup(props, { attrs, slots }) {
        return () =>
            h(
                'a',
                {
                    ...attrs,
                    href: typeof props.href === 'string' ? props.href : props.href.url,
                },
                slots.default?.(),
            );
    },
});

const passthroughStubs = {
    Button: defineComponent({
        inheritAttrs: false,
        setup(_, { attrs, slots }) {
            return () => h('button', attrs, slots.default?.());
        },
    }),
    Checkbox: defineComponent({
        inheritAttrs: false,
        setup(_, { attrs }) {
            return () => h('input', { ...attrs, type: 'checkbox' });
        },
    }),
    Input: inputStub,
    InputError: true,
    Label: defineComponent({
        inheritAttrs: false,
        setup(_, { attrs, slots }) {
            return () => h('label', attrs, slots.default?.());
        },
    }),
    PasskeyVerify: defineComponent({
        props: { separator: { type: String, default: '' } },
        template: '<div data-test="passkey-verify">{{ separator }}</div>',
    }),
    PasswordInput: inputStub,
    Spinner: true,
    TextLink: textLinkStub,
};

const loginProps = {
    canResetPassword: true,
    demoAccounts: [
        {
            label: 'Pelanggan demo',
            email: null,
            role: 'customer',
            whatsapp_phone: '081234567890',
            pin: '654321',
        },
        {
            label: 'Owner demo',
            email: 'owner@laundryhub.test',
            role: 'owner',
        },
    ],
    demoPassword: 'password',
    demoCustomerPin: '123456',
};

const mountLogin = () =>
    mount(Login, {
        props: loginProps,
        global: { stubs: passthroughStubs },
    });

const clickDemoAccount = async (wrapper: ReturnType<typeof mountLogin>): Promise<void> => {
    const button = wrapper
        .findAll('button')
        .find((candidate) => candidate.text().toLowerCase().includes('demo'));

    expect(button).toBeDefined();
    await button?.trigger('click');
};

describe('login page', () => {
    it('defaults to the customer Wayfinder form and customer registration', () => {
        const wrapper = mountLogin();
        const form = wrapper.get('[data-test="customer-login-form"]');

        expect(form.attributes('action')).toBe(customerLoginStore.form().action);
        expect(form.attributes('method')).toBe(customerLoginStore.form().method);
        expect(form.find('input[name="whatsapp_phone"]').exists()).toBe(true);
        expect(form.find('input[name="pin"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="staff-login-panel"]').exists()).toBe(false);
        expect(wrapper.get(`a[href="${registerPage().url}"]`).text()).toBe('Daftar');
    });

    it('switches to staff-only passkey and email authentication', async () => {
        const wrapper = mountLogin();

        await wrapper.get('[data-test="auth-mode-switch"]').trigger('click');

        const panel = wrapper.get('[data-test="staff-login-panel"]');
        const form = panel.get('form');

        expect(form.attributes('action')).toBe(staffLoginStore.form().action);
        expect(form.attributes('method')).toBe(staffLoginStore.form().method);
        expect(panel.get('[data-test="passkey-verify"]').text()).toContain('email');
        expect(form.find('input[name="email"]').exists()).toBe(true);
        expect(form.find('input[name="password"]').exists()).toBe(true);
        expect(wrapper.get(`a[href="${passwordRequest().url}"]`).text()).toContain('Lupa');
        expect(wrapper.find(`a[href="${registerPage().url}"]`).exists()).toBe(false);
        expect(wrapper.get('[data-test="auth-mode-switch"]').text()).toContain('pelanggan');
    });

    it('fills credentials from the visible demo account for each mode', async () => {
        const wrapper = mountLogin();

        await clickDemoAccount(wrapper);

        expect(wrapper.get<HTMLInputElement>('input[name="whatsapp_phone"]').element.value).toBe(
            '081234567890',
        );
        expect(wrapper.get<HTMLInputElement>('input[name="pin"]').element.value).toBe('654321');

        await wrapper.get('[data-test="auth-mode-switch"]').trigger('click');
        await clickDemoAccount(wrapper);

        expect(wrapper.get<HTMLInputElement>('input[name="email"]').element.value).toBe(
            'owner@laundryhub.test',
        );
        expect(wrapper.get<HTMLInputElement>('input[name="password"]').element.value).toBe(
            'password',
        );
    });
});

describe('registration page', () => {
    it('submits only customer identity fields through Wayfinder', () => {
        const wrapper = mount(Register, {
            global: { stubs: passthroughStubs },
        });
        const form = wrapper.get('form');
        const fieldNames = form.findAll('input[name]').map((input) => input.attributes('name'));

        expect(form.attributes('action')).toBe(registerStore.form().action);
        expect(form.attributes('method')).toBe(registerStore.form().method);
        expect(fieldNames).toEqual(['name', 'whatsapp_phone', 'pin', 'pin_confirmation']);
        expect(wrapper.get(`a[href="${loginPage().url}"]`).text()).toBe('Masuk');
    });
});
