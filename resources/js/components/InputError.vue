<script setup lang="ts">
defineProps<{
    id?: string;
    message?: string;
}>();
</script>

<template>
    <p
        v-if="message"
        :id="id"
        class="text-sm text-red-600 dark:text-red-500"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    >
        {{ message }}
    </p>
</template>
