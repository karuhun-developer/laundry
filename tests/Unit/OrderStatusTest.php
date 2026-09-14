<?php

use App\Enums\OrderStatus;

it('allows only the configured forward step or cancellation from an active status', function (): void {
    $allowedTransitions = [
        OrderStatus::PickupRequested->value => [OrderStatus::Cancelled],
        OrderStatus::Received->value => [OrderStatus::Weighed, OrderStatus::Cancelled],
        OrderStatus::Weighed->value => [OrderStatus::Washing, OrderStatus::Cancelled],
        OrderStatus::Washing->value => [OrderStatus::Drying, OrderStatus::Cancelled],
        OrderStatus::Drying->value => [OrderStatus::Ironing, OrderStatus::Cancelled],
        OrderStatus::Ironing->value => [OrderStatus::ReadyForPickup, OrderStatus::Cancelled],
        OrderStatus::ReadyForPickup->value => [OrderStatus::Completed, OrderStatus::Cancelled],
        OrderStatus::Completed->value => [],
        OrderStatus::Cancelled->value => [],
    ];

    foreach (OrderStatus::cases() as $currentStatus) {
        foreach (OrderStatus::cases() as $requestedStatus) {
            expect($currentStatus->canTransitionTo($requestedStatus))->toBe(
                in_array($requestedStatus, $allowedTransitions[$currentStatus->value], true),
            );
        }
    }
});

it('marks only completed and cancelled statuses as terminal', function (): void {
    foreach (OrderStatus::cases() as $status) {
        expect($status->isTerminal())->toBe(in_array($status, [
            OrderStatus::Completed,
            OrderStatus::Cancelled,
        ], true));
    }
});
