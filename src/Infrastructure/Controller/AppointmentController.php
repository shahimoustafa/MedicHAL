<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\DTO\CancelAppointmentDTO;
use App\Application\DTO\CreateAppointmentDTO;
use App\Application\DTO\RescheduleAppointmentDTO;
use App\Application\Service\AppointmentService;
use App\Domain\Model\AppointmentStatus;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use DateTimeImmutable;

class AppointmentController extends BaseController
{
    public function __construct(
        private readonly AppointmentService $appointmentService
    ) {
    }

    /**
     * Recherche et calcul en temps réel des créneaux de réservation disponibles.
     */
    public function slots(Request $request): Response
    {
        $practitionerId = (string) $request->getRouteParam('id');

        $singleDate = $request->getQuery('date');
        $startDateRaw = $request->getQuery('start_date');
        $endDateRaw = $request->getQuery('end_date');

        if (is_string($singleDate) && $singleDate !== '') {
            $startDate = new DateTimeImmutable($singleDate);
            $endDate = $startDate;
        } else {
            $startDate = is_string($startDateRaw) && $startDateRaw !== ''
                ? new DateTimeImmutable($startDateRaw)
                : new DateTimeImmutable('today');

            $endDate = is_string($endDateRaw) && $endDateRaw !== ''
                ? new DateTimeImmutable($endDateRaw)
                : $startDate->modify('+6 days'); // 7 jours par défaut
        }

        $calculatedSlots = $this->appointmentService->getAvailableSlots($practitionerId, $startDate, $endDate);

        return $this->json([
            'practitioner_id' => $practitionerId,
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'slots' => $calculatedSlots,
        ]);
    }

    public function practitionerAppointments(Request $request): Response
    {
        $practitionerId = (string) $request->getRouteParam('id');
        $fromRaw = $request->getQuery('from');
        $toRaw = $request->getQuery('to');
        $statusRaw = $request->getQuery('status');

        $from = is_string($fromRaw) && $fromRaw !== '' ? (new DateTimeImmutable($fromRaw))->setTime(0, 0, 0) : null;
        $to = is_string($toRaw) && $toRaw !== '' ? (new DateTimeImmutable($toRaw))->setTime(23, 59, 59) : null;
        $status = is_string($statusRaw) && $statusRaw !== '' ? AppointmentStatus::tryFrom($statusRaw) : null;

        $listeAppointments = $this->appointmentService->getPractitionerAppointments(
            practitionerId: $practitionerId,
            from: $from,
            to: $to,
            status: $status
        );

        return $this->json($listeAppointments);
    }

    public function show(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $actualAppointment = $this->appointmentService->getAppointment($id);

        return $this->json($actualAppointment);
    }

    public function create(Request $request): Response
    {
        $dto = CreateAppointmentDTO::fromArray($request->getBodyParams());
        $actualAppointment = $this->appointmentService->bookAppointment($dto);

        return $this->created($actualAppointment);
    }

    public function reschedule(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $dto = RescheduleAppointmentDTO::fromArray($request->getBodyParams());
        $actualAppointment = $this->appointmentService->rescheduleAppointment($id, $dto);

        return $this->json($actualAppointment);
    }

    public function cancel(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $dto = CancelAppointmentDTO::fromArray($request->getBodyParams());
        $actualAppointment = $this->appointmentService->cancelAppointment($id, $dto);

        return $this->json($actualAppointment);
    }

    public function update(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $reason = $request->getBody('reason');
        $notes = $request->getBody('notes');

        $actualAppointment = $this->appointmentService->updateAppointment(
            appointmentId: $id,
            reason: is_string($reason) ? $reason : null,
            notes: is_string($notes) ? $notes : null
        );

        return $this->json($actualAppointment);
    }

    public function complete(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $actualAppointment = $this->appointmentService->completeAppointment($id);

        return $this->json($actualAppointment);
    }

    public function markNoShow(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $actualAppointment = $this->appointmentService->markNoShow($id);

        return $this->json($actualAppointment);
    }
}
