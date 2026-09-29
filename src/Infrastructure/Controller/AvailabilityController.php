<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\DTO\AvailabilityItemDTO;
use App\Application\DTO\SetAvailabilityDTO;
use App\Application\Service\AvailabilityService;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

class AvailabilityController extends BaseController
{
    public function __construct(
        private readonly AvailabilityService $availabilityService
    ) {
    }

    public function index(Request $request): Response
    {
        $practitionerId = (string) $request->getRouteParam('id');
        $listeAvailabilities = $this->availabilityService->getAvailabilities($practitionerId);

        return $this->json($listeAvailabilities);
    }

    public function set(Request $request): Response
    {
        $practitionerId = (string) $request->getRouteParam('id');
        $dto = SetAvailabilityDTO::fromArray($request->getBodyParams());
        $listeAvailabilities = $this->availabilityService->setAvailabilities($practitionerId, $dto);

        return $this->json($listeAvailabilities);
    }

    public function delete(Request $request): Response
    {
        $id = (string) $request->getRouteParam('availabilityId');
        $this->availabilityService->deleteAvailability($id);

        return $this->noContent();
    }
}
