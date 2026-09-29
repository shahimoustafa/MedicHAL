<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Availability;

interface AvailabilityRepositoryInterface
{
    public function findById(string $id): ?Availability;

    /**
     * @return Availability[]
     */
    public function findByPractitioner(string $practitionerId): array;

    /**
     * @return Availability[]
     */
    public function findByPractitionerAndDay(string $practitionerId, int $dayOfWeek): array;

    /**
     * @return Availability[]
     */
    public function findByPractitionerAndDate(string $practitionerId, string $date): array;

    public function save(Availability $availability): void;

    public function delete(string $id): void;

    public function deleteByPractitioner(string $practitionerId): void;
}
