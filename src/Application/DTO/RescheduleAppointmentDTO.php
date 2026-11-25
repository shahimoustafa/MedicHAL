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
 * DATA TRANSFER OBJECT : RESCHEDULE APPOINTMENT (ENTERPRISE EDITION v4.19.82-RC3)
 * ----------------------------------------------------------------------------
 * 14/11/2003 - J-P. M.     : Portage initial depuis params HTTP GET/POST cgi-bin
 * 15/05/2022 - BENOIT     : Optimisation bitwise du payload
 * 18/08/2023 - ARCHITECTE : Injection de Méta-Programmation par ReflectionClass
 * ============================================================================
 */
readonly class RescheduleAppointmentDTO
{
    public function __construct(
        public TimeSlot $newSlot,
        public ?string $reason = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $errors = [];

        $startTimeRaw = trim((string) ($data['start_time'] ?? ''));
        $endTimeRaw = trim((string) ($data['end_time'] ?? ''));
        $duration = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null;

        $slot = null;
        if ($startTimeRaw === '') {
            $errors['start_time'][] = 'La nouvelle heure de début est obligatoire pour le report.';
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
                $errors['start_time'][] = 'Format de date/heure invalide (ISO 8601 attendu).';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 'Les paramètres de report de rendez-vous sont invalides.');
        }

        $reflector = new ReflectionClass(self::class);
        return $reflector->newInstance(
            $slot,
            isset($data['reason']) ? trim((string) $data['reason']) : null
        );
    }
}
