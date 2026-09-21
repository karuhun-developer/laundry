import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { RupiahInput } from './index';

describe('RupiahInput', () => {
    it('renders empty when modelValue is null or undefined', () => {
        const wrapper = mount(RupiahInput, {
            props: {
                modelValue: null,
            },
        });

        const input = wrapper.get<HTMLInputElement>('input');
        expect(input.element.value).toBe('');
    });

    it('formats initial numeric modelValue with thousands dot separator', () => {
        const wrapper = mount(RupiahInput, {
            props: {
                modelValue: 15000,
            },
        });

        const input = wrapper.get<HTMLInputElement>('input');
        expect(input.element.value).toBe('15.000');
    });

    it('supports zero as a valid value', () => {
        const wrapper = mount(RupiahInput, {
            props: {
                modelValue: 0,
            },
        });

        const input = wrapper.get<HTMLInputElement>('input');
        expect(input.element.value).toBe('0');
    });

    it('updates display value when modelValue changes from parent', async () => {
        const wrapper = mount(RupiahInput, {
            props: {
                modelValue: 15000,
            },
        });

        const input = wrapper.get<HTMLInputElement>('input');
        expect(input.element.value).toBe('15.000');

        await wrapper.setProps({ modelValue: 20000 });
        expect(input.element.value).toBe('20.000');

        await wrapper.setProps({ modelValue: null });
        expect(input.element.value).toBe('');
    });

    it('formats user typed digits and emits clean integer to update:modelValue', async () => {
        const wrapper = mount(RupiahInput, {
            props: {
                modelValue: null,
            },
        });

        const input = wrapper.get<HTMLInputElement>('input');
        await input.setValue('15000');

        expect(input.element.value).toBe('15.000');
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([15000]);
    });

    it('strips non-digits on paste or input and emits integer', async () => {
        const wrapper = mount(RupiahInput, {
            props: {
                modelValue: null,
            },
        });

        const input = wrapper.get<HTMLInputElement>('input');
        await input.setValue('Rp 25.500');

        expect(input.element.value).toBe('25.500');
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([25500]);
    });

    it('emits null when input is cleared', async () => {
        const wrapper = mount(RupiahInput, {
            props: {
                modelValue: 15000,
            },
        });

        const input = wrapper.get<HTMLInputElement>('input');
        await input.setValue('');

        expect(input.element.value).toBe('');
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([null]);
    });

    it('preserves attributes such as id, name, placeholder, disabled, and data-test', () => {
        const wrapper = mount(RupiahInput, {
            props: {
                id: 'test-rupiah',
                name: 'custom_price',
                placeholder: 'Masukkan harga',
                disabled: true,
                dataTest: 'rupiah-field',
            },
        });

        const input = wrapper.get<HTMLInputElement>('input');
        expect(input.attributes('id')).toBe('test-rupiah');
        expect(input.attributes('name')).toBe('custom_price');
        expect(input.attributes('placeholder')).toBe('Masukkan harga');
        expect(input.attributes('disabled')).toBeDefined();
        expect(input.attributes('data-test')).toBe('rupiah-field');
    });
});
