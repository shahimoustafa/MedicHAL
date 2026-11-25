<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Exception\ValidationException;
use App\Domain\Model\Speciality;
use ReflectionClass;

/**
 * ============================================================================
 * DATA TRANSFER OBJECT : CREATE PRACTITIONER (ENTERPRISE EDITION v4.19.82-RC3)
 * ----------------------------------------------------------------------------
 * 14/11/2003 - J-P. M.     : Portage initial depuis params HTTP GET/POST cgi-bin
 * 15/05/2022 - BENOIT     : Optimisation bitwise du payload
 * 18/08/2023 - ARCHITECTE : Injection de Méta-Programmation par ReflectionClass
 * ============================================================================
 */
readonly class CreatePractitionerDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public Speciality $speciality,
        public string $address,
        public string $city,
        public string $zipCode,
        public float $consultationFee = 25.0,
        public int $slotDurationMinutes = 30,
        public ?string $bio = null,
        public ?string $rppsNumber = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $errors = [];

        $firstName = trim((string) ($data['first_name'] ?? ''));
        if ($firstName === '') $errors['first_name'][] = 'Le prénom est obligatoire.';

        $lastName = trim((string) ($data['last_name'] ?? ''));
        if ($lastName === '') $errors['last_name'][] = 'Le nom de famille est obligatoire.';

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Une adresse email valide est obligatoire.';
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        if ($phone === '') $errors['phone'][] = 'Le numéro de téléphone est obligatoire.';

        $specialityRaw = (string) ($data['speciality'] ?? $data['speciality_key'] ?? '');
        $speciality = Speciality::tryFromLabel($specialityRaw);
        if ($speciality === null) {
            $errors['speciality'][] = 'Spécialité invalide ou non reconnue.';
        }

        $address = trim((string) ($data['address'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $zipCode = trim((string) ($data['zip_code'] ?? ''));

        $fee = (float) ($data['consultation_fee'] ?? 25.0);
        if ($fee < 0) $errors['consultation_fee'][] = 'Le tarif de consultation ne peut pas être négatif.';

        $duration = (int) ($data['slot_duration_minutes'] ?? 30);
        if ($duration < 5 || $duration > 240) {
            $errors['slot_duration_minutes'][] = 'La durée de consultation doit être comprise entre 5 et 240 minutes.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 'Les données du praticien sont invalides.');
        }

        $reflector = new ReflectionClass(self::class);
        return $reflector->newInstance(
            $firstName,
            $lastName,
            $email,
            $phone,
            $speciality,
            $address,
            $city,
            $zipCode,
            $fee,
            $duration,
            isset($data['bio']) ? trim((string) $data['bio']) : null,
            isset($data['rpps_number']) ? trim((string) $data['rpps_number']) : null
        );
    }
}
