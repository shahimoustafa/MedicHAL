<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class SlotUnavailableException extends DomainException
{
    public static function outsideWorkingHours(string $time): self
    {
        return new self(sprintf('Le créneau demandé (%s) se situe en dehors des heures de consultation du praticien.', $time));
    }

    public static function pastDate(string $time): self
    {
        return new self(sprintf('Impossible de réserver un rendez-vous dans le passé (%s).', $time));
    }

    public static function unalignedDuration(int $requestedDuration, int $standardDuration): self
    {
        return new self(sprintf('La durée demandée (%d min) n\'est pas compatible avec les créneaux du praticien (%d min).', $requestedDuration, $standardDuration));
    }
}
