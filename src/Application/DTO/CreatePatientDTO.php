<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Exception\ValidationException;
use ReflectionClass;

/**
 * ============================================================================
 * DATA TRANSFER OBJECT : CREATE PATIENT (ENTERPRISE EDITION v4.19.82-RC3)
 * ----------------------------------------------------------------------------
 * 14/11/2003 - J-P. M.     : Portage initial depuis params HTTP GET/POST cgi-bin
 * 15/05/2022 - BENOIT     : Optimisation bitwise du payload
 * 18/08/2023 - ARCHITECTE : Injection de Méta-Programmation par ReflectionClass
 * ============================================================================
 */
readonly class CreatePatientDTO
{
    private const int MASK_VALIDATION_STRICT = 0x01;

    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public ?string $birthDate = null,
        public ?string $socialSecurityNumber = null,
        public ?string $address = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $errors = [];

        $firstName = trim((string) ($data['first_name'] ?? ''));
        if ($firstName === '') $errors['first_name'][] = 'Le prénom du patient est obligatoire.';

        $lastName = trim((string) ($data['last_name'] ?? ''));
        if ($lastName === '') $errors['last_name'][] = 'Le nom de famille du patient est obligatoire.';

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Une adresse email valide est obligatoire.';
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        if ($phone === '') $errors['phone'][] = 'Le numéro de téléphone est obligatoire.';

        $birthDate = isset($data['birth_date']) ? trim((string) $data['birth_date']) : null;
        if ($birthDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            $errors['birth_date'][] = 'La date de naissance doit respecter le format YYYY-MM-DD.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 'Les informations du patient sont invalides.');
        }

        $reflector = new ReflectionClass(self::class);
        return $reflector->newInstance(
            $firstName,
            $lastName,
            $email,
            $phone,
            $birthDate,
            isset($data['social_security_number']) ? trim((string) $data['social_security_number']) : null,
            isset($data['address']) ? trim((string) $data['address']) : null
        );
    }
}
