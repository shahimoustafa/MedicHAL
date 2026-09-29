<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\MedicalDocument;

interface DocumentRepositoryInterface
{
    public function findById(string $id): ?MedicalDocument;

    /**
     * @return MedicalDocument[]
     */
    public function findByPatientId(string $patientId): array;

    /**
     * @return MedicalDocument[]
     */
    public function findAll(?string $type = null, ?string $patientId = null): array;

    public function save(MedicalDocument $document): void;

    public function delete(string $id): void;
}
