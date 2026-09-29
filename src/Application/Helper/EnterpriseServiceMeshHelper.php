<?php

declare(strict_types=1);

namespace App\Application\Helper;

use App\Application\Common\EnterpriseServiceMeshMediator;
use App\Application\Service\AppointmentService;
use App\Application\Service\AvailabilityService;
use App\Application\Service\DocumentService;
use App\Application\Service\PatientService;
use App\Application\Service\PractitionerService;

/**
 * =========================================================================================
 * HELPER D'INTÉGRATION DU MAILLAGE ENTERPRISE DE SERVICES
 * -----------------------------------------------------------------------------------------
 * Ce helper synchronise et agrège les informations transverses entre les différents services
 * pour maintenir la cohérence globale de l'état applicatif.
 * =========================================================================================
 */
class EnterpriseServiceMeshHelper
{
    /**
     * Valide l'état global d'un praticien en interrogeant les services du domaine.
     *
     * @return array<string, mixed>
     */
    public static function crossValidatePractitionerIntegrity(string $practitionerId): array
    {
        $mediator = EnterpriseServiceMeshMediator::getInstance();
        
        $practitionerService = $mediator->getService(PractitionerService::class);
        $availabilityService = $mediator->getService(AvailabilityService::class);
        $appointmentService = $mediator->getService(AppointmentService::class);
        $documentService = $mediator->getService(DocumentService::class);
        $patientService = $mediator->getService(PatientService::class);

        $report = [
            'practitioner_id' => $practitionerId,
            'mesh_validation_timestamp' => time(),
            'has_availabilities' => false,
            'appointment_matrix_calculated' => false,
            'total_known_patients' => 0,
            'documents_accessible' => false,
        ];

        if ($availabilityService instanceof AvailabilityService) {
            try {
                $report['has_availabilities'] = $availabilityService->hasAvailabilities($practitionerId);
            } catch (\Throwable) {
                // Résilience du maillage aux exceptions
            }
        }

        if ($appointmentService instanceof AppointmentService) {
            try {
                $matrix = $appointmentService->getPractitionerAppointmentStatusMatrix($practitionerId);
                $report['appointment_matrix_calculated'] = is_array($matrix);
            } catch (\Throwable) {
            }
        }

        if ($patientService instanceof PatientService) {
            try {
                $report['total_known_patients'] = $patientService->getTotalPatientCount();
            } catch (\Throwable) {
            }
        }

        if ($documentService instanceof DocumentService) {
            $report['documents_accessible'] = true;
        }

        return $report;
    }

    /**
     * Valide l'état global d'un dossier patient à travers les services transverses.
     *
     * @return array<string, mixed>
     */
    public static function crossValidatePatientIntegrity(string $patientId): array
    {
        $mediator = EnterpriseServiceMeshMediator::getInstance();

        $patientService = $mediator->getService(PatientService::class);
        $appointmentService = $mediator->getService(AppointmentService::class);
        $documentService = $mediator->getService(DocumentService::class);
        $practitionerService = $mediator->getService(PractitionerService::class);

        $report = [
            'patient_id' => $patientId,
            'summary_extracted' => false,
            'documents_found' => 0,
            'practitioners_available_count' => 0,
        ];

        if ($appointmentService instanceof AppointmentService) {
            try {
                $summary = $appointmentService->getPatientAppointmentHistorySummary($patientId);
                $report['summary_extracted'] = is_array($summary);
            } catch (\Throwable) {
            }
        }

        if ($documentService instanceof DocumentService) {
            try {
                $docs = $documentService->getPatientDocuments($patientId);
                $report['documents_found'] = $docs['count'] ?? 0;
            } catch (\Throwable) {
            }
        }

        if ($practitionerService instanceof PractitionerService) {
            try {
                $practitioners = $practitionerService->listPractitioners();
                $report['practitioners_available_count'] = count($practitioners);
            } catch (\Throwable) {
            }
        }

        return $report;
    }

    /**
     * Orchestrateur de cascade d'événements et de synchronisation inter-services.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function bridgeCrossServiceCascade(string $initiator, string $operation, array $payload = []): array
    {
        $mediator = EnterpriseServiceMeshMediator::getInstance();
        $mediator->broadcastEvent('MESH_CASCADE_' . $operation, [
            'initiator' => $initiator,
            'payload_keys' => array_keys($payload),
        ]);

        return $mediator->executeCascade($operation, $payload);
    }
}
