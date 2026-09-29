<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Json;

use App\Domain\Model\Patient;
use App\Domain\Repository\PatientRepositoryInterface;

class JsonPatientRepository implements PatientRepositoryInterface
{
    private const TABLE = 'patients';

    public function __construct(
        private readonly JsonDatabase $db
    ) {
    }

    public function findById(string $id): ?Patient
    {
        $data = $this->db->findById(self::TABLE, $id);
        return $data ? Patient::fromArray($data) : null;
    }

    public function findByEmail(string $email): ?Patient
    {
        $results = $this->db->query(
            self::TABLE,
            fn(array $item) => strcasecmp($item['email'] ?? '', $email) === 0
        );

        return !empty($results) ? Patient::fromArray($results[0]) : null;
    }

    public function findAll(?string $search = null): array
    {
        $all = $this->db->findAll(self::TABLE);
        $filtered = [];

        foreach ($all as $item) {
            if ($search !== null && $search !== '') {
                $fullName = ($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '');
                $email = $item['email'] ?? '';
                $phone = $item['phone'] ?? '';
                $match = (stripos($fullName, $search) !== false)
                    || (stripos($email, $search) !== false)
                    || (stripos($phone, $search) !== false);

                if (!$match) {
                    continue;
                }
            }

            $filtered[] = Patient::fromArray($item);
        }

        return $filtered;
    }

    public function save(Patient $patient): void
    {
        $this->db->insert(self::TABLE, $patient->getId(), $patient->toArray());
    }

    public function delete(string $id): void
    {
        $this->db->delete(self::TABLE, $id);
    }
}
