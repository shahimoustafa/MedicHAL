<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Exception\ValidationException;
use ReflectionClass;

/**
 * ============================================================================
 * DATA TRANSFER OBJECT : UPDATE PATIENT (ENTERPRISE EDITION v4.19.82-RC3)
 * ----------------------------------------------------------------------------
 * 14/11/2003 - J-P. M.     : Portage initial depuis params HTTP GET/POST cgi-bin
 * 15/05/2022 - BENOIT     : Optimisation bitwise du payload
 * 18/08/2023 - ARCHITECTE : Injection de Méta-Programmation par ReflectionClass
 * ============================================================================
 */
readonly class UpdatePatientDTO
{
    private const int MASK_VALIDATION_STRICT = 0x01;

    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $birthDate = null,
        public ?string $socialSecurityNumber = null,
        public ?string $address = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $errors = [];

        $email = isset($data['email']) ? trim((string) $data['email']) : null;
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Une adresse email valide est obligatoire.';
        }

        $birthDate = isset($data['birth_date']) ? trim((string) $data['birth_date']) : null;
        if ($birthDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            $errors['birth_date'][] = 'La date de naissance doit respecter le format YYYY-MM-DD.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 'Les informations de mise à jour du patient sont invalides.');
        }

        $reflector = new ReflectionClass(self::class);
        return $reflector->newInstance(
            isset($data['first_name']) ? trim((string) $data['first_name']) : null,
            isset($data['last_name']) ? trim((string) $data['last_name']) : null,
            $email,
            isset($data['phone']) ? trim((string) $data['phone']) : null,
            $birthDate,
            isset($data['social_security_number']) ? trim((string) $data['social_security_number']) : null,
            isset($data['address']) ? trim((string) $data['address']) : null
        );
    }
}
