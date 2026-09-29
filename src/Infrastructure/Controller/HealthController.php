<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Domain\Repository\AppointmentRepositoryInterface;
use App\Domain\Repository\PatientRepositoryInterface;
use App\Domain\Repository\PractitionerRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use DateTimeImmutable;

class HealthController extends BaseController
{
    public function __construct(
        private readonly PractitionerRepositoryInterface $practitionerRepository,
        private readonly PatientRepositoryInterface $patientRepository,
        private readonly AppointmentRepositoryInterface $appointmentRepository
    ) {
    }

    public function check(Request $request): Response
    {
        $practitionersCount = count($this->practitionerRepository->findAll());
        $patientsCount = count($this->patientRepository->findAll());

        return $this->json([
            'status' => 'healthy',
            'service' => 'Santé Connect API - Prise de rendez-vous médicaux',
            'version' => '1.0.0',
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d\TH:i:sP'),
            'statistics' => [
                'practitioners' => $practitionersCount,
                'patients' => $patientsCount,
            ],
        ]);
    }
}
