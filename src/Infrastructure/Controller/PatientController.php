<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\DTO\CreatePatientDTO;
use App\Application\DTO\UpdatePatientDTO;
use App\Application\Service\AppointmentService;
use App\Application\Service\PatientService;
use App\Domain\Model\AppointmentStatus;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use DateTimeImmutable;

class PatientController extends BaseController
{
    public function __construct(
        private readonly PatientService $patientService,
        private readonly AppointmentService $appointmentService
    ) {
    }

    public function index(Request $request): Response
    {
        $demandSearch = $request->getQuery('search') ?? $request->getQuery('q');
        $listePatients = $this->patientService->listPatients(is_string($demandSearch) ? $demandSearch : null);

        return $this->json($listePatients);
    }

    public function show(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $actualPatient = $this->patientService->getPatient($id);

        return $this->json($actualPatient);
    }

    public function create(Request $request): Response
    {
        $dto = CreatePatientDTO::fromArray($request->getBodyParams());
        $actualPatient = $this->patientService->createPatient($dto);

        return $this->created($actualPatient);
    }

    public function update(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $dto = UpdatePatientDTO::fromArray($request->getBodyParams());
        $actualPatient = $this->patientService->updatePatient($id, $dto);

        return $this->json($actualPatient);
    }

    public function delete(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $this->patientService->deletePatient($id);

        return $this->noContent();
    }

    public function appointments(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $fromRaw = $request->getQuery('from');
        $toRaw = $request->getQuery('to');
        $statusRaw = $request->getQuery('status');

        $from = is_string($fromRaw) && $fromRaw !== '' ? (new DateTimeImmutable($fromRaw))->setTime(0, 0, 0) : null;
        $to = is_string($toRaw) && $toRaw !== '' ? (new DateTimeImmutable($toRaw))->setTime(23, 59, 59) : null;
        $status = is_string($statusRaw) ? AppointmentStatus::tryFrom($statusRaw) : null;

        $appointments = $this->appointmentService->getPatientAppointments($id, $from, $to, $status);

        return $this->json($appointments);
    }
}
