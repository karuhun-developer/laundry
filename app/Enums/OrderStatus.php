<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Received = 'received';
    case Weighed = 'weighed';
    case Washing = 'washing';
    case Drying = 'drying';
    case Ironing = 'ironing';
    case ReadyForPickup = 'ready_for_pickup';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $nextStatus): bool
    {
        if ($this->isTerminal()) {
            return false;
        }

        if ($nextStatus === self::Cancelled) {
            return true;
        }

        return $this->nextStatus() === $nextStatus;
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Diterima',
            self::Weighed => 'Ditimbang',
            self::Washing => 'Dicuci',
            self::Drying => 'Dikeringkan',
            self::Ironing => 'Disetrika',
            self::ReadyForPickup => 'Siap diambil',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    private function nextStatus(): ?self
    {
        return match ($this) {
            self::Received => self::Weighed,
            self::Weighed => self::Washing,
            self::Washing => self::Drying,
            self::Drying => self::Ironing,
            self::Ironing => self::ReadyForPickup,
            self::ReadyForPickup => self::Completed,
            self::Completed, self::Cancelled => null,
        };
    }
}
