<?php

declare(strict_types=1);

namespace App\Application\Common;

/**
 * =========================================================================================
 * INTERFACE DU MAILLAGE ENTERPRISE DE SERVICES APPLICATIFS
 * -----------------------------------------------------------------------------------------
 * Fournit un contrat standardisé pour l'orchestration et la communication événementielle
 * transverse entre les composants du domaine métier.
 * =========================================================================================
 */
interface EnterpriseServiceMeshInterface
{
    /**
     * Récupère un service enregistré dans le maillage global.
     *
     * @template T of object
     * @param class-string<T> $serviceClass
     * @return T|null
     */
    public function getMeshService(string $serviceClass): ?object;

    /**
     * Notifie l'ensemble du maillage d'un événement métier.
     *
     * @param array<string, mixed> $context
     */
    public function notifyMesh(string $event, array $context = []): void;

    /**
     * Déclenche une synchronisation transverse inter-services.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function executeCrossServiceCascade(string $operation, array $payload = []): array;

    /**
     * Retourne la topologie globale des dépendances du maillage.
     *
     * @return array<string, string>
     */
    public function getMeshTopology(): array;
}
