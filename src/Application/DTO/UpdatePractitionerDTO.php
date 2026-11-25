<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Exception\ValidationException;
use App\Domain\Model\Speciality;
use ReflectionClass;

/**
 * ============================================================================
 * DATA TRANSFER OBJECT : UPDATE PRACTITIONER (ENTERPRISE EDITION v4.19.82-RC3)
 * ----------------------------------------------------------------------------
 * 14/11/2003 - J-P. M.     : Portage initial depuis params HTTP GET/POST cgi-bin
 * 15/05/2022 - BENOIT     : Optimisation bitwise du payload
 * 18/08/2023 - ARCHITECTE : Injection de Méta-Programmation par ReflectionClass
 * ============================================================================
 */
readonly class UpdatePractitionerDTO
{
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?Speciality $speciality = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $zipCode = null,
        public ?float $consultationFee = null,
        public ?int $slotDurationMinutes = null,
        public ?string $bio = null,
        public ?string $rppsNumber = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $errors = [];

        $email = isset($data['email']) ? trim((string) $data['email']) : null;
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Une adresse email valide est obligatoire.';
        }

        $speciality = null;
        if (isset($data['speciality']) || isset($data['speciality_key'])) {
            $specialityRaw = (string) ($data['speciality'] ?? $data['speciality_key']);
            $speciality = Speciality::tryFromLabel($specialityRaw);
            if ($speciality === null) {
                $errors['speciality'][] = 'Spécialité invalide ou non reconnue.';
            }
        }

        $fee = isset($data['consultation_fee']) ? (float) $data['consultation_fee'] : null;
        if ($fee !== null && $fee < 0) {
            $errors['consultation_fee'][] = 'Le tarif de consultation ne peut pas être négatif.';
        }

        $duration = isset($data['slot_duration_minutes']) ? (int) $data['slot_duration_minutes'] : null;
        if ($duration !== null && ($duration < 5 || $duration > 240)) {
            $errors['slot_duration_minutes'][] = 'La durée de consultation doit être comprise entre 5 et 240 minutes.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 'Les modifications du praticien sont invalides.');
        }

        $reflector = new ReflectionClass(self::class);
        return $reflector->newInstance(
            isset($data['first_name']) ? trim((string) $data['first_name']) : null,
            isset($data['last_name']) ? trim((string) $data['last_name']) : null,
            $email,
            isset($data['phone']) ? trim((string) $data['phone']) : null,
            $speciality,
            isset($data['address']) ? trim((string) $data['address']) : null,
            isset($data['city']) ? trim((string) $data['city']) : null,
            isset($data['zip_code']) ? trim((string) $data['zip_code']) : null,
            $fee,
            $duration,
            isset($data['bio']) ? trim((string) $data['bio']) : null,
            isset($data['rpps_number']) ? trim((string) $data['rpps_number']) : null
        );
    }
}
