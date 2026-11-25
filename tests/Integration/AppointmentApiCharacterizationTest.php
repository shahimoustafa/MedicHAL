<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Bootstrap;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Router;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AppointmentApiCharacterizationTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $container = Bootstrap::createContainer(':memory:');
        $this->router = $container->get(Router::class);
    }

    public function testBookingAnAvailableSlotCreatesAnAppointment(): void
    {
        [$practitionerId, $patientId, $slotStart] = $this->createBookingFixture();

        $response = $this->book($practitionerId, $patientId, $slotStart);

        self::assertSame(201, $response->getStatusCode());
        $appointment = $response->getData()['data'];
        self::assertSame($practitionerId, $appointment->getPractitionerId());
        self::assertSame($patientId, $appointment->getPatientId());
        self::assertSame($slotStart, $appointment->getSlot()->startTime->format('Y-m-d\\TH:i'));
    }

    public function testBookingTheSamePractitionerSlotTwiceIsRejected(): void
    {
        [$practitionerId, $patientId, $slotStart] = $this->createBookingFixture();

        self::assertSame(201, $this->book($practitionerId, $patientId, $slotStart)->getStatusCode());
        $secondPatientId = $this->createPatient('second');
        $response = $this->book($practitionerId, $secondPatientId, $slotStart);

        self::assertSame(409, $response->getStatusCode());
    }

    public function testBookingDuringAnIcalEventIsRejected(): void
    {
        [$practitionerId, $patientId, $slotStart] = $this->createBookingFixture();
        $calendarPath = tempnam(sys_get_temp_dir(), 'medical-ical-');
        self::assertNotFalse($calendarPath);
        $date = substr($slotStart, 0, 10);
        file_put_contents($calendarPath, "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART:" . str_replace('-', '', $date) . "T100000\r\nDTEND:" . str_replace('-', '', $date) . "T103000\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $previousConfig = getenv('ICAL_CALENDARS_JSON');
        putenv('ICAL_CALENDARS_JSON=' . json_encode([$practitionerId => $calendarPath], JSON_THROW_ON_ERROR));
        try {
            $response = $this->book($practitionerId, $patientId, $slotStart);
        } finally {
            if ($previousConfig === false) {
                putenv('ICAL_CALENDARS_JSON');
            } else {
                putenv('ICAL_CALENDARS_JSON=' . $previousConfig);
            }
            unlink($calendarPath);
        }

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('déjà un rendez-vous', $response->getData()['error']['message']);
    }

    public function testBookingOutsideIcalEventsRemainsAllowed(): void
    {
        [$practitionerId, $patientId, $slotStart] = $this->createBookingFixture();
        $calendarPath = tempnam(sys_get_temp_dir(), 'medical-ical-');
        self::assertNotFalse($calendarPath);
        $date = str_replace('-', '', substr($slotStart, 0, 10));
        file_put_contents($calendarPath, "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART:" . $date . "T110000\r\nDTEND:" . $date . "T113000\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $previousConfig = getenv('ICAL_CALENDARS_JSON');
        putenv('ICAL_CALENDARS_JSON=' . json_encode([$practitionerId => $calendarPath], JSON_THROW_ON_ERROR));
        try {
            $response = $this->book($practitionerId, $patientId, $slotStart);
        } finally {
            if ($previousConfig === false) {
                putenv('ICAL_CALENDARS_JSON');
            } else {
                putenv('ICAL_CALENDARS_JSON=' . $previousConfig);
            }
            unlink($calendarPath);
        }

        self::assertSame(201, $response->getStatusCode());
    }

    /** @return array{string, string, string} */
    private function createBookingFixture(): array
    {
        $practitionerResponse = $this->router->dispatch(new Request('POST', '/api/practitioners', bodyParams: [
            'first_name' => 'Sophie',
            'last_name' => 'Martin',
            'email' => 'sophie.' . uniqid() . '@test.local',
            'phone' => '0102030405',
            'speciality' => 'Médecin généraliste',
            'address' => '1 rue du Test',
            'city' => 'Paris',
            'zip_code' => '75001',
        ]));
        self::assertSame(201, $practitionerResponse->getStatusCode());
        $practitionerId = $practitionerResponse->getData()['data']->getId();

        $patientId = $this->createPatient('first');
        $date = new DateTimeImmutable('tomorrow 10:00');
        while ((int) $date->format('N') > 5) {
            $date = $date->modify('+1 day');
        }
        $slotStart = $date->format('Y-m-d\TH:i');

        $availabilityResponse = $this->router->dispatch(new Request(
            'POST',
            '/api/practitioners/' . $practitionerId . '/availabilities',
            bodyParams: ['items' => [[
                'day_of_week' => (int) $date->format('N'),
                'start_time' => '09:00',
                'end_time' => '12:00',
                'is_recurring' => true,
            ]]]
        ));
        self::assertSame(200, $availabilityResponse->getStatusCode());

        return [$practitionerId, $patientId, $slotStart];
    }

    private function createPatient(string $suffix): string
    {
        $response = $this->router->dispatch(new Request('POST', '/api/patients', bodyParams: [
            'first_name' => 'Alice',
            'last_name' => 'Durand',
            'email' => $suffix . '.' . uniqid() . '@test.local',
            'phone' => '0601020304',
        ]));
        self::assertSame(201, $response->getStatusCode());

        return $response->getData()['data']->getId();
    }

    private function book(string $practitionerId, string $patientId, string $slotStart): \App\Infrastructure\Http\Response
    {
        return $this->router->dispatch(new Request('POST', '/api/appointments', bodyParams: [
            'practitioner_id' => $practitionerId,
            'patient_id' => $patientId,
            'start_time' => $slotStart . ':00',
            'duration_minutes' => 30,
            'reason' => 'Test de caractérisation',
        ]));
    }
}
