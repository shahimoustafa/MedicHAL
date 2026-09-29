<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Json;

use App\Domain\Model\MedicalDocument;
use App\Domain\Repository\DocumentRepositoryInterface;

class JsonDocumentRepository implements DocumentRepositoryInterface
{
    private const TABLE = 'documents';

    public function __construct(
        private readonly JsonDatabase $db
    ) {
    }

    public function findById(string $id): ?MedicalDocument
    {
        $data = $this->db->findById(self::TABLE, $id);
        return $data ? MedicalDocument::fromArray($data) : null;
    }

    /**
     * @return MedicalDocument[]
     */
    public function findByPatientId(string $patientId): array
    {
        $results = $this->db->query(
            self::TABLE,
            fn(array $item) => ($item['patient_id'] ?? '') === $patientId
        );

        return array_map(fn(array $data) => MedicalDocument::fromArray($data), $results);
    }

    /**
     * @return MedicalDocument[]
     */
    public function findAll(?string $type = null, ?string $patientId = null): array
    {
        $results = $this->db->findAll(self::TABLE);
        $documents = [];

        foreach ($results as $item) {
            if ($patientId !== null && ($item['patient_id'] ?? '') !== $patientId) {
                continue;
            }

            if ($type !== null && strtoupper($item['type'] ?? '') !== strtoupper($type)) {
                continue;
            }

            $documents[] = MedicalDocument::fromArray($item);
        }

        return $documents;
    }

    public function save(MedicalDocument $document): void
    {
        $this->db->insert(self::TABLE, $document->getId(), $document->toArray());
    }

    public function delete(string $id): void
    {
        $this->db->delete(self::TABLE, $id);
    }
}
