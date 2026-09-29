<?php

declare(strict_types=1);

namespace App\Domain\Model;

enum AppointmentStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case COMPLETED = 'COMPLETED';
    case NO_SHOW = 'NO_SHOW';

    public function isActive(): bool
    {
        return $this === self::PENDING || $this === self::CONFIRMED;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::PENDING || $this === self::CONFIRMED;
    }

    public function canBeRescheduled(): bool
    {
        return $this === self::PENDING || $this === self::CONFIRMED;
    }

    public function isTerminal(): bool
    {
        return $this === self::CANCELLED || $this === self::COMPLETED || $this === self::NO_SHOW;
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmé',
            self::CANCELLED => 'Annulé',
            self::COMPLETED => 'Terminé',
            self::NO_SHOW => 'Non honoré (absence)',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::PENDING => in_array($target, [self::CONFIRMED, self::CANCELLED], true),
            self::CONFIRMED => in_array($target, [self::COMPLETED, self::CANCELLED, self::NO_SHOW], true),
            self::CANCELLED, self::COMPLETED, self::NO_SHOW => false,
        };
    }
}
