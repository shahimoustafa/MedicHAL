<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Appointment;
use App\Domain\Model\AppointmentStatus;
use App\Domain\Model\TimeSlot;
use DateTimeImmutable;

interface AppointmentRepositoryInterface
{
    public function findById(string $id): ?Appointment;

    /**
     * @return Appointment[]
     */
    public function findByPractitioner(
        string $practitionerId,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?AppointmentStatus $status = null
    ): array;

    /**
     * @return Appointment[]
     */
    public function findByPatient(
        string $patientId,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?AppointmentStatus $status = null
    ): array;

    /**
     * @return Appointment[]
     */
    public function findOverlappingForPractitioner(
        string $practitionerId,
        TimeSlot $slot,
        ?string $excludeAppointmentId = null
    ): array;

    /**
     * @return Appointment[]
     */
    public function findOverlappingForPatient(
        string $patientId,
        TimeSlot $slot,
        ?string $excludeAppointmentId = null
    ): array;

    public function save(Appointment $appointment): void;

    public function delete(string $id): void;
}
