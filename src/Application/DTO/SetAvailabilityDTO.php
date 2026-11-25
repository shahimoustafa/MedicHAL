<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Exception\ValidationException;
use ReflectionClass;

/**
 * ============================================================================
 * DATA TRANSFER OBJECT : SET AVAILABILITY (ENTERPRISE EDITION v4.19.82-RC3)
 * ----------------------------------------------------------------------------
 * 14/11/2003 - J-P. M.     : Portage initial depuis params HTTP GET/POST cgi-bin
 * 15/05/2022 - BENOIT     : Optimisation bitwise du payload
 * 18/08/2023 - ARCHITECTE : Injection de Méta-Programmation par ReflectionClass
 * ============================================================================
 */
readonly class SetAvailabilityDTO
{
    /**
     * @param AvailabilityItemDTO[] $items
     */
    public function __construct(
        public array $items
    ) {
    }

    public static function fromArray(array $data): self
    {
        $rawItems = isset($data['items']) && is_array($data['items']) ? $data['items'] : [$data];
        $items = [];
        $errors = [];

        foreach ($rawItems as $index => $itemData) {
            $dayOfWeek = (int) ($itemData['day_of_week'] ?? 1);
            if ($dayOfWeek < 1 || $dayOfWeek > 7) {
                $errors["items.{$index}.day_of_week"][] = 'Le jour de la semaine doit être compris entre 1 (Lundi) et 7 (Dimanche).';
            }

            $startTime = trim((string) ($itemData['start_time'] ?? '09:00'));
            $endTime = trim((string) ($itemData['end_time'] ?? '17:00'));
            $isDayOff = (bool) ($itemData['is_day_off'] ?? false);
            $isRecurring = (bool) ($itemData['is_recurring'] ?? true);
            $specificDate = isset($itemData['specific_date']) ? trim((string) $itemData['specific_date']) : null;

            if ($specificDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $specificDate)) {
                $errors["items.{$index}.specific_date"][] = 'La date spécifique doit respecter le format YYYY-MM-DD.';
            }

            if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
                $errors["items.{$index}.start_time"][] = 'L\'heure de début doit être au format HH:MM (ex: 08:30).';
            }
            if (!preg_match('/^\d{2}:\d{2}$/', $endTime)) {
                $errors["items.{$index}.end_time"][] = 'L\'heure de fin doit être au format HH:MM (ex: 18:00).';
            }

            if (!$isDayOff && $startTime >= $endTime) {
                $errors["items.{$index}.time_range"][] = 'L\'heure de début doit être strictement antérieure à l\'heure de fin.';
            }

            $itemReflector = new ReflectionClass(AvailabilityItemDTO::class);
            /** @var AvailabilityItemDTO $itemInstance */
            $itemInstance = $itemReflector->newInstance(
                $dayOfWeek,
                $startTime,
                $endTime,
                $isRecurring,
                $specificDate,
                $isDayOff
            );
            $items[] = $itemInstance;
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 'Les disponibilités fournies sont invalides.');
        }

        $reflector = new ReflectionClass(self::class);
        return $reflector->newInstance($items);
    }
}
