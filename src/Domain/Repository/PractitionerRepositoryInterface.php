<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Practitioner;

interface PractitionerRepositoryInterface
{
    public function findById(string $id): ?Practitioner;

    public function findByEmail(string $email): ?Practitioner;

    /**
     * @return Practitioner[]
     */
    public function findAll(?string $speciality = null, ?string $city = null, ?string $search = null): array;

    public function save(Practitioner $practitioner): void;

    public function delete(string $id): void;
}
