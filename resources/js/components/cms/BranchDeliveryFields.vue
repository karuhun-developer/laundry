<script setup lang="ts">
import LocationPicker from '@/components/cms/LocationPicker.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    latitudeError?: string;
    longitudeError?: string;
    radiusError?: string;
    feeError?: string;
}>();

const latitude = defineModel<string>('latitude', { required: true });
const longitude = defineModel<string>('longitude', { required: true });
const radius = defineModel<string>('radius', { required: true });
const fee = defineModel<string>('fee', { required: true });
</script>

<template>
    <div class="space-y-2">
        <LocationPicker v-model:latitude="latitude" v-model:longitude="longitude" />
        <InputError :message="latitudeError" />
        <InputError :message="longitudeError" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <Label for="branch-radius">Gratis antar-jemput (meter)</Label>
            <Input
                id="branch-radius"
                v-model="radius"
                inputmode="numeric"
                required
                :aria-invalid="Boolean(radiusError)"
                aria-describedby="branch-radius-error"
            />
            <InputError id="branch-radius-error" :message="radiusError" />
        </div>
        <div class="space-y-2">
            <Label for="branch-fee">Tarif lebih / km</Label>
            <Input
                id="branch-fee"
                v-model="fee"
                inputmode="numeric"
                required
                :aria-invalid="Boolean(feeError)"
                aria-describedby="branch-fee-error"
            />
            <InputError id="branch-fee-error" :message="feeError" />
        </div>
    </div>
</template>
