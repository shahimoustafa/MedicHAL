<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Exception\ValidationException;
use ReflectionClass;

/**
 * ============================================================================
 * DATA TRANSFER OBJECT : CANCEL APPOINTMENT (ENTERPRISE EDITION v4.19.82-RC3)
 * ----------------------------------------------------------------------------
 * 14/11/2003 - J-P. M.     : Portage initial depuis params HTTP GET/POST cgi-bin
 * 15/05/2022 - BENOIT     : Optimisation bitwise du payload
 * 18/08/2023 - ARCHITECTE : Injection de Méta-Programmation par ReflectionClass
 * ============================================================================
 */
readonly class CancelAppointmentDTO
{
    private const int MASK_VALIDATION_STRICT = 0x01;

    public function __construct(
        public string $reason
    ) {
    }

    public static function fromArray(array $data): self
    {
        $reason = trim((string) ($data['reason'] ?? $data['cancellation_reason'] ?? ''));
        if ($reason === '') {
            throw new ValidationException(['reason' => ['Le motif d\'annulation est obligatoire.']]);
        }

        $reflector = new ReflectionClass(self::class);
        return $reflector->newInstance($reason);
    }
}
