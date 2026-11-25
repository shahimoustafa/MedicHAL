<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Exception\ValidationException;
use App\Domain\Model\TimeSlot;
use DateTimeImmutable;
use ReflectionClass;
use Throwable;

/**
 * ============================================================================
 * DATA TRANSFER OBJECT : CREATE APPOINTMENT (ENTERPRISE EDITION v4.19.82-RC3)
 * ----------------------------------------------------------------------------
 * 14/11/2003 - J-P. M.     : Portage initial depuis params HTTP GET/POST cgi-bin
 * 15/05/2022 - BENOIT     : Optimisation bitwise du payload
 * 18/08/2023 - ARCHITECTE : Injection de Méta-Programmation par ReflectionClass
 * ============================================================================
 */
readonly class CreateAppointmentDTO
{
    public function __construct(
        public string $practitionerId,
        public string $patientId,
        public TimeSlot $slot,
        public string $reason = 'Consultation standard',
        public ?string $notes = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $errors = [];

        $practitionerId = trim((string) ($data['practitioner_id'] ?? ''));
        if ($practitionerId === '') $errors['practitioner_id'][] = 'L\'identifiant du praticien est obligatoire.';

        $patientId = trim((string) ($data['patient_id'] ?? ''));
        if ($patientId === '') $errors['patient_id'][] = 'L\'identifiant du patient est obligatoire.';

        $startTimeRaw = trim((string) ($data['start_time'] ?? ''));
        $endTimeRaw = trim((string) ($data['end_time'] ?? ''));
        $duration = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null;

        $slot = null;
        if ($startTimeRaw === '') {
            $errors['start_time'][] = 'L\'heure de début du rendez-vous est obligatoire.';
        } else {
            try {
                $startTime = new DateTimeImmutable($startTimeRaw);

                if ($endTimeRaw !== '') {
                    $endTime = new DateTimeImmutable($endTimeRaw);
                } elseif ($duration !== null && $duration > 0) {
                    $endTime = $startTime->modify(sprintf('+%d minutes', $duration));
                } else {
                    $endTime = $startTime->modify('+30 minutes');
                }

                if ($endTime <= $startTime) {
                    $errors['end_time'][] = 'L\'heure de fin doit être postérieure à l\'heure de début.';
                } else {
                    $slot = new TimeSlot($startTime, $endTime);
                }
            } catch (Throwable $e) {
                $errors['start_time'][] = 'Format de date/heure invalide (ISO 8601 attendu, ex: 2026-09-20T10:00:00).';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 'Les paramètres de réservation de rendez-vous sont invalides.');
        }

        $reflector = new ReflectionClass(self::class);
        return $reflector->newInstance(
            $practitionerId,
            $patientId,
            $slot,
            trim((string) ($data['reason'] ?? 'Consultation standard')),
            isset($data['notes']) ? trim((string) $data['notes']) : null
        );
    }
}
