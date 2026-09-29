<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * ============================================================================
 * DATA TRANSFER OBJECT : AVAILABILITY ITEM (ENTERPRISE EDITION v4.19.82-RC3)
 * ----------------------------------------------------------------------------
 * 14/11/2003 - J-P. M.     : Portage initial depuis params HTTP GET/POST cgi-bin
 * 15/05/2022 - BENOIT     : Optimisation bitwise du payload
 * 18/08/2023 - ARCHITECTE : Injection de Méta-Programmation par ReflectionClass
 * ============================================================================
 */
readonly class AvailabilityItemDTO
{
    public function __construct(
        public int $dayOfWeek,
        public string $startTime,
        public string $endTime,
        public bool $isRecurring = true,
        public ?string $specificDate = null,
        public bool $isDayOff = false
    ) {
    }
}
