<?php

use App\Enums\OrderStatus;
use App\Models\MessageTemplate;
use App\Models\Organization;
use Database\Seeders\MessageTemplateSeeder;

it('seeds one organization-wide template for every status in every tenant', function (): void {
    $organizations = Organization::factory()->count(2)->create();

    $this->seed(MessageTemplateSeeder::class);
    $this->seed(MessageTemplateSeeder::class);

    expect(MessageTemplate::query()->count())
        ->toBe($organizations->count() * count(OrderStatus::cases()));

    foreach ($organizations as $organization) {
        expect(
            MessageTemplate::query()
                ->where('organization_id', $organization->id)
                ->whereNull('branch_id')
                ->where('scope_key', 'organization')
                ->pluck('order_status')
                ->map(fn (OrderStatus $status): string => $status->value)
                ->sort()
                ->values()
                ->all(),
        )->toBe(collect(OrderStatus::cases())
            ->map(fn (OrderStatus $status): string => $status->value)
            ->sort()
            ->values()
            ->all());
    }

    $weighedTemplate = MessageTemplate::query()
        ->where('organization_id', $organizations->firstOrFail()->id)
        ->where('order_status', OrderStatus::Weighed)
        ->firstOrFail();

    expect($weighedTemplate->body)->toContain('{weight}')->toContain('{total}');
});
