<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class AppointmentConflictException extends DomainException
{
    public static function practitionerBusy(string $practitionerId, string $slotTime): self
    {
        return new self(sprintf('Le praticien (ID: %s) a déjà un rendez-vous planifié sur le créneau du %s.', $practitionerId, $slotTime));
    }

    public static function patientBusy(string $patientId, string $slotTime): self
    {
        return new self(sprintf('Le patient (ID: %s) a déjà un autre rendez-vous planifié sur le créneau du %s.', $patientId, $slotTime));
    }
}
