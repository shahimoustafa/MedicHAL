<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Json;

use App\Domain\Model\Practitioner;
use App\Domain\Repository\PractitionerRepositoryInterface;

class JsonPractitionerRepository implements PractitionerRepositoryInterface
{
    private const TABLE = 'practitioners';

    public function __construct(
        private readonly JsonDatabase $db
    ) {
    }

    public function findById(string $id): ?Practitioner
    {
        $data = $this->db->findById(self::TABLE, $id);
        return $data ? Practitioner::fromArray($data) : null;
    }

    public function findByEmail(string $email): ?Practitioner
    {
        $results = $this->db->query(
            self::TABLE,
            fn(array $item) => strcasecmp($item['email'] ?? '', $email) === 0
        );

        return !empty($results) ? Practitioner::fromArray($results[0]) : null;
    }

    public function findAll(?string $speciality = null, ?string $city = null, ?string $search = null): array
    {
        $all = $this->db->findAll(self::TABLE);
        $filtered = [];

        foreach ($all as $item) {
            if ($speciality !== null && $speciality !== '') {
                $itemSpec = $item['speciality'] ?? '';
                $itemSpecKey = $item['speciality_key'] ?? '';
                if (stripos($itemSpec, $speciality) === false && stripos($itemSpecKey, $speciality) === false) {
                    continue;
                }
            }

            if ($city !== null && $city !== '') {
                $itemCity = $item['city'] ?? '';
                if (stripos($itemCity, $city) === false) {
                    continue;
                }
            }

            if ($search !== null && $search !== '') {
                $fullName = ($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '');
                $address = $item['address'] ?? '';
                $cityField = $item['city'] ?? '';
                $specField = $item['speciality'] ?? '';
                $match = (stripos($fullName, $search) !== false)
                    || (stripos($address, $search) !== false)
                    || (stripos($cityField, $search) !== false)
                    || (stripos($specField, $search) !== false);

                if (!$match) {
                    continue;
                }
            }

            $filtered[] = Practitioner::fromArray($item);
        }

        return $filtered;
    }

    public function save(Practitioner $practitioner): void
    {
        $this->db->insert(self::TABLE, $practitioner->getId(), $practitioner->toArray());
    }

    public function delete(string $id): void
    {
        $this->db->delete(self::TABLE, $id);
    }
}
