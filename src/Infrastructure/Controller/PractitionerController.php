<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\DTO\CreatePractitionerDTO;
use App\Application\DTO\UpdatePractitionerDTO;
use App\Application\Service\PractitionerService;
use App\Domain\Model\Speciality;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

class PractitionerController extends BaseController
{
    public function __construct(
        private readonly PractitionerService $practitionerService
    ) {
    }

    public function index(Request $request): Response
    {
        $speciality = $request->getQuery('speciality');
        $city = $request->getQuery('city');
        $demandSearch = $request->getQuery('search') ?? $request->getQuery('q');

        $listePractitioners = $this->practitionerService->listPractitioners(
            speciality: is_string($speciality) ? $speciality : null,
            city: is_string($city) ? $city : null,
            search: is_string($demandSearch) ? $demandSearch : null
        );

        return $this->json($listePractitioners);
    }

    public function show(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $actualPractitioner = $this->practitionerService->getPractitioner($id);

        return $this->json($actualPractitioner);
    }

    public function create(Request $request): Response
    {
        $dto = CreatePractitionerDTO::fromArray($request->getBodyParams());
        $actualPractitioner = $this->practitionerService->createPractitioner($dto);

        return $this->created($actualPractitioner);
    }

    public function update(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $dto = UpdatePractitionerDTO::fromArray($request->getBodyParams());
        $actualPractitioner = $this->practitionerService->updatePractitioner($id, $dto);

        return $this->json($actualPractitioner);
    }

    public function delete(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $this->practitionerService->deletePractitioner($id);

        return $this->noContent();
    }

    public function specialities(Request $request): Response
    {
        $specialities = array_map(fn(Speciality $s) => [
            'key' => $s->name,
            'label' => $s->value,
        ], Speciality::cases());

        return $this->json($specialities);
    }
}
