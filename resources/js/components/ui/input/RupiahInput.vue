<script lang="ts">
export const formatRupiahNumber = (val: number | string | null | undefined): string => {
    if (val === null || val === undefined || val === '') {
        return '';
    }
    const cleanStr =
        typeof val === 'number' ? Math.round(val).toString() : String(val).replace(/\D/g, '');
    if (!cleanStr) {
        return '';
    }
    const num = Number.parseInt(cleanStr, 10);
    if (Number.isNaN(num)) {
        return '';
    }
    return new Intl.NumberFormat('id-ID').format(num);
};
</script>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

export interface RupiahInputProps {
    modelValue?: number | string | null;
    defaultValue?: number | string | null;
    placeholder?: string;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
    id?: string;
    name?: string;
    required?: boolean;
    min?: number;
    max?: number;
    step?: number | string;
    autocomplete?: string;
    ariaInvalid?: boolean | 'true' | 'false' | 'grammar' | 'spelling';
    ariaDescribedby?: string;
    dataTest?: string;
}

const props = withDefaults(defineProps<RupiahInputProps>(), {
    modelValue: null,
    defaultValue: null,
    placeholder: undefined,
    disabled: false,
    class: undefined,
    id: undefined,
    name: undefined,
    required: false,
    min: undefined,
    max: undefined,
    step: undefined,
    autocomplete: undefined,
    ariaInvalid: undefined,
    ariaDescribedby: undefined,
    dataTest: undefined,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'change', value: number | null): void;
    (e: 'blur', event: FocusEvent): void;
    (e: 'focus', event: FocusEvent): void;
}>();

const inputRef = ref<HTMLInputElement | null>(null);

const initialVal = props.modelValue ?? props.defaultValue;
const displayValue = ref<string>(formatRupiahNumber(initialVal));

watch(
    () => props.modelValue,
    (newVal) => {
        const expectedFormatted = formatRupiahNumber(newVal);
        if (displayValue.value !== expectedFormatted) {
            displayValue.value = expectedFormatted;
            if (inputRef.value && inputRef.value.value !== expectedFormatted) {
                inputRef.value.value = expectedFormatted;
            }
        }
    },
);

const onInput = (event: Event): void => {
    const el = event.target as HTMLInputElement;
    const rawVal = el.value;
    const selectionStart = el.selectionStart ?? rawVal.length;

    // Count how many digits exist before cursor in raw input
    const digitsBeforeCursor = rawVal.slice(0, selectionStart).replace(/\D/g, '').length;

    // Extract digits only
    const cleanDigits = rawVal.replace(/\D/g, '');

    if (!cleanDigits) {
        displayValue.value = '';
        el.value = '';
        emit('update:modelValue', null);
        emit('change', null);
        return;
    }

    const numericValue = Number.parseInt(cleanDigits, 10);
    const formatted = formatRupiahNumber(numericValue);

    displayValue.value = formatted;
    el.value = formatted;

    emit('update:modelValue', numericValue);
    emit('change', numericValue);

    // Reposition cursor based on digit count
    nextTick(() => {
        if (!el) {
            return;
        }
        let countedDigits = 0;
        let targetCursor = 0;

        if (digitsBeforeCursor === 0) {
            targetCursor = 0;
        } else {
            for (let i = 0; i < formatted.length; i++) {
                if (/\d/.test(formatted[i])) {
                    countedDigits++;
                }
                if (countedDigits === digitsBeforeCursor) {
                    targetCursor = i + 1;
                    break;
                }
            }
            if (countedDigits < digitsBeforeCursor) {
                targetCursor = formatted.length;
            }
        }

        try {
            el.setSelectionRange(targetCursor, targetCursor);
        } catch {
            // Ignore if input type doesn't support selection range
        }
    });
};

const onBlur = (event: FocusEvent): void => {
    // Ensure display string matches current model value on blur
    const formatted = formatRupiahNumber(props.modelValue);
    displayValue.value = formatted;
    if (inputRef.value) {
        inputRef.value.value = formatted;
    }
    emit('blur', event);
};

const onFocus = (event: FocusEvent): void => {
    emit('focus', event);
};

defineExpose({
    input: inputRef,
    focus: () => inputRef.value?.focus(),
    blur: () => inputRef.value?.blur(),
});
</script>

<template>
    <input
        ref="inputRef"
        data-slot="input"
        type="text"
        inputmode="numeric"
        :value="displayValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :id="id"
        :name="name"
        :required="required"
        :autocomplete="autocomplete"
        :aria-invalid="ariaInvalid"
        :aria-describedby="ariaDescribedby"
        :data-test="dataTest"
        :class="cn(
            'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input h-11 w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
            'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
            'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
            props.class,
        )"
        @input="onInput"
        @blur="onBlur"
        @focus="onFocus"
    >
</template>
