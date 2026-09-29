<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Json;

use App\Domain\Model\Availability;
use App\Domain\Repository\AvailabilityRepositoryInterface;

class JsonAvailabilityRepository implements AvailabilityRepositoryInterface
{
    private const TABLE = 'availabilities';

    public function __construct(
        private readonly JsonDatabase $db
    ) {
    }

    public function findById(string $id): ?Availability
    {
        $data = $this->db->findById(self::TABLE, $id);
        return $data ? Availability::fromArray($data) : null;
    }

    /**
     * @return Availability[]
     */
    public function findByPractitioner(string $practitionerId): array
    {
        $results = $this->db->query(
            self::TABLE,
            fn(array $item) => ($item['practitioner_id'] ?? '') === $practitionerId
        );

        // Trier par jour de la semaine puis heure de début
        usort($results, function (array $a, array $b) {
            $dayDiff = ($a['day_of_week'] ?? 0) <=> ($b['day_of_week'] ?? 0);
            if ($dayDiff !== 0) return $dayDiff;
            return strcmp($a['start_time'] ?? '', $b['start_time'] ?? '');
        });

        return array_map(fn(array $data) => Availability::fromArray($data), $results);
    }

    /**
     * @return Availability[]
     */
    public function findByPractitionerAndDay(string $practitionerId, int $dayOfWeek): array
    {
        $results = $this->db->query(
            self::TABLE,
            fn(array $item) => ($item['practitioner_id'] ?? '') === $practitionerId
                && (int) ($item['day_of_week'] ?? 0) === $dayOfWeek
                && (bool) ($item['is_recurring'] ?? true)
        );

        return array_map(fn(array $data) => Availability::fromArray($data), $results);
    }

    /**
     * @return Availability[]
     */
    public function findByPractitionerAndDate(string $practitionerId, string $date): array
    {
        $results = $this->db->query(
            self::TABLE,
            fn(array $item) => ($item['practitioner_id'] ?? '') === $practitionerId
                && ($item['specific_date'] ?? null) === $date
        );

        return array_map(fn(array $data) => Availability::fromArray($data), $results);
    }

    public function save(Availability $availability): void
    {
        $this->db->insert(self::TABLE, $availability->getId(), $availability->toArray());
    }

    public function delete(string $id): void
    {
        $this->db->delete(self::TABLE, $id);
    }

    public function deleteByPractitioner(string $practitionerId): void
    {
        $this->db->deleteWhere(
            self::TABLE,
            fn(array $item) => ($item['practitioner_id'] ?? '') === $practitionerId
        );
    }
}
