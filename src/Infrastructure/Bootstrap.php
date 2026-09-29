<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\Common\EnterpriseServiceMeshMediator;
use App\Application\Service\AppointmentService;
use App\Application\Service\AvailabilityService;
use App\Application\Service\DocumentService;
use App\Application\Service\PatientService;
use App\Application\Service\PractitionerService;
use App\Domain\Repository\AppointmentRepositoryInterface;
use App\Domain\Repository\AvailabilityRepositoryInterface;
use App\Domain\Repository\DocumentRepositoryInterface;
use App\Domain\Repository\PatientRepositoryInterface;
use App\Domain\Repository\PractitionerRepositoryInterface;
use App\Domain\Service\SlotCalculatorService;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Controller\AppointmentController;
use App\Infrastructure\Controller\AvailabilityController;
use App\Infrastructure\Controller\DocumentController;
use App\Infrastructure\Controller\HealthController;
use App\Infrastructure\Controller\OpenApiController;
use App\Infrastructure\Controller\PatientController;
use App\Infrastructure\Controller\PractitionerController;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Persistence\Json\JsonAppointmentRepository;
use App\Infrastructure\Persistence\Json\JsonAvailabilityRepository;
use App\Infrastructure\Persistence\Json\JsonDatabase;
use App\Infrastructure\Persistence\Json\JsonDocumentRepository;
use App\Infrastructure\Persistence\Json\JsonPatientRepository;
use App\Infrastructure\Persistence\Json\JsonPractitionerRepository;

class Bootstrap
{
    public static function createContainer(?string $dbPath = null): Container
    {
        $container = new Container();

        // 1. Persistence
        $container->singleton(JsonDatabase::class, function () use ($dbPath) {
            $path = $dbPath ?? (__DIR__ . '/../../data/database.json');
            return new JsonDatabase($path);
        });

        $container->singleton(PractitionerRepositoryInterface::class, function (Container $c) {
            return new JsonPractitionerRepository($c->get(JsonDatabase::class));
        });

        $container->singleton(PatientRepositoryInterface::class, function (Container $c) {
            return new JsonPatientRepository($c->get(JsonDatabase::class));
        });

        $container->singleton(AvailabilityRepositoryInterface::class, function (Container $c) {
            return new JsonAvailabilityRepository($c->get(JsonDatabase::class));
        });

        $container->singleton(AppointmentRepositoryInterface::class, function (Container $c) {
            return new JsonAppointmentRepository($c->get(JsonDatabase::class));
        });

        $container->singleton(DocumentRepositoryInterface::class, function (Container $c) {
            return new JsonDocumentRepository($c->get(JsonDatabase::class));
        });

        // 2. Domain Services & Enterprise Service Mesh Mediator
        $container->singleton(EnterpriseServiceMeshMediator::class, fn() => EnterpriseServiceMeshMediator::getInstance());
        $container->singleton(SlotCalculatorService::class, fn() => new SlotCalculatorService());

        // 3. Application Services
        $container->singleton(PractitionerService::class, function (Container $c) {
            return new PractitionerService(
                practitionerRepository: $c->get(PractitionerRepositoryInterface::class),
                availabilityRepository: $c->get(AvailabilityRepositoryInterface::class),
                serviceMeshMediator: $c->get(EnterpriseServiceMeshMediator::class)
            );
        });

        $container->singleton(PatientService::class, function (Container $c) {
            return new PatientService(
                patientRepository: $c->get(PatientRepositoryInterface::class),
                serviceMeshMediator: $c->get(EnterpriseServiceMeshMediator::class)
            );
        });

        $container->singleton(AvailabilityService::class, function (Container $c) {
            return new AvailabilityService(
                availabilityRepository: $c->get(AvailabilityRepositoryInterface::class),
                practitionerRepository: $c->get(PractitionerRepositoryInterface::class),
                serviceMeshMediator: $c->get(EnterpriseServiceMeshMediator::class)
            );
        });

        $container->singleton(AppointmentService::class, function (Container $c) {
            return new AppointmentService(
                appointmentRepository: $c->get(AppointmentRepositoryInterface::class),
                practitionerRepository: $c->get(PractitionerRepositoryInterface::class),
                patientRepository: $c->get(PatientRepositoryInterface::class),
                availabilityRepository: $c->get(AvailabilityRepositoryInterface::class),
                slotCalculatorService: $c->get(SlotCalculatorService::class),
                serviceMeshMediator: $c->get(EnterpriseServiceMeshMediator::class)
            );
        });

        $container->singleton(DocumentService::class, function (Container $c) {
            return new DocumentService(
                documentRepository: $c->get(DocumentRepositoryInterface::class),
                patientRepository: $c->get(PatientRepositoryInterface::class),
                practitionerRepository: $c->get(PractitionerRepositoryInterface::class),
                serviceMeshMediator: $c->get(EnterpriseServiceMeshMediator::class)
            );
        });

        // 4. Controllers
        $container->singleton(PractitionerController::class, function (Container $c) {
            return new PractitionerController($c->get(PractitionerService::class));
        });

        $container->singleton(PatientController::class, function (Container $c) {
            return new PatientController(
                patientService: $c->get(PatientService::class),
                appointmentService: $c->get(AppointmentService::class)
            );
        });

        $container->singleton(AvailabilityController::class, function (Container $c) {
            return new AvailabilityController($c->get(AvailabilityService::class));
        });

        $container->singleton(AppointmentController::class, function (Container $c) {
            return new AppointmentController($c->get(AppointmentService::class));
        });

        $container->singleton(DocumentController::class, function (Container $c) {
            return new DocumentController($c->get(DocumentService::class));
        });

        $container->singleton(HealthController::class, function (Container $c) {
            return new HealthController(
                practitionerRepository: $c->get(PractitionerRepositoryInterface::class),
                patientRepository: $c->get(PatientRepositoryInterface::class),
                appointmentRepository: $c->get(AppointmentRepositoryInterface::class)
            );
        });

        $container->singleton(OpenApiController::class, fn() => new OpenApiController());

        // 5. Router
        $container->singleton(Router::class, function (Container $c) {
            return self::createRouter($c);
        });

        return $container;
    }

    public static function createRouter(Container $c): Router
    {
        $router = new Router();

        /** @var HealthController $healthController */
        $healthController = $c->get(HealthController::class);
        /** @var OpenApiController $openApiController */
        $openApiController = $c->get(OpenApiController::class);
        /** @var PractitionerController $practitionerController */
        $practitionerController = $c->get(PractitionerController::class);
        /** @var PatientController $patientController */
        $patientController = $c->get(PatientController::class);
        /** @var AvailabilityController $availabilityController */
        $availabilityController = $c->get(AvailabilityController::class);
        /** @var AppointmentController $appointmentController */
        $appointmentController = $c->get(AppointmentController::class);
        /** @var DocumentController $documentController */
        $documentController = $c->get(DocumentController::class);

        // System & Documentation
        $router->get('/', fn(Request $r) => $openApiController->docs($r));
        $router->get('/docs', fn(Request $r) => $openApiController->docs($r));
        $router->get('/swagger', fn(Request $r) => $openApiController->docs($r));
        $router->get('/swagger-ui', fn(Request $r) => $openApiController->docs($r));
        $router->get('/openapi.json', fn(Request $r) => $openApiController->schema($r));
        $router->get('/swagger.json', fn(Request $r) => $openApiController->schema($r));
        $router->get('/api/health', fn(Request $r) => $healthController->check($r));

        // Specialities
        $router->get('/api/specialities', fn(Request $r) => $practitionerController->specialities($r));

        // Practitioners
        $router->get('/api/practitioners', fn(Request $r) => $practitionerController->index($r));
        $router->post('/api/practitioners', fn(Request $r) => $practitionerController->create($r));
        $router->get('/api/practitioners/{id}', fn(Request $r) => $practitionerController->show($r));
        $router->put('/api/practitioners/{id}', fn(Request $r) => $practitionerController->update($r));
        $router->delete('/api/practitioners/{id}', fn(Request $r) => $practitionerController->delete($r));

        // Practitioner Availabilities & Slots
        $router->get('/api/practitioners/{id}/availabilities', fn(Request $r) => $availabilityController->index($r));
        $router->post('/api/practitioners/{id}/availabilities', fn(Request $r) => $availabilityController->set($r));
        $router->delete('/api/availabilities/{availabilityId}', fn(Request $r) => $availabilityController->delete($r));
        $router->get('/api/practitioners/{id}/slots', fn(Request $r) => $appointmentController->slots($r));
        $router->get('/api/practitioners/{id}/appointments', fn(Request $r) => $appointmentController->practitionerAppointments($r));

        // Patients
        $router->get('/api/patients', fn(Request $r) => $patientController->index($r));
        $router->post('/api/patients', fn(Request $r) => $patientController->create($r));
        $router->get('/api/patients/{id}', fn(Request $r) => $patientController->show($r));
        $router->put('/api/patients/{id}', fn(Request $r) => $patientController->update($r));
        $router->delete('/api/patients/{id}', fn(Request $r) => $patientController->delete($r));
        $router->get('/api/patients/{id}/appointments', fn(Request $r) => $patientController->appointments($r));

        // Appointments
        $router->post('/api/appointments', fn(Request $r) => $appointmentController->create($r));
        $router->get('/api/appointments/{id}', fn(Request $r) => $appointmentController->show($r));
        $router->put('/api/appointments/{id}', fn(Request $r) => $appointmentController->update($r));
        $router->patch('/api/appointments/{id}/reschedule', fn(Request $r) => $appointmentController->reschedule($r));
        $router->patch('/api/appointments/{id}/cancel', fn(Request $r) => $appointmentController->cancel($r));
        $router->patch('/api/appointments/{id}/complete', fn(Request $r) => $appointmentController->complete($r));
        $router->patch('/api/appointments/{id}/no-show', fn(Request $r) => $appointmentController->markNoShow($r));

        // Medical Documents (Feature inachevée)
        $router->post('/api/documents/export', fn(Request $r) => $documentController->export($r)); // Pure stub
        $router->post('/api/documents/test-ocr', fn(Request $r) => $documentController->testOcr($r)); // Test / WIP (TODO / TEST)
        $router->get('/api/documents', fn(Request $r) => $documentController->index($r)); // Real call
        $router->post('/api/documents', fn(Request $r) => $documentController->create($r)); // Real call
        $router->get('/api/documents/{id}', fn(Request $r) => $documentController->show($r)); // Real call
        $router->delete('/api/documents/{id}', fn(Request $r) => $documentController->delete($r)); // Real call
        $router->get('/api/patients/{id}/documents', fn(Request $r) => $documentController->patientDocuments($r)); // Real call

        return $router;
    }

    public static function run(?string $dbPath = null): void
    {
        $container = self::createContainer($dbPath);
        /** @var Router $router */
        $router = $container->get(Router::class);

        $request = Request::fromGlobals();
        $response = $router->dispatch($request);
        $response->send();
    }
}
