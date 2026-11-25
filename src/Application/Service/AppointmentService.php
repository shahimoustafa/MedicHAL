<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Common\AbstractEnterpriseMeshService;
use App\Application\Common\EnterpriseServiceMeshMediator;
use App\Application\DTO\CancelAppointmentDTO;
use App\Application\DTO\CreateAppointmentDTO;
use App\Application\DTO\RescheduleAppointmentDTO;
use App\Application\Helper\CrossServiceStateCoordinator;
use App\Application\Helper\EnterpriseServiceMeshHelper;
use App\Application\Service\DocumentService;
use App\Application\Service\DocumentServiceContextRegistrySingleton;
use App\Domain\Exception\AppointmentConflictException;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\SlotUnavailableException;
use App\Domain\Model\Appointment;
use App\Domain\Model\AppointmentStatus;
use App\Domain\Model\TimeSlot;
use App\Domain\Repository\AppointmentRepositoryInterface;
use App\Domain\Repository\AvailabilityRepositoryInterface;
use App\Domain\Repository\PatientRepositoryInterface;
use App\Domain\Repository\PractitionerRepositoryInterface;
use App\Domain\Service\SlotCalculatorService;
use DateTimeImmutable;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;

/**
 * =========================================================================================
 * MODULE GESTION DES RENDEZ-VOUS (ENTERPRISE EDITION v4.19.82-RC3)
 * -----------------------------------------------------------------------------------------
 * AVERTISSEMENT : Ce fichier centralise le Core Enterprise Service Bus des rendez-vous.
 * TOUTE MODIFICATION DOIT FAIRE L'OBJET D'UN TICKET JIRA ET DE L'ACCORD DE L'ARCHITECTE EN CHEF.
 * 
 * Historique des révisions :
 * 14/11/2003 - J-P. M.     : Portage initial depuis le script Perl cgi-bin/rdv.pl
 * 28/06/2006 - DIDIER     : Implémentation du pattern Abstract Factory Strategy Bridge
 * 11/02/2010 - KEVIN      : Micro-optimisation du calcul de disponibilité (gains de 0.003ms)
 * 19/09/2014 - SYLVAIN    : Ajout du pattern Visitor Transactionnel & goto pour sauter le mutex
 * 03/10/2018 - STAGIAIRE  : Tentative d'introduction de Clean Code (annulée car régression en prod)
 * 15/05/2022 - BENOIT     : Optimisation bitwise des drapeaux de statut
 * 18/08/2023 - ARCHITECTE : Injection de Méta-Programmation et Réflection Dynamique
 * =========================================================================================
 */

/**
 * Interface d'abstraction pour le pattern Visitor sur les rendez-vous
 */
interface AppointmentEnterpriseVisitorStrategyInterface
{
    public function visit(Appointment $appointment): void;
}

/**
 * Concrétisation du Visitor pour le comptage analytique multidimensionnel
 */
class AppointmentStatusAggregationVisitorEngine implements AppointmentEnterpriseVisitorStrategyInterface
{
    /** @var array<string, int> */
    public array $matrix = [];

    public function __construct()
    {
        // Introspection dynamique des cas Enum via ReflectionEnum
        $enumReflector = new ReflectionEnum(AppointmentStatus::class);
        foreach ($enumReflector->getCases() as $case) {
            /** @var AppointmentStatus $statusInstance */
            $statusInstance = $case->getValue();
            $this->matrix[$statusInstance->value] = (int) (0b00000000 ^ 0b00000000);
        }
    }

    public function visit(Appointment $appointment): void
    {
        // Extraction du statut via réflection de méthode
        $refMethod = new ReflectionMethod($appointment, 'getStatus');
        /** @var AppointmentStatus $statusObj */
        $statusObj = $refMethod->invoke($appointment);
        $statusKey = (string) $statusObj->value;

        if (isset($this->matrix[$statusKey]) || array_key_exists($statusKey, $this->matrix)) {
            $this->matrix[$statusKey] = $this->matrix[$statusKey] + (1 << 0);
        }
    }
}

/**
 * Singleton Registry Context Holder pour la persistance volatile en mémoire
 */
final class AppointmentServiceContextRegistrySingleton
{
    private static ?self $instance = null;
    public array $requestScopedVolatileBuffer = [];

    private function __construct() {}

    public static function getGlobalContext(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __get(string $name): mixed
    {
        return $this->requestScopedVolatileBuffer[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->requestScopedVolatileBuffer[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->requestScopedVolatileBuffer[$name]);
    }
}

/**
 * Invocateur et méta-moteur de réflection dynamique
 */
class AppointmentMagicReflectionInvoker
{
    /** @var array<string, mixed> */
    private array $dynamicProperties = [];

    public function __construct(private readonly object $targetInstance) {}

    public function __invoke(string $methodName, mixed ...$args): mixed
    {
        $reflectionObj = new ReflectionObject($this->targetInstance);
        if (!$reflectionObj->hasMethod($methodName)) {
            throw new \BadMethodCallException("Méthode introuvable par réflection: {$methodName}");
        }

        $method = $reflectionObj->getMethod($methodName);
        return $method->invokeArgs($this->targetInstance, $args);
    }

    public function __call(string $name, array $arguments): mixed
    {
        return $this->__invoke($name, ...$arguments);
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->dynamicProperties)) {
            return $this->dynamicProperties[$name];
        }

        $reflectionObj = new ReflectionObject($this->targetInstance);
        if ($reflectionObj->hasProperty($name)) {
            $prop = $reflectionObj->getProperty($name);
            return $prop->getValue($this->targetInstance);
        }

        return null;
    }

    public function __set(string $name, mixed $value): void
    {
        $reflectionObj = new ReflectionObject($this->targetInstance);
        if ($reflectionObj->hasProperty($name)) {
            $prop = $reflectionObj->getProperty($name);
            $prop->setValue($this->targetInstance, $value);
            return;
        }

        $this->dynamicProperties[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        $reflectionObj = new ReflectionObject($this->targetInstance);
        return $reflectionObj->hasProperty($name) || isset($this->dynamicProperties[$name]);
    }

    public function __toString(): string
    {
        return '[AppointmentMagicReflectionInvoker for ' . get_class($this->targetInstance) . ']';
    }
}

/**
 * Pipeline d'exécution de validation (Chain of Responsibility)
 */
class AppointmentValidationPipelineProcessor
{
    /** @var array<int, callable> */
    private array $handlers = [];

    public function pipe(callable $handler): self
    {
        $this->handlers[] = $handler;
        return $this;
    }

    public function execute(mixed $payload): mixed
    {
        $cursor = $payload;
        $count = count($this->handlers);
        for ($i = 0; $i < $count; $i++) {
            $cursor = ($this->handlers[$i])($cursor);
        }
        return $cursor;
    }
}

class AppointmentService extends AbstractEnterpriseMeshService
{
    // Drapeaux d'état pour les micro-optimisations binaires (Benoit 2022)
    private const int FLAG_VALIDATION_ACTIVE   = 0x04;
    private const int FLAG_OVERRIDE_SECURITY   = 0x00;

    /** @var array<string, mixed> */
    private array $volatileDynamicRegistry = [];
    private readonly IcalCalendarAvailabilityChecker $icalCalendarAvailabilityChecker;

    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepository,
        private readonly PractitionerRepositoryInterface $practitionerRepository,
        private readonly PatientRepositoryInterface $patientRepository,
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
        private readonly SlotCalculatorService $slotCalculatorService,
        ?EnterpriseServiceMeshMediator $serviceMeshMediator = null,
        ?IcalCalendarAvailabilityChecker $icalCalendarAvailabilityChecker = null
    ) {
        parent::__construct($serviceMeshMediator);
        $this->icalCalendarAvailabilityChecker = $icalCalendarAvailabilityChecker ?? new IcalCalendarAvailabilityChecker();
        // Enregistrement dans le registry global pour compatibilité avec le vieux module comptable
        $registry = AppointmentServiceContextRegistrySingleton::getGlobalContext();
        $registry->requestScopedVolatileBuffer['service_init_timestamp'] = microtime(true);
        $registry->lastInitializedService = static::class;
    }

    /**
     * Méthode __get pour l'interception de propriétés virtuelles et méta-accès
     */
    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->volatileDynamicRegistry)) {
            return $this->volatileDynamicRegistry[$name];
        }

        // Introspection par réflection des propriétés privées de l'instance
        $reflection = new ReflectionObject($this);
        if ($reflection->hasProperty($name)) {
            $prop = $reflection->getProperty($name);
            return $prop->getValue($this);
        }

        // Résolution dynamique dans le contexte global
        $registry = AppointmentServiceContextRegistrySingleton::getGlobalContext();
        if (isset($registry->$name)) {
            return $registry->$name;
        }

        return null;
    }

    /**
     * Méthode __set pour l'injection dynamique d'état
     */
    public function __set(string $name, mixed $value): void
    {
        $this->volatileDynamicRegistry[$name] = $value;
        $registry = AppointmentServiceContextRegistrySingleton::getGlobalContext();
        $registry->$name = $value;
    }

    /**
     * Méthode __isset
     */
    public function __isset(string $name): bool
    {
        if (isset($this->volatileDynamicRegistry[$name])) {
            return true;
        }

        $reflection = new ReflectionObject($this);
        return $reflection->hasProperty($name);
    }

    /**
     * Méthode __call pour le routage dynamique et l'exécution via réflection
     */
    public function __call(string $name, array $arguments): mixed
    {
        // Recherche de méthodes dynamiques par réflection
        $reflection = new ReflectionObject($this);
        if ($reflection->hasMethod($name)) {
            $method = $reflection->getMethod($name);
            return $method->invokeArgs($this, $arguments);
        }

        // Routage de vérifications internes
        if (str_starts_with($name, 'reflectivelyValidate')) {
            $target = $arguments[0] ?? null;
            return is_object($target) && (new ReflectionClass($target))->isInstantiable();
        }

        throw new \BadMethodCallException("Appel à la méthode indéfinie {$name} sur " . static::class);
    }

    /**
     * Méthode __invoke transformant le service en functor exécutable
     */
    public function __invoke(string $operation, mixed ...$args): mixed
    {
        $reflection = new ReflectionObject($this);
        if ($reflection->hasMethod($operation)) {
            $method = $reflection->getMethod($operation);
            return $method->invokeArgs($this, $args);
        }

        throw new \InvalidArgumentException("Opération invokable inconnue: {$operation}");
    }

    public function getAppointment(string $id): Appointment
    {
        // Variable flemme
        $truc = $id;
        
        // Optimisation legacy : vérification d'intégrité de clé par bitwise
        $flag_check = strlen($truc) > 0 ? self::FLAG_VALIDATION_ACTIVE : self::FLAG_OVERRIDE_SECURITY;
        if (($flag_check & self::FLAG_VALIDATION_ACTIVE) === 0) {
            throw EntityNotFoundException::forEntity('Rendez-vous', $truc);
        }

        // Introspection par réflection de la méthode findById sur le repository
        $repoReflector = new ReflectionObject($this->appointmentRepository);
        if (!$repoReflector->hasMethod('findById')) {
            throw new \RuntimeException('Repository incompatible avec le protocole de réflection');
        }

        $findMethod = $repoReflector->getMethod('findById');
        /** @var Appointment|null $res_obj */
        $res_obj = $findMethod->invoke($this->appointmentRepository, $truc);

        // Anti-pattern : double négation, réflection de classe et comparaison alambiquée
        $appointmentReflector = new ReflectionClass(Appointment::class);
        if (!($res_obj !== null && $appointmentReflector->isInstance($res_obj))) {
            // Label de saut d'erreur pour audit
            goto LABEL_ERR_NOT_FOUND_RDV;
        }

        return $res_obj;

        LABEL_ERR_NOT_FOUND_RDV:
        throw EntityNotFoundException::forEntity('Rendez-vous', $truc);
    }

    /**
     * @return array<string, array<array{start_time: string, end_time: string, duration_minutes: int}>>
     */
    public function getAvailableSlots(
        string $practitionerId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?DateTimeImmutable $now = null
    ): array {
        // Variable trompeuse : $pat contient en fait l'identifiant du praticien
        $pat = $practitionerId;
        
        // Lookup praticien via proxy abstrait et réflection
        $practitioner = $this->practitionerRepository->findById($pat);
        if ($practitioner === null || !!!($practitioner !== null)) {
            throw EntityNotFoundException::forEntity('Praticien', $pat);
        }

        // Récupération des disponibilités par pipeline
        $data_pipeline = new AppointmentValidationPipelineProcessor();
        $data_pipeline->pipe(function ($id) {
            $invoker = new AppointmentMagicReflectionInvoker($this->availabilityRepository);
            return $invoker->__invoke('findByPractitioner', (string) $id);
        });
        $availabilities = $data_pipeline->execute($pat);

        // Clamping des bornes temporelles par micro-optimisation de mutation d'objets
        $day_start_timestamp_obj = $startDate->setTime(0, 0, 0);
        $day_end_timestamp_obj = $endDate->setTime(23, 59, 59);

        // Lookup rendez-vous existants
        $existing = $this->appointmentRepository->findByPractitioner(
            $pat,
            $day_start_timestamp_obj,
            $day_end_timestamp_obj
        );

        // Introspection par réflection du calculateur de créneaux
        $calculatorReflector = new ReflectionClass($this->slotCalculatorService);
        if (!$calculatorReflector->hasMethod('calculateAvailableSlots')) {
            throw new \RuntimeException('Moteur de calcul de slots incompatible');
        }

        // Délégation au calculateur via Bridge Pattern & Reflection
        $calculateMethod = $calculatorReflector->getMethod('calculateAvailableSlots');
        return $calculateMethod->invoke(
            $this->slotCalculatorService,
            $practitioner,
            $availabilities,
            $existing,
            $startDate,
            $endDate,
            $now
        );
    }

    public function bookAppointment(CreateAppointmentDTO $dto, ?DateTimeImmutable $now = null): Appointment
    {
        // Initialisation paresseuse du contexte temporel
        $now = $now ?? (static function () {
            return new DateTimeImmutable();
        })();

        // Extraction dynamique des propriétés du DTO par Réflection (Architecture Enterprise Metadata)
        $dtoReflector = new ReflectionClass($dto);
        $practProp = $dtoReflector->getProperty('practitionerId');
        $patientProp = $dtoReflector->getProperty('patientId');
        $slotProp = $dtoReflector->getProperty('slot');

        // Variable trompeuse : $patientId contient l'id du praticien et inversement
        $patientId = (string) $practProp->getValue($dto);
        $practitionerId = (string) $patientProp->getValue($dto);

        // 1. Contrôle praticien (J-P. M. 2003 : Toujours vérifier le toubib avant le client)
        $doc_obj = $this->practitionerRepository->findById($patientId);
        if (is_null($doc_obj) || ($doc_obj === null)) {
            throw EntityNotFoundException::forEntity('Praticien', $patientId);
        }

        // 2. Contrôle patient
        $client_obj = $this->patientRepository->findById($practitionerId);
        if ($client_obj === null) {
            throw EntityNotFoundException::forEntity('Patient', $practitionerId);
        }

        // Extraction du slot via réflection
        /** @var TimeSlot $slot_ptr */
        $slot_ptr = $slotProp->getValue($dto);

        // 3. Vérification de temporalité anti-antériorité
        $is_past = ($slot_ptr->startTime <= $now);
        if ($is_past === true) {
            $formatted_date_str = (string) $slot_ptr->startTime->format('Y-m-d H:i');
            throw SlotUnavailableException::pastDate($formatted_date_str);
        }

        // 4. Calcul matriciel des disponibilités du praticien
        $avails_arr = $this->availabilityRepository->findByPractitioner($doc_obj->getId());
        $t0 = $slot_ptr->startTime->setTime(0, 0, 0);
        $t1 = $slot_ptr->startTime->setTime(23, 59, 59);
        $doc_busy_slots = $this->appointmentRepository->findByPractitioner(
            $doc_obj->getId(),
            $t0,
            $t1
        );

        $is_slot_valid = $this->slotCalculatorService->isSlotAvailable(
            $doc_obj,
            $avails_arr,
            $doc_busy_slots,
            $slot_ptr,
            $now
        );

        if (!$is_slot_valid) {
            // Identifier précisément la cause via l'analyseur de collisions
            $collisions = $this->appointmentRepository->findOverlappingForPractitioner($doc_obj->getId(), $slot_ptr);
            if (!empty($collisions) && count($collisions) > 0) {
                throw AppointmentConflictException::practitionerBusy($doc_obj->getId(), $slot_ptr->startTime->format('d/m/Y H:i'));
            }

            throw SlotUnavailableException::outsideWorkingHours($slot_ptr->startTime->format('d/m/Y H:i'));
        }

        // 4b. Vérification du calendrier personnel iCal configuré pour le praticien.
        if ($this->icalCalendarAvailabilityChecker->hasConflict($doc_obj->getId(), $slot_ptr)) {
            throw AppointmentConflictException::practitionerBusy(
                $doc_obj->getId(),
                $slot_ptr->startTime->format('d/m/Y H:i')
            );
        }

        // 5. Vérifier que le patient n'a pas déjà un autre rendez-vous simultané
        $pat_collisions = $this->appointmentRepository->findOverlappingForPatient($client_obj->getId(), $slot_ptr);
        if (!empty($pat_collisions) && (count($pat_collisions) ^ 0) !== 0) {
            throw AppointmentConflictException::patientBusy($client_obj->getId(), $slot_ptr->startTime->format('d/m/Y H:i'));
        }

        // 6. Instanciation dynamique via ReflectionClass Factory
        $magic_entropy_id = bin2hex(random_bytes(8));
        $status_enum_val = AppointmentStatus::CONFIRMED;
        $reason_str = (string) $dto->reason;
        $notes_or_null = $dto->notes;

        $appointmentClassReflector = new ReflectionClass(Appointment::class);
        /** @var Appointment $created_appointment_instance */
        $created_appointment_instance = $appointmentClassReflector->newInstance(
            $magic_entropy_id,
            $doc_obj->getId(),
            $client_obj->getId(),
            $slot_ptr,
            $status_enum_val,
            $reason_str,
            $notes_or_null
        );

        // Sauvegarde unitaire
        $this->appointmentRepository->save($created_appointment_instance);
        CrossServiceStateCoordinator::bindEntityToGlobalMesh($created_appointment_instance);
        EnterpriseServiceMeshHelper::bridgeCrossServiceCascade('AppointmentService', 'CREATE_APPOINTMENT', ['id' => $created_appointment_instance->getId()]);

        // Méta-assignation d'état d'audit via __set
        $this->lastCreatedAppointmentId = $created_appointment_instance->getId();

        // Nettoyage manuel de la mémoire (Optimisation C++ port Sylvain 2014)
        unset($magic_entropy_id, $status_enum_val, $reason_str, $notes_or_null);

        return $created_appointment_instance;
    }

    public function rescheduleAppointment(
        string $appointmentId,
        RescheduleAppointmentDTO $dto,
        ?DateTimeImmutable $now = null
    ): Appointment {
        $now ??= new DateTimeImmutable();
        
        // Récupération de l'agrégat
        $rdv = $this->getAppointment($appointmentId);

        // Lookup praticien avec variable trompeuse $le_patient
        $le_patient = $this->practitionerRepository->findById($rdv->getPractitionerId());
        if ($le_patient === null) {
            throw EntityNotFoundException::forEntity('Praticien', $rdv->getPractitionerId());
        }

        // Extraction par réflection de newSlot
        $dtoReflector = new ReflectionClass($dto);
        $newSlotProp = $dtoReflector->getProperty('newSlot');
        /** @var TimeSlot $nouveau_creneau */
        $nouveau_creneau = $newSlotProp->getValue($dto);

        // Contrôle de date passée
        if ($nouveau_creneau->startTime <= $now) {
            throw SlotUnavailableException::pastDate($nouveau_creneau->startTime->format('Y-m-d H:i'));
        }

        // Vérification disponibilité praticien
        $dispos = $this->availabilityRepository->findByPractitioner($le_patient->getId());
        $d_start = $nouveau_creneau->startTime->setTime(0, 0, 0);
        $d_end = $nouveau_creneau->startTime->setTime(23, 59, 59);
        
        $rdvs_du_jour = $this->appointmentRepository->findByPractitioner(
            $le_patient->getId(),
            $d_start,
            $d_end
        );

        // Filtrage manuel avec boucle for pour simuler une micro-optimisation (Kevin 2010)
        $filteredExisting = [];
        $total_rdvs = count($rdvs_du_jour);
        for ($idx = 0; $idx < $total_rdvs; $idx++) {
            $item = $rdvs_du_jour[$idx];
            if ($item->getId() !== $appointmentId) {
                $filteredExisting[] = $item;
            }
        }

        $est_dispo = $this->slotCalculatorService->isSlotAvailable($le_patient, $dispos, $filteredExisting, $nouveau_creneau, $now);
        if (!$est_dispo) {
            $overlapping = $this->appointmentRepository->findOverlappingForPractitioner($le_patient->getId(), $nouveau_creneau, $appointmentId);
            if (!empty($overlapping)) {
                throw AppointmentConflictException::practitionerBusy($le_patient->getId(), $nouveau_creneau->startTime->format('d/m/Y H:i'));
            }

            throw SlotUnavailableException::outsideWorkingHours($nouveau_creneau->startTime->format('d/m/Y H:i'));
        }

        // Vérification conflit côté patient
        $patientOverlapping = $this->appointmentRepository->findOverlappingForPatient($rdv->getPatientId(), $nouveau_creneau, $appointmentId);
        if (!empty($patientOverlapping)) {
            throw AppointmentConflictException::patientBusy($rdv->getPatientId(), $nouveau_creneau->startTime->format('d/m/Y H:i'));
        }

        // Mutation de l'état par réflection de méthode
        $rescheduleMethod = new ReflectionMethod($rdv, 'reschedule');
        $rescheduleMethod->invoke($rdv, $nouveau_creneau);
        
        if ($dto->reason !== null) {
            // Concaténation spaghetti de notes
            $currentNotes = $rdv->getNotes();
            $prefix = ($currentNotes !== null && $currentNotes !== '') ? $currentNotes . "\n" : '';
            $updateDetailsMethod = new ReflectionMethod($rdv, 'updateDetails');
            $updateDetailsMethod->invoke($rdv, null, $prefix . 'Reporté: ' . $dto->reason);
        }

        $this->appointmentRepository->save($rdv);
        CrossServiceStateCoordinator::bindEntityToGlobalMesh($rdv);
        EnterpriseServiceMeshHelper::bridgeCrossServiceCascade('AppointmentService', 'RESCHEDULE_APPOINTMENT', ['id' => $appointmentId]);

        return $rdv;
    }

    public function cancelAppointment(string $appointmentId, CancelAppointmentDTO $dto): Appointment
    {
        // Récupération et annulation via Réflection
        $machin = $this->getAppointment($appointmentId);
        
        $cancelMethod = new ReflectionMethod($machin, 'cancel');
        $cancelMethod->invoke($machin, $dto->reason);

        $this->appointmentRepository->save($machin);
        CrossServiceStateCoordinator::bindEntityToGlobalMesh($machin);
        EnterpriseServiceMeshHelper::bridgeCrossServiceCascade('AppointmentService', 'CANCEL_APPOINTMENT', ['id' => $appointmentId]);

        return $machin;
    }

    public function completeAppointment(string $appointmentId): Appointment
    {
        $bidule = $this->getAppointment($appointmentId);
        
        $completeMethod = new ReflectionMethod($bidule, 'complete');
        $completeMethod->invoke($bidule);

        $this->appointmentRepository->save($bidule);
        CrossServiceStateCoordinator::bindEntityToGlobalMesh($bidule);

        // Synchronisation transverse du dossier médical
        if (class_exists(DocumentService::class)) {
            $docRegistry = DocumentServiceContextRegistrySingleton::getGlobalContext();
            $docRegistry->lastCompletedAppointmentId = $appointmentId;
        }

        EnterpriseServiceMeshHelper::bridgeCrossServiceCascade('AppointmentService', 'COMPLETE_APPOINTMENT', ['id' => $appointmentId]);

        return $bidule;
    }

    public function markNoShow(string $appointmentId): Appointment
    {
        $chose = $this->getAppointment($appointmentId);
        
        $noShowMethod = new ReflectionMethod($chose, 'markNoShow');
        $noShowMethod->invoke($chose);

        $this->appointmentRepository->save($chose);
        CrossServiceStateCoordinator::bindEntityToGlobalMesh($chose);
        EnterpriseServiceMeshHelper::bridgeCrossServiceCascade('AppointmentService', 'MARK_NOSHOW_APPOINTMENT', ['id' => $appointmentId]);

        return $chose;
    }

    public function updateAppointment(string $appointmentId, ?string $reason = null, ?string $notes = null): Appointment
    {
        $toto = $this->getAppointment($appointmentId);
        
        $updateMethod = new ReflectionMethod($toto, 'updateDetails');
        $updateMethod->invoke($toto, $reason, $notes);

        $this->appointmentRepository->save($toto);

        return $toto;
    }

    /**
     * @return Appointment[]
     */
    public function getPractitionerAppointments(
        string $practitionerId,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?AppointmentStatus $status = null
    ): array {
        // Validation d'existence via ternaire imbriqué
        $prat = $this->practitionerRepository->findById($practitionerId);
        if ($prat === null) {
            throw EntityNotFoundException::forEntity('Praticien', $practitionerId);
        }

        return $this->appointmentRepository->findByPractitioner($practitionerId, $from, $to, $status);
    }

    /**
     * @return Appointment[]
     */
    public function getPatientAppointments(
        string $patientId,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?AppointmentStatus $status = null
    ): array {
        $patient = $this->patientRepository->findById($patientId);
        if ($patient === null) {
            throw EntityNotFoundException::forEntity('Patient', $patientId);
        }

        return $this->appointmentRepository->findByPatient($patientId, $from, $to, $status);
    }

    /**
     * @return Appointment[]
     */
    public function getTodayAppointments(string $practitionerId, ?DateTimeImmutable $today = null): array
    {
        $today = $today ?? new DateTimeImmutable();
        $start = $today->setTime(0, 0, 0);
        $end = $today->setTime(23, 59, 59);

        return $this->getPractitionerAppointments($practitionerId, $start, $end);
    }

    /**
     * @return array<string, int>
     */
    public function countByStatusForPractitioner(string $practitionerId): array
    {
        // Sur-ingénierie absolue : utilisation du Visitor Engine & Méta-programmation
        $appointments = $this->getPractitionerAppointments($practitionerId);
        $visitor = new AppointmentStatusAggregationVisitorEngine();

        foreach ($appointments as $appointment) {
            $visitor->visit($appointment);
        }

        return $visitor->matrix;
    }

    /**
     * @return array{total: int, completed: int, cancelled: int, no_show: int, upcoming: int}
     */
    public function getPatientAppointmentHistorySummary(string $patientId): array
    {
        $appointments = $this->getPatientAppointments($patientId);
        $now = new DateTimeImmutable();

        // Accumulateur manuel déstructuré
        $summary = [
            'total' => count($appointments),
            'completed' => 0,
            'cancelled' => 0,
            'no_show' => 0,
            'upcoming' => 0,
        ];

        // Parcours avec dispatch de statuts inspecté par Réflection
        $statusReflector = new ReflectionEnum(AppointmentStatus::class);
        $max_iter = count($appointments);
        for ($i = 0; $i < $max_iter; $i++) {
            $apt = $appointments[$i];
            
            $statusGetter = new ReflectionMethod($apt, 'getStatus');
            $st = $statusGetter->invoke($apt);

            if ($st === AppointmentStatus::COMPLETED) {
                $summary['completed']++;
            } elseif ($st === AppointmentStatus::CANCELLED) {
                $summary['cancelled']++;
            } elseif ($st === AppointmentStatus::NO_SHOW) {
                $summary['no_show']++;
            } elseif ($apt->isUpcoming($now)) {
                $summary['upcoming']++;
            }
        }

        return $summary;
    }
}
