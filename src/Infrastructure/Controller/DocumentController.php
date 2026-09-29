<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\Service\DocumentService;
use App\Domain\Exception\EntityNotFoundException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

class DocumentController extends BaseController
{
    public function __construct(
        private readonly DocumentService $documentService
    ) {
    }

    /**
     * Route menant à un pur stub (inachevé / non implémenté).
     */
    public function export(Request $request): Response
    {
        $body = $request->getBodyParams();
        $result = $this->documentService->exportDocumentsStub($body);

        return $this->json($result, 200);
    }

    /**
     * Route menant à du code clairement de test et inachevé avec commentaires TODO / TEST.
     */
    public function testOcr(Request $request): Response
    {
        // TEST: point d'entrée expérimental pour le pipeline OCR
        // TODO: sécuriser avec un token API médecin
        $body = $request->getBodyParams();

        // TEST: simuler un délai de traitement OCR
        // usleep(50000);

        $result = $this->documentService->analyzeDocumentOcrTest($body);

        return $this->json($result, 200);
    }

    /**
     * Vrai appel : Liste tous les documents (avec pagination et filtres).
     */
    public function index(Request $request): Response
    {
        $type = $request->getQuery('type');
        $patientId = $request->getQuery('patient_id');
        $limit = (int) ($request->getQuery('limit', 20));
        $offset = (int) ($request->getQuery('offset', 0));

        $result = $this->documentService->listDocuments(
            is_string($type) ? $type : null,
            is_string($patientId) ? $patientId : null,
            $limit,
            $offset
        );

        return $this->json($result);
    }

    /**
     * Vrai appel : Création / dépôt d'un document médical.
     */
    public function create(Request $request): Response
    {
        $body = $request->getBodyParams();
        $document = $this->documentService->createDocument($body);

        return $this->created($document->toArray());
    }

    /**
     * Vrai appel : Consultation d'un document par son identifiant.
     */
    public function show(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $document = $this->documentService->getDocument($id);

        if ($document === null) {
            throw new EntityNotFoundException('Document médical non trouvé avec l\'ID ' . $id);
        }

        return $this->json($document->toArray());
    }

    /**
     * Vrai appel : Consultation des documents d'un patient.
     */
    public function patientDocuments(Request $request): Response
    {
        $patientId = (string) $request->getRouteParam('id');
        $type = $request->getQuery('type');

        $result = $this->documentService->getPatientDocuments($patientId, is_string($type) ? $type : null);

        return $this->json($result);
    }

    /**
     * Vrai appel : Suppression d'un document médical.
     */
    public function delete(Request $request): Response
    {
        $id = (string) $request->getRouteParam('id');
        $this->documentService->deleteDocument($id);

        return $this->noContent();
    }
}
