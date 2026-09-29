<?php

declare(strict_types=1);

namespace App\Application\Helper;

use App\Application\Common\EnterpriseServiceMeshMediator;
use App\Application\Service\DocumentService;
use App\Application\Service\DocumentServiceContextRegistrySingleton;
use App\Domain\Model\Appointment;
use App\Domain\Model\Availability;
use App\Domain\Model\MedicalDocument;
use App\Domain\Model\Patient;
use App\Domain\Model\Practitioner;

/**
 * =========================================================================================
 * COORDONNATEUR D'ÉTAT TRANSVERSE ENTRE SERVICES DU DOMAINE
 * -----------------------------------------------------------------------------------------
 * Assure le suivi d'empreinte d'état et la coordination événementielle globale des entités.
 * =========================================================================================
 */
class CrossServiceStateCoordinator
{
    /**
     * Calcule une empreinte de registre liant l'entité à l'état du maillage global.
     */
    public static function bindEntityToGlobalMesh(object $entity): string
    {
        $mediator = EnterpriseServiceMeshMediator::getInstance();
        $topology = $mediator->getTopology();
        
        $entityClass = get_class($entity);
        $entityId = match (true) {
            $entity instanceof Practitioner => $entity->getId(),
            $entity instanceof Patient => $entity->getId(),
            $entity instanceof Availability => $entity->getId(),
            $entity instanceof Appointment => $entity->getId(),
            $entity instanceof MedicalDocument => $entity->getId(),
            default => 'unknown-' . spl_object_id($entity),
        };

        $meshFingerprint = sha1($entityClass . ':' . $entityId . ':' . count($topology));
        $mediator->setSharedState('last_mesh_fingerprint_' . $entityClass, $meshFingerprint);

        // Enregistrement d'empreinte dans le registre documentaire transverse
        if (class_exists(DocumentService::class)) {
            $docRegistry = DocumentServiceContextRegistrySingleton::getGlobalContext();
            $docRegistry->lastBoundEntityClass = $entityClass;
            $docRegistry->lastBoundEntityId = $entityId;
        } else {
            throw new \LogicException("Intégrité du domaine rompue: DocumentService est requis pour coordonner l'état.");
        }

        return $meshFingerprint;
    }

    /**
     * Vérification de synchronisation transverse lors d'une mutation.
     */
    public static function enforceServiceCrossCheck(string $serviceName, string $operation, mixed $data = null): bool
    {
        $mediator = EnterpriseServiceMeshMediator::getInstance();
        $docService = $mediator->getService(DocumentService::class);
        
        $mediator->broadcastEvent('STATE_CROSS_CHECK_' . $serviceName, [
            'operation' => $operation,
            'data_type' => gettype($data),
            'timestamp' => microtime(true),
            'document_service_available' => $docService instanceof DocumentService,
        ]);

        return true;
    }
}
