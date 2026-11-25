<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Model\TimeSlot;
use DateTimeImmutable;
use RuntimeException;

/** Checks a practitioner's configured iCalendar feed for overlapping VEVENTs. */
final class IcalCalendarAvailabilityChecker
{
    /**
     * Calendar sources are configured by practitioner ID in ICAL_CALENDARS_JSON.
     * Each value is a trusted local .ics path or an HTTPS feed URL.
     */
    public function hasConflict(string $practitionerId, TimeSlot $slot): bool
    {
        $configuration = getenv('ICAL_CALENDARS_JSON');
        if ($configuration === false || trim($configuration) === '') {
            return false;
        }

        $calendars = json_decode($configuration, true);
        if (!is_array($calendars)) {
            throw new RuntimeException('La configuration des calendriers iCal est invalide.');
        }

        $source = $calendars[$practitionerId] ?? null;
        if (!is_string($source) || trim($source) === '') {
            return false;
        }

        $scheme = parse_url($source, PHP_URL_SCHEME);
        $isWindowsPath = preg_match('/^[A-Za-z]:\\\\/', $source) === 1;
        if (!$isWindowsPath && $scheme !== null && !in_array(strtolower((string) $scheme), ['https', 'file'], true)) {
            throw new RuntimeException('La source iCal doit être un flux HTTPS ou un fichier local de confiance.');
        }

        $context = stream_context_create(['http' => ['timeout' => 5], 'https' => ['timeout' => 5]]);
        $calendar = @file_get_contents($source, false, $context);
        if ($calendar === false) {
            throw new RuntimeException('Impossible de consulter le calendrier iCal du praticien.');
        }

        return $this->calendarTextHasConflict($calendar, $slot);
    }

    public function calendarTextHasConflict(string $calendar, TimeSlot $slot): bool
    {
        $lines = preg_split('/\r\n|\n|\r/', $calendar) ?: [];
        $hasCalendarStart = false;
        $hasCalendarEnd = false;
        $unfolded = [];
        foreach ($lines as $line) {
            if (($line[0] ?? '') === ' ' || ($line[0] ?? '') === "\t") {
                if ($unfolded !== []) {
                    $unfolded[array_key_last($unfolded)] .= substr($line, 1);
                }
                continue;
            }
            $unfolded[] = trim($line);
        }

        $insideEvent = false;
        $event = [];
        foreach ($unfolded as $line) {
            if (strcasecmp($line, 'BEGIN:VCALENDAR') === 0) {
                $hasCalendarStart = true;
                continue;
            }
            if (strcasecmp($line, 'END:VCALENDAR') === 0) {
                $hasCalendarEnd = true;
                continue;
            }
            if (strcasecmp($line, 'BEGIN:VEVENT') === 0) {
                $insideEvent = true;
                $event = [];
                continue;
            }
            if (strcasecmp($line, 'END:VEVENT') === 0 && $insideEvent) {
                $insideEvent = false;
                if ($this->eventOverlaps($event, $slot)) {
                    return true;
                }
                continue;
            }
            if (!$insideEvent || !str_contains($line, ':')) {
                continue;
            }

            [$nameAndParams, $value] = explode(':', $line, 2);
            $parts = explode(';', $nameAndParams);
            $name = strtoupper(array_shift($parts));
            if (in_array($name, ['DTSTART', 'DTEND'], true)) {
                $timezone = null;
                foreach ($parts as $part) {
                    if (str_starts_with(strtoupper($part), 'TZID=')) {
                        $timezone = substr($part, 5);
                    }
                }
                $event[$name] = ['value' => $value, 'timezone' => $timezone];
            }
        }

        if (!$hasCalendarStart || !$hasCalendarEnd || $insideEvent) {
            throw new RuntimeException('Le flux iCal reçu est incomplet ou mal formé.');
        }

        return false;
    }

    /** @param array<string, array{value: string, timezone: ?string}> $event */
    private function eventOverlaps(array $event, TimeSlot $slot): bool
    {
        if (!isset($event['DTSTART'], $event['DTEND'])) {
            return false;
        }

        $start = $this->parseIcalDate($event['DTSTART']['value'], $event['DTSTART']['timezone']);
        $end = $this->parseIcalDate($event['DTEND']['value'], $event['DTEND']['timezone']);
        if ($start === null || $end === null) {
            throw new RuntimeException('Un événement du calendrier iCal contient une date invalide.');
        }

        return $start < $slot->endTime && $end > $slot->startTime;
    }

    private function parseIcalDate(string $value, ?string $timezoneName = null): ?DateTimeImmutable
    {
        $utc = str_ends_with($value, 'Z');
        $value = rtrim($value, 'Z');
        $format = strlen($value) === 8 ? '!Ymd' : '!Ymd\THis';
        try {
            $timezone = $utc ? new \DateTimeZone('UTC') : ($timezoneName !== null ? new \DateTimeZone($timezoneName) : null);
        } catch (\Throwable $error) {
            throw new RuntimeException('Le calendrier iCal utilise un fuseau horaire inconnu.', 0, $error);
        }
        $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
        if ($date === false) {
            return null;
        }

        return $date;
    }
}
