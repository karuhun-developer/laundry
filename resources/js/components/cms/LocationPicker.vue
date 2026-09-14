<script setup lang="ts">
import LocationPicker from '@/components/LocationPicker.vue';

const latitude = defineModel<string>('latitude', { required: true });
const longitude = defineModel<string>('longitude', { required: true });
</script>

<template>
    <LocationPicker
        v-model:latitude="latitude"
        v-model:longitude="longitude"
        title="Titik lokasi outlet"
        map-label="Peta pemilih lokasi outlet"
        input-id-prefix="branch"
    />
</template>
