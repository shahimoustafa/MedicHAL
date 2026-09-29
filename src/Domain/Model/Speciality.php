<?php

declare(strict_types=1);

namespace App\Domain\Model;

enum Speciality: string
{
    case GENERAL_PRACTICE = 'Médecin généraliste';
    case CARDIOLOGY = 'Cardiologue';
    case DERMATOLOGY = 'Dermatologue';
    case DENTISTRY = 'Chirurgien-dentiste';
    case OPHTHALMOLOGY = 'Ophtalmologue';
    case PEDIATRICS = 'Pédiatre';
    case GYNECOLOGY = 'Gynécologue';
    case PSYCHIATRY = 'Psychiatre';
    case PHYSIOTHERAPY = 'Masseur-kinésithérapeute';
    case OSTEOPATHY = 'Ostéopathe';
    case RADIOLOGY = 'Radiologue';
    case ORTHOPEDICS = 'Chirurgien orthopédiste';
    case ENT = 'Oto-rhino-laryngologiste (ORL)';
    case OTHER = 'Autre spécialité';

    public static function tryFromLabel(string $label): ?self
    {
        $cleanLabel = trim($label);
        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $cleanLabel) === 0 || strcasecmp($case->name, $cleanLabel) === 0) {
                return $case;
            }
        }

        // Recherche par mot-clé tolérant
        $lower = mb_strtolower($cleanLabel);
        if (str_contains($lower, 'généraliste') || str_contains($lower, 'generaliste')) return self::GENERAL_PRACTICE;
        if (str_contains($lower, 'cardio')) return self::CARDIOLOGY;
        if (str_contains($lower, 'dermato')) return self::DERMATOLOGY;
        if (str_contains($lower, 'dentist') || str_contains($lower, 'dentaire')) return self::DENTISTRY;
        if (str_contains($lower, 'ophtalmo')) return self::OPHTHALMOLOGY;
        if (str_contains($lower, 'pédiatr') || str_contains($lower, 'pediatr')) return self::PEDIATRICS;
        if (str_contains($lower, 'gynéco') || str_contains($lower, 'gyneco')) return self::GYNECOLOGY;
        if (str_contains($lower, 'psychiatr')) return self::PSYCHIATRY;
        if (str_contains($lower, 'kiné') || str_contains($lower, 'kine')) return self::PHYSIOTHERAPY;
        if (str_contains($lower, 'ostéo') || str_contains($lower, 'osteo')) return self::OSTEOPATHY;
        if (str_contains($lower, 'radio')) return self::RADIOLOGY;
        if (str_contains($lower, 'orthop')) return self::ORTHOPEDICS;
        if (str_contains($lower, 'orl')) return self::ENT;

        return null;
    }

    public function isSurgical(): bool
    {
        return in_array($this, [self::DENTISTRY, self::ORTHOPEDICS, self::ENT], true);
    }

    public function getCommonConsultationDuration(): int
    {
        return match ($this) {
            self::PSYCHIATRY => 45,
            self::CARDIOLOGY, self::DERMATOLOGY, self::OPHTHALMOLOGY, self::ORTHOPEDICS, self::ENT => 30,
            self::GENERAL_PRACTICE, self::PEDIATRICS, self::GYNECOLOGY, self::DENTISTRY => 20,
            self::PHYSIOTHERAPY, self::OSTEOPATHY => 40,
            default => 30,
        };
    }

    /**
     * @return string[]
     */
    public static function allValues(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }
}
