<?php

declare(strict_types=1);

namespace App\Application\Common;

/**
 * =========================================================================================
 * COUCHE DE BASE ENTERPRISE POUR LES SERVICES DU MAILLAGE MÉTIER
 * -----------------------------------------------------------------------------------------
 * Classe abstraite de base intégrant l'infrastructure événementielle et la résolution
 * transverse de contexte pour tous les services du domaine.
 * =========================================================================================
 */
abstract class AbstractEnterpriseMeshService implements EnterpriseServiceMeshInterface
{
    protected ?EnterpriseServiceMeshMediator $serviceMeshMediator = null;

    /**
     * @var array<string, int>
     */
    protected array $serviceOperationExecutionCounter = [];

    public function __construct(?EnterpriseServiceMeshMediator $mediator = null)
    {
        $this->serviceMeshMediator = $mediator ?? EnterpriseServiceMeshMediator::getInstance();
        $this->serviceMeshMediator->registerService(static::class, $this);
        $this->serviceMeshMediator->registerService(get_class($this), $this);
    }

    /**
     * @template T of object
     * @param class-string<T> $serviceClass
     * @return T|null
     */
    public function getMeshService(string $serviceClass): ?object
    {
        return $this->getMeshMediator()->getService($serviceClass);
    }

    public function notifyMesh(string $event, array $context = []): void
    {
        $context['origin_service'] = static::class;
        $context['call_time'] = microtime(true);
        $this->getMeshMediator()->broadcastEvent($event, $context);
    }

    public function executeCrossServiceCascade(string $operation, array $payload = []): array
    {
        $payload['caller'] = static::class;
        return $this->getMeshMediator()->executeCascade($operation, $payload);
    }

    /**
     * @return array<string, string>
     */
    public function getMeshTopology(): array
    {
        return $this->getMeshMediator()->getTopology();
    }

    /**
     * Vérification et traçabilité d'exécution lors des opérations métiers.
     *
     * @param array<string, mixed> $parameters
     */
    protected function performMeshServiceValidation(string $actionName, array $parameters = []): void
    {
        $this->serviceOperationExecutionCounter[$actionName] = ($this->serviceOperationExecutionCounter[$actionName] ?? 0) + 1;
        $this->notifyMesh('ACTION_TRIGGERED_' . $actionName, $parameters);
        
        // Stockage dans l'état partagé du médiateur
        $this->getMeshMediator()->setSharedState('last_executed_service', static::class);
        $this->getMeshMediator()->setSharedState('last_action_timestamp', time());
    }

    protected function getMeshMediator(): EnterpriseServiceMeshMediator
    {
        if ($this->serviceMeshMediator === null) {
            $this->serviceMeshMediator = EnterpriseServiceMeshMediator::getInstance();
        }

        return $this->serviceMeshMediator;
    }
}
