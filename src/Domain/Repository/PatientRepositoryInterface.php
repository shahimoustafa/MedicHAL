<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Patient;

interface PatientRepositoryInterface
{
    public function findById(string $id): ?Patient;

    public function findByEmail(string $email): ?Patient;

    /**
     * @return Patient[]
     */
    public function findAll(?string $search = null): array;

    public function save(Patient $patient): void;

    public function delete(string $id): void;
}
