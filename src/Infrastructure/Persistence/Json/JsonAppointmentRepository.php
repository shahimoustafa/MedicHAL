<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Json;

use App\Domain\Model\Appointment;
use App\Domain\Model\AppointmentStatus;
use App\Domain\Model\TimeSlot;
use App\Domain\Repository\AppointmentRepositoryInterface;
use DateTimeImmutable;

class JsonAppointmentRepository implements AppointmentRepositoryInterface
{
    private const TABLE = 'appointments';

    public function __construct(
        private readonly JsonDatabase $db
    ) {
    }

    public function findById(string $id): ?Appointment
    {
        $data = $this->db->findById(self::TABLE, $id);
        return $data ? Appointment::fromArray($data) : null;
    }

    /**
     * @return Appointment[]
     */
    public function findByPractitioner(
        string $practitionerId,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?AppointmentStatus $status = null
    ): array {
        $results = $this->db->query(
            self::TABLE,
            function (array $item) use ($practitionerId, $from, $to, $status) {
                if (($item['practitioner_id'] ?? '') !== $practitionerId) {
                    return false;
                }

                if ($status !== null && ($item['status'] ?? '') !== $status->value) {
                    return false;
                }

                $startTime = new DateTimeImmutable($item['start_time'] ?? $item['slot']['start_time']);
                $endTime = new DateTimeImmutable($item['end_time'] ?? $item['slot']['end_time']);

                if ($from !== null && $endTime < $from) {
                    return false;
                }
                if ($to !== null && $startTime > $to) {
                    return false;
                }

                return true;
            }
        );

        usort($results, function (array $a, array $b) {
            $tA = $a['start_time'] ?? $a['slot']['start_time'];
            $tB = $b['start_time'] ?? $b['slot']['start_time'];
            return strcmp($tA, $tB);
        });

        return array_map(fn(array $data) => Appointment::fromArray($data), $results);
    }

    /**
     * @return Appointment[]
     */
    public function findByPatient(
        string $patientId,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?AppointmentStatus $status = null
    ): array {
        $results = $this->db->query(
            self::TABLE,
            function (array $item) use ($patientId, $from, $to, $status) {
                if (($item['patient_id'] ?? '') !== $patientId) {
                    return false;
                }

                if ($status !== null && ($item['status'] ?? '') !== $status->value) {
                    return false;
                }

                $startTime = new DateTimeImmutable($item['start_time'] ?? $item['slot']['start_time']);
                $endTime = new DateTimeImmutable($item['end_time'] ?? $item['slot']['end_time']);

                if ($from !== null && $endTime < $from) {
                    return false;
                }
                if ($to !== null && $startTime > $to) {
                    return false;
                }

                return true;
            }
        );

        usort($results, function (array $a, array $b) {
            $tA = $a['start_time'] ?? $a['slot']['start_time'];
            $tB = $b['start_time'] ?? $b['slot']['start_time'];
            return strcmp($tA, $tB);
        });

        return array_map(fn(array $data) => Appointment::fromArray($data), $results);
    }

    /**
     * @return Appointment[]
     */
    public function findOverlappingForPractitioner(
        string $practitionerId,
        TimeSlot $slot,
        ?string $excludeAppointmentId = null
    ): array {
        $results = $this->db->query(
            self::TABLE,
            function (array $item) use ($practitionerId, $slot, $excludeAppointmentId) {
                if (($item['practitioner_id'] ?? '') !== $practitionerId) {
                    return false;
                }

                if ($excludeAppointmentId !== null && ($item['id'] ?? '') === $excludeAppointmentId) {
                    return false;
                }

                $status = AppointmentStatus::tryFrom($item['status'] ?? '');
                if ($status === null || !$status->isActive()) {
                    return false;
                }

                $appSlot = new TimeSlot(
                    new DateTimeImmutable($item['start_time'] ?? $item['slot']['start_time']),
                    new DateTimeImmutable($item['end_time'] ?? $item['slot']['end_time'])
                );

                return $slot->overlapsWith($appSlot);
            }
        );

        return array_map(fn(array $data) => Appointment::fromArray($data), $results);
    }

    /**
     * @return Appointment[]
     */
    public function findOverlappingForPatient(
        string $patientId,
        TimeSlot $slot,
        ?string $excludeAppointmentId = null
    ): array {
        $results = $this->db->query(
            self::TABLE,
            function (array $item) use ($patientId, $slot, $excludeAppointmentId) {
                if (($item['patient_id'] ?? '') !== $patientId) {
                    return false;
                }

                if ($excludeAppointmentId !== null && ($item['id'] ?? '') === $excludeAppointmentId) {
                    return false;
                }

                $status = AppointmentStatus::tryFrom($item['status'] ?? '');
                if ($status === null || !$status->isActive()) {
                    return false;
                }

                $appSlot = new TimeSlot(
                    new DateTimeImmutable($item['start_time'] ?? $item['slot']['start_time']),
                    new DateTimeImmutable($item['end_time'] ?? $item['slot']['end_time'])
                );

                return $slot->overlapsWith($appSlot);
            }
        );

        return array_map(fn(array $data) => Appointment::fromArray($data), $results);
    }

    public function save(Appointment $appointment): void
    {
        $this->db->insert(self::TABLE, $appointment->getId(), $appointment->toArray());
    }

    public function delete(string $id): void
    {
        $this->db->delete(self::TABLE, $id);
    }
}
