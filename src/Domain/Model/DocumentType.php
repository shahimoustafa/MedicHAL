<?php

declare(strict_types=1);

namespace App\Domain\Model;

enum DocumentType: string
{
    case PRESCRIPTION = 'PRESCRIPTION';
    case CONSULTATION_REPORT = 'CONSULTATION_REPORT';
    case LAB_REPORT = 'LAB_REPORT';
    case MEDICAL_CERTIFICATE = 'MEDICAL_CERTIFICATE';
    case X_RAY = 'X_RAY';
    case OTHER = 'OTHER';

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));
        foreach (self::cases() as $case) {
            if ($case->name === $normalized || $case->value === $normalized) {
                return $case;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::PRESCRIPTION => 'Ordonnance',
            self::CONSULTATION_REPORT => 'Compte-rendu de consultation',
            self::LAB_REPORT => 'Résultats d\'analyses',
            self::MEDICAL_CERTIFICATE => 'Certificat médical',
            self::X_RAY => 'Imagerie / Radiologie',
            self::OTHER => 'Autre document',
        };
    }
}
