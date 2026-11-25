<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Service\IcalCalendarAvailabilityChecker;
use App\Domain\Model\TimeSlot;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class IcalCalendarAvailabilityCheckerTest extends TestCase
{
    private IcalCalendarAvailabilityChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new IcalCalendarAvailabilityChecker();
    }

    public function testUtcEventIsComparedAsAnAbsoluteTime(): void
    {
        $calendar = $this->calendar("DTSTART:20261125T090000Z\r\nDTEND:20261125T093000Z");

        self::assertTrue($this->checker->calendarTextHasConflict($calendar, $this->slot()));
    }

    public function testTzidEventOverlappingTheSlotIsDetected(): void
    {
        $calendar = $this->calendar("DTSTART;TZID=Europe/Paris:20261125T100000\r\nDTEND;TZID=Europe/Paris:20261125T103000");

        self::assertTrue($this->checker->calendarTextHasConflict($calendar, $this->slot()));
    }

    public function testMalformedCalendarIsRejectedInsteadOfTreatedAsFree(): void
    {
        $this->expectException(RuntimeException::class);
        $this->checker->calendarTextHasConflict('BEGIN:VCALENDAR', $this->slot());
    }

    public function testUnknownTimeZoneRejectsTheFeed(): void
    {
        $calendar = $this->calendar("DTSTART;TZID=Unknown/Zone:20261125T100000\r\nDTEND;TZID=Unknown/Zone:20261125T103000");

        $this->expectException(RuntimeException::class);
        $this->checker->calendarTextHasConflict($calendar, $this->slot());
    }

    public function testUnavailableConfiguredCalendarIsRejected(): void
    {
        $previousConfig = getenv('ICAL_CALENDARS_JSON');
        putenv('ICAL_CALENDARS_JSON=' . json_encode(['pract-1' => 'Z:\\missing-calendar.ics'], JSON_THROW_ON_ERROR));
        try {
            $this->expectException(RuntimeException::class);
            $this->checker->hasConflict('pract-1', $this->slot());
        } finally {
            if ($previousConfig === false) {
                putenv('ICAL_CALENDARS_JSON');
            } else {
                putenv('ICAL_CALENDARS_JSON=' . $previousConfig);
            }
        }
    }

    private function slot(): TimeSlot
    {
        return new TimeSlot(
            new DateTimeImmutable('2026-11-25T10:00:00+01:00'),
            new DateTimeImmutable('2026-11-25T10:30:00+01:00')
        );
    }

    private function calendar(string $eventDates): string
    {
        return "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\n{$eventDates}\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    }
}
